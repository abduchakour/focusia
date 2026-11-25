<script setup lang="ts">
import { useReveal } from '@/composables/useReveal';
import { formatScore } from '@/lib/score';
import { computed, ref } from 'vue';

/** Group average out of 20, quiz after quiz (one series, one axis, 10/20 reference line). */
const props = defineProps<{ points: { label: string; value: number | null; hint?: string }[] }>();

const W = 600;
const H = 220;
const pad = { top: 16, right: 16, bottom: 28, left: 32 };
const plotW = W - pad.left - pad.right;
const plotH = H - pad.top - pad.bottom;

const data = computed(() => props.points.filter((p) => p.value !== null) as { label: string; value: number; hint?: string }[]);
const x = (i: number) => pad.left + (data.value.length === 1 ? plotW / 2 : (i / (data.value.length - 1)) * plotW);
const y = (v: number) => pad.top + plotH - (v / 20) * plotH;
const path = computed(() => data.value.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`).join(' '));
const area = computed(() => (data.value.length ? `${path.value} L${x(data.value.length - 1)},${pad.top + plotH} L${x(0)},${pad.top + plotH} Z` : ''));

const revealed = useReveal();
const hovered = ref<number | null>(null);
</script>

<template>
    <div class="relative">
        <svg :viewBox="`0 0 ${W} ${H}`" class="h-auto w-full overflow-visible" role="img" aria-label="Moyenne du groupe par quiz, sur 20">
            <!-- recessive grid -->
            <g class="text-[11px]" fill="currentColor">
                <template v-for="v in [0, 5, 10, 15, 20]" :key="v">
                    <line
                        :x1="pad.left"
                        :x2="W - pad.right"
                        :y1="y(v)"
                        :y2="y(v)"
                        :class="v === 10 ? 'stroke-foreground/40' : 'stroke-border'"
                        :stroke-dasharray="v === 10 ? '4 4' : undefined"
                        stroke-width="1"
                    />
                    <text :x="pad.left - 8" :y="y(v) + 4" text-anchor="end" class="fill-muted-foreground">{{ v }}</text>
                </template>
            </g>

            <path :d="area" class="fill-primary/10 transition-opacity duration-700" :class="revealed ? 'opacity-100' : 'opacity-0'" />
            <path
                :d="path"
                class="fill-none stroke-primary"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                pathLength="1"
                stroke-dasharray="1"
                :stroke-dashoffset="revealed ? 0 : 1"
                style="transition: stroke-dashoffset 1.2s ease-out"
            />

            <g v-for="(p, i) in data" :key="i">
                <circle
                    :cx="x(i)"
                    :cy="y(p.value)"
                    :r="hovered === i ? 7 : 5"
                    class="fill-background stroke-primary transition-all"
                    stroke-width="2.5"
                    :style="{ opacity: revealed ? 1 : 0, transition: `opacity .3s ${0.2 + i * 0.15}s, r .15s` }"
                />
                <!-- hit target larger than the mark -->
                <circle
                    :cx="x(i)"
                    :cy="y(p.value)"
                    r="18"
                    fill="transparent"
                    class="cursor-pointer"
                    tabindex="0"
                    :aria-label="`${p.label} : ${formatScore(p.value)} sur 20`"
                    @mouseenter="hovered = i"
                    @mouseleave="hovered = null"
                    @focus="hovered = i"
                    @blur="hovered = null"
                />
            </g>
        </svg>

        <div
            v-if="hovered !== null && data[hovered]"
            class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-foreground px-3 py-1.5 text-xs text-background shadow-lg"
            :style="{ left: `${(x(hovered) / W) * 100}%`, top: `${(y(data[hovered].value) / H) * 100}%`, marginTop: '-12px' }"
        >
            <p class="font-semibold">{{ data[hovered].label }}</p>
            <p>
                Moyenne {{ formatScore(data[hovered].value) }}/20<template v-if="data[hovered].hint"> · {{ data[hovered].hint }}</template>
            </p>
        </div>
    </div>
</template>
