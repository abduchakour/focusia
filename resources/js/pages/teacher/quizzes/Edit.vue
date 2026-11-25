<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import NewNotionDialog from '@/components/quiz/NewNotionDialog.vue';
import QuestionCard from '@/components/quiz/QuestionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { localInputValue, plural } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import type { EditableQuestion, GroupOption, NotionOption } from '@/types/quiz';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, Check, CopyPlus, FileText, LoaderCircle, Lock, Plus, Rocket, Save, UsersRound } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

interface QuizData {
    id: number;
    title: string;
    description: string | null;
    subject: string;
    level: string;
    duration_minutes: number | null;
    due_at: string;
    corrections_at: string;
    published: boolean;
    group_ids: number[];
    questions: { text: string; notion_id: number; choices: string[]; correct: number }[];
}

const props = defineProps<{
    quiz: QuizData | null;
    locked: boolean;
    attemptsCount?: number;
    groups: GroupOption[];
    notions: NotionOption[];
    levels: { value: string; label: string }[];
}>();

// ---------- Form state ----------
let nextKey = 1;
const blankQuestion = (notionId: number | '' = ''): EditableQuestion => ({
    key: nextKey++,
    text: '',
    notion_id: notionId,
    choices: ['', '', '', ''],
    correct: null,
});

type QuizForm = {
    title: string;
    description: string;
    subject: string;
    level: string;
    duration_minutes: number | null;
    due_at: string;
    corrections_at: string;
    group_ids: number[];
    questions: EditableQuestion[];
};

const form = useForm<QuizForm>({
    title: props.quiz?.title ?? '',
    description: props.quiz?.description ?? '',
    subject: props.quiz?.subject ?? (props.groups.length === 1 ? props.groups[0].subject : ''),
    level: props.quiz?.level ?? (props.groups.length === 1 ? props.groups[0].level : ''),
    duration_minutes: (props.quiz?.duration_minutes ?? null) as number | null,
    due_at: props.quiz?.due_at ?? localInputValue(7),
    corrections_at: props.quiz?.corrections_at ?? localInputValue(7),
    group_ids: (props.quiz?.group_ids ?? (props.groups.length === 1 ? [props.groups[0].id] : [])) as number[],
    questions: (props.quiz?.questions.map((q) => ({ key: nextKey++, ...q, choices: [...q.choices] })) ?? [blankQuestion()]) as EditableQuestion[],
});

const showDescription = ref(Boolean(form.description));
const focusKey = ref<number | null>(null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Quiz', href: route('teacher.quizzes.index') },
    { title: props.quiz ? 'Modifier' : 'Nouveau quiz', href: '#' },
]);

// ---------- Groups ----------
function toggleGroup(group: GroupOption) {
    const i = form.group_ids.indexOf(group.id);
    if (i >= 0) {
        form.group_ids.splice(i, 1);
        return;
    }
    form.group_ids.push(group.id);
    // Speed: the first chosen group fills subject and level.
    if (!form.subject) form.subject = group.subject;
    if (!form.level) form.level = group.level;
}

const subjects = computed(() => [...new Set([...props.groups.map((g) => g.subject), ...props.notions.map((n) => n.subject)])].sort());

// ---------- Notions of the subject ----------
const sameSubject = (a: string, b: string) => a.trim().toLowerCase() === b.trim().toLowerCase();
const subjectNotions = computed(() => {
    const used = new Set(form.questions.map((q) => q.notion_id));
    return props.notions.filter((n) => !form.subject || sameSubject(n.subject, form.subject) || used.has(n.id));
});

const notionDialog = ref(false);
const notionTarget = ref<number | null>(null);
function askNewNotion(questionIndex: number) {
    notionTarget.value = questionIndex;
    notionDialog.value = true;
}
function onNotionCreated(name: string) {
    // The page props now contain the new notion: select it on the question that asked for it.
    const created = props.notions.find((n) => n.name === name && sameSubject(n.subject, form.subject));
    if (created && notionTarget.value !== null) form.questions[notionTarget.value].notion_id = created.id;
}

// ---------- Dates ----------
const dueShortcuts = [
    { label: 'Demain', days: 1 },
    { label: '3 jours', days: 3 },
    { label: '1 semaine', days: 7 },
    { label: '2 semaines', days: 14 },
];
const durations = [null, 10, 15, 20, 30, 45];
watch(
    () => form.due_at,
    (due, previous) => {
        // Corrections follow the due date unless the teacher chose a later moment.
        if (!form.corrections_at || form.corrections_at < due || form.corrections_at === previous) form.corrections_at = due;
    },
);

// ---------- Questions ----------
function addQuestion() {
    const last = form.questions[form.questions.length - 1];
    const q = blankQuestion(last?.notion_id ?? '');
    focusKey.value = q.key;
    form.questions.push(q);
}
function duplicateQuestion(index: number) {
    const source = form.questions[index];
    const copy = { ...source, key: nextKey++, choices: [...source.choices] };
    focusKey.value = copy.key;
    form.questions.splice(index + 1, 0, copy);
}
function removeQuestion(index: number) {
    form.questions.splice(index, 1);
}
function moveQuestion(index: number, direction: -1 | 1) {
    const target = index + direction;
    const [q] = form.questions.splice(index, 1);
    form.questions.splice(target, 0, q);
}

// Errors are indexed by position: re-key them after reordering is not needed because
// they are cleared on every new submit.
const errors = computed(() => form.errors as Record<string, string>);

// ---------- Summary ----------
const notionCount = computed(() => new Set(form.questions.map((q) => q.notion_id).filter((id) => id !== '')).size);
const readyCount = computed(
    () => form.questions.filter((q) => q.text.trim() && q.notion_id !== '' && q.correct !== null && q.choices.every((c) => c.trim())).length,
);

// ---------- Save ----------
const publishing = ref(false);
function save(publish: boolean) {
    publishing.value = publish;
    const submit = form.transform((data) => ({
        ...data,
        publish,
        questions: data.questions.map(({ text, notion_id, choices, correct }) => ({ text, notion_id, choices, correct })),
    }));
    const options = {
        preserveScroll: true,
        onError: async () => {
            await nextTick();
            document.querySelector('[data-field-error]')?.closest('section, article')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
    };
    if (props.quiz) submit.put(route('teacher.quizzes.update', { quiz: props.quiz.id }), options);
    else submit.post(route('teacher.quizzes.store'), options);
}

function duplicate() {
    if (props.quiz) router.post(route('teacher.quizzes.duplicate', { quiz: props.quiz.id }));
}

const errorCount = computed(() => Object.keys(form.errors).length);
</script>

<template>
    <Head :title="quiz ? `Modifier · ${quiz.title}` : 'Nouveau quiz'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader
                :title="quiz ? 'Modifier le quiz' : 'Nouveau quiz'"
                description="Questions à 4 propositions, une seule bonne réponse, chacune liée à une notion."
                :back-href="route('teacher.quizzes.index')"
                back-label="Mes quiz"
            />

            <div
                v-if="locked"
                class="animate-enter flex flex-col gap-3 rounded-2xl border border-warning/40 bg-warning/10 p-4 text-sm sm:flex-row sm:items-center"
            >
                <Lock class="size-5 shrink-0 text-warning" />
                <p class="flex-1">
                    Déjà passé par <strong>{{ plural(attemptsCount ?? 0, 'étudiant') }}</strong> : les questions sont verrouillées pour garder des
                    résultats justes. Vous pouvez encore changer les dates et les groupes.
                </p>
                <Button variant="outline" class="h-10 shrink-0" @click="duplicate"><CopyPlus /> Dupliquer pour modifier</Button>
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="save(true)">
                <!-- 1. Information -->
                <section class="animate-stagger space-y-5 rounded-2xl border bg-card p-4 shadow-sm sm:p-6" style="--i: 1">
                    <h2 class="flex items-center gap-2 font-semibold"><FileText class="size-5 text-primary" /> Informations</h2>

                    <div class="grid gap-2">
                        <Label for="title">Titre</Label>
                        <Input id="title" v-model="form.title" class="h-11 text-base" placeholder="Ex. : Quiz 3 – Les boucles" required />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label class="flex items-center gap-2"><UsersRound class="size-4" /> Groupes</Label>
                        <div v-if="groups.length" class="flex flex-wrap gap-2">
                            <button
                                v-for="group in groups"
                                :key="group.id"
                                type="button"
                                :aria-pressed="form.group_ids.includes(group.id)"
                                :class="[
                                    'press inline-flex h-10 items-center gap-1.5 rounded-full border px-4 text-sm font-medium transition-colors duration-200',
                                    form.group_ids.includes(group.id)
                                        ? 'border-primary bg-primary text-primary-foreground shadow-sm shadow-primary/30'
                                        : 'bg-background hover:border-primary/50',
                                ]"
                                @click="toggleGroup(group)"
                            >
                                <Check v-if="form.group_ids.includes(group.id)" class="animate-pop size-4" />
                                {{ group.name }}
                            </button>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">Créez d'abord un groupe pour pouvoir publier ce quiz.</p>
                        <InputError :message="form.errors.group_ids ?? Object.entries(errors).find(([k]) => k.startsWith('group_ids.'))?.[1]" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-[1fr_9rem]">
                        <div class="grid gap-2">
                            <Label for="subject">Matière</Label>
                            <Input id="subject" v-model="form.subject" list="subjects" class="h-11" placeholder="Ex. : Algorithmique" required />
                            <datalist id="subjects">
                                <option v-for="s in subjects" :key="s" :value="s" />
                            </datalist>
                            <InputError :message="form.errors.subject" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="level">Niveau</Label>
                            <NativeSelect id="level" v-model="form.level" :options="levels" placeholder="Choisir" />
                            <InputError :message="form.errors.level" />
                        </div>
                    </div>

                    <div>
                        <button
                            v-if="!showDescription"
                            type="button"
                            class="text-sm font-medium text-primary hover:underline"
                            @click="showDescription = true"
                        >
                            + Ajouter une consigne pour les étudiants
                        </button>
                        <div v-else class="animate-enter grid gap-2">
                            <Label for="description">Consigne (facultatif)</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="2"
                                class="rounded-xl border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>
                </section>

                <!-- 2. Dates -->
                <section class="animate-stagger space-y-5 rounded-2xl border bg-card p-4 shadow-sm sm:p-6" style="--i: 2">
                    <h2 class="flex items-center gap-2 font-semibold"><CalendarClock class="size-5 text-primary" /> Dates et durée</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="due_at">Date limite</Label>
                            <Input id="due_at" v-model="form.due_at" type="datetime-local" class="h-11" required />
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="s in dueShortcuts"
                                    :key="s.days"
                                    type="button"
                                    class="press rounded-full bg-secondary px-3 py-1 text-xs font-medium text-secondary-foreground hover:bg-primary hover:text-primary-foreground"
                                    @click="form.due_at = localInputValue(s.days)"
                                >
                                    {{ s.label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.due_at" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="corrections_at">Corrections visibles à partir du</Label>
                            <Input id="corrections_at" v-model="form.corrections_at" type="datetime-local" class="h-11" required />
                            <p class="text-xs text-muted-foreground">
                                Avant cette date, les étudiants voient leur note mais pas les bonnes réponses.
                            </p>
                            <InputError :message="form.errors.corrections_at" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Durée pour répondre</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="d in durations"
                                :key="String(d)"
                                type="button"
                                :class="[
                                    'press h-10 rounded-full border px-4 text-sm font-medium transition-colors duration-200',
                                    form.duration_minutes === d
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-background hover:border-primary/50',
                                ]"
                                @click="form.duration_minutes = d"
                            >
                                {{ d === null ? 'Sans limite' : `${d} min` }}
                            </button>
                        </div>
                        <InputError :message="form.errors.duration_minutes" />
                    </div>
                </section>

                <!-- 3. Questions -->
                <section class="space-y-4">
                    <div class="flex items-end justify-between">
                        <h2 class="text-lg font-semibold">Questions</h2>
                        <p class="text-sm text-muted-foreground">
                            <span :key="readyCount" class="animate-pop inline-block font-semibold text-foreground">{{ readyCount }}</span> /
                            {{ form.questions.length }} prête{{ form.questions.length > 1 ? 's' : '' }}
                        </p>
                    </div>
                    <InputError :message="form.errors.questions" />

                    <TransitionGroup tag="div" name="question" class="relative flex flex-col gap-4">
                        <QuestionCard
                            v-for="(question, index) in form.questions"
                            :key="question.key"
                            v-model="form.questions[index]"
                            :index="index"
                            :total="form.questions.length"
                            :notions="subjectNotions"
                            :errors="errors"
                            :locked="locked"
                            :autofocus="question.key === focusKey"
                            @remove="removeQuestion(index)"
                            @duplicate="duplicateQuestion(index)"
                            @move="(dir) => moveQuestion(index, dir)"
                            @new-notion="askNewNotion(index)"
                        />
                    </TransitionGroup>

                    <button
                        v-if="!locked"
                        type="button"
                        class="press group flex h-16 w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed text-base font-medium text-muted-foreground transition-colors hover:border-primary hover:bg-primary/5 hover:text-primary"
                        @click="addQuestion"
                    >
                        <Plus class="size-5 transition-transform duration-300 group-hover:rotate-90" /> Ajouter une question
                    </button>
                </section>

                <!-- Sticky action bar (above the mobile bottom navigation) -->
                <div
                    class="sticky bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-20 flex flex-col gap-3 rounded-2xl border bg-background/95 p-3 shadow-lg backdrop-blur sm:flex-row sm:items-center md:bottom-4"
                >
                    <p class="flex-1 text-sm text-muted-foreground">
                        <template v-if="errorCount && readyCount < form.questions.length">
                            <span :key="readyCount" class="animate-enter inline-block font-medium text-destructive">
                                {{ plural(form.questions.length - readyCount, 'question') }} à compléter
                            </span>
                        </template>
                        <template v-else>
                            {{ plural(form.questions.length, 'question') }} · {{ plural(notionCount, 'notion') }} ·
                            {{ plural(form.group_ids.length, 'groupe') }}
                        </template>
                    </p>
                    <div class="flex gap-2">
                        <Button
                            v-if="!quiz?.published"
                            type="button"
                            variant="outline"
                            class="h-11 flex-1 sm:flex-none"
                            :disabled="form.processing"
                            @click="save(false)"
                        >
                            <LoaderCircle v-if="form.processing && !publishing" class="animate-spin" />
                            <Save v-else /> Brouillon
                        </Button>
                        <Button type="submit" class="h-11 flex-1 shadow-md shadow-primary/30 sm:flex-none" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing && publishing" class="animate-spin" />
                            <Rocket v-else /> {{ quiz?.published ? 'Enregistrer' : 'Publier' }}
                        </Button>
                    </div>
                </div>
            </form>
        </PageContainer>

        <NewNotionDialog v-model:open="notionDialog" :subject="form.subject" @created="onNotionCreated" />
    </AppLayout>
</template>

<style scoped>
.question-enter-active,
.question-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
.question-enter-from {
    opacity: 0;
    transform: translateY(16px) scale(0.98);
}
.question-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
.question-leave-active {
    position: absolute;
    width: 100%;
}
.question-move {
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
</style>
