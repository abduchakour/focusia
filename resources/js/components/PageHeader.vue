<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    title: string;
    description?: string;
    backHref?: string;
    backLabel?: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0 space-y-1">
            <Link
                v-if="backHref"
                :href="backHref"
                class="press mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> {{ backLabel ?? 'Retour' }}
            </Link>
            <h1 class="truncate text-2xl font-bold tracking-tight sm:text-3xl">{{ title }}</h1>
            <p v-if="description" class="text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
