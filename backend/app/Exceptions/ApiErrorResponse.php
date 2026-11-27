<?php

namespace App\Exceptions;

use DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Turns any exception raised by an API request into the same JSON shape:
 *
 *   {"status": "error", "message": "...", "errors": {...}}   (errors only for validation)
 *
 * Non-API requests return null so Laravel's normal handling (HTML error pages) still applies.
 */
class ApiErrorResponse
{
    public static function from(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        [$status, $message, $errors] = match (true) {
            $e instanceof ValidationException => [
                422,
                collect($e->errors())->flatten()->first() ?? 'Dữ liệu không hợp lệ.',
                $e->errors(),
            ],
            // Business-rule violations, e.g. not enough stock in the batches (FEFO).
            $e instanceof DomainException => [422, $e->getMessage(), null],
            $e instanceof AuthenticationException => [401, 'Vui lòng đăng nhập để tiếp tục.', null],
            $e instanceof AuthorizationException => [403, 'Bạn không có quyền thực hiện thao tác này.', null],
            $e instanceof ModelNotFoundException => [404, 'Không tìm thấy dữ liệu yêu cầu.', null],
            // Our own abort(403|422, '...') messages are shown; framework 404/405 texts are replaced.
            $e instanceof HttpException => [
                $e->getStatusCode(),
                $e->getMessage() !== '' && ! in_array($e->getStatusCode(), [404, 405], true)
                    ? $e->getMessage()
                    : self::httpMessage($e->getStatusCode()),
                null,
            ],
            default => [
                500,
                'Hệ thống đang gặp sự cố, vui lòng thử lại sau.',
                null,
            ],
        };

        $body = ['status' => 'error', 'message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        if ($status === 500 && config('app.debug')) {
            $body['debug'] = $e->getMessage();
        }

        return response()->json($body, $status);
    }

    private static function httpMessage(int $status): string
    {
        return match ($status) {
            400 => 'Yêu cầu không hợp lệ.',
            403 => 'Bạn không có quyền thực hiện thao tác này.',
            404 => 'Không tìm thấy đường dẫn yêu cầu.',
            405 => 'Phương thức không được hỗ trợ cho đường dẫn này.',
            429 => 'Bạn gửi quá nhiều yêu cầu, vui lòng thử lại sau.',
            default => 'Có lỗi xảy ra, vui lòng thử lại.',
        };
    }
}
