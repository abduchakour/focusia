// French formatting helpers shared by every page.

const relative = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });

/** "dans 3 jours", "demain", "il y a 2 heures"… */
export function fromNow(iso: string): string {
    const diffSeconds = (new Date(iso).getTime() - Date.now()) / 1000;
    const abs = Math.abs(diffSeconds);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [unit, seconds] of units) {
        if (abs >= seconds) return relative.format(Math.round(diffSeconds / seconds), unit);
    }
    return 'maintenant';
}

/** "25 nov. 2026 à 18:00" */
export function dateTime(iso: string): string {
    return new Date(iso).toLocaleString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** "25 nov. 2026" */
export function date(iso: string): string {
    return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

/** Value for <input type="datetime-local"> (local time), n days from now at hh:mm. */
export function localInputValue(daysFromNow: number, hours = 23, minutes = 59): string {
    const d = new Date();
    d.setDate(d.getDate() + daysFromNow);
    d.setHours(hours, minutes, 0, 0);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** "1 question", "5 questions" */
export function plural(count: number, singular: string, pluralForm = `${singular}s`): string {
    return `${count} ${count > 1 ? pluralForm : singular}`;
}

/** "42 %" from a 0..1 rate. */
export function percent(rate: number): string {
    return `${Math.round(rate * 100)} %`;
}
