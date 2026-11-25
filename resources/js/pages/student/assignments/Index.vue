<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, fromNow } from '@/lib/format';
import { stateBadge, type HomeworkState } from '@/lib/homework';
import { formatScore } from '@/lib/score';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, ChevronRight, ClipboardList } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    assignments: {
        id: number;
        title: string;
        teacher: string;
        due_at: string;
        allow_late: boolean;
        state: HomeworkState;
        state_label: string;
        grade: number | null;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Devoirs', href: route('student.assignments.index') }];

const todo = computed(() => props.assignments.filter((a) => a.state === 'A_RENDRE'));
const others = computed(() => props.assignments.filter((a) => a.state !== 'A_RENDRE').reverse());
const soon = (iso: string) => new Date(iso).getTime() - Date.now() < 48 * 3600 * 1000;
</script>

<template>
    <Head title="Devoirs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader
                title="Devoirs"
                description="Rendez un fichier, un texte, ou les deux. Vous pouvez remplacer votre rendu jusqu'à la date limite."
            />

            <EmptyState
                v-if="assignments.length === 0"
                :icon="ClipboardList"
                title="Aucun devoir"
                description="Les devoirs de vos groupes apparaîtront ici."
            />

            <template
                v-for="section in [
                    { title: 'À rendre', list: todo },
                    { title: 'Rendus et terminés', list: others },
                ]"
                :key="section.title"
            >
                <section v-if="section.list.length" class="space-y-3">
                    <h2 class="text-lg font-semibold">{{ section.title }}</h2>
                    <ul class="grid gap-3 md:grid-cols-2">
                        <li v-for="(a, i) in section.list" :key="a.id" class="animate-stagger" :style="{ '--i': Math.min(i, 8) }">
                            <Link
                                :href="route('student.assignments.show', { assignment: a.id })"
                                class="lift press group flex items-center gap-4 rounded-2xl border bg-card p-4"
                            >
                                <div
                                    v-if="a.grade !== null"
                                    class="flex size-12 shrink-0 flex-col items-center justify-center rounded-xl bg-success text-success-foreground"
                                >
                                    <span class="font-bold tabular-nums leading-none">{{ formatScore(a.grade) }}</span>
                                    <span class="text-[10px] opacity-80">/20</span>
                                </div>
                                <div v-else class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary">
                                    <ClipboardList class="size-6" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold">{{ a.title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ a.teacher }}</p>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                                        <span :class="['rounded-full px-2 py-0.5 font-semibold', stateBadge[a.state]]">{{ a.state_label }}</span>
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1',
                                                a.state === 'A_RENDRE' && soon(a.due_at) ? 'font-semibold text-warning' : 'text-muted-foreground',
                                            ]"
                                            :title="dateTime(a.due_at)"
                                        >
                                            <CalendarClock class="size-3.5" /> {{ fromNow(a.due_at) }}
                                        </span>
                                    </div>
                                </div>
                                <ChevronRight class="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                            </Link>
                        </li>
                    </ul>
                </section>
            </template>
        </PageContainer>
    </AppLayout>
</template>
