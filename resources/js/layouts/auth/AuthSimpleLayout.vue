<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-gradient-to-b from-secondary/70 via-background to-background p-4 sm:p-6"
    >
        <!-- Soft decorative halo, purely visual. -->
        <div class="pointer-events-none absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />

        <div class="animate-enter relative w-full max-w-md">
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                <Link :href="route('home')" class="press block" aria-label="Accueil FocusIA">
                    <img
                        src="/images/focusia-logo.webp"
                        alt="FocusIA – Apprendre, réviser, progresser"
                        width="900"
                        height="600"
                        class="animate-pop h-28 w-auto rounded-2xl bg-white px-2 shadow-lg shadow-primary/10 sm:h-32"
                    />
                </Link>
            </div>

            <div class="rounded-2xl border bg-card p-6 shadow-xl shadow-primary/5 sm:p-8">
                <div class="mb-6 space-y-1 text-center">
                    <h1 class="text-xl font-semibold">{{ title }}</h1>
                    <p class="text-sm text-muted-foreground">{{ description }}</p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
