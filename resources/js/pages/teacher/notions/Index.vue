<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { plural } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Notion {
    id: number;
    name: string;
    subject: string;
    questions_count: number;
}

const props = defineProps<{ notions: Notion[]; subjects: string[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notions', href: route('teacher.notions.index') }];

const bySubject = computed(() => {
    const map = new Map<string, Notion[]>();
    for (const s of props.subjects) map.set(s, []);
    for (const n of props.notions) map.set(n.subject, [...(map.get(n.subject) ?? []), n]);
    return [...map.entries()];
});

// Add (one inline field per subject + a free form for a new subject)
const addForm = useForm({ subject: '', name: '' });
const drafts = ref<Record<string, string>>({});
function add(subject: string) {
    addForm.subject = subject;
    addForm.name = drafts.value[subject] ?? '';
    addForm.post(route('teacher.notions.store'), {
        preserveScroll: true,
        onSuccess: () => (drafts.value[subject] = ''),
    });
}
const newSubject = ref('');
const newSubjectName = ref('');
function addWithNewSubject() {
    addForm.subject = newSubject.value;
    addForm.name = newSubjectName.value;
    addForm.post(route('teacher.notions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newSubject.value = '';
            newSubjectName.value = '';
        },
    });
}

// Rename
const editing = ref<Notion | null>(null);
const renameForm = useForm({ subject: '', name: '' });
function startRename(n: Notion) {
    editing.value = n;
    renameForm.subject = n.subject;
    renameForm.name = n.name;
    renameForm.clearErrors();
}
function rename() {
    if (!editing.value) return;
    renameForm.put(route('teacher.notions.update', { notion: editing.value.id }), { preserveScroll: true, onSuccess: () => (editing.value = null) });
}

// Delete
const toDelete = ref<Notion | null>(null);
function destroy() {
    if (!toDelete.value) return;
    router.delete(route('teacher.notions.destroy', { notion: toDelete.value.id }), { preserveScroll: true, onFinish: () => (toDelete.value = null) });
}
</script>

<template>
    <Head title="Notions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader
                title="Notions"
                description="Les notions évaluées par vos questions. Elles servent à mesurer la compréhension, notion par notion."
            />

            <InputError :message="addForm.errors.name ?? addForm.errors.subject" />

            <EmptyState
                v-if="bySubject.length === 0"
                :icon="Tags"
                title="Aucune notion pour le moment"
                description="Ajoutez vos notions ci-dessous, ou directement pendant la création d'un quiz."
            />

            <section
                v-for="([subject, items], i) in bySubject"
                :key="subject"
                class="animate-stagger space-y-4 rounded-2xl border bg-card p-4 shadow-sm sm:p-6"
                :style="{ '--i': i }"
            >
                <h2 class="flex items-center justify-between gap-2 font-semibold">
                    <span>{{ subject }}</span>
                    <span class="text-sm font-normal text-muted-foreground">{{ plural(items.length, 'notion') }}</span>
                </h2>

                <TransitionGroup tag="ul" name="chip" class="flex flex-wrap gap-2">
                    <li
                        v-for="n in items"
                        :key="n.id"
                        class="group inline-flex items-center gap-1 rounded-full border bg-secondary/60 py-1 pl-3 pr-1 text-sm"
                    >
                        <span class="font-medium">{{ n.name }}</span>
                        <span class="text-xs text-muted-foreground">· {{ plural(n.questions_count, 'question') }}</span>
                        <button
                            type="button"
                            class="press ml-1 rounded-full p-1.5 text-muted-foreground hover:bg-background hover:text-foreground"
                            :aria-label="`Renommer ${n.name}`"
                            @click="startRename(n)"
                        >
                            <Pencil class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="press rounded-full p-1.5 text-muted-foreground hover:bg-background hover:text-destructive"
                            :aria-label="`Supprimer ${n.name}`"
                            @click="toDelete = n"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </li>
                </TransitionGroup>

                <form class="flex gap-2" @submit.prevent="add(subject)">
                    <Input v-model="drafts[subject]" class="h-11" :placeholder="`Nouvelle notion en ${subject}…`" />
                    <Button type="submit" class="h-11 shrink-0" :disabled="addForm.processing || !drafts[subject]?.trim()"><Plus /> Ajouter</Button>
                </form>
            </section>

            <section class="animate-stagger space-y-3 rounded-2xl border border-dashed p-4 sm:p-6" :style="{ '--i': bySubject.length }">
                <h2 class="font-semibold">Autre matière</h2>
                <form class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="addWithNewSubject">
                    <Input v-model="newSubject" class="h-11" placeholder="Matière" />
                    <Input v-model="newSubjectName" class="h-11" placeholder="Notion" />
                    <Button type="submit" class="h-11" :disabled="addForm.processing || !newSubject.trim() || !newSubjectName.trim()"
                        ><Plus /> Ajouter</Button
                    >
                </form>
            </section>
        </PageContainer>

        <Dialog :open="editing !== null" @update:open="(v) => !v && (editing = null)">
            <DialogContent class="sm:max-w-md">
                <form class="space-y-4" @submit.prevent="rename">
                    <DialogHeader><DialogTitle>Renommer la notion</DialogTitle></DialogHeader>
                    <div class="grid gap-2">
                        <Label for="rename">Nom</Label>
                        <Input id="rename" v-model="renameForm.name" class="h-11" required />
                        <InputError :message="renameForm.errors.name" />
                    </div>
                    <DialogFooter><Button type="submit" class="h-11" :disabled="renameForm.processing">Enregistrer</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="toDelete !== null"
            :title="`Supprimer « ${toDelete?.name ?? ''} » ?`"
            description="Une notion utilisée par des questions ne peut pas être supprimée."
            confirm-label="Supprimer"
            destructive
            @update:open="(v) => !v && (toDelete = null)"
            @confirm="destroy"
        />
    </AppLayout>
</template>

<style scoped>
.chip-enter-active {
    animation: focus-pop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
.chip-leave-active {
    transition: all 0.2s ease;
}
.chip-leave-to {
    opacity: 0;
    transform: scale(0.8);
}
</style>
