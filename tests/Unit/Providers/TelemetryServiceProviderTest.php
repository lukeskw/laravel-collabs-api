<?php

use App\Http\Middleware\TelemetryMiddleware;
use App\Providers\TelemetryServiceProvider;
use Illuminate\Http\Request;
use OpenTelemetry\API\Logs\LoggerProviderInterface as ApiLoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface as ApiMeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface as ApiTracerProviderInterface;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface as SdkLoggerProviderInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface as SdkMeterProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as SdkTracerProviderInterface;
use Symfony\Component\HttpFoundation\Response;

uses()->group('telemetry-provider');

beforeEach(function (): void {
    resetTelemetryProviderBindings();
    config()->set('telemetry', null);
});

afterEach(function (): void {
    resetTelemetryProviderBindings();
    config()->set('telemetry', ['enabled' => false]);
});

it('registers telemetry exporters and middleware when enabled', function (): void {
    config()->set('telemetry', [
        'enabled' => true,
        'exporter' => [
            'endpoint' => 'http://localhost:4318/v1/traces',
            'metrics_endpoint' => 'http://localhost:4318/v1/metrics',
            'logs_endpoint' => 'http://localhost:4318/v1/logs',
            'protocol' => 'http/protobuf',
            'metrics_protocol' => 'http/x-ndjson',
            'logs_protocol' => 'http/protobuf',
            'headers' => 'Authorization=Bearer token',
            'metrics_headers' => 'x-metrics=1',
            'logs_headers' => 'x-logs=1',
        ],
        'exporters' => [
            'traces' => 'otlp',
            'metrics' => 'otlp',
            'logs' => 'otlp',
        ],
        'service_name' => 'laravel-collabs-api',
        'resource_attributes' => 'deployment.environment=testing, team=platform',
    ]);

    $provider = new TelemetryServiceProvider(app());

    $router = app('router');
    if (! $router->hasMiddlewareGroup('web')) {
        $router->middlewareGroup('web', []);
    }
    if (! $router->hasMiddlewareGroup('api')) {
        $router->middlewareGroup('api', []);
    }

    $provider->register();
    $provider->boot();

    expect(app()->bound(SdkTracerProviderInterface::class))->toBeTrue()
        ->and(app()->bound(ApiTracerProviderInterface::class))->toBeTrue()
        ->and(app()->bound(SdkMeterProviderInterface::class))->toBeTrue()
        ->and(app()->bound(ApiMeterProviderInterface::class))->toBeTrue()
        ->and(app()->bound(SdkLoggerProviderInterface::class))->toBeTrue()
        ->and(app()->bound(ApiLoggerProviderInterface::class))->toBeTrue();

    $middlewareGroups = $router->getMiddlewareGroups();

    if ($router->hasMiddlewareGroup('web')) {
        $webGroup = $middlewareGroups['web'];
        expect($webGroup[0])->toBe(TelemetryMiddleware::class);
    }

    expect($router->hasMiddlewareGroup('api'))->toBeTrue();

    $apiGroup = $middlewareGroups['api'] ?? [];
    expect($apiGroup)->not->toBeEmpty()
        ->and($apiGroup[0])->toBe(TelemetryMiddleware::class);

    // Execute terminating callbacks to ensure shutdown hook is wired.
    $request = Request::create('/', 'GET');
    $response = new Response;

    app()->terminate($request, $response);
});

it('skips telemetry wiring when configuration is disabled', function (): void {
    config()->set('telemetry', [
        'enabled' => false,
        'exporters' => [
            'traces' => 'none',
            'metrics' => 'none',
            'logs' => 'none',
        ],
    ]);

    $provider = new TelemetryServiceProvider(app());

    $provider->register();
    $provider->boot();

    expect(app()->bound(SdkTracerProviderInterface::class))->toBeFalse()
        ->and(app()->bound(SdkMeterProviderInterface::class))->toBeFalse()
        ->and(app()->bound(SdkLoggerProviderInterface::class))->toBeFalse();
});

function resetTelemetryProviderBindings(): void
{
    $app = app();

    foreach ([
        SdkTracerProviderInterface::class,
        ApiTracerProviderInterface::class,
        \OpenTelemetry\SDK\Trace\TracerProvider::class,
        SdkMeterProviderInterface::class,
        ApiMeterProviderInterface::class,
        \OpenTelemetry\SDK\Metrics\MeterProvider::class,
        SdkLoggerProviderInterface::class,
        ApiLoggerProviderInterface::class,
        \OpenTelemetry\SDK\Logs\LoggerProvider::class,
    ] as $abstract) {
        if ($app->bound($abstract)) {
            $app->forgetInstance($abstract);
        }

        if (isset($app[$abstract])) {
            unset($app[$abstract]);
        }
    }

    // Clear router middleware groups so the provider can prepend again.
    $router = $app['router'];
    $reflection = new ReflectionObject($router);
    if ($reflection->hasProperty('middlewareGroups')) {
        $property = $reflection->getProperty('middlewareGroups');
        $property->setAccessible(true);
        $groups = $property->getValue($router);
        if (is_array($groups)) {
            foreach (['web', 'api'] as $group) {
                if (! isset($groups[$group]) || ! is_array($groups[$group])) {
                    continue;
                }

                $groups[$group] = array_values(array_filter(
                    $groups[$group],
                    static fn (string $middleware): bool => $middleware !== TelemetryMiddleware::class,
                ));
            }

            $property->setValue($router, $groups);
        }
    }
}
