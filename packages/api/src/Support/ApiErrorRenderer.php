<?php

namespace KusikusiCMS\Api\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use KusikusiCMS\Api\Api;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders errors under the API prefix as {message, code, errors?}. Other requests keep Laravel's rendering.
 */
final class ApiErrorRenderer
{
    /** @var array<class-string<Throwable>, array{status: int, code: string}> */
    private static array $mapped = [];

    /**
     * Map a package exception to a status and machine code, for example a routes path conflict to 409 `conflict`.
     * The exception message is used as the error message.
     *
     * @param  class-string<Throwable>  $exception
     */
    public static function map(string $exception, int $status, string $code): void
    {
        self::$mapped[$exception] = ['status' => $status, 'code' => $code];
    }

    /**
     * Register the renderer on the application's exception handler.
     */
    public static function register(Application $app): void
    {
        $app->make(ExceptionHandler::class)->renderable(function (Throwable $e, Request $request) {
            if (! self::isApiRequest($request)) {
                return null;
            }

            return self::render($e, $request);
        });
    }

    /**
     * Whether the request targets the configured API prefix.
     */
    public static function isApiRequest(Request $request): bool
    {
        $prefix = Api::prefix();

        return $request->is($prefix) || $request->is($prefix.'/*');
    }

    /**
     * Build the JSON error envelope for the exception.
     */
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        $mapped = self::mappingFor($e);

        [$status, $code, $message, $errors, $headers] = match (true) {
            $mapped !== null => [$mapped['status'], $mapped['code'], $e->getMessage(), [], []],
            $e instanceof ApiException => [$e->status, $e->errorCode, $e->getMessage(), $e->errors, []],
            $e instanceof ValidationException => [422, 'validation_failed', $e->getMessage(), $e->errors(), []],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Unauthenticated.', [], []],
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => [403, 'forbidden', 'This action is unauthorized.', [], []],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'not_found', 'Not found.', [], []],
            self::isCsrfMismatch($e) => [419, 'csrf_mismatch', 'CSRF token mismatch.', [], []],
            $e instanceof ThrottleRequestsException => [429, 'too_many_requests', 'Too many requests.', [], $e->getHeaders()],
            $e instanceof HttpException => [$e->getStatusCode(), 'http_error', $e->getMessage() ?: 'HTTP error.', [], $e->getHeaders()],
            default => [500, 'server_error', config('app.debug') ? $e->getMessage() : 'Server error.', [], []],
        };

        $body = ['message' => $message, 'code' => $code];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status, $headers);
    }

    /**
     * @return array{status: int, code: string}|null
     */
    protected static function mappingFor(Throwable $e): ?array
    {
        foreach (self::$mapped as $class => $mapping) {
            if ($e instanceof $class) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Laravel turns TokenMismatchException into a 419 HttpException before render callbacks run.
     */
    protected static function isCsrfMismatch(Throwable $e): bool
    {
        return $e instanceof TokenMismatchException
            || $e->getPrevious() instanceof TokenMismatchException
            || ($e instanceof HttpException && $e->getStatusCode() === 419);
    }
}
