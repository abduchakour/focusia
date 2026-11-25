<script setup lang="ts">
import FileDrop from '@/components/content/FileDrop.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { fileTone, formatBytes } from '@/lib/content';
import { dateTime, fromNow } from '@/lib/format';
import { stateBadge, type HomeworkState } from '@/lib/homework';
import { formatScore, scoreTone, toneText } from '@/lib/score';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    CircleCheck,
    Download,
    ExternalLink,
    FileText,
    LoaderCircle,
    Lock,
    MessageSquareQuote,
    Send,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed } from 'vue';

type FileInfo = { name: string; size: number; extension: string | null; download: string };

const props = defineProps<{
    assignment: {
        id: number;
        title: string;
        instructions: string;
        teacher: string;
        due_at: string;
        allow_late: boolean;
        material: { title: string; url: string | null; file: FileInfo | null } | null;
    };
    state: HomeworkState;
    stateLabel: string;
    canSubmit: boolean;
    willBeLate: boolean;
    submission: {
        body: string | null;
        submitted_at: string;
        is_late: boolean;
        grade: number | null;
        comment: string | null;
        graded_at: string | null;
        file: FileInfo | null;
    } | null;
    limits: { max_mb: number; extensions: string[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devoirs', href: route('student.assignments.index') },
    { title: props.assignment.title, href: route('student.assignments.show', { assignment: props.assignment.id }) },
];

const form = useForm({ body: props.submission?.body ?? '', file: null as File | null, remove_file: false });
const hasContent = computed(() => form.file !== null || (props.submission?.file && !form.remove_file) || form.body.trim() !== '');

function submit() {
    form.post(route('student.assignments.submit', { assignment: props.assignment.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.file = null;
            form.remove_file = false;
        },
    });
}
</script>

<template>
    <Head :title="assignment.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer class="max-w-3xl">
            <PageHeader
                :title="assignment.title"
                :description="assignment.teacher"
                :back-href="route('student.assignments.index')"
                back-label="Devoirs"
            />

            <!-- Grade (rule 16) -->
            <section
                v-if="submission?.grade !== null && submission?.grade !== undefined"
                class="animate-enter flex items-center gap-5 rounded-2xl border bg-card p-5"
            >
                <div class="animate-pop flex size-20 shrink-0 flex-col items-center justify-center rounded-2xl bg-success/10">
                    <span :class="['text-3xl font-bold tabular-nums', toneText[scoreTone(submission.grade)]]">{{
                        formatScore(submission.grade)
                    }}</span>
                    <span class="text-xs text-muted-foreground">sur 20</span>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold">Devoir noté</p>
                    <p v-if="submission.comment" class="mt-1 flex gap-2 text-sm text-muted-foreground">
                        <MessageSquareQuote class="mt-0.5 size-4 shrink-0" /> <span class="whitespace-pre-line">{{ submission.comment }}</span>
                    </p>
                </div>
            </section>

            <!-- Instructions -->
            <section class="animate-enter rounded-2xl border bg-card p-5" style="animation-delay: 0.05s">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', stateBadge[state]]">{{ stateLabel }}</span>
                    <span class="inline-flex items-center gap-1 text-muted-foreground">
                        <CalendarClock class="size-4" /> À rendre {{ fromNow(assignment.due_at) }} ({{ dateTime(assignment.due_at) }})
                    </span>
                </div>
                <h2 class="mt-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Consigne</h2>
                <p class="mt-1 whitespace-pre-line leading-relaxed">{{ assignment.instructions }}</p>

                <div v-if="assignment.material" class="mt-4 rounded-xl border p-3">
                    <p class="flex items-center gap-2 text-sm font-medium">
                        <FileText class="size-4 text-primary" /> {{ assignment.material.title }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Button v-if="assignment.material.file" as-child variant="outline" class="h-10">
                            <a :href="assignment.material.file.download"
                                ><Download /> Télécharger · {{ formatBytes(assignment.material.file.size) }}</a
                            >
                        </Button>
                        <Button v-if="assignment.material.url" as-child variant="outline" class="h-10">
                            <a :href="assignment.material.url" target="_blank" rel="noopener noreferrer"><ExternalLink /> Ouvrir le lien</a>
                        </Button>
                    </div>
                </div>
            </section>

            <!-- Current submission -->
            <section v-if="submission" class="animate-enter rounded-2xl border border-success/30 bg-success/5 p-5" style="animation-delay: 0.1s">
                <p class="flex items-center gap-2 font-semibold text-success">
                    <CircleCheck class="size-5" /> Rendu le {{ dateTime(submission.submitted_at) }}
                    <span v-if="submission.is_late" class="rounded-full bg-warning/15 px-2 py-0.5 text-xs text-warning">en retard</span>
                </p>
                <p v-if="submission.body && !canSubmit" class="mt-3 whitespace-pre-line rounded-xl bg-background p-3 text-sm">
                    {{ submission.body }}
                </p>
                <a
                    v-if="submission.file"
                    :href="submission.file.download"
                    class="press mt-3 flex items-center gap-3 rounded-xl border bg-background p-2.5 text-sm hover:bg-muted/40"
                >
                    <span
                        :class="['flex size-9 items-center justify-center rounded-lg text-[11px] font-bold', fileTone(submission.file.extension)]"
                        >{{ submission.file.extension }}</span
                    >
                    <span class="min-w-0 flex-1 truncate">{{ submission.file.name }}</span>
                    <Download class="size-4 text-primary" />
                </a>
            </section>

            <!-- Hand in / replace (rules 14, 15) -->
            <form
                v-if="canSubmit"
                class="animate-enter space-y-4 rounded-2xl border bg-card p-5"
                style="animation-delay: 0.15s"
                @submit.prevent="submit"
            >
                <div>
                    <h2 class="font-semibold">{{ submission ? 'Remplacer mon rendu' : 'Mon rendu' }}</h2>
                    <p class="text-sm text-muted-foreground">Un fichier, un texte, ou les deux.</p>
                </div>
                <p v-if="willBeLate" class="flex items-start gap-2 rounded-xl bg-warning/10 p-3 text-sm text-warning">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" /> La date limite est dépassée : votre rendu sera signalé en retard.
                </p>
                <textarea
                    v-model="form.body"
                    rows="6"
                    aria-label="Votre réponse"
                    class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-base shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
                    placeholder="Écrivez votre réponse ici…"
                />
                <InputError :message="form.errors.body" />
                <FileDrop
                    v-model="form.file"
                    v-model:remove="form.remove_file"
                    :current="submission?.file ?? null"
                    :max-mb="limits.max_mb"
                    :extensions="limits.extensions"
                    :error="form.errors.file"
                />
                <div v-if="form.progress" class="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div class="h-full bg-primary transition-[width]" :style="{ width: `${form.progress.percentage ?? 0}%` }" />
                </div>
                <Button type="submit" class="h-12 w-full text-base shadow-md shadow-primary/25" :disabled="form.processing || !hasContent">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Send v-else /> {{ submission ? 'Remplacer mon rendu' : 'Rendre le devoir' }}
                </Button>
            </form>

            <p
                v-else-if="!submission || submission.grade === null"
                class="flex items-center gap-2 rounded-2xl border border-dashed p-5 text-sm text-muted-foreground"
            >
                <Lock class="size-4" />
                {{
                    state === 'NON_RENDU' ? 'La date limite est dépassée : les rendus ne sont plus acceptés.' : 'Le rendu ne peut plus être modifié.'
                }}
            </p>
        </PageContainer>
    </AppLayout>
</template>
