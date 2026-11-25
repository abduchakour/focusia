// Homework states (derived on the server, see App\Support\SubmissionState).

export type HomeworkState = 'A_RENDRE' | 'RENDU' | 'EN_RETARD' | 'NOTE' | 'NON_RENDU';

/** Badge colours; the state is always written in words next to the colour. */
export const stateBadge: Record<HomeworkState, string> = {
    A_RENDRE: 'bg-primary/10 text-primary',
    RENDU: 'bg-success/10 text-success',
    EN_RETARD: 'bg-warning/15 text-warning',
    NOTE: 'bg-success text-success-foreground',
    NON_RENDU: 'bg-destructive/10 text-destructive',
};
