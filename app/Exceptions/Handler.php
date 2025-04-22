<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // 413: Payload Too Large
        if ($exception instanceof PostTooLargeException) {
            return back()->withErrors(['error' => 'File size is too large.'], 413);
        }

        // 403: Access Denied handler
        // if ($exception instanceof HttpException && $exception->getStatusCode() == 403) {

        //     $user = $request->user();

        //     if ($user->hasRole('shop') && !$user->hasRole('root')) {
        //         return to_route('shop.dashboard.index');
        //     }

        //     return to_route('admin.dashboard.index');
        // }

        return parent::render($request, $exception);
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
