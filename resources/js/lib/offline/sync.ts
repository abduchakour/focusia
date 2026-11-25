import { ref } from 'vue';
import type { PendingSubmission } from './db';

/** IndexedDB (Dexie) is only loaded when a student needs the queue: lighter first page for everyone else. */
const database = () => import('./db');

/**
 * Offline queue of quiz answers. Answers are written to IndexedDB first, then sent;
 * whatever cannot be sent now is retried when the network comes back
 * (`online` event, app start, every 30 s). Entries belong to one user: they are only
 * ever sent with that user's session.
 */

export interface SubmitResult {
    score: number;
    correct: number;
    total: number;
    result_url: string;
}

type SyncedListener = (entry: PendingSubmission, result: SubmitResult) => void;

export const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
export const pending = ref<PendingSubmission[]>([]);
/** The session expired while offline: the student must log in again to send. */
export const needsLogin = ref(false);

const listeners = new Set<SyncedListener>();
let userId: number | null = null;
let flushing: Promise<void> | null = null;
let started = false;

export function onSynced(listener: SyncedListener): () => void {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

/** Called on every page with the logged-in student (null for teachers and guests). */
export function useSyncFor(id: number | null): void {
    if (id !== userId) {
        userId = id;
        needsLogin.value = false;
        void refresh();
    }
    if (id === null) return;
    if (!started) {
        started = true;
        window.addEventListener('online', () => {
            online.value = true;
            void flush();
        });
        window.addEventListener('offline', () => (online.value = false));
        window.setInterval(() => pending.value.length && void flush(), 30_000);
    }
    void flush();
}

export async function refresh(): Promise<void> {
    if (userId === null) {
        pending.value = [];
        return;
    }
    const { db } = await database();
    pending.value = await db.outbox.where('userId').equals(userId).toArray();
}

export async function enqueue(entry: Omit<PendingSubmission, 'key' | 'createdAt' | 'error'>): Promise<void> {
    const { db, entryKey } = await database();
    await db.outbox.put({ ...entry, key: entryKey(entry.userId, entry.quizId), createdAt: Date.now(), error: null });
    await refresh();
    await flush();
}

export async function dismiss(entry: PendingSubmission): Promise<void> {
    const { db } = await database();
    await db.outbox.delete(entry.key);
    await refresh();
}

/** Sends every pending entry of the current user; safe to call at any time. */
export function flush(): Promise<void> {
    flushing ??= run().finally(() => (flushing = null));
    return flushing;
}

async function run(): Promise<void> {
    if (userId === null || !navigator.onLine) return;
    const { db } = await database();
    const entries = await db.outbox.where('userId').equals(userId).toArray();

    for (const entry of entries) {
        if (entry.error) continue; // refused: stays visible until the student dismisses it
        const outcome = await send(entry);
        if (outcome === 'stop') break;
    }
    await refresh();
}

async function send(entry: PendingSubmission): Promise<'next' | 'stop'> {
    let response: Response;
    try {
        response = await fetch(entry.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ answers: entry.answers, answered_at: entry.answeredAt }),
        });
    } catch {
        return 'stop'; // no network (or a captive Wi-Fi): try again later
    }

    if (response.ok) {
        const result = (await response.json()) as SubmitResult;
        const { db } = await database();
        await db.transaction('rw', db.outbox, db.drafts, async () => {
            await db.outbox.delete(entry.key);
            await db.drafts.delete(entry.key);
        });
        needsLogin.value = false;
        listeners.forEach((listener) => listener(entry, result));
        return 'next';
    }
    if (response.status === 401) {
        needsLogin.value = true;
        return 'stop';
    }
    if ([403, 409, 422].includes(response.status)) {
        // A final answer from the server: show it, never retry silently.
        const body = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };
        const message =
            Object.values(body.errors ?? {})[0]?.[0] ??
            (response.status === 403 ? "Vous n'avez plus accès à ce quiz." : (body.message ?? "Vos réponses n'ont pas pu être acceptées."));
        const { db } = await database();
        await db.outbox.update(entry.key, { error: message });
        return 'next';
    }

    return 'stop'; // 419, 429, 5xx: temporary, retried later
}

/** Laravel keeps the CSRF token in the XSRF-TOKEN cookie, always up to date (unlike a cached page). */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
