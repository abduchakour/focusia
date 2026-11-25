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
