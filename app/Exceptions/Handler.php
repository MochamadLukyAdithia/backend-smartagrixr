<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
     * Render the exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Handle AuthenticationException (user tidak login)
        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Anda harus login terlebih dahulu untuk mengakses resource ini',
                'status_code' => 401,
                'data' => null,
            ], 401);
        }

        // Handle ModelNotFoundException (dari findOrFail)
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource tidak ditemukan',
                'status_code' => 404,
                'data' => null,
            ], 404);
        }

        // Handle ValidationException
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'status_code' => 422,
                'errors' => $exception->validator->errors()->toArray(),
                'data' => null,
            ], 422);
        }

        // Handle 404 Not Found
        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint tidak ditemukan',
                'status_code' => 404,
                'data' => null,
            ], 404);
        }

        // Handle generic exceptions
        if (!config('app.debug')) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server',
                'status_code' => 500,
                'data' => null,
            ], 500);
        }

        return parent::render($request, $exception);
    }
}
