<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // Check the role before loading route models: a student calling a teacher
        // route always gets 403, never a 404 that would reveal whether an id exists.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureRole::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // French error pages rendered with the app layout instead of Laravel's defaults.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($status === 419) {
                return back()->with('error', 'La page a expiré. Veuillez réessayer.');
            }

            $alwaysHandled = [403, 404, 429];
            $handledInProduction = [500, 503];

            if (! in_array($status, $alwaysHandled, true)
                && ! (in_array($status, $handledInProduction, true) && ! config('app.debug'))) {
                return $response;
            }

            $message = $e instanceof HttpExceptionInterface && $e->getMessage() !== '' ? $e->getMessage() : null;

            return Inertia::render('Error', ['status' => $status, 'message' => $message])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
