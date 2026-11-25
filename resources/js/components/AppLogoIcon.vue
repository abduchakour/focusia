<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- FocusIA mark: a target (focus) whose centre is a dot of understanding. -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" :class="className" v-bind="$attrs" aria-hidden="true">
        <circle cx="16" cy="16" r="13" fill="none" stroke="currentColor" stroke-width="3" opacity="0.45" />
        <circle cx="16" cy="16" r="7.5" fill="none" stroke="currentColor" stroke-width="3" />
        <circle cx="16" cy="16" r="3" fill="currentColor" />
        <path d="M23 9 29 3" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
</template>
