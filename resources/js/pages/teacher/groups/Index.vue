<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import GroupFormDialog from '@/components/groups/GroupFormDialog.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, KeyRound, Plus, UsersRound } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    groups: { id: number; name: string; subject: string; level: string; access_code: string; students_count: number }[];
    levels: { value: string; label: string }[];
}>();

const creating = ref(false);
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Groupes', href: route('teacher.groups.index') }];
</script>

<template>
    <Head title="Mes groupes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader title="Mes groupes" description="Chaque groupe a son code d’accès à partager avec les étudiants.">
                <template #actions>
                    <Button class="h-11" @click="creating = true"><Plus /> Nouveau groupe</Button>
                </template>
            </PageHeader>

            <EmptyState
                v-if="groups.length === 0"
                :icon="UsersRound"
                title="Aucun groupe pour le moment"
                description="Créez votre premier groupe, puis partagez son code d’accès : vos étudiants le rejoignent en quelques secondes."
            >
                <Button class="h-11" @click="creating = true"><Plus /> Créer un groupe</Button>
            </EmptyState>

            <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="(group, i) in groups" :key="group.id" class="animate-stagger" :style="{ '--i': i }">
                    <Link
                        :href="route('teacher.groups.show', { group: group.id })"
                        class="lift press group flex h-full flex-col gap-4 rounded-2xl border bg-card p-5"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h2 class="truncate font-semibold">{{ group.name }}</h2>
                                <p class="truncate text-sm text-muted-foreground">{{ group.subject }} · {{ group.level }}</p>
                            </div>
                            <ChevronRight class="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                        </div>
                        <div class="mt-auto flex items-center justify-between text-sm">
                            <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                <UsersRound class="size-4" />
                                {{ group.students_count }} étudiant{{ group.students_count > 1 ? 's' : '' }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md bg-secondary px-2 py-1 font-mono font-semibold tracking-widest text-secondary-foreground"
                            >
                                <KeyRound class="size-3.5" />{{ group.access_code }}
                            </span>
                        </div>
                    </Link>
                </li>
            </ul>
        </PageContainer>

        <GroupFormDialog v-model:open="creating" :levels="levels" />
    </AppLayout>
</template>
