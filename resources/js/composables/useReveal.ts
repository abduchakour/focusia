import { onMounted, ref } from 'vue';

/** False on the first frame, then true: lets bars and lines grow from zero with a CSS transition. */
export function useReveal() {
    const revealed = ref(false);
    onMounted(() => requestAnimationFrame(() => requestAnimationFrame(() => (revealed.value = true))));
    return revealed;
}
