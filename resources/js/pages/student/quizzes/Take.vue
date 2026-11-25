<script setup lang="ts">
import OfflineBanner from '@/components/OfflineBanner.vue';
import { Button } from '@/components/ui/button';
import { plural } from '@/lib/format';
import { clockOffset, serverTime } from '@/lib/offline/clock';
import { db, entryKey } from '@/lib/offline/db';
import { keepPageForOffline } from '@/lib/offline/setup';
import { enqueue, flush, needsLogin, onSynced, online } from '@/lib/offline/sync';
import type { SharedData } from '@/types';
import type { TakeQuestion } from '@/types/quiz';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    CloudUpload,
    ListChecks,
    LoaderCircle,
    RefreshCw,
    Send,
    Timer,
    TriangleAlert,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
    quiz: {
        id: number;
        title: string;
        subject: string;
        description: string | null;
        teacher: string;
        duration_minutes: number | null;
        due_at: string;
    };
    attempt: { id: number; started_at: string; deadline: string };
    questions: TakeQuestion[];
    serverNow: number;
}>();

const page = usePage<SharedData>();
const userId = page.props.auth.user.id;
const key = entryKey(userId, props.quiz.id);
const letters = ['A', 'B', 'C', 'D'];

type Phase = 'loading' | 'answering' | 'review' | 'sending' | 'waiting' | 'refused';
const phase = ref<Phase>('loading');
const refusal = ref<string | null>(null);
const timeIsUp = ref(false);

// ---------- Answers, saved on the phone at every tap ----------
const answers = reactive<Record<number, number>>({});
const current = ref(0);
const direction = ref<1 | -1>(1);
const savedTick = ref(0);

const question = computed(() => props.questions[current.value]);
const answeredCount = computed(() => props.questions.filter((q) => answers[q.id] !== undefined).length);
const unanswered = computed(() => props.questions.length - answeredCount.value);
const isLast = computed(() => current.value === props.questions.length - 1);

function choose(choiceId: number) {
    answers[question.value.id] = choiceId;
}

function goTo(index: number) {
    if (index < 0 || index >= props.questions.length) return;
    direction.value = index >= current.value ? 1 : -1;
    current.value = index;
    phase.value = 'answering';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function next() {
    if (isLast.value) {
        phase.value = 'review';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        goTo(current.value + 1);
    }
}

watch(
    [answers, current],
    async () => {
        if (phase.value !== 'answering' && phase.value !== 'review') return;
        await db.drafts.put({ key, userId, quizId: props.quiz.id, answers: { ...answers }, current: current.value, updatedAt: Date.now() });
        savedTick.value++;
    },
    { deep: true },
);

// ---------- Timer (server time, measured clock gap) ----------
const offset = clockOffset(props.serverNow);
const now = ref(serverTime(offset));
const deadline = new Date(props.attempt.deadline).getTime();
const remaining = computed(() => Math.max(0, deadline - now.value));
const showTimer = computed(() => props.quiz.duration_minutes !== null || remaining.value < 3600_000);
const clock = computed(() => {
    const total = Math.ceil(remaining.value / 1000);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const pad = (n: number) => String(n).padStart(2, '0');
    return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
});
const urgency = computed(() => (remaining.value <= 60_000 ? 'critical' : remaining.value <= 180_000 ? 'low' : 'ok'));

let interval: number | undefined;
function tick() {
    now.value = serverTime(offset);
    if (remaining.value === 0 && (phase.value === 'answering' || phase.value === 'review')) {
        timeIsUp.value = true;
        void submit();
    }
}

// ---------- Sending (through the offline queue) ----------
async function submit() {
    phase.value = 'sending';
    const payload: Record<number, number | null> = {};
    props.questions.forEach((q) => (payload[q.id] = answers[q.id] ?? null));

    await enqueue({
        userId,
        quizId: props.quiz.id,
        quizTitle: props.quiz.title,
        url: route('student.quizzes.submit', { quiz: props.quiz.id }),
        answers: payload,
        answeredAt: new Date(Math.min(serverTime(offset), deadline)).toISOString(),
    });
    await showQueueState();
}

/** After a sync attempt: either we already left for the result page, or the answers wait on the phone. */
async function showQueueState() {
    const entry = await db.outbox.get(key);
    if (!entry) return;
    refusal.value = entry.error;
    phase.value = entry.error ? 'refused' : 'waiting';
}

async function retry() {
    phase.value = 'sending';
    await flush();
    await showQueueState();
}

const stopSynced = onSynced((entry, result) => {
    if (entry.quizId === props.quiz.id) router.visit(result.result_url);
});

// Keyboard on computers: A-D or 1-4 to answer, arrows to move.
function onKey(event: KeyboardEvent) {
    if (phase.value !== 'answering' || event.ctrlKey || event.metaKey || event.altKey) return;
    const index = ['a', 'b', 'c', 'd'].indexOf(event.key.toLowerCase());
    const digit = ['1', '2', '3', '4'].indexOf(event.key);
    const choice = question.value.choices[index >= 0 ? index : digit];
    if (choice) choose(choice.id);
    else if (event.key === 'ArrowRight') next();
    else if (event.key === 'ArrowLeft') goTo(current.value - 1);
}

onMounted(async () => {
    const [draft, queued] = await Promise.all([db.drafts.get(key), db.outbox.get(key)]);
    if (queued) {
        refusal.value = queued.error;
        phase.value = queued.error ? 'refused' : 'waiting';
        void flush().then(showQueueState);
    } else {
        if (draft) {
            Object.assign(answers, draft.answers);
            current.value = Math.min(draft.current, props.questions.length - 1);
        }
        phase.value = 'answering';
    }
    tick();
    interval = window.setInterval(tick, 1000);
    window.addEventListener('keydown', onKey);
    void keepPageForOffline();
});

onUnmounted(() => {
    window.clearInterval(interval);
    window.removeEventListener('keydown', onKey);
    stopSynced();
});
</script>

<template>
    <Head :title="quiz.title" />

    <div class="flex min-h-dvh flex-col bg-background">
        <!-- Top bar: leave, title, timer, progress -->
        <header class="sticky top-0 z-30 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <div class="mx-auto flex h-16 max-w-2xl items-center gap-3 px-4">
                <Link
                    :href="route('student.quizzes.index')"
                    class="press -ml-2 flex size-11 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Quitter le quiz (vos réponses restent sur ce téléphone)"
                    title="Quitter : vos réponses restent sur ce téléphone"
                >
                    <X class="size-5" />
                </Link>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold leading-tight">{{ quiz.title }}</p>
                    <p class="truncate text-xs text-muted-foreground">{{ quiz.subject }} · {{ quiz.teacher }}</p>
                </div>
                <div
                    v-if="showTimer && phase !== 'waiting' && phase !== 'refused'"
                    :class="[
                        'flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 font-mono text-sm font-bold tabular-nums transition-colors duration-500',
                        urgency === 'critical'
                            ? 'bg-destructive text-destructive-foreground shadow-md shadow-destructive/30'
                            : urgency === 'low'
                              ? 'bg-warning/15 text-warning'
                              : 'bg-muted text-foreground',
                    ]"
                    role="timer"
                    :aria-label="`Temps restant ${clock}`"
                >
                    <Timer :class="['size-4', urgency === 'critical' ? 'animate-tick' : '']" />
                    {{ clock }}
                </div>
            </div>
            <div class="h-1 bg-muted" aria-hidden="true">
                <div
                    class="h-full rounded-r-full bg-primary transition-[width] duration-500 ease-out"
                    :style="{ width: `${(answeredCount / questions.length) * 100}%` }"
                />
            </div>
            <p v-if="urgency === 'critical' && phase === 'answering'" class="sr-only" aria-live="assertive">Moins d'une minute restante</p>
        </header>

        <OfflineBanner />

        <main class="mx-auto w-full max-w-2xl flex-1 px-4 pb-36 pt-5">
            <div v-if="phase === 'loading'" class="flex justify-center py-20">
                <LoaderCircle class="size-8 animate-spin text-primary" />
            </div>

            <!-- One question per screen -->
            <template v-else-if="phase === 'answering'">
                <nav class="-mx-4 overflow-x-auto px-4 py-1.5" aria-label="Questions">
                    <ol class="flex w-max gap-2">
                        <li v-for="(q, i) in questions" :key="q.id">
                            <button
                                type="button"
                                :class="[
                                    'press flex size-9 items-center justify-center rounded-full text-sm font-semibold transition-all duration-200',
                                    i === current ? 'ring-2 ring-primary ring-offset-2 ring-offset-background' : '',
                                    answers[q.id] !== undefined ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                                ]"
                                :aria-label="`Question ${i + 1}${answers[q.id] !== undefined ? ' (répondue)' : ''}`"
                                :aria-current="i === current ? 'step' : undefined"
                                @click="goTo(i)"
                            >
                                {{ i + 1 }}
                            </button>
                        </li>
                    </ol>
                </nav>

                <Transition :name="direction === 1 ? 'q-next' : 'q-prev'" mode="out-in">
                    <section :key="question.id" class="mt-4">
                        <p class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span class="font-medium text-foreground">Question {{ current + 1 }}</span> sur {{ questions.length }}
                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">{{
                                question.notion
                            }}</span>
                        </p>
                        <h1 class="mt-3 whitespace-pre-line text-xl font-semibold leading-snug sm:text-2xl">{{ question.text }}</h1>

                        <ul class="mt-6 grid gap-3" role="radiogroup" :aria-label="`Propositions de la question ${current + 1}`">
                            <li v-for="(choice, c) in question.choices" :key="choice.id" class="animate-stagger" :style="{ '--i': c }">
                                <button
                                    type="button"
                                    role="radio"
                                    :aria-checked="answers[question.id] === choice.id"
                                    :class="[
                                        'press flex min-h-14 w-full items-center gap-3 rounded-2xl border-2 p-3 text-left transition-[border-color,background-color,box-shadow] duration-200',
                                        answers[question.id] === choice.id
                                            ? 'border-primary bg-primary/5 shadow-md shadow-primary/10'
                                            : 'border-border hover:border-primary/40 hover:bg-muted/50',
                                    ]"
                                    @click="choose(choice.id)"
                                >
                                    <span
                                        :class="[
                                            'flex size-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold transition-colors duration-200',
                                            answers[question.id] === choice.id
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        <Check v-if="answers[question.id] === choice.id" class="animate-pop size-5" />
                                        <span v-else>{{ letters[c] }}</span>
                                    </span>
                                    <span class="flex-1 text-base leading-snug">{{ choice.text }}</span>
                                </button>
                            </li>
                        </ul>
                    </section>
                </Transition>
            </template>

            <!-- Review before sending -->
            <section v-else-if="phase === 'review'" class="animate-enter">
                <div class="text-center">
                    <div class="animate-pop mx-auto flex size-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <ListChecks class="size-8" />
                    </div>
                    <h1 class="mt-4 text-2xl font-bold">Prêt à envoyer ?</h1>
                    <p class="mt-1 text-muted-foreground">
                        {{ answeredCount }} {{ answeredCount > 1 ? 'réponses' : 'réponse' }} sur {{ questions.length }}
                    </p>
                </div>

                <p v-if="unanswered > 0" class="mt-5 flex items-start gap-2 rounded-xl bg-warning/10 p-3 text-sm text-warning">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    {{ plural(unanswered, 'question') }} sans réponse {{ unanswered > 1 ? 'compteront' : 'comptera' }} comme
                    {{ unanswered > 1 ? 'fausses' : 'fausse' }}. Touchez un numéro pour y revenir.
                </p>

                <ol class="mt-6 grid grid-cols-5 gap-2 sm:grid-cols-8">
                    <li v-for="(q, i) in questions" :key="q.id" class="animate-stagger" :style="{ '--i': i }">
                        <button
                            type="button"
                            :class="[
                                'press flex aspect-square w-full items-center justify-center rounded-xl text-sm font-bold',
                                answers[q.id] !== undefined
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border-2 border-dashed border-warning bg-warning/5 text-warning',
                            ]"
                            :aria-label="`Revenir à la question ${i + 1}`"
                            @click="goTo(i)"
                        >
                            {{ i + 1 }}
                        </button>
                    </li>
                </ol>

                <p class="mt-6 text-center text-sm text-muted-foreground">
                    Une seule tentative : après l'envoi, vos réponses ne pourront plus être modifiées.
                    <template v-if="!online"><br />Vous êtes hors connexion : elles partiront automatiquement au retour du réseau.</template>
                </p>
            </section>

            <!-- Sending -->
            <section v-else-if="phase === 'sending'" class="animate-enter py-16 text-center">
                <LoaderCircle class="mx-auto size-10 animate-spin text-primary" />
                <p class="mt-4 font-medium">{{ timeIsUp ? 'Temps écoulé : envoi de vos réponses…' : 'Envoi de vos réponses…' }}</p>
            </section>

            <!-- Saved on the phone, waiting for the network -->
            <section v-else-if="phase === 'waiting'" class="animate-enter py-8 text-center">
                <div class="relative mx-auto size-24">
                    <span class="animate-halo absolute inset-0 rounded-full bg-warning/25" />
                    <div class="animate-pop relative flex size-24 items-center justify-center rounded-full bg-warning/15 text-warning">
                        <CloudUpload class="size-10" />
                    </div>
                </div>
                <h1 class="mt-6 text-2xl font-bold">Réponses enregistrées sur ce téléphone</h1>
                <p v-if="timeIsUp" class="mt-2 font-medium text-warning">Le temps est écoulé.</p>
                <p v-if="needsLogin" class="mx-auto mt-2 max-w-sm text-muted-foreground">
                    Votre session a expiré. Reconnectez-vous : vos réponses seront envoyées juste après.
                </p>
                <p v-else-if="!online" class="mx-auto mt-2 max-w-sm text-muted-foreground">
                    Vous êtes hors connexion. Elles seront envoyées <strong class="text-foreground">automatiquement</strong> dès le retour du réseau,
                    et votre note s'affichera. Vous pouvez fermer l'application.
                </p>
                <p v-else class="mx-auto mt-2 max-w-sm text-muted-foreground">
                    Le serveur ne répond pas pour le moment. Nouvel essai automatique dans quelques instants.
                </p>

                <div class="mx-auto mt-8 flex max-w-sm flex-col gap-2">
                    <Button v-if="needsLogin" as-child class="h-12"><Link :href="route('login')">Se reconnecter</Link></Button>
                    <Button v-else class="h-12" :disabled="!online" @click="retry"><RefreshCw /> Réessayer maintenant</Button>
                    <Button variant="outline" as-child class="h-12"><Link :href="route('student.quizzes.index')">Retour à mes quiz</Link></Button>
                </div>
            </section>

            <!-- Refused by the server (e.g. answered after the due date) -->
            <section v-else-if="phase === 'refused'" class="animate-enter py-8 text-center">
                <div class="animate-pop mx-auto flex size-20 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                    <CircleAlert class="size-10" />
                </div>
                <h1 class="mt-6 text-2xl font-bold">Réponses non acceptées</h1>
                <p class="mx-auto mt-2 max-w-sm text-muted-foreground">{{ refusal }}</p>
                <Button variant="outline" as-child class="mt-8 h-12"><Link :href="route('student.quizzes.index')">Retour à mes quiz</Link></Button>
            </section>
        </main>

        <!-- Bottom actions -->
        <footer
            v-if="phase === 'answering' || phase === 'review'"
            class="fixed inset-x-0 bottom-0 z-30 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur supports-[backdrop-filter]:bg-background/80"
        >
            <div class="mx-auto flex max-w-2xl items-center gap-3 px-4 py-3">
                <template v-if="phase === 'answering'">
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-12 shrink-0"
                        :disabled="current === 0"
                        aria-label="Question précédente"
                        @click="goTo(current - 1)"
                    >
                        <ChevronLeft class="size-5" />
                    </Button>
                    <p class="flex flex-1 items-center justify-center gap-1.5 text-xs text-muted-foreground">
                        <CloudUpload v-if="!online" class="size-3.5" />
                        <Check v-else :key="savedTick" class="animate-pop size-3.5 text-success" />
                        <span class="hidden min-[360px]:inline">Enregistré sur ce téléphone</span>
                    </p>
                    <Button class="h-12 shrink-0 px-6 text-base" @click="next">
                        {{ isLast ? 'Terminer' : 'Suivant' }} <ChevronRight class="size-5" />
                    </Button>
                </template>
                <template v-else>
                    <Button variant="outline" class="h-12 shrink-0" @click="goTo(current)"><ChevronLeft /> Revoir</Button>
                    <Button class="h-12 flex-1 text-base shadow-md shadow-primary/25" @click="submit"><Send /> Envoyer mes réponses</Button>
                </template>
            </div>
        </footer>
    </div>
</template>
