<script setup lang="ts">
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
</script>

<template>
    <Head title="Bienvenue" />
    <div class="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <header class="mx-auto flex w-full max-w-4xl items-center justify-between p-4 sm:p-6">
            <span class="text-xl font-semibold">FocusIA</span>
            <nav class="flex gap-2">
                <Link v-if="page.props.auth.user" :href="route('dashboard')" class="rounded-md border px-4 py-2 text-sm font-medium">
                    Tableau de bord
                </Link>
                <template v-else>
                    <Link :href="route('login')" class="rounded-md px-4 py-2 text-sm font-medium">Connexion</Link>
                    <Link :href="route('register')" class="rounded-md border px-4 py-2 text-sm font-medium">Inscription</Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-4xl flex-1 flex-col justify-center gap-6 p-4 sm:p-6">
            <h1 class="text-3xl font-semibold sm:text-4xl">Suivez la compréhension de chaque étudiant, notion par notion.</h1>
            <p class="max-w-2xl text-lg text-neutral-600 dark:text-neutral-400">
                Quiz corrigés automatiquement, supports de cours, devoirs et bibliothèque réunis au même endroit. Les étudiants répondent même avec
                une connexion intermittente.
            </p>
            <div class="flex flex-col gap-3 sm:flex-row">
                <Link
                    :href="route('register')"
                    class="rounded-md bg-[#1b1b18] px-6 py-3 text-center font-medium text-white dark:bg-white dark:text-[#1b1b18]"
                >
                    Créer un compte
                </Link>
                <Link :href="route('login')" class="rounded-md border px-6 py-3 text-center font-medium">J'ai déjà un compte</Link>
            </div>
        </main>
    </div>
</template>
