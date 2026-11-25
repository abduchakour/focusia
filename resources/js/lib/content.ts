// Course materials ("support") and library resources ("ressource") share the same screens.

export type ContentKind = 'support' | 'ressource';

export interface ContentFile {
    name: string;
    size: number;
    extension: string | null;
    download: string;
}

export interface ContentItem {
    id: number;
    title: string;
    description: string | null;
    type: string;
    type_label: string;
    subject: string | null;
    level: string | null;
    url: string | null;
    file: ContentFile | null;
    groups: { id: number; name: string }[];
    created_at: string;
    assignments_count?: number;
    teacher?: string;
}

export const kindText: Record<
    ContentKind,
    { title: string; one: string; add: string; teacherIntro: string; studentIntro: string; empty: string; studentEmpty: string }
> = {
    support: {
        title: 'Supports de cours',
        one: 'support',
        add: 'Nouveau support',
        teacherIntro: 'Cours, études de cas… un fichier, un lien, ou les deux, publiés dans vos groupes.',
        studentIntro: 'Les supports publiés par vos enseignants dans vos groupes.',
        empty: 'Déposez votre premier cours : PDF, Word, PowerPoint… ou simplement un lien.',
        studentEmpty: 'Vos enseignants n’ont pas encore publié de support dans vos groupes.',
    },
    ressource: {
        title: 'Bibliothèque',
        one: 'ressource',
        add: 'Nouvelle ressource',
        teacherIntro: 'Documents, vidéos, liens et exercices classés par matière et niveau.',
        studentIntro: 'Documents, vidéos, liens et exercices pour approfondir.',
        empty: 'Partagez une vidéo, un exercice ou un site utile avec vos étudiants.',
        studentEmpty: 'La bibliothèque de vos groupes est encore vide.',
    },
};

/** "1,2 Mo", "350 Ko" */
export function formatBytes(bytes: number): string {
    if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Mo`;
    return `${Math.max(1, Math.round(bytes / 1024))} Ko`;
}

/** Colour of the file badge per family (identity only: the extension is always written on it). */
export function fileTone(extension: string | null): string {
    switch ((extension ?? '').toLowerCase()) {
        case 'pdf':
            return 'bg-red-500/10 text-red-700 dark:text-red-300';
        case 'doc':
        case 'docx':
        case 'odt':
            return 'bg-blue-500/10 text-blue-700 dark:text-blue-300';
        case 'xls':
        case 'xlsx':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'ppt':
        case 'pptx':
            return 'bg-orange-500/10 text-orange-700 dark:text-orange-300';
        case 'jpg':
        case 'jpeg':
        case 'png':
            return 'bg-violet-500/10 text-violet-700 dark:text-violet-300';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

/** Host name of a link, shown instead of the full address ("youtube.com"). */
export function linkHost(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}
