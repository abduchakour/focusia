<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { localInputValue } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, LoaderCircle, UsersRound } from 'lucide-vue-next';

const props = defineProps<{
    assignment: {
        id: number;
        title: string;
        instructions: string;
        due_at: string;
        allow_late: boolean;
        material_id: number | null;
        group_ids: number[];
        submissions_count: number;
    } | null;
    groups: { id: number; name: string }[];
    materials: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devoirs', href: route('teacher.assignments.index') },
    { title: props.assignment ? 'Modifier' : 'Nouveau devoir', href: '#' },
];

const form = useForm({
    title: props.assignment?.title ?? '',
    instructions: props.assignment?.instructions ?? '',
    due_at: props.assignment?.due_at ?? localInputValue(7),
    allow_late: props.assignment?.allow_late ?? false,
    material_id: props.assignment?.material_id ? String(props.assignment.material_id) : '',
    group_ids: [...(props.assignment?.group_ids ?? (props.groups.length === 1 ? [props.groups[0].id] : []))] as number[],
});

const shortcuts = [
    { label: 'Dans 3 jours', days: 3 },
    { label: '1 semaine', days: 7 },
    { label: '2 semaines', days: 14 },
];
const materialOptions = [{ value: '', label: 'Aucun support' }, ...props.materials];

function toggleGroup(id: number) {
    const i = form.group_ids.indexOf(id);
    if (i >= 0) form.group_ids.splice(i, 1);
    else form.group_ids.push(id);
}

function submit() {
    form.transform((d) => ({ ...d, material_id: d.material_id === '' ? null : Number(d.material_id) }));
    if (props.assignment) form.put(route('teacher.assignments.update', { assignment: props.assignment.id }));
    else form.post(route('teacher.assignments.store'));
}
</script>

<template>
    <Head :title="assignment ? 'Modifier le devoir' : 'Nouveau devoir'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer class="max-w-3xl">
            <PageHeader
                :title="assignment ? 'Modifier le devoir' : 'Nouveau devoir'"
                description="Une consigne, une date limite, et un support de cours si besoin."
                :back-href="assignment ? route('teacher.assignments.show', { assignment: assignment.id }) : route('teacher.assignments.index')"
                back-label="Retour"
            />

            <form class="space-y-6" @submit.prevent="submit">
                <section class="animate-enter space-y-5 rounded-2xl border bg-card p-5">
                    <div class="grid gap-2">
                        <Label for="title">Titre</Label>
                        <Input id="title" v-model="form.title" class="h-11" placeholder="Ex. Devoir 2 – Tri à bulles" autofocus />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="instructions">Consigne</Label>
                        <textarea
                            id="instructions"
                            v-model="form.instructions"
                            rows="6"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
                            placeholder="Ce que les étudiants doivent faire et rendre…"
                        />
                        <InputError :message="form.errors.instructions" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="material">Support de cours lié <span class="font-normal text-muted-foreground">(facultatif)</span></Label>
                        <NativeSelect id="material" v-model="form.material_id" :options="materialOptions" />
                        <InputError :message="form.errors.material_id" />
                    </div>
                </section>

                <section class="animate-enter space-y-4 rounded-2xl border bg-card p-5" style="animation-delay: 0.08s">
                    <div class="grid gap-2">
                        <Label for="due_at">Date limite</Label>
                        <Input id="due_at" v-model="form.due_at" type="datetime-local" class="h-11" />
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="s in shortcuts"
                                :key="s.days"
                                type="button"
                                class="press h-9 rounded-full border px-3 text-sm hover:bg-muted"
                                @click="form.due_at = localInputValue(s.days)"
                            >
                                {{ s.label }}
                            </button>
                        </div>
                        <InputError :message="form.errors.due_at" />
                    </div>

                    <!-- Rule 15 -->
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors hover:bg-muted/40">
                        <input v-model="form.allow_late" type="checkbox" class="mt-0.5 size-5 rounded border-input text-primary focus:ring-ring" />
                        <span>
                            <span class="block text-sm font-medium">Accepter les rendus en retard</span>
                            <span class="block text-xs text-muted-foreground"
                                >Ils seront signalés « en retard ». Sinon, plus aucun rendu après la date limite.</span
                            >
                        </span>
                    </label>
                </section>

                <section class="animate-enter space-y-3 rounded-2xl border bg-card p-5" style="animation-delay: 0.16s">
                    <h2 class="font-semibold">Groupes</h2>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="g in groups"
                            :key="g.id"
                            type="button"
                            :aria-pressed="form.group_ids.includes(g.id)"
                            :class="[
                                'press inline-flex h-11 items-center gap-2 rounded-xl border px-3 text-sm font-medium transition-colors',
                                form.group_ids.includes(g.id)
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'hover:border-primary/40 hover:bg-muted',
                            ]"
                            @click="toggleGroup(g.id)"
                        >
                            <Check v-if="form.group_ids.includes(g.id)" class="animate-pop size-4" />
                            <UsersRound v-else class="size-4 text-muted-foreground" />
                            {{ g.name }}
                        </button>
                    </div>
                    <InputError :message="form.errors.group_ids ?? Object.entries(form.errors).find(([k]) => k.startsWith('group_ids.'))?.[1]" />
                </section>

                <div
                    class="sticky bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-20 flex items-center gap-3 rounded-2xl border bg-background/95 p-3 shadow-lg backdrop-blur md:bottom-4"
                >
                    <p class="flex-1 text-sm text-muted-foreground">
                        <template v-if="assignment?.submissions_count">{{ assignment.submissions_count }} rendu(s) déjà reçu(s)</template>
                        <template v-else>{{ form.group_ids.length }} groupe(s)</template>
                    </p>
                    <Button type="submit" class="h-11 px-6 shadow-md shadow-primary/25" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        {{ assignment ? 'Enregistrer' : 'Publier le devoir' }}
                    </Button>
                </div>
            </form>
        </PageContainer>
    </AppLayout>
</template>
