<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

defineProps<{
    stats: { groups: number; attempts: number; average: number | null };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tableau de bord', href: route('student.dashboard') }];

const formatGrade = (value: number) => value.toLocaleString('fr-FR', { maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <h1 class="text-2xl font-semibold">Bonjour {{ page.props.auth.user.first_name }}</h1>

            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard label="Mes groupes" :value="stats.groups" />
                <StatCard label="Quiz passés" :value="stats.attempts" />
                <StatCard
                    label="Moyenne des quiz"
                    :value="stats.average === null ? '—' : `${formatGrade(stats.average)} / 20`"
                    :hint="stats.average === null ? 'Aucun quiz passé pour le moment' : undefined"
                />
            </div>
        </div>
    </AppLayout>
</template>
