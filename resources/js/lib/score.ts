// Colours and words for a grade out of 20 (the "difficulty" threshold is 10).

export type ScoreTone = 'success' | 'primary' | 'warning';

export function scoreTone(score: number): ScoreTone {
    if (score >= 14) return 'success';
    if (score >= 10) return 'primary';
    return 'warning';
}

export const toneText: Record<ScoreTone, string> = {
    success: 'text-success',
    primary: 'text-primary',
    warning: 'text-warning',
};

export const toneBadge: Record<ScoreTone, string> = {
    success: 'bg-success/10 text-success ring-success/20',
    primary: 'bg-primary/10 text-primary ring-primary/20',
    warning: 'bg-warning/15 text-warning ring-warning/25',
};

/** Short encouraging message shown with the grade. */
export function scoreMessage(score: number): { title: string; emoji: string } {
    if (score >= 16) return { title: 'Excellent travail !', emoji: '🏆' };
    if (score >= 14) return { title: 'Très bien !', emoji: '🎉' };
    if (score >= 10) return { title: 'Bien, continuez !', emoji: '👍' };
    return { title: 'À retravailler, vous allez y arriver', emoji: '💪' };
}

/** "12" or "14,4" (French decimal comma). */
export function formatScore(score: number): string {
    return score.toLocaleString('fr-FR', { maximumFractionDigits: 2 });
}
