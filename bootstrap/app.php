<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Usando Redis throttle pro rate limiting
        $middleware->throttleWithRedis();

    })

    // Configuração personalizada para tratamento de exceções, já tinha implementado em projetos anteriores.
    // O bom de fazer dessa forma é que fica tudo centralizado, e isso nos gera logs únicos que podem ajudar no debug.
    // Quando temos um logger externo, como o New Relic/Datadog/Sentry, facilita bastante na identificação de problemas.
    ->withExceptions(function (Exceptions $exceptions) {
        $apiException = function (string $exceptionClass, int $statusCode, callable $payloadBuilder) use ($exceptions) {
            $exceptions->renderable(function (Throwable $e, Request $request) use ($exceptionClass, $statusCode, $payloadBuilder) {
                if (! $request->expectsJson() || ! $e instanceof $exceptionClass) {
                    return;
                }
                $data = $payloadBuilder($e);
                $data['message'] = $data['message'] ?? $e->getMessage() ?: 'Error';
                $data['code'] = $data['code'] ?? $e->getCode() ?: $statusCode;
                $data['error'] = true;
                $data['error_id'] = (string) Str::uuid();
                $data['exception'] = class_basename($e);
                Log::error('Errors:', [$data]);

                return response()->json($data, $statusCode);
            });
        };

        // Exceções específicas com mensagens traduzidas
        $apiException(JWTException::class, 400, fn () => [
            'message' => trans('auth.token_parse_error'),
        ]);
        $apiException(TokenBlacklistedException::class, 401, fn () => [
            'message' => trans('auth.token_invalid'),
        ]);

        $apiException(TokenExpiredException::class, 401, fn () => [
            'message' => trans('auth.token_invalid'),
        ]);

        $apiException(AuthenticationException::class, 401, fn () => [
            'message' => trans('auth.token_invalid'),
        ]);

        $apiException(AccessDeniedHttpException::class, 403, function (AccessDeniedHttpException $e) {
            return [
                'message' => trans('auth.validation_error'),
                'errors' => $e->getMessage(),
            ];
        });

        $apiException(NotFoundHttpException::class, 404, function (NotFoundHttpException $exception) {
            $previous = $exception->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                $model = $previous->getModel();
                $ids = $previous->getIds();

                return [
                    'message' => trans('validation.model_not_found', [
                        'model' => $model !== null ? "[{$model}]" : 'Resource',
                        'id' => $ids !== [] ? implode(', ', array_map(static fn ($value): string => (string) $value, $ids)) : '-',
                    ]),
                ];
            }

            return [
                'message' => trans('validation.resource_not_found'),
            ];
        });

        $apiException(ValidationException::class, 422, function (ValidationException $e) {
            return [
                'message' => trans('auth.validation_error'),
                'errors' => $e->errors(),
            ];
        });

        // Handler de exceções genérico
        $apiException(Throwable::class, 500, function ($e) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = $e->getMessage() ?: trans('auth.general_error');

            $data = [
                'message' => $message,
                'code' => $status,
                'exception' => class_basename($e),
            ];

            Log::error('Errors:', [$data]);

            return $data;
        });
    })->create();
