<script setup lang="ts">
import RateBars from '@/components/charts/RateBars.vue';
import TrendLine from '@/components/charts/TrendLine.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { percent } from '@/lib/format';
import { formatScore, scoreTone, toneBadge } from '@/lib/score';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpenCheck, ChartNoAxesColumn, GraduationCap, LifeBuoy, PartyPopper, Target, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

interface StudentRow {
    id: number;
    name: string;
    average: number | null;
    done: number;
    total: number;
    rates: Record<number, number | null>;
    difficulty: boolean;
    reasons: string[];
}

const props = defineProps<{
    group: { id: number; name: string; subject: string; level: string };
    thresholds: { average: number; rate: number };
    quizzes: { id: number; title: string; due_at: string; submitted: number; expected: number; average: number | null }[];
    notions: { id: number; name: string; correct: number; answered: number; rate: number | null }[];
    students: StudentRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Groupes', href: route('teacher.groups.index') },
    { title: props.group.name, href: route('teacher.groups.show', { group: props.group.id }) },
    { title: 'Suivi', href: route('teacher.groups.insights', { group: props.group.id }) },
];

const flagged = computed(() => props.students.filter((s) => s.difficulty));
const groupAverage = computed(() => {
    const values = props.students.map((s) => s.average).filter((a): a is number => a !== null);
    return values.length ? Math.round((values.reduce((a, b) => a + b, 0) / values.length) * 100) / 100 : null;
});
const trend = computed(() => props.quizzes.map((q) => ({ label: q.title, value: q.average, hint: `${q.submitted}/${q.expected} copies` })));

/** Heat map cell: one hue, darker = better understood; below the threshold also gets a marker. */
function cellStyle(rate: number | null | undefined) {
    if (rate === null || rate === undefined) return {};
    return { backgroundColor: `hsl(var(--primary) / ${0.08 + rate * 0.72})` };
}
const darkText = (rate: number | null | undefined) => rate !== null && rate !== undefined && rate >= 0.6;
</script>

<template>
    <Head :title="`Suivi – ${group.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader
                :title="`Suivi de la compréhension`"
                :description="`${group.name} · ${group.subject} · ${group.level}`"
                :back-href="route('teacher.groups.show', { group: group.id })"
                back-label="Retour au groupe"
            />

            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatCard label="Étudiants" :value="students.length" :icon="GraduationCap" :index="1" />
                <StatCard label="Moyenne des quiz" :value="groupAverage ?? '—'" suffix="/ 20" :decimals="2" :icon="Target" :index="2" />
                <StatCard
                    label="En difficulté"
                    :value="flagged.length"
                    :icon="LifeBuoy"
                    :index="3"
                    :hint="`moyenne < ${thresholds.average} ou notion < ${percent(thresholds.rate)}`"
                />
                <StatCard label="Quiz publiés" :value="quizzes.length" :icon="BookOpenCheck" :index="4" />
            </div>

            <EmptyState
                v-if="notions.length === 0"
                :icon="ChartNoAxesColumn"
                title="Pas encore de résultats"
                description="Le suivi par notion apparaîtra dès que les étudiants de ce groupe auront répondu à un quiz."
            />

            <template v-else>
                <div class="grid gap-4 lg:grid-cols-2">
                    <section class="animate-enter rounded-2xl border bg-card p-5" style="animation-delay: 0.1s">
                        <h2 class="font-semibold">Compréhension par notion</h2>
                        <p class="mb-5 text-sm text-muted-foreground">
                            Toutes les réponses du groupe, des notions les moins comprises aux mieux comprises.
                        </p>
                        <RateBars :items="notions" :threshold="thresholds.rate" sort />
                    </section>

                    <section class="animate-enter rounded-2xl border bg-card p-5" style="animation-delay: 0.2s">
                        <h2 class="font-semibold">Moyenne du groupe, quiz après quiz</h2>
                        <p class="mb-4 text-sm text-muted-foreground">Sur 20 ; la ligne pointillée marque 10/20.</p>
                        <TrendLine :points="trend" />
                        <ul class="mt-3 space-y-1 text-xs text-muted-foreground">
                            <li v-for="q in quizzes" :key="q.id" class="flex justify-between gap-3">
                                <Link :href="route('teacher.quizzes.results', { quiz: q.id })" class="truncate hover:text-primary hover:underline">{{
                                    q.title
                                }}</Link>
                                <span class="shrink-0 tabular-nums"
                                    >{{ q.average === null ? '—' : `${formatScore(q.average)}/20` }} · {{ q.submitted }}/{{ q.expected }}</span
                                >
                            </li>
                        </ul>
                    </section>
                </div>

                <!-- Students to support -->
                <section class="animate-enter rounded-2xl border bg-card p-5" style="animation-delay: 0.3s">
                    <div class="flex items-center gap-2">
                        <LifeBuoy class="size-5 text-warning" />
                        <h2 class="font-semibold">Étudiants à accompagner ({{ flagged.length }})</h2>
                    </div>
                    <p v-if="flagged.length === 0" class="mt-3 flex items-center gap-2 text-sm text-muted-foreground">
                        <PartyPopper class="size-4 text-primary" /> Aucun étudiant en difficulté. Bravo au groupe !
                    </p>
                    <ul v-else class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <li
                            v-for="(s, i) in flagged"
                            :key="s.id"
                            class="animate-stagger flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/5 p-3"
                            :style="{ '--i': i }"
                        >
                            <span
                                v-if="s.average !== null"
                                :class="[
                                    'flex size-11 shrink-0 flex-col items-center justify-center rounded-lg text-sm font-bold tabular-nums ring-1',
                                    toneBadge[scoreTone(s.average)],
                                ]"
                            >
                                {{ formatScore(s.average) }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ s.name }}</p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span
                                        v-for="r in s.reasons"
                                        :key="r"
                                        class="inline-flex items-center gap-1 rounded-md bg-warning/15 px-1.5 py-0.5 text-[11px] font-medium text-warning"
                                    >
                                        <TriangleAlert class="size-3" /> {{ r }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Heat map: students x notions -->
                <section class="animate-enter overflow-hidden rounded-2xl border bg-card" style="animation-delay: 0.4s">
                    <div class="flex flex-wrap items-end justify-between gap-3 p-5 pb-3">
                        <div>
                            <h2 class="font-semibold">Carte de compréhension</h2>
                            <p class="text-sm text-muted-foreground">Réussite de chaque étudiant sur chaque notion.</p>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground" aria-hidden="true">
                            0 %
                            <span
                                class="h-3 w-28 rounded-full"
                                style="background: linear-gradient(to right, hsl(var(--primary) / 0.08), hsl(var(--primary) / 0.8))"
                            />
                            100 %
                            <span class="ml-2 inline-flex items-center gap-1"
                                ><TriangleAlert class="size-3 text-warning" /> sous {{ percent(thresholds.rate) }}</span
                            >
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-separate border-spacing-0.5 px-3 pb-3 text-sm">
                            <thead>
                                <tr>
                                    <th class="sticky left-0 z-10 bg-card px-2 py-2 text-left text-xs font-medium text-muted-foreground">Étudiant</th>
                                    <th class="px-2 py-2 text-center text-xs font-medium text-muted-foreground">Moyenne</th>
                                    <th
                                        v-for="n in notions"
                                        :key="n.id"
                                        class="min-w-20 px-2 py-2 text-center text-xs font-medium text-muted-foreground"
                                    >
                                        {{ n.name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in students" :key="s.id">
                                    <th class="sticky left-0 z-10 max-w-44 truncate bg-card px-2 py-1.5 text-left font-medium" scope="row">
                                        <TriangleAlert v-if="s.difficulty" class="mr-1 inline size-3.5 text-warning" aria-label="En difficulté" />{{
                                            s.name
                                        }}
                                    </th>
                                    <td class="px-2 py-1.5 text-center tabular-nums">
                                        {{ s.average === null ? '—' : formatScore(s.average) }}
                                        <span class="text-[10px] text-muted-foreground">({{ s.done }}/{{ s.total }})</span>
                                    </td>
                                    <td
                                        v-for="n in notions"
                                        :key="n.id"
                                        :class="[
                                            'rounded-md px-2 py-1.5 text-center text-xs font-semibold tabular-nums transition-transform hover:scale-105',
                                            darkText(s.rates[n.id]) ? 'text-primary-foreground' : '',
                                            s.rates[n.id] === undefined || s.rates[n.id] === null ? 'text-muted-foreground' : '',
                                        ]"
                                        :style="cellStyle(s.rates[n.id])"
                                        :title="`${s.name} · ${n.name}`"
                                    >
                                        <template v-if="s.rates[n.id] === undefined || s.rates[n.id] === null">—</template>
                                        <template v-else>
                                            <TriangleAlert
                                                v-if="(s.rates[n.id] ?? 1) < thresholds.rate"
                                                class="mr-0.5 inline size-3 text-warning"
                                            />{{ percent(s.rates[n.id] ?? 0) }}
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </PageContainer>
    </AppLayout>
</template>
