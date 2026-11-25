<script setup lang="ts">
import { fileTone, formatBytes } from '@/lib/content';
import { CloudUpload, RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * File picker with drag & drop. Checks type and size immediately (the server checks
 * again, on the real content). Shows the current file when editing.
 */
const props = defineProps<{
    current: { name: string; size: number; extension: string | null } | null;
    maxMb: number;
    extensions: string[];
    error?: string;
}>();

const file = defineModel<File | null>({ required: true });
const removeCurrent = defineModel<boolean>('remove', { default: false });

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const localError = ref<string | null>(null);

const accept = computed(() => props.extensions.map((e) => `.${e}`).join(','));
const extensionOf = (name: string) => name.split('.').pop()?.toUpperCase() ?? null;

function pick(selected: File | undefined) {
    localError.value = null;
    if (!selected) return;
    const ext = (selected.name.split('.').pop() ?? '').toLowerCase();
    if (!props.extensions.includes(ext)) {
        localError.value = `Ce type de fichier n'est pas accepté (${props.extensions.join(', ').toUpperCase()}).`;
        return;
    }
    if (selected.size > props.maxMb * 1024 * 1024) {
        localError.value = `Le fichier fait ${formatBytes(selected.size)} : la taille maximale est de ${props.maxMb} Mo.`;
        return;
    }
    file.value = selected;
    removeCurrent.value = false;
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    pick(event.dataTransfer?.files?.[0]);
}

function clear() {
    file.value = null;
    if (input.value) input.value.value = '';
}
</script>

<template>
    <div class="space-y-2">
        <!-- Chosen or current file -->
        <div v-if="file || (current && !removeCurrent)" class="animate-pop flex items-center gap-3 rounded-xl border bg-card p-3">
            <div
                :class="[
                    'flex size-11 shrink-0 items-center justify-center rounded-lg text-xs font-bold',
                    fileTone(file ? extensionOf(file.name) : (current?.extension ?? null)),
                ]"
            >
                {{ file ? extensionOf(file.name) : current?.extension }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">{{ file ? file.name : current?.name }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ formatBytes(file ? file.size : (current?.size ?? 0)) }} · {{ file ? 'nouveau fichier' : 'fichier actuel' }}
                </p>
            </div>
            <button
                type="button"
                class="press flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                @click="input?.click()"
            >
                <RotateCcw class="size-4" /> Remplacer
            </button>
            <button
                type="button"
                class="press flex size-10 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                aria-label="Retirer le fichier"
                @click="file ? clear() : (removeCurrent = true)"
            >
                <Trash2 class="size-4" />
            </button>
        </div>

        <!-- Drop zone -->
        <button
            v-else
            type="button"
            :class="[
                'press flex w-full flex-col items-center gap-2 rounded-2xl border-2 border-dashed px-4 py-8 text-center transition-colors duration-200',
                dragging
                    ? 'border-primary bg-primary/5'
                    : error || localError
                      ? 'border-destructive/60 bg-destructive/5'
                      : 'border-input hover:border-primary/50 hover:bg-muted/40',
            ]"
            @click="input?.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <span
                :class="[
                    'flex size-12 items-center justify-center rounded-full bg-secondary text-primary transition-transform',
                    dragging ? 'scale-110' : '',
                ]"
            >
                <CloudUpload class="size-6" />
            </span>
            <span class="font-medium">Touchez pour choisir un fichier</span>
            <span class="text-xs text-muted-foreground">ou glissez-le ici · {{ extensions.join(', ').toUpperCase() }} · {{ maxMb }} Mo max.</span>
        </button>

        <p
            v-if="current && removeCurrent && !file"
            class="flex items-center justify-between rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground"
        >
            Le fichier « {{ current.name }} » sera retiré.
            <button type="button" class="font-semibold text-primary hover:underline" @click="removeCurrent = false">Annuler</button>
        </p>

        <input
            ref="input"
            type="file"
            class="sr-only"
            :accept="accept"
            tabindex="-1"
            @change="pick(($event.target as HTMLInputElement).files?.[0])"
        />
        <p v-if="localError || error" class="text-sm text-destructive" data-field-error>{{ localError ?? error }}</p>
    </div>
</template>
