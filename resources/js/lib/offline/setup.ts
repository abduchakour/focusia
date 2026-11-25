import type { SharedData } from '@/types';
import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { useSyncFor } from './sync';

export const PAGES_CACHE = 'focusia-pages';

/**
 * Offline wiring, called once at start-up:
 *  - registers the service worker (production build only);
 *  - offline, a link click becomes a full page load so the service worker can
 *    answer from its cache (Inertia JSON responses are not cached);
 *  - logging out removes the cached pages (shared phones);
 *  - starts the answer queue for the logged-in student.
 */
export function setupOffline(initialPage: Page<SharedData>): void {
    if ('serviceWorker' in navigator && import.meta.env.PROD) {
        const register = () =>
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
                /* the app still works online */
            });
        // Inertia mounts after its page chunk is loaded, often after the `load` event.
        if (document.readyState === 'complete') void register();
        else window.addEventListener('load', () => void register(), { once: true });
    }

    router.on('before', (event) => {
        const visit = event.detail.visit;

        if (visit.method === 'post' && visit.url.pathname === '/logout') {
            void clearCachedPages();
            return;
        }
        if (!navigator.onLine && visit.method === 'get') {
            event.preventDefault();
            // A partial reload has nothing to show offline; a real link opens the cached page.
            if (visit.only.length === 0) window.location.href = visit.url.href;
        }
    });

    const follow = (page: Page<SharedData>) => {
        const user = page.props.auth?.user;
        useSyncFor(user?.role === 'student' ? user.id : null);
    };
    follow(initialPage);
    router.on('navigate', (event) => follow(event.detail.page as Page<SharedData>));
}

/** Stores the current page (full HTML) so it opens offline, even if it was reached by an Inertia link. */
export async function keepPageForOffline(url = window.location.href): Promise<void> {
    if (!navigator.onLine || !('caches' in window) || !import.meta.env.PROD) return;
    try {
        const cache = await caches.open(PAGES_CACHE);
        await cache.add(new Request(url, { credentials: 'same-origin' }));
    } catch {
        /* not critical */
    }
}

export async function clearCachedPages(): Promise<void> {
    if ('caches' in window) await caches.delete(PAGES_CACHE);
}
