<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';

// Native <select>: lightest option and the best picker on phones.
defineProps<{
    id?: string;
    options: { value: string; label: string }[];
    placeholder?: string;
}>();

const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="relative">
        <select
            :id="id"
            v-model="model"
            class="h-11 w-full appearance-none rounded-md border border-input bg-background px-3 pr-10 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <ChevronDown class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
    </div>
</template>
