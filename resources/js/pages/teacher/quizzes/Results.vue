<script setup lang="ts">
import GradeHistogram from '@/components/charts/GradeHistogram.vue';
import RateBars from '@/components/charts/RateBars.vue';
import EmptyState from '@/components/EmptyState.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useReveal } from '@/composables/useReveal';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, fromNow, percent, plural } from '@/lib/format';
import { formatScore, scoreTone, toneBadge } from '@/lib/score';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, CircleCheckBig, CloudOff, Hourglass, Pencil, Sigma, Target, TriangleAlert, UsersRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface QuestionStat {
    id: number;
    position: number;
    text: string;
    notion: string;
    answered: number;
    blank: number;
    rate: number | null;
    choices: { id: number; text: string; is_correct: boolean; count: number }[];
}

const props = defineProps<{
    quiz: { id: number; title: string; subject: string; due_at: string; corrections_at: string; is_open: boolean };
    groups: { id: number; name: string }[];
    selectedGroup: number | null;
    stats: {
        submitted: number;
        expected: number;
        average: number | null;
        median: number | null;
        min: number | null;
        max: number | null;
        passed: number;
    };
    distribution: { label: string; from: number; to: number; count: number }[];
    notions: { name: string; correct: number; answered: number; rate: number | null }[];
    questions: QuestionStat[];
    students: {
        id: number;
        name: string;
        groups: string[];
        score: number;
        correct: number;
        total: number;
        answered_at: string;
        synced_late: boolean;
        removed: boolean;
    }[];
    missing: { id: number; name: string; groups: string[]; started: boolean }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Quiz', href: route('teacher.quizzes.index') },
    { title: props.quiz.title, href: route('teacher.quizzes.results', { quiz: props.quiz.id }) },
];

// Group filter (kept in the URL so the page can be shared or reloaded).
const group = ref(props.selectedGroup ? String(props.selectedGroup) : '');
const groupOptions = computed(() => [{ value: '', label: 'Tous les groupes' }, ...props.groups.map((g) => ({ value: String(g.id), label: g.name }))]);
watch(group, (value) =>
    router.get(route('teacher.quizzes.results', { quiz: props.quiz.id }), value ? { groupe: value } : {}, {
        preserveScroll: true,
        preserveState: true,
    }),
);

const participation = computed(() => (props.stats.expected ? props.stats.submitted / props.stats.expected : 0));
const submittedCount = computed(() => props.students.length);

// Questions: in quiz order or the most missed first.
const hardestFirst = ref(false);
const questionList = computed(() => (hardestFirst.value ? [...props.questions].sort((a, b) => (a.rate ?? 2) - (b.rate ?? 2)) : props.questions));
const letters = ['A', 'B', 'C', 'D'];
const share = (q: QuestionStat, count: number) => (q.answered ? count / q.answered : 0);
/** The wrong answer chosen most often, if it is a real trend (at least 25 % of the answers). */
const frequentError = (q: QuestionStat) => {
    const wrong = q.choices.filter((c) => !c.is_correct).sort((a, b) => b.count - a.count)[0];
    return wrong && q.answered && wrong.count / q.answered >= 0.25 ? wrong.id : null;
};

const revealed = useReveal();
</script>

<template>
    <Head :title="`Résultats – ${quiz.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader
                :title="quiz.title"
                :description="`${quiz.subject} · ${quiz.is_open ? 'se termine' : 'terminé'} ${fromNow(quiz.due_at)}`"
                :back-href="route('teacher.quizzes.index')"
                back-label="Mes quiz"
            >
                <template #actions>
                    <NativeSelect v-if="groups.length > 1" v-model="group" :options="groupOptions" class="w-56" aria-label="Filtrer par groupe" />
                    <Button as-child variant="outline" class="h-11">
                        <Link :href="route('teacher.quizzes.edit', { quiz: quiz.id })"><Pencil /> Modifier</Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Key figures -->
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div class="animate-stagger lift rounded-2xl border bg-card p-5" style="--i: 1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-medium text-muted-foreground">Participation</p>
                        <div class="flex size-9 items-center justify-center rounded-xl bg-secondary text-primary"><UsersRound class="size-5" /></div>
                    </div>
                    <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight">
                        {{ stats.submitted }}<span class="ml-1 text-lg font-medium text-muted-foreground">/ {{ stats.expected }}</span>
                    </p>
                    <div class="mt-2 h-1.5 rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary transition-[width] duration-1000 ease-out"
                            :style="{ width: revealed ? `${participation * 100}%` : '0%' }"
                        />
                    </div>
                </div>
                <StatCard
                    label="Moyenne"
                    :value="stats.average ?? '—'"
                    suffix="/ 20"
                    :decimals="2"
                    :icon="Target"
                    :index="2"
                    :hint="stats.min !== null ? `de ${formatScore(stats.min)} à ${formatScore(stats.max ?? 0)}` : undefined"
                />
                <StatCard label="Médiane" :value="stats.median ?? '—'" suffix="/ 20" :decimals="2" :icon="Sigma" :index="3" />
                <StatCard
                    label="Au moins 10/20"
                    :value="submittedCount ? stats.passed : '—'"
                    :suffix="`/ ${submittedCount}`"
                    :icon="CircleCheckBig"
                    :index="4"
                    :hint="submittedCount ? `${percent(stats.passed / submittedCount)} des copies` : undefined"
                />
            </div>

            <EmptyState
                v-if="submittedCount === 0"
                :icon="Hourglass"
                title="Aucune copie pour le moment"
                :description="
                    quiz.is_open
                        ? `Les résultats apparaîtront ici dès que les étudiants auront répondu. Date limite : ${dateTime(quiz.due_at)}.`
                        : 'Aucun étudiant n\'a répondu à ce quiz.'
                "
            />

            <template v-else>
                <div class="grid gap-4 lg:grid-cols-2">
                    <section class="animate-enter rounded-2xl border bg-card p-5" style="animation-delay: 0.1s">
                        <h2 class="font-semibold">Répartition des notes</h2>
                        <p class="mb-6 text-sm text-muted-foreground">Nombre d'étudiants par tranche de note.</p>
                        <GradeHistogram :bins="distribution" />
                    </section>
                    <section class="animate-enter rounded-2xl border bg-card p-5" style="animation-delay: 0.2s">
                        <h2 class="font-semibold">Compréhension par notion</h2>
                        <p class="mb-5 text-sm text-muted-foreground">Part de bonnes réponses sur chaque notion évaluée.</p>
                        <RateBars :items="notions" sort />
                    </section>
                </div>

                <!-- Question by question -->
                <section class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold">Question par question</h2>
                        <div class="flex rounded-xl bg-muted p-1 text-sm font-medium" role="group" aria-label="Ordre des questions">
                            <button
                                type="button"
                                :class="[
                                    'h-9 rounded-lg px-3 transition-colors',
                                    !hardestFirst ? 'bg-background shadow-sm' : 'text-muted-foreground',
                                ]"
                                :aria-pressed="!hardestFirst"
                                @click="hardestFirst = false"
                            >
                                Ordre du quiz
                            </button>
                            <button
                                type="button"
                                :class="['h-9 rounded-lg px-3 transition-colors', hardestFirst ? 'bg-background shadow-sm' : 'text-muted-foreground']"
                                :aria-pressed="hardestFirst"
                                @click="hardestFirst = true"
                            >
                                Les plus ratées
                            </button>
                        </div>
                    </div>

                    <TransitionGroup tag="ol" name="fade-list" class="grid gap-3 lg:grid-cols-2">
                        <li v-for="q in questionList" :key="q.id" class="rounded-2xl border bg-card p-4 sm:p-5">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-secondary text-sm font-bold text-primary"
                                    >{{ q.position }}</span
                                >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">{{
                                            q.notion
                                        }}</span>
                                        <span
                                            v-if="q.rate !== null"
                                            :class="[
                                                'inline-flex items-center gap-1 text-xs font-semibold',
                                                q.rate < 0.5 ? 'text-warning' : 'text-success',
                                            ]"
                                        >
                                            <TriangleAlert v-if="q.rate < 0.5" class="size-3.5" />
                                            {{ percent(q.rate) }} de réussite
                                        </span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line font-medium leading-snug">{{ q.text }}</p>
                                </div>
                            </div>

                            <ul class="mt-3 space-y-2">
                                <li v-for="(c, i) in q.choices" :key="c.id" class="relative overflow-hidden rounded-xl border px-3 py-2 text-sm">
                                    <div
                                        :class="[
                                            'absolute inset-y-0 left-0 transition-[width] duration-700 ease-out',
                                            c.is_correct ? 'bg-success/15' : c.id === frequentError(q) ? 'bg-warning/15' : 'bg-muted',
                                        ]"
                                        :style="{ width: revealed ? `${share(q, c.count) * 100}%` : '0%' }"
                                    />
                                    <div class="relative flex items-center gap-2">
                                        <span
                                            :class="[
                                                'flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-bold',
                                                c.is_correct ? 'bg-success text-success-foreground' : 'bg-background',
                                            ]"
                                        >
                                            <Check v-if="c.is_correct" class="size-3.5" />
                                            <template v-else>{{ letters[i] }}</template>
                                        </span>
                                        <span class="min-w-0 flex-1 truncate">{{ c.text }}</span>
                                        <span v-if="c.id === frequentError(q)" class="shrink-0 text-xs font-medium text-warning"
                                            >Erreur fréquente</span
                                        >
                                        <span class="w-10 shrink-0 text-right text-xs font-semibold tabular-nums">{{
                                            percent(share(q, c.count))
                                        }}</span>
                                    </div>
                                </li>
                            </ul>
                            <p v-if="q.blank" class="mt-2 text-xs text-muted-foreground">{{ plural(q.blank, 'étudiant') }} sans réponse</p>
                        </li>
                    </TransitionGroup>
                </section>

                <!-- Students (also the table view of the charts) -->
                <section class="animate-enter overflow-hidden rounded-2xl border bg-card">
                    <div class="flex items-center justify-between p-5 pb-3">
                        <h2 class="font-semibold">Copies ({{ students.length }})</h2>
                        <p class="text-xs text-muted-foreground"><CloudOff class="inline size-3.5" /> = répondu hors ligne, reçu plus tard</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-y bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-5 py-2.5 font-medium">Étudiant</th>
                                    <th class="hidden px-3 py-2.5 font-medium sm:table-cell">Groupe</th>
                                    <th class="px-3 py-2.5 font-medium">Note</th>
                                    <th class="hidden px-5 py-2.5 font-medium md:table-cell">Répondu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in students" :key="s.id" class="border-b last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <span class="font-medium">{{ s.name }}</span>
                                        <span v-if="s.removed" class="ml-2 rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground"
                                            >retiré du groupe</span
                                        >
                                    </td>
                                    <td class="hidden px-3 py-3 text-muted-foreground sm:table-cell">{{ s.groups.join(', ') }}</td>
                                    <td class="px-3 py-3">
                                        <span
                                            :class="[
                                                'inline-flex rounded-lg px-2 py-0.5 font-bold tabular-nums ring-1',
                                                toneBadge[scoreTone(s.score)],
                                            ]"
                                        >
                                            {{ formatScore(s.score) }}
                                        </span>
                                        <span class="ml-2 text-xs text-muted-foreground">{{ s.correct }}/{{ s.total }}</span>
                                    </td>
                                    <td class="hidden px-5 py-3 text-muted-foreground md:table-cell">
                                        {{ dateTime(s.answered_at) }}
                                        <CloudOff v-if="s.synced_late" class="ml-1 inline size-3.5" aria-label="Répondu hors ligne, reçu plus tard" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>

            <section v-if="missing.length" class="animate-enter rounded-2xl border border-dashed p-5">
                <h2 class="font-semibold">{{ quiz.is_open ? 'Pas encore répondu' : "N'ont pas répondu" }} ({{ missing.length }})</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    <li v-for="m in missing" :key="m.id" class="rounded-full bg-muted px-3 py-1 text-sm">
                        {{ m.name }}<span v-if="m.started" class="ml-1 text-xs text-warning">· commencé</span>
                    </li>
                </ul>
            </section>
        </PageContainer>
    </AppLayout>
</template>

<style scoped>
.fade-list-move {
    transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
</style>
