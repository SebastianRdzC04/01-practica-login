<?php

namespace App\Exceptions;

use App\Support\AuthLog;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
            $request = request();

            if ($request && app()->bound('auth')) {
                $user = $request->user();
                $statusCode = 500;

                if ($e instanceof HttpExceptionInterface) {
                    $statusCode = $e->getStatusCode();
                }

                $context = [
                    'event' => AuthLog::EVENT_EXCEPTION,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'status_code' => $statusCode,
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ];

                if ($user) {
                    $context['user_id'] = $user->id;
                    $context['email'] = $user->email;
                    $context['role'] = $user->role;
                }

                if ($statusCode >= 500) {
                    AuthLog::error('Application exception', $context);
                } else {
                    AuthLog::warning('Application exception', $context);
                }
            }
        });
    }
}
