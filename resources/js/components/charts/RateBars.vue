<script setup lang="ts">
import { useReveal } from '@/composables/useReveal';
import { percent } from '@/lib/format';
import { TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Success rate per notion: one horizontal bar each, on a 0-100 % scale with the
 * "difficulty" threshold drawn as a dashed line. Below the threshold the bar turns
 * amber AND gets an icon + text, so the meaning never relies on colour alone.
 */
const props = withDefaults(
    defineProps<{
        items: { name: string; rate: number | null; correct?: number; answered: number }[];
        threshold?: number;
        sort?: boolean;
        compact?: boolean;
    }>(),
    { threshold: 0.5, sort: false, compact: false },
);

const rows = computed(() => {
    const list = props.items.filter((i) => i.rate !== null) as { name: string; rate: number; correct?: number; answered: number }[];
    return props.sort ? [...list].sort((a, b) => a.rate - b.rate) : list;
});
const revealed = useReveal();
</script>

<template>
    <div>
        <ul :class="compact ? 'space-y-3' : 'space-y-4'">
            <li v-for="(item, i) in rows" :key="item.name" class="group relative">
                <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-center gap-1.5 font-medium">
                        <TriangleAlert v-if="item.rate < threshold" class="size-3.5 shrink-0 text-warning" aria-hidden="true" />
                        <span class="truncate">{{ item.name }}</span>
                    </span>
                    <span class="shrink-0 tabular-nums">
                        <strong>{{ percent(item.rate) }}</strong>
                        <span v-if="item.rate < threshold" class="ml-1 text-xs font-medium text-warning">à revoir</span>
                    </span>
                </div>
                <div class="relative h-2.5 rounded-full bg-muted">
                    <div
                        :class="[
                            'h-full rounded-full transition-[width] duration-1000 ease-out',
                            item.rate < threshold ? 'bg-warning' : 'bg-primary',
                        ]"
                        :style="{ width: revealed ? `${Math.max(item.rate * 100, 1.5)}%` : '0%', transitionDelay: `${i * 70}ms` }"
                    />
                    <span
                        class="absolute -inset-y-1 w-px border-l border-dashed border-foreground/40"
                        :style="{ left: `${threshold * 100}%` }"
                        aria-hidden="true"
                    />
                </div>
                <!-- Hover details -->
                <span
                    class="pointer-events-none absolute -top-9 right-0 z-10 whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1 text-xs text-background opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                >
                    <template v-if="item.correct !== undefined">{{ item.correct }} bonnes réponses sur {{ item.answered }} données</template>
                    <template v-else>{{ item.answered }} réponses données</template>
                </span>
            </li>
        </ul>
        <p v-if="!compact" class="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
            <span class="inline-block h-3 w-px border-l border-dashed border-foreground/50" /> seuil de {{ percent(threshold) }} · les réponses
            laissées vides ne comptent pas
        </p>
    </div>
</template>
