/**
 * Gap between the phone clock and the server clock, so the times sent with offline
 * answers (answered_at) are expressed in server time even when the phone is wrong.
 * Measured on pages loaded online; offline (a page served from the cache carries an
 * old server time) the last measured value is reused.
 */
const STORAGE_KEY = 'focusia.clockOffset';

export function clockOffset(serverNow: number): number {
    if (navigator.onLine) {
        const offset = serverNow - Date.now();
        localStorage.setItem(STORAGE_KEY, String(offset));
        return offset;
    }
    const saved = Number(localStorage.getItem(STORAGE_KEY));

    return Number.isFinite(saved) ? saved : 0;
}

/** "Now" in server time. */
export const serverTime = (offset: number) => Date.now() + offset;
