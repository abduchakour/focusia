<script setup lang="ts">
import RateBars from '@/components/charts/RateBars.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, fromNow, plural } from '@/lib/format';
import { keepPageForOffline } from '@/lib/offline/setup';
import { formatScore, scoreTone, toneBadge } from '@/lib/score';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BookOpenCheck, CalendarClock, ChevronRight, PartyPopper, Target, Timer, UsersRound } from 'lucide-vue-next';
import { onMounted } from 'vue';

defineProps<{
    stats: { groups: number; attempts: number; average: number | null };
    todo: { id: number; title: string; subject: string; questions_count: number; duration_minutes: number | null; due_at: string }[];
    recent: { quiz_id: number; title: string; subject: string; score: number; answered_at: string }[];
    notions: { name: string; correct: number; answered: number; rate: number | null }[];
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tableau de bord', href: route('student.dashboard') }];

const soon = (iso: string) => new Date(iso).getTime() - Date.now() < 24 * 3600 * 1000;

// The installed app opens here: keep it available offline.
onMounted(() => keepPageForOffline());
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader :title="`Bonjour ${page.props.auth.user.first_name} 👋`" description="Vos quiz et vos résultats en un coup d'œil.">
                <template v-if="stats.groups === 0" #actions>
                    <Button as-child class="h-11"><Link :href="route('student.groups.index')">Rejoindre un groupe</Link></Button>
                </template>
            </PageHeader>

            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard label="Mes groupes" :value="stats.groups" :icon="UsersRound" :index="1" />
                <StatCard label="Quiz passés" :value="stats.attempts" :icon="BookOpenCheck" :index="2" />
                <StatCard
                    label="Moyenne des quiz"
                    :value="stats.average ?? '—'"
                    suffix="/ 20"
                    :decimals="2"
                    :icon="Target"
                    :index="3"
                    :hint="stats.average === null ? 'Aucun quiz passé pour le moment' : undefined"
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- To do -->
                <section class="animate-enter space-y-3" style="animation-delay: 0.15s">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">À faire</h2>
                        <Link :href="route('student.quizzes.index')" class="text-sm font-medium text-primary hover:underline">Tous mes quiz</Link>
                    </div>

                    <div v-if="todo.length === 0" class="flex items-center gap-3 rounded-2xl border border-dashed p-5 text-sm text-muted-foreground">
                        <PartyPopper class="size-5 text-primary" /> Aucun quiz en attente. Bravo !
                    </div>

                    <Link
                        v-for="(quiz, i) in todo"
                        :key="quiz.id"
                        :href="route('student.quizzes.index')"
                        class="lift press animate-stagger group flex items-center gap-4 rounded-2xl border bg-card p-4"
                        :style="{ '--i': i + 3 }"
                    >
                        <div
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-md shadow-primary/25"
                        >
                            <BookOpenCheck class="size-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ quiz.title }}</p>
                            <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                <span>{{ plural(quiz.questions_count, 'question') }}</span>
                                <span v-if="quiz.duration_minutes" class="inline-flex items-center gap-1"
                                    ><Timer class="size-3" /> {{ quiz.duration_minutes }} min</span
                                >
                                <span
                                    :class="['inline-flex items-center gap-1', soon(quiz.due_at) ? 'font-semibold text-warning' : '']"
                                    :title="dateTime(quiz.due_at)"
                                >
                                    <CalendarClock class="size-3" /> {{ fromNow(quiz.due_at) }}
                                </span>
                            </p>
                        </div>
                        <ChevronRight class="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </section>

                <!-- Latest grades -->
                <section class="animate-enter space-y-3" style="animation-delay: 0.25s">
                    <h2 class="text-lg font-semibold">Derniers résultats</h2>

                    <div v-if="recent.length === 0" class="rounded-2xl border border-dashed p-5 text-sm text-muted-foreground">
                        Vos notes apparaîtront ici après votre premier quiz.
                    </div>

                    <Link
                        v-for="(result, i) in recent"
                        :key="result.quiz_id"
                        :href="route('student.quizzes.result', { quiz: result.quiz_id })"
                        class="lift press animate-stagger group flex items-center gap-4 rounded-2xl border bg-card p-4"
                        :style="{ '--i': i + 4 }"
                    >
                        <div
                            :class="[
                                'flex size-12 shrink-0 flex-col items-center justify-center rounded-xl ring-1',
                                toneBadge[scoreTone(result.score)],
                            ]"
                        >
                            <span class="text-base font-bold tabular-nums leading-none">{{ formatScore(result.score) }}</span>
                            <span class="text-[10px] opacity-80">/20</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ result.title }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ result.subject }} · {{ fromNow(result.answered_at) }}</p>
                        </div>
                        <ArrowRight class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </section>
            </div>

            <!-- Personal understanding per notion (only corrected quizzes, rule 7) -->
            <section v-if="notions.length" class="animate-enter rounded-2xl border bg-card p-5" style="animation-delay: 0.35s">
                <h2 class="font-semibold">Ma compréhension par notion</h2>
                <p class="mb-5 text-sm text-muted-foreground">D'après vos quiz corrigés : commencez vos révisions par les notions signalées.</p>
                <RateBars :items="notions" sort />
            </section>
        </PageContainer>
    </AppLayout>
</template>
