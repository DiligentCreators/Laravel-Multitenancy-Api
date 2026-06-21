<?php

namespace App\Services;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponseService
{
    public function success($message = 'Success', $data = [], $code = 200)
    {
        // If $data is a ResourceCollection (pagination)
        if (
            $data instanceof ResourceCollection &&
            $data->resource instanceof LengthAwarePaginator
        ) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $data->collection,
                'meta' => [
                    'current_page' => $data->resource->currentPage(),
                    'last_page' => $data->resource->lastPage(),
                    'per_page' => $data->resource->perPage(),
                    'total' => $data->resource->total(),
                    'next_page_url' => $data->resource->nextPageUrl(),
                    'prev_page_url' => $data->resource->previousPageUrl(),
                ],
            ], $code);
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => null,
        ], $code);
    }

    public function successWithAdditional($message = 'Success', $data = [], array $additionalData = [], $code = 200)
    {
        // If $data is a ResourceCollection (pagination)
        if (
            $data instanceof ResourceCollection &&
            $data->resource instanceof LengthAwarePaginator
        ) {
            return response()->json(array_merge([
                'status' => 'success',
                'message' => $message,
                'data' => $data->collection,
                'meta' => [
                    'current_page' => $data->resource->currentPage(),
                    'last_page' => $data->resource->lastPage(),
                    'per_page' => $data->resource->perPage(),
                    'total' => $data->resource->total(),
                    'next_page_url' => $data->resource->nextPageUrl(),
                    'prev_page_url' => $data->resource->previousPageUrl(),
                ],
            ], $additionalData), $code);
        }

        return response()->json(array_merge([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => null,
        ], $additionalData), $code);
    }

    public function error($message = 'Something went wrong', $code = 400, $errors = [])
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    public function validationError($errors, $message = 'Validation failed', $code = 422)
    {
        return $this->error($message, $code, $errors);
    }

    public function notFound($message = 'Resource not found')
    {
        return $this->error($message, 404);
    }

    public function unauthorized($message = 'Unauthorized')
    {
        return $this->error($message, 403);
    }
}
