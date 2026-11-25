<script setup lang="ts">
import RateBars from '@/components/charts/RateBars.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useReveal } from '@/composables/useReveal';
import AppLayout from '@/layouts/AppLayout.vue';
import { fromNow } from '@/lib/format';
import { formatScore, scoreTone, toneBadge } from '@/lib/score';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BookOpenCheck, ChartNoAxesColumn, GraduationCap, LifeBuoy, Plus, TriangleAlert, UsersRound } from 'lucide-vue-next';

defineProps<{
    stats: { groups: number; students: number; quizzes: number; difficulty: number };
    groups: {
        id: number;
        name: string;
        students: number;
        average: number | null;
        difficulty_count: number;
        difficulty: { id: number; name: string; reasons: string[] }[];
    }[];
    weakNotions: { name: string; rate: number; answered: number }[];
    recentQuizzes: { id: number; title: string; due_at: string; is_open: boolean; submitted: number; expected: number; average: number | null }[];
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tableau de bord', href: route('teacher.dashboard') }];
const revealed = useReveal();
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader :title="`Bonjour ${page.props.auth.user.first_name} 👋`" description="Voici l'essentiel de vos groupes.">
                <template #actions>
                    <Button as-child variant="outline" class="h-11"><Link :href="route('teacher.groups.index')">Mes groupes</Link></Button>
                    <Button as-child class="h-11 shadow-md shadow-primary/25">
                        <Link :href="route('teacher.quizzes.create')"><Plus /> Nouveau quiz</Link>
                    </Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatCard label="Groupes" :value="stats.groups" :icon="UsersRound" :index="1" />
                <StatCard label="Étudiants suivis" :value="stats.students" :icon="GraduationCap" :index="2" />
                <StatCard label="Quiz créés" :value="stats.quizzes" :icon="BookOpenCheck" :index="3" />
                <StatCard label="En difficulté" :value="stats.difficulty" :icon="LifeBuoy" :index="4" hint="à accompagner en priorité" />
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <!-- Latest quizzes -->
                <section class="animate-enter space-y-3 lg:col-span-3" style="animation-delay: 0.15s">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Derniers quiz</h2>
                        <Link :href="route('teacher.quizzes.index')" class="text-sm font-medium text-primary hover:underline">Tous les quiz</Link>
                    </div>
                    <p v-if="recentQuizzes.length === 0" class="rounded-2xl border border-dashed p-5 text-sm text-muted-foreground">
                        Publiez un quiz pour voir les résultats ici.
                    </p>
                    <Link
                        v-for="(quiz, i) in recentQuizzes"
                        :key="quiz.id"
                        :href="route('teacher.quizzes.results', { quiz: quiz.id })"
                        class="lift press animate-stagger group flex items-center gap-4 rounded-2xl border bg-card p-4"
                        :style="{ '--i': i + 4 }"
                    >
                        <div
                            v-if="quiz.average !== null"
                            :class="[
                                'flex size-12 shrink-0 flex-col items-center justify-center rounded-xl ring-1',
                                toneBadge[scoreTone(quiz.average)],
                            ]"
                        >
                            <span class="text-base font-bold tabular-nums leading-none">{{ formatScore(quiz.average) }}</span>
                            <span class="text-[10px] opacity-80">moy.</span>
                        </div>
                        <div v-else class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                            <BookOpenCheck class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-semibold">{{ quiz.title }}</p>
                                <span v-if="quiz.is_open" class="relative flex size-2 shrink-0" title="Ouvert">
                                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60" />
                                    <span class="relative inline-flex size-2 rounded-full bg-success" />
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="h-1.5 flex-1 rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-primary transition-[width] duration-1000 ease-out"
                                        :style="{ width: revealed && quiz.expected ? `${(quiz.submitted / quiz.expected) * 100}%` : '0%' }"
                                    />
                                </div>
                                <span class="shrink-0 text-xs tabular-nums text-muted-foreground"
                                    >{{ quiz.submitted }}/{{ quiz.expected }} copies</span
                                >
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ quiz.is_open ? 'Se termine' : 'Terminé' }} {{ fromNow(quiz.due_at) }}</p>
                        </div>
                        <ArrowRight class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </section>

                <!-- Notions to review -->
                <section class="animate-enter rounded-2xl border bg-card p-5 lg:col-span-2" style="animation-delay: 0.25s">
                    <h2 class="font-semibold">Notions à retravailler</h2>
                    <p class="mb-5 text-sm text-muted-foreground">Les moins bien comprises, tous groupes confondus.</p>
                    <RateBars v-if="weakNotions.length" :items="weakNotions" compact />
                    <p v-else class="text-sm text-muted-foreground">Pas encore assez de réponses.</p>
                </section>
            </div>

            <!-- Groups -->
            <section class="animate-enter space-y-3" style="animation-delay: 0.35s">
                <h2 class="text-lg font-semibold">Suivi par groupe</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="(g, i) in groups"
                        :key="g.id"
                        :href="route('teacher.groups.insights', { group: g.id })"
                        class="lift press animate-stagger group flex flex-col gap-3 rounded-2xl border bg-card p-5"
                        :style="{ '--i': i + 6 }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ g.name }}</p>
                                <p class="text-sm text-muted-foreground">{{ g.students }} étudiant{{ g.students > 1 ? 's' : '' }}</p>
                            </div>
                            <span
                                v-if="g.average !== null"
                                :class="['shrink-0 rounded-lg px-2 py-1 text-sm font-bold tabular-nums ring-1', toneBadge[scoreTone(g.average)]]"
                                >{{ formatScore(g.average) }}/20</span
                            >
                        </div>
                        <div v-if="g.difficulty_count" class="text-sm">
                            <p class="flex items-center gap-1.5 font-medium text-warning">
                                <TriangleAlert class="size-4 shrink-0" /> {{ g.difficulty_count }} en difficulté
                            </p>
                            <p class="mt-0.5 line-clamp-2 text-muted-foreground">
                                {{ g.difficulty.map((d) => d.name).join(', ') }}{{ g.difficulty_count > g.difficulty.length ? '…' : '' }}
                            </p>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">Aucun étudiant en difficulté</p>
                        <span class="mt-auto inline-flex items-center gap-1 text-sm font-medium text-primary">
                            <ChartNoAxesColumn class="size-4" /> Voir le suivi
                            <ArrowRight class="size-4 transition-transform group-hover:translate-x-0.5" />
                        </span>
                    </Link>
                </div>
            </section>
        </PageContainer>
    </AppLayout>
</template>
