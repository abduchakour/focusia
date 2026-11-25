<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

/** Create a notion without leaving the quiz editor (the editor keeps its state). */
const props = defineProps<{ subject: string }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ created: [name: string] }>();

const name = ref('');
const error = ref<string | undefined>();
const processing = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        name.value = '';
        error.value = undefined;
    }
});

function submit() {
    processing.value = true;
    const created = name.value.trim();
    router.post(
        route('teacher.notions.store'),
        { subject: props.subject, name: created },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                emit('created', created);
            },
            onError: (errors) => (error.value = errors.name ?? errors.subject),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Nouvelle notion</DialogTitle>
                    <DialogDescription>
                        Matière : <strong>{{ subject || '—' }}</strong
                        >. Elle sera réutilisable dans tous vos quiz de cette matière.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="notion-name">Nom de la notion</Label>
                    <Input id="notion-name" v-model="name" class="h-11" placeholder="Ex. : Boucles" required autofocus />
                    <InputError :message="error" />
                </div>
                <DialogFooter>
                    <Button type="submit" class="h-11 w-full sm:w-auto" :disabled="processing || !name.trim() || !subject">
                        <LoaderCircle v-if="processing" class="animate-spin" />
                        Ajouter la notion
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
