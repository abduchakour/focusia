<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="animate-enter flex flex-col items-center gap-3 rounded-2xl border border-dashed px-6 py-12 text-center">
        <div class="animate-pop flex size-14 items-center justify-center rounded-full bg-secondary text-primary">
            <component :is="icon" class="size-7" />
        </div>
        <h2 class="text-lg font-semibold">{{ title }}</h2>
        <p v-if="description" class="max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
