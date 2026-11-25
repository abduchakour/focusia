<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    status: number;
    message: string | null;
}>();

const texts: Record<number, { title: string; description: string }> = {
    403: { title: 'Accès refusé', description: "Vous n'avez pas accès à cette page." },
    404: { title: 'Page introuvable', description: "Cette page n'existe pas ou a été supprimée." },
    429: { title: 'Trop de tentatives', description: 'Patientez une minute avant de réessayer.' },
    500: { title: 'Erreur du serveur', description: 'Un problème est survenu. Réessayez dans quelques instants.' },
    503: { title: 'Service indisponible', description: 'FocusIA est en maintenance. Revenez dans quelques minutes.' },
};

const text = computed(() => texts[props.status] ?? texts[500]);
</script>

<template>
    <Head :title="text.title" />
    <main class="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
        <p class="text-5xl font-semibold text-muted-foreground">{{ status }}</p>
        <h1 class="text-2xl font-semibold">{{ text.title }}</h1>
        <p class="max-w-md text-muted-foreground">{{ message ?? text.description }}</p>
        <Button as-child class="h-11 px-6">
            <Link :href="route('dashboard')">Retour à l'accueil</Link>
        </Button>
    </main>
</template>
