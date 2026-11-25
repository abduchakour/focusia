<script setup lang="ts">
import { CircleAlert } from 'lucide-vue-next';

defineProps<{
    message?: string;
}>();
</script>

<template>
    <Transition
        enter-from-class="-translate-y-1 opacity-0"
        enter-active-class="transition duration-200"
        leave-active-class="transition duration-150"
        leave-to-class="opacity-0"
    >
        <p v-if="message" data-field-error class="flex items-start gap-1.5 text-sm text-destructive" role="alert">
            <CircleAlert class="mt-0.5 size-4 shrink-0" />
            <span>{{ message }}</span>
        </p>
    </Transition>
</template>
