<?php

namespace App\Traits;

/**
 * Trait untuk response JSON yang konsisten di semua API endpoint
 * 
 * Usage:
 *   return $this->success($data);
 *   return $this->success($data, 'Custom message', 201);
 *   return $this->error('Error message', 400);
 */
trait ApiResponse
{
    /**
     * Success response
     */
    protected function success($data = null, string $message = 'Success', int $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'status_code' => $statusCode,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Error response
     */
    protected function error(string $message, int $statusCode = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode,
            'data' => null,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Not found response
     */
    protected function notFound(string $message = 'Resource tidak ditemukan')
    {
        return $this->error($message, 404);
    }

    /**
     * Unauthorized response
     */
    protected function unauthorized(string $message = 'Unauthorized')
    {
        return $this->error($message, 401);
    }

    /**
     * Forbidden response
     */
    protected function forbidden(string $message = 'Forbidden')
    {
        return $this->error($message, 403);
    }

    /**
     * Validation error response
     */
    protected function validationError($errors, string $message = 'Validasi gagal')
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'status_code' => 422,
            'data' => null,
            'errors' => $errors,
        ], 422);
    }
}
