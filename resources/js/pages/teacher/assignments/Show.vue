<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { fileTone, formatBytes } from '@/lib/content';
import { dateTime, fromNow } from '@/lib/format';
import { stateBadge, type HomeworkState } from '@/lib/homework';
import { formatScore, scoreTone, toneBadge } from '@/lib/score';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, ClipboardCheck, Download, FileText, Inbox, LoaderCircle, Pencil, Star, Target, Trash2, UsersRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Row {
    student: { id: number; name: string };
    groups: string[];
    removed: boolean;
    state: HomeworkState;
    state_label: string;
    submission: {
        id: number;
        body: string | null;
        submitted_at: string;
        is_late: boolean;
        grade: number | null;
        comment: string | null;
        file: { name: string; size: number; extension: string | null; download: string } | null;
    } | null;
}

const props = defineProps<{
    assignment: {
        id: number;
        title: string;
        instructions: string;
        due_at: string;
        is_open: boolean;
        allow_late: boolean;
        material: { id: number; title: string } | null;
        groups: string[];
    };
    stats: { expected: number; submitted: number; late: number; graded: number; average: number | null; missing: number };
    rows: Row[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devoirs', href: route('teacher.assignments.index') },
    { title: props.assignment.title, href: route('teacher.assignments.show', { assignment: props.assignment.id }) },
];

const filter = ref<'all' | 'todo' | 'graded' | 'none'>('all');
const filters = computed(() => [
    { value: 'all', label: 'Tous', count: props.rows.length },
    { value: 'todo', label: 'À noter', count: props.rows.filter((r) => r.submission && r.submission.grade === null).length },
    { value: 'graded', label: 'Notés', count: props.stats.graded },
    { value: 'none', label: 'Sans rendu', count: props.rows.filter((r) => !r.submission).length },
]);
const visible = computed(() =>
    props.rows.filter((r) =>
        filter.value === 'todo'
            ? r.submission && r.submission.grade === null
            : filter.value === 'graded'
              ? r.submission?.grade !== null && r.submission?.grade !== undefined
              : filter.value === 'none'
                ? !r.submission
                : true,
    ),
);

// Grading dialog (rule 16).
const grading = ref<Row | null>(null);
const form = useForm({ grade: '', comment: '' });
function openGrade(row: Row) {
    grading.value = row;
    form.clearErrors();
    form.grade = row.submission?.grade !== null && row.submission?.grade !== undefined ? String(row.submission.grade).replace('.', ',') : '';
    form.comment = row.submission?.comment ?? '';
}
function saveGrade() {
    if (!grading.value?.submission) return;
    form.put(route('teacher.assignments.grade', { assignment: props.assignment.id, submission: grading.value.submission.id }), {
        preserveScroll: true,
        onSuccess: () => (grading.value = null),
    });
}

const confirmDelete = ref(false);
const remove = () =>
    router.delete(route('teacher.assignments.destroy', { assignment: props.assignment.id }), { onFinish: () => (confirmDelete.value = false) });
</script>

<template>
    <Head :title="assignment.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader
                :title="assignment.title"
                :description="assignment.groups.join(' · ')"
                :back-href="route('teacher.assignments.index')"
                back-label="Devoirs"
            >
                <template #actions>
                    <Button as-child variant="outline" class="h-11">
                        <Link :href="route('teacher.assignments.edit', { assignment: assignment.id })"><Pencil /> Modifier</Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-11 text-destructive"
                        aria-label="Supprimer le devoir"
                        @click="confirmDelete = true"
                    >
                        <Trash2 />
                    </Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatCard
                    label="Rendus"
                    :value="stats.submitted"
                    :suffix="`/ ${stats.expected}`"
                    :icon="Inbox"
                    :index="1"
                    :hint="stats.late ? `dont ${stats.late} en retard` : undefined"
                />
                <StatCard label="Notés" :value="stats.graded" :suffix="`/ ${stats.submitted}`" :icon="ClipboardCheck" :index="2" />
                <StatCard
                    label="Moyenne du devoir"
                    :value="stats.average ?? '—'"
                    suffix="/ 20"
                    :decimals="2"
                    :icon="Target"
                    :index="3"
                    hint="séparée des quiz"
                />
                <StatCard
                    label="Non rendus"
                    :value="stats.missing"
                    :icon="UsersRound"
                    :index="4"
                    :hint="assignment.is_open ? 'date limite non atteinte' : undefined"
                />
            </div>

            <section class="animate-enter rounded-2xl border bg-card p-5" style="animation-delay: 0.1s">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span
                        :class="[
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            assignment.is_open ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground',
                        ]"
                        >{{ assignment.is_open ? 'En cours' : 'Terminé' }}</span
                    >
                    <span class="inline-flex items-center gap-1 text-muted-foreground">
                        <CalendarClock class="size-4" /> {{ dateTime(assignment.due_at) }} ({{ fromNow(assignment.due_at) }})
                    </span>
                    <span v-if="assignment.allow_late" class="rounded-full bg-warning/10 px-2.5 py-0.5 text-xs font-medium text-warning"
                        >Retards acceptés</span
                    >
                </div>
                <p class="mt-3 whitespace-pre-line leading-relaxed">{{ assignment.instructions }}</p>
                <p
                    v-if="assignment.material"
                    class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-secondary px-3 py-1.5 text-sm text-secondary-foreground"
                >
                    <FileText class="size-4" /> {{ assignment.material.title }}
                </p>
            </section>

            <section class="space-y-3">
                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <button
                        v-for="f in filters"
                        :key="f.value"
                        type="button"
                        :aria-pressed="filter === f.value"
                        :class="[
                            'press h-10 shrink-0 rounded-full border px-4 text-sm font-medium transition-colors',
                            filter === f.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-muted',
                        ]"
                        @click="filter = f.value as typeof filter"
                    >
                        {{ f.label }} <span class="opacity-70">{{ f.count }}</span>
                    </button>
                </div>

                <ul class="grid gap-3 lg:grid-cols-2">
                    <li
                        v-for="(row, i) in visible"
                        :key="row.student.id"
                        class="animate-stagger flex flex-col gap-3 rounded-2xl border bg-card p-4"
                        :style="{ '--i': Math.min(i, 8) }"
                    >
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">
                                    {{ row.student.name }}
                                    <span v-if="row.removed" class="ml-1 rounded bg-muted px-1.5 py-0.5 text-[11px] font-normal text-muted-foreground"
                                        >retiré du groupe</span
                                    >
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ row.groups.join(', ') }}
                                    <template v-if="row.submission"> · rendu {{ dateTime(row.submission.submitted_at) }}</template>
                                </p>
                            </div>
                            <span :class="['shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold', stateBadge[row.state]]">{{
                                row.state_label
                            }}</span>
                        </div>

                        <template v-if="row.submission">
                            <p v-if="row.submission.body" class="line-clamp-4 whitespace-pre-line rounded-xl bg-muted/50 p-3 text-sm">
                                {{ row.submission.body }}
                            </p>
                            <a
                                v-if="row.submission.file"
                                :href="row.submission.file.download"
                                class="press flex items-center gap-3 rounded-xl border p-2.5 text-sm hover:bg-muted/40"
                            >
                                <span
                                    :class="[
                                        'flex size-9 items-center justify-center rounded-lg text-[11px] font-bold',
                                        fileTone(row.submission.file.extension),
                                    ]"
                                    >{{ row.submission.file.extension }}</span
                                >
                                <span class="min-w-0 flex-1 truncate">{{ row.submission.file.name }}</span>
                                <span class="text-xs text-muted-foreground">{{ formatBytes(row.submission.file.size) }}</span>
                                <Download class="size-4 text-primary" />
                            </a>
                            <div class="mt-auto flex items-center gap-3 border-t pt-3">
                                <template v-if="row.submission.grade !== null">
                                    <span :class="['rounded-lg px-2 py-1 font-bold tabular-nums ring-1', toneBadge[scoreTone(row.submission.grade)]]"
                                        >{{ formatScore(row.submission.grade) }}/20</span
                                    >
                                    <p class="min-w-0 flex-1 truncate text-sm text-muted-foreground">{{ row.submission.comment }}</p>
                                    <Button variant="ghost" class="h-10" @click="openGrade(row)"><Pencil /> Modifier</Button>
                                </template>
                                <Button v-else class="h-10 w-full" @click="openGrade(row)"><Star /> Noter</Button>
                            </div>
                        </template>
                    </li>
                </ul>
                <p v-if="visible.length === 0" class="py-8 text-center text-muted-foreground">Personne dans cette catégorie.</p>
            </section>
        </PageContainer>

        <Dialog :open="grading !== null" @update:open="(v) => !v && (grading = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Noter {{ grading?.student.name }}</DialogTitle>
                    <DialogDescription>Note sur 20, décimales acceptées (ex. 12,5). Le commentaire est facultatif.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="saveGrade">
                    <div class="grid gap-2">
                        <Label for="grade">Note / 20</Label>
                        <Input
                            id="grade"
                            v-model="form.grade"
                            inputmode="decimal"
                            class="h-14 text-center text-2xl font-bold"
                            placeholder="—"
                            autofocus
                        />
                        <div class="flex flex-wrap justify-center gap-1.5">
                            <button
                                v-for="g in [8, 10, 12, 14, 16, 18, 20]"
                                :key="g"
                                type="button"
                                class="press h-9 min-w-10 rounded-lg border text-sm font-medium hover:bg-muted"
                                @click="form.grade = String(g)"
                            >
                                {{ g }}
                            </button>
                        </div>
                        <InputError :message="form.errors.grade" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="comment">Commentaire</Label>
                        <textarea
                            id="comment"
                            v-model="form.comment"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
                            placeholder="Points forts, points à améliorer…"
                        />
                        <InputError :message="form.errors.comment" />
                    </div>
                    <DialogFooter>
                        <Button type="submit" class="h-11 w-full" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="animate-spin" /> Enregistrer la note
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="confirmDelete"
            :title="`Supprimer « ${assignment.title} » ?`"
            description="Un devoir qui a déjà reçu des rendus ne peut pas être supprimé, pour conserver le travail des étudiants."
            confirm-label="Supprimer"
            destructive
            @confirm="remove"
        />
    </AppLayout>
</template>
