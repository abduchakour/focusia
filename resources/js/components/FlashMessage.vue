<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

// Shows the one-shot success / error message sent by the server after an action.
const page = usePage<SharedData>();
const visible = ref(true);

watch(
    () => page.props.flash,
    () => (visible.value = true),
);
</script>

<template>
    <div v-if="visible && (page.props.flash.success || page.props.flash.error)" class="px-4 pt-4" role="status" aria-live="polite">
        <div
            :class="[
                'flex items-start gap-3 rounded-lg border p-3 text-sm',
                page.props.flash.error
                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
                    : 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200',
            ]"
        >
            <CircleAlert v-if="page.props.flash.error" class="mt-0.5 size-4 shrink-0" />
            <CircleCheck v-else class="mt-0.5 size-4 shrink-0" />
            <p class="flex-1">{{ page.props.flash.error ?? page.props.flash.success }}</p>
            <button type="button" class="shrink-0 opacity-70 hover:opacity-100" aria-label="Fermer" @click="visible = false">
                <X class="size-4" />
            </button>
        </div>
    </div>
</template>
