<script setup lang="ts">
import { useReveal } from '@/composables/useReveal';
import { computed } from 'vue';

/** How many students got a grade in each range out of 20. Single hue; the 10/20 mark is a dashed divider. */
const props = defineProps<{ bins: { label: string; from: number; to: number; count: number }[] }>();

const max = computed(() => Math.max(1, ...props.bins.map((b) => b.count)));
const revealed = useReveal();
const passIndex = computed(() => props.bins.findIndex((b) => b.from >= 10));
</script>

<template>
    <div>
        <div class="flex h-44 items-end gap-1.5 border-b border-border sm:gap-2">
            <template v-for="(bin, i) in bins" :key="bin.label">
                <span v-if="i === passIndex" class="relative h-full w-px shrink-0 border-l border-dashed border-foreground/40" aria-hidden="true">
                    <span class="absolute -top-1 left-1 whitespace-nowrap text-[10px] font-medium text-muted-foreground">10/20</span>
                </span>
                <div class="group relative flex h-full flex-1 flex-col items-center justify-end">
                    <span class="mb-1 text-xs font-semibold tabular-nums" :class="bin.count ? '' : 'text-muted-foreground/50'">{{ bin.count }}</span>
                    <div
                        :class="[
                            'w-full max-w-12 rounded-t transition-[height] duration-700 ease-out',
                            bin.from < 10 ? 'bg-primary/45' : 'bg-primary',
                            'group-hover:brightness-110',
                        ]"
                        :style="{
                            height: revealed ? `${(bin.count / max) * 80}%` : '0%',
                            transitionDelay: `${i * 60}ms`,
                            minHeight: bin.count ? '4px' : '0',
                        }"
                    />
                    <span
                        class="pointer-events-none absolute -top-8 z-10 whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1 text-xs text-background opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                    >
                        {{ bin.count }} étudiant{{ bin.count > 1 ? 's' : '' }} entre {{ bin.from }} et {{ bin.to }}
                    </span>
                </div>
            </template>
        </div>
        <div class="mt-1.5 flex gap-1.5 sm:gap-2">
            <template v-for="(bin, i) in bins" :key="bin.label">
                <span v-if="i === passIndex" class="w-px shrink-0" />
                <span class="flex-1 text-center text-[11px] tabular-nums text-muted-foreground">{{ bin.label }}</span>
            </template>
        </div>
    </div>
</template>
