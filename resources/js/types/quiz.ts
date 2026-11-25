/** A question being edited in the quiz editor. */
export type EditableQuestion = {
    key: number; // client-side identity, used by list animations
    text: string;
    notion_id: number | '';
    choices: string[];
    correct: number | null;
};

export interface NotionOption {
    id: number;
    name: string;
    subject: string;
}

export interface GroupOption {
    id: number;
    name: string;
    subject: string;
    level: string;
}

export type QuizStatus = 'draft' | 'open' | 'closed';

/** Student side (phase 4). */
export type StudentQuizStatus = 'todo' | 'started' | 'done' | 'missed' | 'started_late';

export interface StudentQuizCard {
    id: number;
    title: string;
    subject: string;
    teacher: string;
    questions_count: number;
    duration_minutes: number | null;
    due_at: string;
    corrections_at: string;
    corrections_visible: boolean;
    status: StudentQuizStatus;
    score: number | null;
}

export interface TakeQuestion {
    id: number;
    text: string;
    notion: string;
    choices: { id: number; text: string }[];
}
