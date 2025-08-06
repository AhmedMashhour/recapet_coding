<?php
namespace App\Exceptions;

use  Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        if ($request->wantsJson()) {
            // Handle validation errors
            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'details' => $e->errors(),
                ], 422);
            }

            // Handle authentication errors
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthenticated',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }

            // Handle authorization errors
            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'code' => 'UNAUTHORIZED',
                ], 403);
            }

            // Handle model not found
            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'error' => 'Resource not found',
                    'code' => 'RESOURCE_NOT_FOUND',
                ], 404);
            }

            // Handle route not found
            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'error' => 'Route not found',
                    'code' => 'ROUTE_NOT_FOUND',
                ], 404);
            }

            // Handle method not allowed
            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'success' => false,
                    'error' => 'Method not allowed',
                    'code' => 'METHOD_NOT_ALLOWED',
                    'allowed_methods' => $e->getHeaders()['Allow'] ?? '',
                ], 405);
            }



            // Handle generic exceptions
            $statusCode = method_exists($e, 'getStatusCode')
                ? $e->getStatusCode()
                : 500;

            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
                'code' => 'SERVER_ERROR',
                'trace' => config('app.debug') ? $e->getTrace() : null,
            ], $statusCode);
        }

        return parent::render($request, $e);
    }
}
