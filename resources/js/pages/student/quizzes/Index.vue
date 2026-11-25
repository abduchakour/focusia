<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, fromNow, plural } from '@/lib/format';
import { keepPageForOffline } from '@/lib/offline/setup';
import { onSynced, pending } from '@/lib/offline/sync';
import { formatScore, scoreTone, toneBadge } from '@/lib/score';
import { type BreadcrumbItem } from '@/types';
import type { StudentQuizCard } from '@/types/quiz';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenCheck,
    CalendarClock,
    CloudUpload,
    Hourglass,
    Lock,
    PartyPopper,
    Play,
    Timer,
    UserRound,
    UsersRound,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{ quizzes: StudentQuizCard[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mes quiz', href: route('student.quizzes.index') }];

/** Answers finished on this phone but not yet received by the server. */
const waiting = computed(() => new Set(pending.value.filter((p) => !p.error).map((p) => p.quizId)));

const isTodo = (q: StudentQuizCard) => q.status === 'todo' || q.status === 'started';
const todo = computed(() => props.quizzes.filter((q) => isTodo(q) && !waiting.value.has(q.id)));
const finished = computed(() =>
    props.quizzes.filter((q) => !isTodo(q) || waiting.value.has(q.id)).sort((a, b) => new Date(b.due_at).getTime() - new Date(a.due_at).getTime()),
);

const tabs = [
    { value: 'todo', label: 'À faire' },
    { value: 'finished', label: 'Terminés' },
] as const;
const tab = ref<'todo' | 'finished'>(todo.value.length === 0 && props.quizzes.length > 0 ? 'finished' : 'todo');
const visible = computed(() => (tab.value === 'todo' ? todo.value : finished.value));

const soon = (q: StudentQuizCard) => new Date(q.due_at).getTime() - Date.now() < 24 * 3600 * 1000;

// Start: one attempt only, the timer starts on the server.
const toStart = ref<StudentQuizCard | null>(null);
const starting = ref(false);
const startDescription = computed(() => {
    const q = toStart.value;
    if (!q) return '';
    const timer = q.duration_minutes
        ? `Vous aurez ${q.duration_minutes} minutes à partir de maintenant.`
        : `Vous pourrez répondre jusqu'au ${dateTime(q.due_at)}.`;
    return `${plural(q.questions_count, 'question')}, une seule tentative. ${timer} Une fois le quiz ouvert, vous pouvez continuer même sans connexion.`;
});
function start() {
    if (!toStart.value) return;
    starting.value = true;
    router.post(
        route('student.quizzes.start', { quiz: toStart.value.id }),
        {},
        {
            onFinish: () => {
                starting.value = false;
                toStart.value = null;
            },
        },
    );
}

// A queued answer reached the server: refresh the grades.
const stop = onSynced(() => router.reload({ only: ['quizzes'] }));
onUnmounted(stop);
onMounted(() => keepPageForOffline());
</script>

<template>
    <Head title="Mes quiz" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader title="Mes quiz" description="Les quiz publiés dans vos groupes. Une seule tentative par quiz." />

            <EmptyState
                v-if="quizzes.length === 0"
                :icon="BookOpenCheck"
                title="Aucun quiz pour le moment"
                description="Les quiz de vos enseignants apparaîtront ici dès leur publication. Vérifiez que vous avez rejoint vos groupes."
            >
                <Button as-child variant="outline" class="h-11">
                    <Link :href="route('student.groups.index')"><UsersRound /> Mes groupes</Link>
                </Button>
            </EmptyState>

            <template v-else>
                <div class="relative grid grid-cols-2 rounded-xl bg-muted p-1 text-sm font-medium sm:w-80" role="tablist">
                    <span
                        class="absolute inset-y-1 left-1 rounded-lg bg-background shadow-sm transition-transform duration-300 ease-out"
                        :style="{ width: 'calc((100% - 0.5rem) / 2)', transform: `translateX(${tab === 'todo' ? 0 : 100}%)` }"
                    />
                    <button
                        v-for="t in tabs"
                        :key="t.value"
                        type="button"
                        role="tab"
                        :aria-selected="tab === t.value"
                        :class="[
                            'relative z-10 h-10 rounded-lg px-2 transition-colors',
                            tab === t.value ? 'text-foreground' : 'text-muted-foreground',
                        ]"
                        @click="tab = t.value"
                    >
                        {{ t.label }}
                        <span
                            v-if="t.value === 'todo' && todo.length"
                            class="ml-1 inline-flex size-5 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground"
                            >{{ todo.length }}</span
                        >
                        <span v-else class="text-xs opacity-60">{{ t.value === 'todo' ? todo.length : finished.length }}</span>
                    </button>
                </div>

                <EmptyState
                    v-if="visible.length === 0"
                    :icon="tab === 'todo' ? PartyPopper : Hourglass"
                    :title="tab === 'todo' ? 'Tout est fait !' : 'Aucun quiz terminé'"
                    :description="
                        tab === 'todo' ? 'Aucun quiz en attente. Revenez quand votre enseignant en publiera un.' : 'Vos notes apparaîtront ici.'
                    "
                />

                <ul v-else :key="tab" class="grid gap-4 md:grid-cols-2">
                    <li v-for="(quiz, i) in visible" :key="quiz.id" class="animate-stagger" :style="{ '--i': i }">
                        <article
                            :class="[
                                'lift relative flex h-full flex-col gap-4 overflow-hidden rounded-2xl border bg-card p-5',
                                quiz.status === 'missed' || quiz.status === 'started_late' ? 'opacity-75' : '',
                            ]"
                        >
                            <!-- accent bar -->
                            <span
                                :class="[
                                    'absolute inset-y-0 left-0 w-1',
                                    waiting.has(quiz.id)
                                        ? 'bg-warning'
                                        : quiz.status === 'done'
                                          ? 'bg-success'
                                          : isTodo(quiz)
                                            ? 'bg-primary'
                                            : 'bg-muted-foreground/30',
                                ]"
                            />

                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-primary">{{ quiz.subject }}</p>
                                    <h2 class="mt-1 text-lg font-semibold leading-snug">{{ quiz.title }}</h2>
                                    <p class="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                                        <UserRound class="size-3.5" /> {{ quiz.teacher }}
                                    </p>
                                </div>

                                <div
                                    v-if="quiz.status === 'done' && quiz.score !== null"
                                    :class="['animate-pop shrink-0 rounded-2xl px-3 py-2 text-center ring-1', toneBadge[scoreTone(quiz.score)]]"
                                >
                                    <span class="block text-2xl font-bold tabular-nums leading-none">{{ formatScore(quiz.score) }}</span>
                                    <span class="text-[11px] font-medium opacity-80">/ 20</span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs font-medium">
                                <span class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1">
                                    <BookOpenCheck class="size-3.5" /> {{ plural(quiz.questions_count, 'question') }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1">
                                    <Timer class="size-3.5" /> {{ quiz.duration_minutes ? `${quiz.duration_minutes} min` : 'Sans limite' }}
                                </span>
                                <span
                                    v-if="isTodo(quiz) && !waiting.has(quiz.id)"
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-1',
                                        soon(quiz) ? 'bg-warning/15 text-warning' : 'bg-muted',
                                    ]"
                                    :title="dateTime(quiz.due_at)"
                                >
                                    <CalendarClock class="size-3.5" /> Se termine {{ fromNow(quiz.due_at) }}
                                </span>
                            </div>

                            <div class="mt-auto">
                                <!-- Finished offline, waiting for the network -->
                                <p
                                    v-if="waiting.has(quiz.id)"
                                    class="flex items-center gap-2 rounded-xl bg-warning/10 px-3 py-2.5 text-sm font-medium text-warning"
                                >
                                    <CloudUpload class="size-4 shrink-0 animate-pulse" />
                                    Réponses enregistrées, envoi dès le retour du réseau
                                </p>

                                <Button
                                    v-else-if="quiz.status === 'todo'"
                                    class="press h-12 w-full text-base shadow-md shadow-primary/25"
                                    @click="toStart = quiz"
                                >
                                    <Play /> Commencer
                                </Button>

                                <Button v-else-if="quiz.status === 'started'" as-child class="press h-12 w-full text-base">
                                    <Link :href="route('student.quizzes.take', { quiz: quiz.id })"><Play /> Reprendre le quiz</Link>
                                </Button>

                                <template v-else-if="quiz.status === 'done'">
                                    <Button as-child variant="outline" class="press h-11 w-full">
                                        <Link :href="route('student.quizzes.result', { quiz: quiz.id })"> Voir mon résultat <ArrowRight /> </Link>
                                    </Button>
                                    <p class="mt-2 flex items-center justify-center gap-1 text-xs text-muted-foreground">
                                        <template v-if="quiz.corrections_visible">✓ Corrections disponibles</template>
                                        <template v-else><Lock class="size-3" /> Corrections {{ fromNow(quiz.corrections_at) }}</template>
                                    </p>
                                </template>

                                <p v-else class="rounded-xl bg-muted px-3 py-2.5 text-center text-sm text-muted-foreground">
                                    {{ quiz.status === 'missed' ? 'Non passé : la date limite est dépassée' : 'Non terminé avant la date limite' }}
                                </p>
                            </div>
                        </article>
                    </li>
                </ul>
            </template>
        </PageContainer>

        <ConfirmDialog
            :open="toStart !== null"
            :title="`Commencer « ${toStart?.title ?? ''} » ?`"
            :description="startDescription"
            confirm-label="C'est parti !"
            :processing="starting"
            @update:open="(v) => !v && (toStart = null)"
            @confirm="start"
        />
    </AppLayout>
</template>
