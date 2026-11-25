<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, fromNow, plural } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import type { QuizStatus } from '@/types/quiz';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpenCheck, CalendarClock, ChartNoAxesColumn, CopyPlus, EllipsisVertical, Pencil, Plus, Trash2, UsersRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface QuizRow {
    id: number;
    title: string;
    subject: string;
    level: string;
    questions_count: number;
    attempts_count: number;
    groups: string[];
    status: QuizStatus;
    due_at: string;
}

const props = defineProps<{ quizzes: QuizRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Quiz', href: route('teacher.quizzes.index') }];

const tabs: { value: QuizStatus | 'all'; label: string }[] = [
    { value: 'all', label: 'Tous' },
    { value: 'open', label: 'Ouverts' },
    { value: 'draft', label: 'Brouillons' },
    { value: 'closed', label: 'Terminés' },
];
const tab = ref<QuizStatus | 'all'>('all');
const counts = computed(() => ({
    all: props.quizzes.length,
    open: props.quizzes.filter((q) => q.status === 'open').length,
    draft: props.quizzes.filter((q) => q.status === 'draft').length,
    closed: props.quizzes.filter((q) => q.status === 'closed').length,
}));
const visible = computed(() => (tab.value === 'all' ? props.quizzes : props.quizzes.filter((q) => q.status === tab.value)));
const tabIndex = computed(() => tabs.findIndex((t) => t.value === tab.value));

const badge: Record<QuizStatus, { label: string; class: string }> = {
    draft: { label: 'Brouillon', class: 'bg-muted text-muted-foreground' },
    open: { label: 'Ouvert', class: 'bg-success/10 text-success' },
    closed: { label: 'Terminé', class: 'bg-secondary text-secondary-foreground' },
};

const toDelete = ref<QuizRow | null>(null);
const deleting = ref(false);
function destroy() {
    if (!toDelete.value) return;
    deleting.value = true;
    router.delete(route('teacher.quizzes.destroy', { quiz: toDelete.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            toDelete.value = null;
        },
    });
}
const duplicate = (quiz: QuizRow) => router.post(route('teacher.quizzes.duplicate', { quiz: quiz.id }));
</script>

<template>
    <Head title="Mes quiz" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader title="Mes quiz" description="Créez, publiez et réutilisez vos quiz.">
                <template #actions>
                    <Button as-child class="h-11 shadow-md shadow-primary/25">
                        <Link :href="route('teacher.quizzes.create')"><Plus /> Nouveau quiz</Link>
                    </Button>
                </template>
            </PageHeader>

            <EmptyState
                v-if="quizzes.length === 0"
                :icon="BookOpenCheck"
                title="Aucun quiz pour le moment"
                description="Un quiz de 5 questions se crée en quelques minutes. Chaque question est liée à une notion pour suivre la compréhension."
            >
                <Button as-child class="h-11"
                    ><Link :href="route('teacher.quizzes.create')"><Plus /> Créer mon premier quiz</Link></Button
                >
            </EmptyState>

            <template v-else>
                <!-- Tabs with a sliding indicator -->
                <div class="relative grid grid-cols-4 rounded-xl bg-muted p-1 text-sm font-medium sm:w-fit sm:min-w-[28rem]" role="tablist">
                    <span
                        class="absolute inset-y-1 left-1 rounded-lg bg-background shadow-sm transition-transform duration-300 ease-out"
                        :style="{ width: 'calc((100% - 0.5rem) / 4)', transform: `translateX(${tabIndex * 100}%)` }"
                    />
                    <button
                        v-for="t in tabs"
                        :key="t.value"
                        type="button"
                        role="tab"
                        :aria-selected="tab === t.value"
                        :class="[
                            'relative z-10 rounded-lg px-2 py-2 transition-colors',
                            tab === t.value ? 'text-foreground' : 'text-muted-foreground',
                        ]"
                        @click="tab = t.value"
                    >
                        {{ t.label }} <span class="text-xs opacity-60">{{ counts[t.value] }}</span>
                    </button>
                </div>

                <TransitionGroup tag="ul" name="fade-list" class="grid gap-4 md:grid-cols-2">
                    <li v-for="(quiz, i) in visible" :key="quiz.id" class="animate-stagger" :style="{ '--i': i }">
                        <article class="lift flex h-full flex-col gap-4 rounded-2xl border bg-card p-5">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <span
                                        :class="[
                                            'mb-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                            badge[quiz.status].class,
                                        ]"
                                    >
                                        <span v-if="quiz.status === 'open'" class="relative flex size-2">
                                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60" />
                                            <span class="relative inline-flex size-2 rounded-full bg-success" />
                                        </span>
                                        {{ badge[quiz.status].label }}
                                    </span>
                                    <h2 class="truncate font-semibold">
                                        <Link :href="route('teacher.quizzes.edit', { quiz: quiz.id })" class="hover:underline">{{ quiz.title }}</Link>
                                    </h2>
                                    <p class="truncate text-sm text-muted-foreground">{{ quiz.subject }} · {{ quiz.level }}</p>
                                </div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" class="size-10 shrink-0" :aria-label="`Actions pour ${quiz.title}`"
                                            ><EllipsisVertical
                                        /></Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('teacher.quizzes.edit', { quiz: quiz.id })" class="w-full"
                                                ><Pencil class="mr-2 size-4" /> Modifier</Link
                                            >
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="quiz.status !== 'draft'" as-child>
                                            <Link :href="route('teacher.quizzes.results', { quiz: quiz.id })" class="w-full"
                                                ><ChartNoAxesColumn class="mr-2 size-4" /> Résultats</Link
                                            >
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @select="duplicate(quiz)"><CopyPlus class="mr-2 size-4" /> Dupliquer</DropdownMenuItem>
                                        <DropdownMenuItem class="text-destructive focus:text-destructive" @select="toDelete = quiz">
                                            <Trash2 class="mr-2 size-4" /> Supprimer
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="g in quiz.groups"
                                    :key="g"
                                    class="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-0.5 text-xs text-secondary-foreground"
                                >
                                    <UsersRound class="size-3" /> {{ g }}
                                </span>
                                <span v-if="quiz.groups.length === 0" class="text-xs text-muted-foreground">Aucun groupe</span>
                            </div>

                            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-sm text-muted-foreground">
                                <Link
                                    v-if="quiz.status !== 'draft'"
                                    :href="route('teacher.quizzes.results', { quiz: quiz.id })"
                                    class="press inline-flex items-center gap-1.5 rounded-lg bg-primary/10 px-2.5 py-1.5 font-medium text-primary hover:bg-primary/15"
                                >
                                    <ChartNoAxesColumn class="size-4" /> Résultats · {{ quiz.attempts_count }}
                                </Link>
                                <span v-else>{{ plural(quiz.questions_count, 'question') }}</span>
                                <span class="inline-flex items-center gap-1" :title="dateTime(quiz.due_at)">
                                    <CalendarClock class="size-4" />
                                    {{ quiz.status === 'closed' ? 'Terminé' : 'Se termine' }} {{ fromNow(quiz.due_at) }}
                                </span>
                            </div>
                        </article>
                    </li>
                </TransitionGroup>

                <p v-if="visible.length === 0" class="animate-enter py-8 text-center text-muted-foreground">Aucun quiz dans cette catégorie.</p>
            </template>
        </PageContainer>

        <ConfirmDialog
            :open="toDelete !== null"
            :title="`Supprimer « ${toDelete?.title ?? ''} » ?`"
            description="Le quiz et ses questions seront supprimés. Un quiz déjà passé par des étudiants ne peut pas être supprimé."
            confirm-label="Supprimer"
            destructive
            :processing="deleting"
            @update:open="(v) => !v && (toDelete = null)"
            @confirm="destroy"
        />
    </AppLayout>
</template>

<style scoped>
.fade-list-move {
    transition: transform 0.35s ease;
}
.fade-list-leave-active {
    display: none;
}
</style>
