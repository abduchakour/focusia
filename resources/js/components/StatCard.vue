<script setup lang="ts">
import { useCountUp } from '@/composables/useCountUp';
import type { Component } from 'vue';
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    /** Numbers are animated; strings (e.g. "—") are shown as is. */
    value: number | string;
    suffix?: string;
    decimals?: number;
    hint?: string;
    icon?: Component;
    index?: number;
}>();

const numeric = computed(() => (typeof props.value === 'number' ? props.value : 0));
const animated = useCountUp(numeric);

const shown = computed(() =>
    typeof props.value === 'number'
        ? animated.value.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: props.decimals ?? 0 })
        : props.value,
);
</script>

<template>
    <div class="animate-stagger lift rounded-2xl border bg-card p-5" :style="{ '--i': index ?? 0 }">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
            <div v-if="icon" class="flex size-9 items-center justify-center rounded-xl bg-secondary text-primary">
                <component :is="icon" class="size-5" />
            </div>
        </div>
        <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight">
            {{ shown }}<span v-if="suffix && typeof value === 'number'" class="ml-1 text-lg font-medium text-muted-foreground">{{ suffix }}</span>
        </p>
        <p v-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
