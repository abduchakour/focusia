<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useReveal } from '@/composables/useReveal';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, fromNow } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, ChevronRight, ClipboardList, FileText, Plus, UsersRound } from 'lucide-vue-next';

defineProps<{
    assignments: {
        id: number;
        title: string;
        due_at: string;
        is_open: boolean;
        allow_late: boolean;
        material: string | null;
        groups: string[];
        submitted: number;
        graded: number;
        expected: number;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Devoirs', href: route('teacher.assignments.index') }];
const revealed = useReveal();
</script>

<template>
    <Head title="Devoirs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader title="Devoirs" description="Consignes, rendus des étudiants et notes sur 20.">
                <template #actions>
                    <Button as-child class="h-11 shadow-md shadow-primary/25">
                        <Link :href="route('teacher.assignments.create')"><Plus /> Nouveau devoir</Link>
                    </Button>
                </template>
            </PageHeader>

            <EmptyState
                v-if="assignments.length === 0"
                :icon="ClipboardList"
                title="Aucun devoir pour le moment"
                description="Donnez une consigne et une date limite ; les étudiants rendent un fichier, un texte, ou les deux."
            >
                <Button as-child class="h-11"
                    ><Link :href="route('teacher.assignments.create')"><Plus /> Créer un devoir</Link></Button
                >
            </EmptyState>

            <ul v-else class="grid gap-3">
                <li v-for="(a, i) in assignments" :key="a.id" class="animate-stagger" :style="{ '--i': Math.min(i, 8) }">
                    <Link
                        :href="route('teacher.assignments.show', { assignment: a.id })"
                        class="lift press group flex items-center gap-4 rounded-2xl border bg-card p-4 sm:p-5"
                    >
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    :class="[
                                        'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        a.is_open ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground',
                                    ]"
                                    >{{ a.is_open ? 'En cours' : 'Terminé' }}</span
                                >
                                <span v-if="a.allow_late" class="rounded-full bg-warning/10 px-2.5 py-0.5 text-xs font-medium text-warning"
                                    >Retards acceptés</span
                                >
                            </div>
                            <p class="font-semibold leading-snug">{{ a.title }}</p>
                            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                <span class="inline-flex items-center gap-1" :title="dateTime(a.due_at)"
                                    ><CalendarClock class="size-3.5" /> {{ fromNow(a.due_at) }}</span
                                >
                                <span class="inline-flex items-center gap-1"><UsersRound class="size-3.5" /> {{ a.groups.join(', ') }}</span>
                                <span v-if="a.material" class="inline-flex items-center gap-1"><FileText class="size-3.5" /> {{ a.material }}</span>
                            </p>
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 max-w-64 flex-1 rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-primary transition-[width] duration-1000 ease-out"
                                        :style="{ width: revealed && a.expected ? `${Math.min(100, (a.submitted / a.expected) * 100)}%` : '0%' }"
                                    />
                                </div>
                                <span class="text-xs tabular-nums text-muted-foreground">
                                    {{ a.submitted }}/{{ a.expected }} rendus · {{ a.graded }} noté{{ a.graded > 1 ? 's' : '' }}
                                </span>
                            </div>
                        </div>
                        <span
                            v-if="a.submitted - a.graded > 0"
                            class="hidden shrink-0 rounded-full bg-primary px-2.5 py-1 text-xs font-semibold text-primary-foreground sm:inline"
                            >{{ a.submitted - a.graded }} à noter</span
                        >
                        <ChevronRight class="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </li>
            </ul>
        </PageContainer>
    </AppLayout>
</template>
