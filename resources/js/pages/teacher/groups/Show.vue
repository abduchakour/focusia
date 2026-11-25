<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import CopyButton from '@/components/CopyButton.vue';
import EmptyState from '@/components/EmptyState.vue';
import GroupFormDialog from '@/components/groups/GroupFormDialog.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { EllipsisVertical, Pencil, RefreshCw, Share2, Trash2, UserMinus, UserPlus, UsersRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Student {
    id: number;
    name: string;
    email: string;
    joined_at: string;
    removed_at: string | null;
}

const props = defineProps<{
    group: { id: number; name: string; subject: string; level: string; level_label: string; access_code: string; invite_url: string };
    students: Student[];
    removedStudents: Student[];
    levels: { value: string; label: string }[];
}>();

const { getInitials } = useInitials();
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Groupes', href: route('teacher.groups.index') },
    { title: props.group.name, href: route('teacher.groups.show', { group: props.group.id }) },
]);

const editing = ref(false);
const confirmRegenerate = ref(false);
const confirmDelete = ref(false);
const studentToRemove = ref<Student | null>(null);
const processing = ref(false);

const inviteMessage = computed(
    () => `Rejoignez le groupe « ${props.group.name} » sur FocusIA avec le code ${props.group.access_code} : ${props.group.invite_url}`,
);

async function share() {
    if (navigator.share) {
        try {
            await navigator.share({ title: 'FocusIA', text: inviteMessage.value });
            return;
        } catch {
            /* cancelled: fall back to WhatsApp below only if share is unsupported */
            return;
        }
    }
    window.open(`https://wa.me/?text=${encodeURIComponent(inviteMessage.value)}`, '_blank', 'noopener');
}

const done = () => {
    processing.value = false;
};

function regenerate() {
    processing.value = true;
    router.post(
        route('teacher.groups.code', { group: props.group.id }),
        {},
        { preserveScroll: true, onFinish: done, onSuccess: () => (confirmRegenerate.value = false) },
    );
}

function destroy() {
    processing.value = true;
    router.delete(route('teacher.groups.destroy', { group: props.group.id }), { onFinish: done });
}

function removeStudent() {
    if (!studentToRemove.value) return;
    processing.value = true;
    router.delete(route('teacher.groups.students.remove', { group: props.group.id, student: studentToRemove.value.id }), {
        preserveScroll: true,
        onFinish: done,
        onSuccess: () => (studentToRemove.value = null),
    });
}

function restoreStudent(student: Student) {
    router.post(route('teacher.groups.students.restore', { group: props.group.id, student: student.id }), {}, { preserveScroll: true });
}

const formatDate = (iso: string) => new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head :title="group.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader
                :title="group.name"
                :description="`${group.subject} · ${group.level_label}`"
                :back-href="route('teacher.groups.index')"
                back-label="Mes groupes"
            >
                <template #actions>
                    <Button variant="outline" class="h-11" @click="editing = true"><Pencil /> Modifier</Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="icon" class="size-11" aria-label="Plus d'actions"><EllipsisVertical /></Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem class="text-destructive focus:text-destructive" @select="confirmDelete = true">
                                <Trash2 class="mr-2 size-4" /> Supprimer le groupe
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </PageHeader>

            <!-- Access code -->
            <section
                class="animate-stagger overflow-hidden rounded-2xl border bg-gradient-to-br from-primary to-primary/80 p-6 text-primary-foreground shadow-lg shadow-primary/20"
                style="--i: 1"
            >
                <p class="text-sm font-medium opacity-90">Code d'accès du groupe</p>
                <p :key="group.access_code" class="animate-pop my-3 font-mono text-5xl font-bold tracking-[0.3em] sm:text-6xl">
                    {{ group.access_code }}
                </p>
                <p class="mb-5 text-sm opacity-90">Les étudiants le saisissent dans « Mes groupes », ou ouvrent directement le lien d'invitation.</p>
                <div class="flex flex-wrap gap-2">
                    <CopyButton :text="group.access_code" label="Copier le code" variant="secondary" />
                    <CopyButton :text="group.invite_url" label="Copier le lien" variant="secondary" />
                    <Button variant="secondary" class="h-11" @click="share"><Share2 /> Partager</Button>
                    <Button
                        variant="ghost"
                        class="h-11 text-primary-foreground hover:bg-white/15 hover:text-primary-foreground"
                        @click="confirmRegenerate = true"
                    >
                        <RefreshCw /> Nouveau code
                    </Button>
                </div>
            </section>

            <!-- Students -->
            <section class="animate-stagger space-y-3" style="--i: 2">
                <h2 class="text-lg font-semibold">
                    Étudiants <span class="ml-1 rounded-full bg-secondary px-2 py-0.5 text-sm text-secondary-foreground">{{ students.length }}</span>
                </h2>

                <EmptyState
                    v-if="students.length === 0"
                    :icon="UsersRound"
                    title="Personne n'a encore rejoint ce groupe"
                    description="Partagez le code ci-dessus : les étudiants apparaîtront ici dès qu'ils l'auront saisi."
                />

                <ul v-else class="divide-y overflow-hidden rounded-2xl border bg-card">
                    <li
                        v-for="(student, i) in students"
                        :key="student.id"
                        class="animate-stagger flex items-center gap-3 p-3 sm:p-4"
                        :style="{ '--i': Math.min(i, 12) }"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-secondary text-sm font-semibold text-secondary-foreground"
                        >
                            {{ getInitials(student.name) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ student.name }}</p>
                            <p class="truncate text-sm text-muted-foreground">{{ student.email }} · depuis le {{ formatDate(student.joined_at) }}</p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-10 shrink-0 text-muted-foreground hover:text-destructive"
                            :aria-label="`Retirer ${student.name}`"
                            @click="studentToRemove = student"
                        >
                            <UserMinus />
                        </Button>
                    </li>
                </ul>
            </section>

            <section v-if="removedStudents.length > 0" class="space-y-3">
                <h2 class="text-lg font-semibold text-muted-foreground">Étudiants retirés</h2>
                <p class="text-sm text-muted-foreground">Ils n'ont plus accès aux contenus du groupe ; leurs résultats sont conservés.</p>
                <ul class="divide-y rounded-2xl border border-dashed">
                    <li v-for="student in removedStudents" :key="student.id" class="flex items-center gap-3 p-3 sm:p-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-muted-foreground">{{ student.name }}</p>
                            <p class="truncate text-sm text-muted-foreground">Retiré le {{ formatDate(student.removed_at!) }}</p>
                        </div>
                        <Button variant="outline" class="h-10 shrink-0" @click="restoreStudent(student)"><UserPlus /> Réintégrer</Button>
                    </li>
                </ul>
            </section>
        </PageContainer>

        <GroupFormDialog
            v-model:open="editing"
            :levels="levels"
            :group="{ id: group.id, name: group.name, subject: group.subject, level: group.level }"
        />

        <ConfirmDialog
            v-model:open="confirmRegenerate"
            title="Générer un nouveau code ?"
            description="L'ancien code ne fonctionnera plus. Les étudiants déjà inscrits restent dans le groupe."
            confirm-label="Nouveau code"
            :processing="processing"
            @confirm="regenerate"
        />

        <ConfirmDialog
            :open="studentToRemove !== null"
            :title="`Retirer ${studentToRemove?.name ?? ''} ?`"
            description="Cet étudiant n'aura plus accès aux contenus du groupe. Ses notes et ses rendus sont conservés, et vous pourrez le réintégrer."
            confirm-label="Retirer du groupe"
            destructive
            :processing="processing"
            @update:open="(value) => !value && (studentToRemove = null)"
            @confirm="removeStudent"
        />

        <ConfirmDialog
            v-model:open="confirmDelete"
            title="Supprimer ce groupe ?"
            description="Le groupe et ses inscriptions seront supprimés. Les quiz, supports et résultats des étudiants sont conservés."
            confirm-label="Supprimer"
            destructive
            :processing="processing"
            @confirm="destroy"
        />
    </AppLayout>
</template>
