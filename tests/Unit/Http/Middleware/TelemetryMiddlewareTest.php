<?php

use App\Http\Middleware\TelemetryMiddleware;
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
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\HttpFoundation\Response;

uses()->group('telemetry-middleware');

beforeEach(function (): void {
    resetTelemetryMiddlewareState();
});

it('records metrics and logs for successful requests', function (): void {
    $spanAttributes = [];
    $spanEnded = false;

    $scope = Mockery::mock(ScopeInterface::class);
    $scope->shouldReceive('detach')->once();

    $span = Mockery::mock(SpanInterface::class);
    $span->shouldReceive('setAttribute')
        ->andReturnUsing(function (string $key, mixed $value) use (&$spanAttributes, $span) {
            $spanAttributes[$key] = $value;

            return $span;
        });
    $span->shouldReceive('setAttributes')
        ->andReturnUsing(function (iterable $attributes) use (&$spanAttributes, $span) {
            foreach ($attributes as $key => $value) {
                $spanAttributes[$key] = $value;
            }

            return $span;
        });
    $span->shouldReceive('activate')->once()->andReturn($scope);
    $span->shouldReceive('setStatus')->andReturnSelf();
    $span->shouldReceive('recordException')->never();
    $span->shouldReceive('end')->andReturnUsing(function () use (&$spanEnded): void {
        $spanEnded = true;
    });

    $builder = Mockery::mock(SpanBuilderInterface::class);
    $builder->shouldReceive('setSpanKind')
        ->once()
        ->with(SpanKind::KIND_SERVER)
        ->andReturnSelf();
    $builder->shouldReceive('startSpan')->once()->andReturn($span);

    $tracer = Mockery::mock(TracerInterface::class);
    $tracer->shouldReceive('spanBuilder')
        ->once()
        ->withArgs(fn (string $name): bool => str_starts_with($name, 'GET '))
        ->andReturn($builder);

    $tracerProvider = Mockery::mock(TracerProviderInterface::class);
    $tracerProvider->shouldReceive('getTracer')
        ->once()
        ->with('laravel')
        ->andReturn($tracer);

    $histogramRecords = [];
    $histogram = Mockery::mock(HistogramInterface::class);
    $histogram->shouldReceive('record')
        ->once()
        ->withArgs(function (float $value, iterable $attributes) use (&$histogramRecords): bool {
            $histogramRecords[] = ['value' => $value, 'attributes' => $attributes];

            return true;
        });

    $counterAdds = [];
    $counter = Mockery::mock(CounterInterface::class);
    $counter->shouldReceive('add')
        ->once()
        ->withArgs(function (float|int $amount, iterable $attributes) use (&$counterAdds): bool {
            $counterAdds[] = ['amount' => $amount, 'attributes' => $attributes];

            return true;
        });

    $activeAdds = [];
    $activeCounter = Mockery::mock(UpDownCounterInterface::class);
    $activeCounter->shouldReceive('add')
        ->twice()
        ->withArgs(function (float|int $amount, iterable $attributes) use (&$activeAdds): bool {
            $activeAdds[] = ['amount' => $amount, 'attributes' => $attributes];

            return true;
        });

    $meter = Mockery::mock(MeterInterface::class);
    $meter->shouldReceive('createHistogram')->once()->andReturn($histogram);
    $meter->shouldReceive('createCounter')->once()->andReturn($counter);
    $meter->shouldReceive('createUpDownCounter')->once()->andReturn($activeCounter);

    $meterProvider = Mockery::mock(MeterProviderInterface::class);
    $meterProvider->shouldReceive('getMeter')
        ->once()
        ->with('laravel')
        ->andReturn($meter);

    $loggerRecords = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('isEnabled')->andReturn(true);
    $logger->shouldReceive('emit')
        ->once()
        ->withArgs(function (LogRecord $record) use (&$loggerRecords): bool {
            $loggerRecords[] = $record;

            return true;
        });

    $loggerProvider = Mockery::mock(LoggerProviderInterface::class);
    $loggerProvider->shouldReceive('getLogger')
        ->once()
        ->with('laravel')
        ->andReturn($logger);

    $middleware = new TelemetryMiddleware($tracerProvider, $meterProvider, $loggerProvider);

    $request = Request::create('/api/v1/collaborators', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Pest',
        'SERVER_PROTOCOL' => 'HTTP/2.0',
        'HTTP_HOST' => 'example.test',
    ]);

    $request->setRouteResolver(static fn () => new Route(['GET'], 'api.collaborators.index', fn () => null));

    $response = $middleware->handle($request, static fn () => new Response('OK', 204));

    expect($response->getStatusCode())->toBe(204);
    expect($spanAttributes['http.response.status_code'] ?? null)->toBe(204);
    expect($spanEnded)->toBeTrue();

    expect($histogramRecords)->toHaveCount(1);
    expect($counterAdds)->toHaveCount(1);
    expect($activeAdds)->toHaveCount(2);

    $counterAttributes = iterator_to_array($counterAdds[0]['attributes']);
    expect($counterAdds[0]['amount'])->toBe(1)
        ->and($counterAttributes['http.response.status_code'])->toBe(204);

    expect($activeAdds[0]['amount'])->toBe(1)
        ->and($activeAdds[1]['amount'])->toBe(-1);

    expect($loggerRecords)->toHaveCount(1);

    /** @var LogRecord $logRecord */
    $logRecord = $loggerRecords[0];
    $logAttributes = logRecordAttributes($logRecord);

    expect(logRecordSeverity($logRecord))->toBe(Severity::INFO->value)
        ->and($logAttributes['http.route'])->toBe('api.collaborators.index');
});

it('records error telemetry when the request handler throws', function (): void {
    $spanAttributes = [];
    $spanEnded = false;
    $statusSet = null;

    $scope = Mockery::mock(ScopeInterface::class);
    $scope->shouldReceive('detach')->once();

    $span = Mockery::mock(SpanInterface::class);
    $span->shouldReceive('setAttribute')
        ->andReturnUsing(function (string $key, mixed $value) use (&$spanAttributes, $span) {
            $spanAttributes[$key] = $value;

            return $span;
        });
    $span->shouldReceive('setAttributes')
        ->andReturnUsing(function (iterable $attributes) use (&$spanAttributes, $span) {
            foreach ($attributes as $key => $value) {
                $spanAttributes[$key] = $value;
            }

            return $span;
        });
    $span->shouldReceive('activate')->once()->andReturn($scope);
    $span->shouldReceive('setStatus')
        ->andReturnUsing(function (string $code, ?string $description) use (&$statusSet, $span) {
            $statusSet = ['code' => $code, 'description' => $description];

            return $span;
        });
    $span->shouldReceive('end')->andReturnUsing(function () use (&$spanEnded): void {
        $spanEnded = true;
    });

    $builder = Mockery::mock(SpanBuilderInterface::class);
    $builder->shouldReceive('setSpanKind')
        ->once()
        ->with(SpanKind::KIND_SERVER)
        ->andReturnSelf();
    $builder->shouldReceive('startSpan')->once()->andReturn($span);

    $tracer = Mockery::mock(TracerInterface::class);
    $tracer->shouldReceive('spanBuilder')
        ->once()
        ->withArgs(fn (string $name): bool => str_starts_with($name, 'POST '))
        ->andReturn($builder);

    $tracerProvider = Mockery::mock(TracerProviderInterface::class);
    $tracerProvider->shouldReceive('getTracer')
        ->once()
        ->with('laravel')
        ->andReturn($tracer);

    $histogram = Mockery::mock(HistogramInterface::class);
    $histogram->shouldReceive('record')->once();

    $counter = Mockery::mock(CounterInterface::class);
    $counter->shouldReceive('add')->once();

    $activeCounter = Mockery::mock(UpDownCounterInterface::class);
    $activeCounter->shouldReceive('add')->twice();

    $meter = Mockery::mock(MeterInterface::class);
    $meter->shouldReceive('createHistogram')->once()->andReturn($histogram);
    $meter->shouldReceive('createCounter')->once()->andReturn($counter);
    $meter->shouldReceive('createUpDownCounter')->once()->andReturn($activeCounter);

    $meterProvider = Mockery::mock(MeterProviderInterface::class);
    $meterProvider->shouldReceive('getMeter')
        ->once()
        ->with('laravel')
        ->andReturn($meter);

    $loggerRecords = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('isEnabled')->andReturn(true);
    $logger->shouldReceive('emit')
        ->once()
        ->withArgs(function (LogRecord $record) use (&$loggerRecords): bool {
            $loggerRecords[] = $record;

            return true;
        });

    $loggerProvider = Mockery::mock(LoggerProviderInterface::class);
    $loggerProvider->shouldReceive('getLogger')
        ->once()
        ->with('laravel')
        ->andReturn($logger);

    $middleware = new TelemetryMiddleware($tracerProvider, $meterProvider, $loggerProvider);

    $request = Request::create('/api/v1/collaborators', 'POST');
    $request->setRouteResolver(static fn () => 'api.collaborators.store');

    $exception = new RuntimeException('boom');
    $span->shouldReceive('recordException')
        ->once()
        ->with($exception, Mockery::type('iterable'))
        ->andReturnSelf();

    expect(fn () => $middleware->handle($request, static function () use ($exception) {
        throw $exception;
    }))->toThrow(RuntimeException::class);

    expect($spanAttributes['http.response.status_code'] ?? null)->toBe(500);
    expect($spanEnded)->toBeTrue();
    expect($statusSet)->toMatchArray([
        'code' => StatusCode::STATUS_ERROR,
        'description' => 'boom',
    ]);

    expect($loggerRecords)->toHaveCount(1);
    expect(logRecordSeverity($loggerRecords[0]))->toBe(Severity::ERROR->value);
});

function resetTelemetryMiddlewareState(): void
{
    $reflection = new \ReflectionClass(TelemetryMiddleware::class);

    foreach (['meter', 'requestDuration', 'requestCount', 'activeRequests', 'logger'] as $property) {
        $propertyReflection = $reflection->getProperty($property);
        $propertyReflection->setAccessible(true);
        $propertyReflection->setValue(null, null);
    }
}

function logRecordSeverity(LogRecord $record): int
{
    $property = new \ReflectionProperty(LogRecord::class, 'severityNumber');
    $property->setAccessible(true);

    return (int) $property->getValue($record);
}

/**
 * @return array<string, mixed>
 */
function logRecordAttributes(LogRecord $record): array
{
    $property = new \ReflectionProperty(LogRecord::class, 'attributes');
    $property->setAccessible(true);

    $attributes = $property->getValue($record);

    return is_array($attributes) ? $attributes : [];
}
