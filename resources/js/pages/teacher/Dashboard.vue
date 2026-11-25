<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

defineProps<{
    stats: { groups: number; students: number; quizzes: number };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tableau de bord', href: route('teacher.dashboard') }];
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <h1 class="text-2xl font-semibold">Bonjour {{ page.props.auth.user.first_name }}</h1>

            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard label="Groupes" :value="stats.groups" />
                <StatCard label="Étudiants suivis" :value="stats.students" />
                <StatCard label="Quiz créés" :value="stats.quizzes" />
            </div>
        </div>
    </AppLayout>
</template>
