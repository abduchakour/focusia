<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Check, Copy } from 'lucide-vue-next';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        text: string;
        label?: string;
        copiedLabel?: string;
        variant?: 'default' | 'outline' | 'secondary' | 'ghost';
    }>(),
    { label: 'Copier', copiedLabel: 'Copié !', variant: 'outline' },
);

const copied = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

async function copy() {
    try {
        await navigator.clipboard.writeText(props.text);
    } catch {
        // Older browsers / non-HTTPS: fallback through a hidden textarea.
        const area = document.createElement('textarea');
        area.value = props.text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Button type="button" :variant="variant" class="h-11" @click="copy">
        <Transition
            mode="out-in"
            enter-from-class="scale-50 opacity-0"
            enter-active-class="transition duration-200"
            leave-active-class="transition duration-100"
            leave-to-class="scale-50 opacity-0"
        >
            <Check v-if="copied" class="text-success" />
            <Copy v-else />
        </Transition>
        <span>{{ copied ? copiedLabel : label }}</span>
    </Button>
</template>
