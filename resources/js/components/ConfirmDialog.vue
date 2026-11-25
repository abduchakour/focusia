<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { LoaderCircle } from 'lucide-vue-next';

/** Confirmation before a sensitive action (delete, remove, regenerate…). */
defineProps<{
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    processing?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="outline" class="h-11">Annuler</Button>
                </DialogClose>
                <Button :variant="destructive ? 'destructive' : 'default'" class="h-11" :disabled="processing" @click="emit('confirm')">
                    <LoaderCircle v-if="processing" class="animate-spin" />
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
