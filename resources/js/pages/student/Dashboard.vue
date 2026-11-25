<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpenCheck, Target, UsersRound } from 'lucide-vue-next';

defineProps<{
    stats: { groups: number; attempts: number; average: number | null };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tableau de bord', href: route('student.dashboard') }];
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader :title="`Bonjour ${page.props.auth.user.first_name} 👋`" description="Vos résultats et vos groupes en un coup d'œil.">
                <template v-if="stats.groups === 0" #actions>
                    <Button as-child class="h-11"><Link :href="route('student.groups.index')">Rejoindre un groupe</Link></Button>
                </template>
            </PageHeader>

            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard label="Mes groupes" :value="stats.groups" :icon="UsersRound" :index="1" />
                <StatCard label="Quiz passés" :value="stats.attempts" :icon="BookOpenCheck" :index="2" />
                <StatCard
                    label="Moyenne des quiz"
                    :value="stats.average ?? '—'"
                    suffix="/ 20"
                    :decimals="2"
                    :icon="Target"
                    :index="3"
                    :hint="stats.average === null ? 'Aucun quiz passé pour le moment' : undefined"
                />
            </div>
        </PageContainer>
    </AppLayout>
</template>
