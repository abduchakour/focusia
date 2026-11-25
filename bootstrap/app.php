<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
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
        $middleware->append(SecurityHeaders::class);

        // Demo on phones through the Cloudflare tunnel (cloudflared runs on this computer and
        // forwards HTTPS requests): trust its X-Forwarded-* headers so links stay in https.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

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

            // JSON calls (offline sync of quiz answers) keep plain JSON errors.
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return $response;
            }

            if ($status === 419) {
                return back()->with('error', 'La page a expiré. Veuillez réessayer.');
            }

            $alwaysHandled = [403, 404, 429];
            $handledInProduction = [500, 503];

            if (! in_array($status, $alwaysHandled, true)
                && ! (in_array($status, $handledInProduction, true) && ! config('app.debug'))) {
                return $response;
            }

            if ($e instanceof InvalidSignatureException) {
                return Inertia::render('Error', [
                    'status' => 403,
                    'message' => 'Ce lien de téléchargement a expiré. Retournez sur la page et touchez à nouveau « Télécharger ».',
                ])->toResponse($request)->setStatusCode(403);
            }

            // Only our own French messages (abort(403, '...')) are shown; Laravel's English defaults are not.
            $message = $e instanceof HttpExceptionInterface && $e->getMessage() !== '' && ! $e->getPrevious() instanceof AuthorizationException
                ? $e->getMessage()
                : null;

            return Inertia::render('Error', ['status' => $status, 'message' => $message])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
