/// <reference lib="webworker" />
/**
 * FocusIA service worker (built by vite-plugin-pwa, "injectManifest" strategy).
 *
 * - Precaches the built JS/CSS so the app opens without network.
 * - Student pages (/etudiant/...) are "network first": fresh when online, the last
 *   version from the cache when offline. Only HTML navigations are cached, never
 *   Inertia JSON responses, and never another role's pages.
 * - A page never visited shows a small French offline page.
 * Quiz answers themselves live in IndexedDB (see lib/offline), not here.
 */
import { CacheableResponsePlugin } from 'workbox-cacheable-response';
import { clientsClaim } from 'workbox-core';
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';
import { registerRoute, setCatchHandler } from 'workbox-routing';
import { NetworkFirst, NetworkOnly } from 'workbox-strategies';

declare const self: ServiceWorkerGlobalScope;

const PAGES_CACHE = 'focusia-pages';

self.skipWaiting();
clientsClaim();

precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

registerRoute(
    ({ request, url }) => request.mode === 'navigate' && url.origin === self.location.origin && url.pathname.startsWith('/etudiant'),
    new NetworkFirst({
        cacheName: PAGES_CACHE,
        networkTimeoutSeconds: 6,
        plugins: [new CacheableResponsePlugin({ statuses: [200] })],
    }),
);

// Other pages are never cached, but go through the router so the offline page can answer.
registerRoute(({ request }) => request.mode === 'navigate', new NetworkOnly());

const offlinePage = `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hors connexion - FocusIA</title>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f6f9fc;color:#0f172a;padding:24px;box-sizing:border-box}
  main{max-width:360px;text-align:center}
  .icon{width:72px;height:72px;border-radius:50%;background:#e0effa;display:grid;place-items:center;margin:0 auto 20px;font-size:34px}
  h1{font-size:22px;margin:0 0 8px} p{color:#475569;line-height:1.5;margin:0 0 24px}
  a,button{display:block;width:100%;box-sizing:border-box;height:48px;border-radius:12px;border:0;font-size:16px;font-weight:600;margin-top:10px;cursor:pointer;text-decoration:none;line-height:48px}
  button{background:#1679b8;color:#fff} a{background:#fff;color:#1679b8;border:1px solid #cbd5e1}
  @media (prefers-color-scheme:dark){body{background:#0b1220;color:#e2e8f0}p{color:#94a3b8}.icon{background:#12324a}a{background:#0f172a;border-color:#334155}}
</style></head>
<body><main>
  <div class="icon" aria-hidden="true">📡</div>
  <h1>Vous êtes hors connexion</h1>
  <p>Cette page n'a pas encore été ouverte sur ce téléphone. Les quiz déjà commencés restent disponibles, et vos réponses sont envoyées dès le retour du réseau.</p>
  <button onclick="location.reload()">Réessayer</button>
  <a href="/etudiant/quiz">Mes quiz</a>
</main></body></html>`;

setCatchHandler(async ({ request, url }) => {
    if (request.mode === 'navigate') {
        // The installed app opens on /dashboard: offline, show the student home page kept in cache.
        if (url.pathname === '/dashboard') {
            const home = await caches.open(PAGES_CACHE).then((cache) => cache.match('/etudiant'));
            if (home) return home;
        }
        return new Response(offlinePage, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    }

    return Response.error();
});
