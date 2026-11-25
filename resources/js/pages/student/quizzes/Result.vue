<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useCountUp } from '@/composables/useCountUp';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, fromNow } from '@/lib/format';
import { formatScore, scoreMessage, scoreTone, toneText } from '@/lib/score';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Check, CloudOff, Lock, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Correction {
    id: number;
    text: string;
    notion: string;
    chosen_id: number | null;
    is_correct: boolean;
    choices: { id: number; text: string; is_correct: boolean }[];
}

const props = defineProps<{
    quiz: { id: number; title: string; subject: string; teacher: string; due_at: string; corrections_at: string };
    attempt: { score: number; correct: number; total: number; answered_at: string; synced_at: string; synced_late: boolean };
    corrections: { notions: { name: string; correct: number; total: number }[]; questions: Correction[] } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mes quiz', href: route('student.quizzes.index') },
    { title: props.quiz.title, href: route('student.quizzes.result', { quiz: props.quiz.id }) },
];

const tone = computed(() => scoreTone(props.attempt.score));
const message = computed(() => scoreMessage(props.attempt.score));
const score = computed(() => props.attempt.score);
const shown = useCountUp(score, 1100);

// Score ring: animates from empty to the grade once mounted.
const radius = 70;
const circumference = 2 * Math.PI * radius;
const filled = ref(false);
onMounted(() => requestAnimationFrame(() => (filled.value = true)));
const dashOffset = computed(() => (filled.value ? circumference * (1 - props.attempt.score / 20) : circumference));

const ringColor = { success: 'stroke-success', primary: 'stroke-primary', warning: 'stroke-warning' };

// A few confetti for a good grade (CSS only).
const confettiColors = ['#1679b8', '#2e9e5b', '#f2a93b', '#7cc4ee', '#e5484d'];
const confetti = Array.from({ length: 18 }, (_, i) => ({
    x: `${(i % 2 ? 1 : -1) * (30 + ((i * 37) % 140))}px`,
    r: `${(i % 3) + 1}turn`,
    d: `${(i % 6) * 0.06}s`,
    color: confettiColors[i % confettiColors.length],
}));

const letters = ['A', 'B', 'C', 'D'];
const rate = (n: { correct: number; total: number }) => Math.round((n.correct / n.total) * 100);
</script>

<template>
    <Head :title="`Résultat – ${quiz.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader
                :title="quiz.title"
                :description="`${quiz.subject} · ${quiz.teacher}`"
                :back-href="route('student.quizzes.index')"
                back-label="Mes quiz"
            />

            <!-- Grade -->
            <section class="relative overflow-hidden rounded-2xl border bg-card p-6 text-center sm:p-8">
                <div v-if="attempt.score >= 14" class="pointer-events-none absolute inset-x-0 top-0 h-72" aria-hidden="true">
                    <span v-for="(c, i) in confetti" :key="i" class="confetti" :style="{ '--x': c.x, '--r': c.r, '--d': c.d, background: c.color }" />
                </div>

                <div class="relative mx-auto size-44">
                    <svg viewBox="0 0 160 160" class="size-full -rotate-90">
                        <circle cx="80" cy="80" :r="radius" class="fill-none stroke-muted" stroke-width="12" />
                        <circle
                            cx="80"
                            cy="80"
                            :r="radius"
                            :class="['fill-none transition-[stroke-dashoffset] ease-out', ringColor[tone]]"
                            stroke-width="12"
                            stroke-linecap="round"
                            style="transition-duration: 1100ms"
                            :stroke-dasharray="circumference"
                            :stroke-dashoffset="dashOffset"
                        />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span :class="['text-5xl font-bold tabular-nums tracking-tight', toneText[tone]]">{{
                            formatScore(Math.round(shown * 100) / 100)
                        }}</span>
                        <span class="text-sm font-medium text-muted-foreground">sur 20</span>
                    </div>
                </div>

                <h2 class="animate-enter mt-5 text-xl font-bold" style="animation-delay: 0.6s">{{ message.emoji }} {{ message.title }}</h2>
                <p class="animate-enter mt-1 text-muted-foreground" style="animation-delay: 0.7s">
                    {{ attempt.correct }} bonne{{ attempt.correct > 1 ? 's' : '' }} réponse{{ attempt.correct > 1 ? 's' : '' }} sur
                    {{ attempt.total }}
                </p>

                <p
                    v-if="attempt.synced_late"
                    class="animate-enter mx-auto mt-4 inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1.5 text-xs text-muted-foreground"
                    style="animation-delay: 0.8s"
                >
                    <CloudOff class="size-3.5" /> Répondu hors ligne le {{ dateTime(attempt.answered_at) }}, reçu le {{ dateTime(attempt.synced_at) }}
                </p>
            </section>

            <!-- Corrections locked until corrections_at (rule 7) -->
            <section
                v-if="!corrections"
                class="animate-enter flex items-start gap-4 rounded-2xl border border-dashed p-5"
                style="animation-delay: 0.3s"
            >
                <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-secondary text-primary">
                    <Lock class="size-5" />
                </div>
                <div>
                    <h2 class="font-semibold">Corrections disponibles {{ fromNow(quiz.corrections_at) }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Le {{ dateTime(quiz.corrections_at) }}, vous verrez les bonnes réponses et votre réussite par notion. Elles restent cachées
                        jusque-là pour que chacun réponde sans les connaître.
                    </p>
                </div>
            </section>

            <template v-else>
                <!-- Per notion -->
                <section class="rounded-2xl border bg-card p-5">
                    <h2 class="font-semibold">Réussite par notion</h2>
                    <p class="text-sm text-muted-foreground">Ce qui est compris, et ce qu'il faut revoir.</p>
                    <ul class="mt-4 space-y-4">
                        <li v-for="(notion, i) in corrections.notions" :key="notion.name" class="animate-stagger" :style="{ '--i': i }">
                            <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                <span class="font-medium">{{ notion.name }}</span>
                                <span class="tabular-nums text-muted-foreground">{{ notion.correct }}/{{ notion.total }} · {{ rate(notion) }} %</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    :class="[
                                        'h-full rounded-full transition-[width] duration-1000 ease-out',
                                        rate(notion) >= 50 ? 'bg-success' : 'bg-warning',
                                    ]"
                                    :style="{ width: filled ? `${rate(notion)}%` : '0%' }"
                                />
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Question by question -->
                <section>
                    <h2 class="mb-3 font-semibold">Correction</h2>
                    <ol class="space-y-4">
                        <li
                            v-for="(q, i) in corrections.questions"
                            :key="q.id"
                            class="animate-stagger rounded-2xl border bg-card p-4 sm:p-5"
                            :style="{ '--i': Math.min(i, 8) }"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    :class="[
                                        'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                                        q.is_correct ? 'bg-success text-success-foreground' : 'bg-destructive/10 text-destructive',
                                    ]"
                                >
                                    {{ i + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">{{
                                            q.notion
                                        }}</span>
                                        <span :class="['text-xs font-semibold', q.is_correct ? 'text-success' : 'text-destructive']">
                                            {{ q.is_correct ? 'Juste' : q.chosen_id === null ? 'Sans réponse' : 'Faux' }}
                                        </span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line font-medium leading-snug">{{ q.text }}</p>
                                </div>
                            </div>

                            <ul class="mt-3 grid gap-2">
                                <li
                                    v-for="(choice, c) in q.choices"
                                    :key="choice.id"
                                    :class="[
                                        'flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm',
                                        choice.is_correct
                                            ? 'border-success/40 bg-success/10'
                                            : choice.id === q.chosen_id
                                              ? 'border-destructive/40 bg-destructive/5'
                                              : 'border-transparent bg-muted/50 text-muted-foreground',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold',
                                            choice.is_correct
                                                ? 'bg-success text-success-foreground'
                                                : choice.id === q.chosen_id
                                                  ? 'bg-destructive text-destructive-foreground'
                                                  : 'bg-background',
                                        ]"
                                    >
                                        <Check v-if="choice.is_correct" class="size-4" />
                                        <X v-else-if="choice.id === q.chosen_id" class="size-4" />
                                        <template v-else>{{ letters[c] }}</template>
                                    </span>
                                    <span class="flex-1">{{ choice.text }}</span>
                                    <span v-if="choice.id === q.chosen_id" class="shrink-0 text-xs font-medium">Votre réponse</span>
                                </li>
                            </ul>
                        </li>
                    </ol>
                </section>
            </template>
        </PageContainer>
    </AppLayout>
</template>
