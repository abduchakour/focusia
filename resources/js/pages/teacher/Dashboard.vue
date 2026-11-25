<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpenCheck, GraduationCap, UsersRound } from 'lucide-vue-next';

defineProps<{
    stats: { groups: number; students: number; quizzes: number };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tableau de bord', href: route('teacher.dashboard') }];
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader :title="`Bonjour ${page.props.auth.user.first_name} 👋`" description="Voici l'essentiel de vos groupes.">
                <template #actions>
                    <Button as-child class="h-11"><Link :href="route('teacher.groups.index')">Mes groupes</Link></Button>
                </template>
            </PageHeader>

            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard label="Groupes" :value="stats.groups" :icon="UsersRound" :index="1" />
                <StatCard label="Étudiants suivis" :value="stats.students" :icon="GraduationCap" :index="2" />
                <StatCard label="Quiz créés" :value="stats.quizzes" :icon="BookOpenCheck" :index="3" />
            </div>
        </PageContainer>
    </AppLayout>
</template>
