<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // expired CSRF token
        $exceptions->render(function (TokenMismatchException $e, $request) {
            return back()->with('status', 'Your session expired. Please try again.');
        });

        // rate limited
        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            return back()->with('status', 'Too many attempts. Please wait a moment and try again.');
        });

        // page not found
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            return Inertia::render('Errors/NotFound', [
                'homeUrl' => $request->user() ? route('dashboard') : route('login'),
            ])->toResponse($request)->setStatusCode(404);
        });

        // 403s
        $exceptions->render(function (HttpException $e, $request) {
            if ($e->getStatusCode() === 403 && $request->header('X-Inertia')) {
                return back()->with('status', $e->getMessage() ?: 'You are not authorized to do that.');
            }
        });

        // deleting something still in use (e.g. a department with programs)
        $exceptions->render(function (QueryException $e, $request) {
            if ($e->getCode() === '23000') {
                return back()->with('status', 'That can\'t be deleted while other records still depend on it.');
            }
        });
    })->create();
