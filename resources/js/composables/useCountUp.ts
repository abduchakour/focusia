import { onMounted, ref, watch, type Ref } from 'vue';

/**
 * Animates a number from 0 to its value (ease-out, ~0.8 s). Instant when the
 * device asks for reduced motion.
 */
export function useCountUp(target: Ref<number>, duration = 800) {
    const display = ref(0);

    const run = (to: number) => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            display.value = to;
            return;
        }
        const from = display.value;
        const start = performance.now();
        const step = (now: number) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            display.value = from + (to - from) * eased;
            if (t < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    };

    onMounted(() => run(target.value));
    watch(target, run);

    return display;
}
