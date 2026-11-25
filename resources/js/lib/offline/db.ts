import Dexie, { type Table } from 'dexie';

/** Answers being given, saved at every tap so nothing is lost (closed app, empty battery, no network). */
export interface Draft {
    key: string; // `${userId}:${quizId}`
    userId: number;
    quizId: number;
    answers: Record<number, number>; // question id => choice id
    current: number; // question shown
    updatedAt: number;
}

/** Finished answers waiting to reach the server. Only the server grades them (rule 7). */
export interface PendingSubmission {
    key: string; // `${userId}:${quizId}`
    userId: number;
    quizId: number;
    quizTitle: string;
    url: string;
    answers: Record<number, number | null>;
    answeredAt: string; // ISO, device time corrected by the measured clock gap
    createdAt: number;
    error: string | null; // refusal from the server (e.g. after the due date), shown to the student
}

class FocusDatabase extends Dexie {
    drafts!: Table<Draft, string>;
    outbox!: Table<PendingSubmission, string>;

    constructor() {
        super('focusia');
        this.version(1).stores({
            drafts: 'key, userId',
            outbox: 'key, userId',
        });
    }
}

export const db = new FocusDatabase();

export const entryKey = (userId: number, quizId: number) => `${userId}:${quizId}`;
