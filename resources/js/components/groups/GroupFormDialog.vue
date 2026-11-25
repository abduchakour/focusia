<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { watch } from 'vue';

/** Create a group (no `group`) or edit one. Kept short so a group is ready in seconds. */
const props = defineProps<{
    levels: { value: string; label: string }[];
    group?: { id: number; name: string; subject: string; level: string };
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: props.group?.name ?? '',
    subject: props.group?.subject ?? '',
    level: props.group?.level ?? '',
});

watch(open, (isOpen) => {
    if (isOpen && !props.group) form.reset();
    form.clearErrors();
});

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (props.group) {
        form.put(route('teacher.groups.update', { group: props.group.id }), options);
    } else {
        form.post(route('teacher.groups.store'), options);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ group ? 'Modifier le groupe' : 'Nouveau groupe' }}</DialogTitle>
                    <DialogDescription>
                        {{ group ? 'Le code d’accès ne change pas.' : 'Un code d’accès sera créé automatiquement pour vos étudiants.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="group-name">Nom du groupe</Label>
                    <Input id="group-name" v-model="form.name" required class="h-11" placeholder="Ex. : L1 Informatique – TD2" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-[1fr_9rem]">
                    <div class="grid gap-2">
                        <Label for="group-subject">Matière</Label>
                        <Input id="group-subject" v-model="form.subject" required class="h-11" placeholder="Ex. : Algorithmique" />
                        <InputError :message="form.errors.subject" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="group-level">Niveau</Label>
                        <NativeSelect id="group-level" v-model="form.level" :options="levels" placeholder="Choisir" />
                        <InputError :message="form.errors.level" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="submit" class="h-11 w-full sm:w-auto" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        {{ group ? 'Enregistrer' : 'Créer le groupe' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
