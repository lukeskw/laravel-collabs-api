<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\TelemetryMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Common\Time\SystemClock;
use OpenTelemetry\API\Logs\LoggerProviderInterface as ApiLoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface as ApiMeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface as ApiTracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface as SdkLoggerProviderInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface as SdkMeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as SdkTracerProviderInterface;
use Throwable;

/**
 * @phpstan-type RawExporterConfig array{
 *     endpoint?: string|null,
 *     protocol?: string|null,
 *     headers?: string|null,
 *     metrics_endpoint?: string|null,
 *     metrics_protocol?: string|null,
 *     metrics_headers?: string|null,
 *     logs_endpoint?: string|null,
 *     logs_protocol?: string|null,
 *     logs_headers?: string|null
 * }
 * @phpstan-type TelemetrySettings array{
 *     enabled?: bool,
 *     exporter?: RawExporterConfig|null,
 *     exporters?: array<string, string|null>,
 *     service_name?: string|null,
 *     resource_attributes?: string|null
 * }
 * @phpstan-type SignalConfig array{
 *     endpoint: string,
 *     contentType: 'application/json'|'application/x-ndjson'|'application/x-protobuf',
 *     headers: array<string, string>
 * }
 */
// Já possuia esse arquivo de um outro projeto, então adaptei para esse.
class TelemetryServiceProvider extends ServiceProvider
{
    private ?SdkTracerProviderInterface $tracerProvider = null;

    private ?SdkMeterProviderInterface $meterProvider = null;

    private ?SdkLoggerProviderInterface $loggerProvider = null;

    private bool $shutdownHookRegistered = false;

    public function register(): void
    {
        $settings = $this->getTelemetrySettings();

        if (! $this->isEnabled($settings)) {
            return;
        }

        $resource = $this->buildResource($settings);

        $this->configureTracerProvider($settings, $resource);
        $this->configureMeterProvider($settings, $resource);
        $this->configureLoggerProvider($settings, $resource);
    }

    public function boot(): void
    {
        if (! $this->isEnabled($this->getTelemetrySettings())) {
            return;
        }

        $this->registerMiddleware();
    }

    /**
     * @param  TelemetrySettings  $settings
     */
    private function configureTracerProvider(array $settings, ResourceInfo $resource): void
    {
        $config = $this->resolveSignalConfiguration($settings, 'traces');
        if ($config === null) {
            return;
        }

        $transport = $this->createTransport($config);
        $exporter = new SpanExporter($transport);
        $processor = new BatchSpanProcessor($exporter, SystemClock::create());

        $provider = TracerProvider::builder()
            ->setResource($resource)
            ->addSpanProcessor($processor)
            ->build();

        $this->tracerProvider = $provider;

        $this->app->instance(SdkTracerProviderInterface::class, $provider);
        $this->app->instance(ApiTracerProviderInterface::class, $provider);
        if ($provider instanceof TracerProvider) {
            $this->app->instance(TracerProvider::class, $provider);
        }

        $this->registerShutdownHook();
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        $router->prependMiddlewareToGroup('web', TelemetryMiddleware::class);
        $router->prependMiddlewareToGroup('api', TelemetryMiddleware::class);
    }

    /**
     * @param  TelemetrySettings  $settings
     */
    private function configureMeterProvider(array $settings, ResourceInfo $resource): void
    {
        $config = $this->resolveSignalConfiguration($settings, 'metrics');
        if ($config === null) {
            return;
        }

        $transport = $this->createTransport($config);
        $exporter = new MetricExporter($transport);
        $reader = new ExportingReader($exporter);

        $provider = MeterProvider::builder()
            ->setResource($resource)
            ->addReader($reader)
            ->build();

        $this->meterProvider = $provider;

        $this->app->instance(SdkMeterProviderInterface::class, $provider);
        $this->app->instance(ApiMeterProviderInterface::class, $provider);
        if ($provider instanceof MeterProvider) {
            $this->app->instance(MeterProvider::class, $provider);
        }

        $this->registerShutdownHook();
    }

    /**
     * @param  TelemetrySettings  $settings
     */
    private function configureLoggerProvider(array $settings, ResourceInfo $resource): void
    {
        $config = $this->resolveSignalConfiguration($settings, 'logs');
        if ($config === null) {
            return;
        }

        $transport = $this->createTransport($config);
        $exporter = new LogsExporter($transport);
        $processor = new BatchLogRecordProcessor($exporter, SystemClock::create());

        $provider = LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor($processor)
            ->build();

        $this->loggerProvider = $provider;

        $this->app->instance(SdkLoggerProviderInterface::class, $provider);
        $this->app->instance(ApiLoggerProviderInterface::class, $provider);
        if ($provider instanceof LoggerProvider) {
            $this->app->instance(LoggerProvider::class, $provider);
        }

        $this->registerShutdownHook();
    }

    /**
     * @param  TelemetrySettings  $settings
     */
    private function buildResource(array $settings): ResourceInfo
    {
        $attributes = $this->parseKeyValueList($this->stringValue($settings['resource_attributes'] ?? null));

        $serviceName = $this->stringValue($settings['service_name'] ?? null);
        if ($serviceName !== null) {
            $attributes['service.name'] = $serviceName;
        }

        if ($attributes === []) {
            return ResourceInfoFactory::defaultResource();
        }

        return ResourceInfoFactory::defaultResource()
            ->merge(ResourceInfo::create(Attributes::create($attributes)));
    }

    /**
     * @return array<string, string>
     */
    private function parseKeyValueList(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $parsed = [];

        foreach (explode(',', $value) as $pair) {
            $pair = trim($pair, " \"'");
            if ($pair === '') {
                continue;
            }

            [$key, $val] = array_pad(
                array_map(
                    static fn (string $segment): string => trim($segment, " \"'"),
                    explode('=', $pair, 2),
                ),
                2,
                null,
            );
            if ($key === null || $val === null || $key === '') {
                continue;
            }

            $parsed[$key] = $val;
        }

        return $parsed;
    }

    /**
     * @return 'application/json'|'application/x-ndjson'|'application/x-protobuf'
     */
    private function resolveContentType(?string $protocol): string
    {
        return match ($protocol) {
            'http/protobuf' => 'application/x-protobuf',
            'http/x-ndjson' => 'application/x-ndjson',
            default => 'application/json',
        };
    }

    /**
     * @param  array<string, string|null>  $exporter
     * @return array{endpoint?: string, protocol?: string, headers: array<string, string>}
     */
    private function resolveSignalExporterConfig(array $exporter, string $signal): array
    {
        $endpointKey = $signal === 'traces' ? 'endpoint' : "{$signal}_endpoint";
        $protocolKey = $signal === 'traces' ? 'protocol' : "{$signal}_protocol";
        $headersKey = $signal === 'traces' ? 'headers' : "{$signal}_headers";

        $endpoint = $this->stringValue($exporter[$endpointKey] ?? null);
        if ($endpoint === null) {
            $fallbackEndpoint = $this->stringValue($exporter['endpoint'] ?? null);
            if ($fallbackEndpoint !== null) {
                $endpoint = $this->inferEndpoint($fallbackEndpoint, $signal);
            }
        }

        $protocol = $this->stringValue($exporter[$protocolKey] ?? null)
            ?? $this->stringValue($exporter['protocol'] ?? null);

        $headers = $this->parseKeyValueList($this->stringValue($exporter[$headersKey] ?? null));
        if ($headers === [] && isset($exporter['headers'])) {
            $headers = $this->parseKeyValueList($this->stringValue($exporter['headers']));
        }

        $config = ['headers' => $headers];

        if ($endpoint !== null) {
            $config['endpoint'] = $endpoint;
        }

        if ($protocol !== null) {
            $config['protocol'] = $protocol;
        }

        return $config;
    }

    private function inferEndpoint(string $endpoint, string $signal): string
    {
        if ($signal === 'traces') {
            return $endpoint;
        }

        return preg_replace('/\/v1\/[^\/]+$/', "/v1/{$signal}", $endpoint) ?? $endpoint;
    }

    /**
     * @param  TelemetrySettings  $settings
     * @return SignalConfig|null
     */
    private function resolveSignalConfiguration(array $settings, string $signal): ?array
    {
        $exporters = $settings['exporters'] ?? [];
        $configured = $exporters[$signal] ?? 'none';
        if ($configured === 'none') {
            return null;
        }

        /** @var array<string, string|null> $rawExporter */
        $rawExporter = $settings['exporter'] ?? [];

        $rawConfig = $this->resolveSignalExporterConfig($rawExporter, $signal);
        $endpoint = $rawConfig['endpoint'] ?? null;
        if ($endpoint === null) {
            return null;
        }

        $protocol = $rawConfig['protocol'] ?? null;

        return [
            'endpoint' => $endpoint,
            'contentType' => $this->resolveContentType($protocol),
            'headers' => $rawConfig['headers'],
        ];
    }

    /**
     * @param  SignalConfig  $config
     * @return TransportInterface<'application/json'|'application/x-ndjson'|'application/x-protobuf'>
     */
    private function createTransport(array $config): TransportInterface
    {
        /** @var TransportInterface<'application/json'|'application/x-ndjson'|'application/x-protobuf'> $transport */
        $transport = (new OtlpHttpTransportFactory)->create(
            $config['endpoint'],
            $config['contentType'],
            $config['headers'],
        );

        return $transport;
    }

    /**
     * @param  TelemetrySettings  $settings
     */
    private function isEnabled(array $settings): bool
    {
        return (bool) ($settings['enabled'] ?? false);
    }

    /**
     * @return TelemetrySettings
     */
    private function getTelemetrySettings(): array
    {
        $settings = config('telemetry');
        if (! is_array($settings)) {
            return [];
        }

        $exporter = [];
        if (isset($settings['exporter']) && is_array($settings['exporter'])) {
            foreach ($settings['exporter'] as $key => $value) {
                if (is_string($key)) {
                    $exporter[$key] = is_string($value) ? $value : null;
                }
            }
        }

        $exporters = [];
        if (isset($settings['exporters']) && is_array($settings['exporters'])) {
            foreach ($settings['exporters'] as $key => $value) {
                if (is_string($key)) {
                    $exporters[$key] = is_string($value) ? $value : null;
                }
            }
        }

        $result = [
            'enabled' => (bool) ($settings['enabled'] ?? false),
        ];

        if ($exporter !== []) {
            $result['exporter'] = $exporter;
        }

        if ($exporters !== []) {
            $result['exporters'] = $exporters;
        }

        $serviceName = $this->stringValue($settings['service_name'] ?? null);
        if ($serviceName !== null) {
            $result['service_name'] = $serviceName;
        }

        $resourceAttributes = $this->stringValue($settings['resource_attributes'] ?? null);
        if ($resourceAttributes !== null) {
            $result['resource_attributes'] = $resourceAttributes;
        }

        return $result;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function registerShutdownHook(): void
    {
        if ($this->shutdownHookRegistered) {
            return;
        }

        $this->app->terminating(function (): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            try {
                $this->tracerProvider?->forceFlush();
            } catch (Throwable) {
                // noop
            }
            try {
                $this->meterProvider?->forceFlush();
            } catch (Throwable) {
                // noop
            }
            try {
                $this->loggerProvider?->forceFlush();
            } catch (Throwable) {
                // noop
            }

            $this->tracerProvider?->shutdown();
            $this->meterProvider?->shutdown();
            $this->loggerProvider?->shutdown();
        });

        $this->shutdownHookRegistered = true;
    }
}
