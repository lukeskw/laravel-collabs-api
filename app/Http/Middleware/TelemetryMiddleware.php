<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Já possuia esse arquivo de outros projetos, então adaptei para esse.
class TelemetryMiddleware
{
    private static ?MeterInterface $meter = null;

    private static ?HistogramInterface $requestDuration = null;

    private static ?CounterInterface $requestCount = null;

    private static ?UpDownCounterInterface $activeRequests = null;

    private static ?LoggerInterface $logger = null;

    public function __construct(
        private TracerProviderInterface $tracerProvider,
        private ?MeterProviderInterface $meterProvider = null,
        private ?LoggerProviderInterface $loggerProvider = null,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tracer = $this->getTracer();
        if ($tracer === null) {
            return $next($request);
        }

        $span = $tracer
            ->spanBuilder(sprintf('%s %s', $request->getMethod(), $request->path()))
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $span->setAttribute('http.request.method', $request->method());
        $span->setAttribute('url.path', '/'.ltrim($request->path(), '/'));
        $span->setAttribute('url.full', $request->fullUrl());
        $span->setAttribute('user_agent.original', (string) ($request->userAgent() ?? ''));

        $scope = $span->activate();
        $start = hrtime(true);
        $baseAttributes = $this->buildBaseAttributes($request);
        $this->incrementActiveRequests($baseAttributes);

        try {
            /** @var Response $response */
            $response = $next($request);
            $status = $response->getStatusCode();
            $span->setAttribute('http.response.status_code', $status);

            if ($route = $this->resolveRoute($request)) {
                $span->setAttribute('http.route', $route);
            }

            if ($status >= 400) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            }

            $duration = $this->calculateDurationSeconds($start);
            $this->recordMetrics($request, $status, $duration);
            $this->emitLog($request, $status, $duration, $span);

            return $response;
        } catch (Throwable $throwable) {
            $status = 500;
            $span->setAttribute('http.response.status_code', $status);
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());

            $duration = $this->calculateDurationSeconds($start);
            $this->recordMetrics($request, $status, $duration);
            $this->emitLog($request, $status, $duration, $span, $throwable);

            throw $throwable;
        } finally {
            $this->decrementActiveRequests($baseAttributes);
            $span->end();
            $scope->detach();
        }
    }

    private function getTracer(): ?TracerInterface
    {
        try {
            return $this->tracerProvider->getTracer('laravel');
        } catch (Throwable) {
            return null;
        }
    }

    private function getMeter(): ?MeterInterface
    {
        if ($this->meterProvider === null) {
            return null;
        }

        if (self::$meter === null) {
            try {
                self::$meter = $this->meterProvider->getMeter('laravel');
            } catch (Throwable) {
                return null;
            }
        }

        return self::$meter;
    }

    private function getRequestDurationHistogram(MeterInterface $meter): HistogramInterface
    {
        if (self::$requestDuration === null) {
            self::$requestDuration = $meter->createHistogram(
                'http.server.request.duration',
                's',
                'Duration of inbound HTTP requests',
            );
        }

        return self::$requestDuration;
    }

    private function getRequestCounter(MeterInterface $meter): CounterInterface
    {
        if (self::$requestCount === null) {
            self::$requestCount = $meter->createCounter(
                'http.server.request.count',
                null,
                'Total number of inbound HTTP requests',
            );
        }

        return self::$requestCount;
    }

    private function getActiveRequestsCounter(MeterInterface $meter): UpDownCounterInterface
    {
        if (self::$activeRequests === null) {
            self::$activeRequests = $meter->createUpDownCounter(
                'http.server.active_requests',
                'requests',
                'Number of concurrent HTTP requests being processed',
            );
        }

        return self::$activeRequests;
    }

    /** @param array<non-empty-string, bool|float|int|string|null> $attributes */
    private function incrementActiveRequests(array $attributes): void
    {
        $meter = $this->getMeter();
        if ($meter === null) {
            return;
        }

        $this->getActiveRequestsCounter($meter)->add(1, $attributes);
    }

    /** @param array<non-empty-string, bool|float|int|string|null> $attributes */
    private function decrementActiveRequests(array $attributes): void
    {
        $meter = $this->getMeter();
        if ($meter === null || self::$activeRequests === null) {
            return;
        }

        self::$activeRequests->add(-1, $attributes);
    }

    private function recordMetrics(Request $request, int $statusCode, float $durationSeconds): void
    {
        $meter = $this->getMeter();
        if ($meter === null) {
            return;
        }

        $attributes = $this->buildMetricAttributes($request, $statusCode);
        $this->getRequestDurationHistogram($meter)->record($durationSeconds, $attributes);
        $this->getRequestCounter($meter)->add(1, $attributes);

        // metrics flushed on application termination
    }

    private function emitLog(Request $request, int $statusCode, float $durationSeconds, SpanInterface $span, ?Throwable $throwable = null): void
    {
        $logger = $this->getLogger();
        if ($logger === null || ! $logger->isEnabled()) {
            return;
        }

        $severity = $this->resolveSeverity($statusCode, $throwable);
        $route = $this->resolveRoute($request);

        $record = (new LogRecord(sprintf(
            'HTTP %s %s %d',
            $request->method(),
            $route ?? '/'.ltrim($request->path(), '/'),
            $statusCode,
        )))
            ->setSeverityNumber($severity)
            ->setSeverityText($severity->name)
            ->setTimestamp((int) (microtime(true) * LogRecord::NANOS_PER_SECOND))
            ->setContext(Context::getCurrent())
            ->setAttributes([
                'http.request.method' => $request->method(),
                'http.response.status_code' => $statusCode,
                'http.route' => $route ?? 'unknown',
                'server.address' => $request->getHost(),
                'http.request.duration' => $durationSeconds,
            ]);

        if ($throwable !== null) {
            $record->setAttribute('error.type', $throwable::class);
            $record->setAttribute('error.message', $throwable->getMessage());
        }

        $logger->emit($record);

        // logs flushed on application termination
    }

    private function getLogger(): ?LoggerInterface
    {
        if ($this->loggerProvider === null) {
            return null;
        }

        if (self::$logger === null) {
            try {
                self::$logger = $this->loggerProvider->getLogger('laravel');
            } catch (Throwable) {
                return null;
            }
        }

        return self::$logger;
    }

    /** @return array<non-empty-string, bool|float|int|string|null> */
    private function buildBaseAttributes(Request $request): array
    {
        $attributes = [
            'http.request.method' => $request->method(),
        ];

        if ($route = $this->resolveRoute($request)) {
            $attributes['http.route'] = $route;
        }

        if ($host = $request->getHost()) {
            $attributes['server.address'] = $host;
        }

        return $attributes;
    }

    /** @return array<non-empty-string, bool|float|int|string|null> */
    private function buildMetricAttributes(Request $request, int $statusCode): array
    {
        $attributes = $this->buildBaseAttributes($request);
        $attributes['http.response.status_code'] = $statusCode;

        if ($scheme = $request->getScheme()) {
            $attributes['url.scheme'] = $scheme;
        }

        $protocol = $request->server('SERVER_PROTOCOL');
        if (is_string($protocol)) {
            $attributes['network.protocol.version'] = $this->parseProtocolVersion($protocol);
        }

        return $attributes;
    }

    private function resolveRoute(Request $request): ?string
    {
        /** @var Route|string|null $route */
        $route = $request->route();

        if ($route instanceof Route) {
            return $route->uri();
        }

        return is_string($route) ? $route : null;
    }

    private function parseProtocolVersion(string $protocol): string
    {
        $parts = explode('/', $protocol);
        $candidate = $parts[count($parts) - 1] ?? $protocol;

        return $candidate === '' ? $protocol : $candidate;
    }

    private function calculateDurationSeconds(int $start): float
    {
        return (hrtime(true) - $start) / 1_000_000_000;
    }

    private function resolveSeverity(int $statusCode, ?Throwable $throwable = null): Severity
    {
        if ($throwable !== null || $statusCode >= 500) {
            return Severity::ERROR;
        }

        if ($statusCode >= 400) {
            return Severity::WARN;
        }

        return Severity::INFO;
    }
}
