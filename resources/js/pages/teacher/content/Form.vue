<script setup lang="ts">
import FileDrop from '@/components/content/FileDrop.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { kindText, type ContentItem, type ContentKind } from '@/lib/content';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Link2, LoaderCircle, Paperclip, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    kind: ContentKind;
    item: ContentItem | null;
    types: { value: string; label: string }[];
    groups: { id: number; name: string; subject: string; level: string }[];
    levels: { value: string; label: string }[];
    limits: { max_mb: number; extensions: string[] };
}>();

const text = kindText[props.kind];
const isResource = props.kind === 'ressource';
const routes = isResource
    ? { index: 'teacher.resources.index', store: 'teacher.resources.store', update: 'teacher.resources.update', param: 'resource' }
    : { index: 'teacher.materials.index', store: 'teacher.materials.store', update: 'teacher.materials.update', param: 'material' };

const breadcrumbs: BreadcrumbItem[] = [
    { title: text.title, href: route(routes.index) },
    { title: props.item ? 'Modifier' : text.add, href: '#' },
];

type ContentForm = {
    title: string;
    description: string;
    type: string;
    subject: string;
    level: string;
    url: string;
    file: File | null;
    remove_file: boolean;
    group_ids: number[];
};

const form = useForm<ContentForm>({
    title: props.item?.title ?? '',
    description: props.item?.description ?? '',
    type: props.item?.type ?? props.types[0].value,
    subject: props.item?.subject ?? props.groups[0]?.subject ?? '',
    level: props.item?.level ?? props.groups[0]?.level ?? 'L1',
    url: props.item?.url ?? '',
    file: null,
    remove_file: false,
    group_ids: props.item?.groups.map((g) => g.id) ?? [],
});

function toggleGroup(id: number) {
    const i = form.group_ids.indexOf(id);
    if (i >= 0) form.group_ids.splice(i, 1);
    else {
        form.group_ids.push(id);
        // The first chosen group suggests the subject and level of a library resource.
        if (isResource && !props.item && form.group_ids.length === 1) {
            const g = props.groups.find((x) => x.id === id);
            if (g) Object.assign(form, { subject: g.subject, level: g.level });
        }
    }
}

const hasSource = computed(() => form.file !== null || (props.item?.file && !form.remove_file) || form.url.trim() !== '');

function submit() {
    const options = { forceFormData: true, preserveScroll: true };
    if (props.item) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(route(routes.update, { [routes.param]: props.item.id }), options);
    } else {
        form.post(route(routes.store), options);
    }
}
</script>

<template>
    <Head :title="item ? `Modifier – ${item.title}` : text.add" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer class="max-w-3xl">
            <PageHeader
                :title="item ? `Modifier ${text.one === 'support' ? 'le support' : 'la ressource'}` : text.add"
                :description="text.teacherIntro"
                :back-href="route(routes.index)"
                :back-label="text.title"
            />

            <form class="space-y-6" @submit.prevent="submit">
                <section class="animate-enter space-y-5 rounded-2xl border bg-card p-5">
                    <div class="grid gap-2">
                        <Label for="title">Titre</Label>
                        <Input id="title" v-model="form.title" class="h-11" placeholder="Ex. Cours 2 – Les boucles" autofocus />
                        <InputError :message="form.errors.title" />
                    </div>

                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">Type</legend>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="t in types"
                                :key="t.value"
                                type="button"
                                :aria-pressed="form.type === t.value"
                                :class="[
                                    'press h-10 rounded-full border px-4 text-sm font-medium transition-colors',
                                    form.type === t.value
                                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                        : 'hover:border-primary/40 hover:bg-muted',
                                ]"
                                @click="form.type = t.value"
                            >
                                {{ t.label }}
                            </button>
                        </div>
                    </fieldset>

                    <div v-if="isResource" class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="subject">Matière</Label>
                            <Input id="subject" v-model="form.subject" class="h-11" placeholder="Ex. Algorithmique" />
                            <InputError :message="form.errors.subject" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="level">Niveau</Label>
                            <NativeSelect id="level" v-model="form.level" :options="levels" />
                            <InputError :message="form.errors.level" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Description <span class="font-normal text-muted-foreground">(facultatif)</span></Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
                            placeholder="Ce que contient ce document, comment l'utiliser…"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                </section>

                <!-- Rule 10: a file, a link, or both -->
                <section class="animate-enter space-y-5 rounded-2xl border bg-card p-5" style="animation-delay: 0.08s">
                    <div>
                        <h2 class="font-semibold">Contenu</h2>
                        <p class="text-sm text-muted-foreground">Un fichier, un lien, ou les deux.</p>
                    </div>

                    <div class="grid gap-2">
                        <Label class="flex items-center gap-1.5"><Paperclip class="size-4" /> Fichier</Label>
                        <FileDrop
                            v-model="form.file"
                            v-model:remove="form.remove_file"
                            :current="item?.file ?? null"
                            :max-mb="limits.max_mb"
                            :extensions="limits.extensions"
                            :error="form.errors.file"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="url" class="flex items-center gap-1.5"><Link2 class="size-4" /> Lien</Label>
                        <Input id="url" v-model="form.url" type="url" inputmode="url" class="h-11" placeholder="https://…" />
                        <InputError :message="form.errors.url" />
                    </div>
                </section>

                <!-- Rule 11: several groups, never twice the same -->
                <section class="animate-enter space-y-3 rounded-2xl border bg-card p-5" style="animation-delay: 0.16s">
                    <div>
                        <h2 class="font-semibold">Publier dans les groupes</h2>
                        <p class="text-sm text-muted-foreground">
                            Sans groupe, {{ isResource ? 'la ressource reste' : 'le support reste' }} visible par vous seul.
                        </p>
                    </div>
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
                        <p v-if="groups.length === 0" class="text-sm text-muted-foreground">Créez d'abord un groupe pour publier.</p>
                    </div>
                    <InputError :message="form.errors.group_ids ?? Object.entries(form.errors).find(([k]) => k.startsWith('group_ids.'))?.[1]" />
                </section>

                <!-- Sticky actions -->
                <div
                    class="sticky bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-20 overflow-hidden rounded-2xl border bg-background/95 shadow-lg backdrop-blur md:bottom-4"
                >
                    <div v-if="form.progress" class="h-1 bg-muted">
                        <div class="h-full bg-primary transition-[width] duration-200" :style="{ width: `${form.progress.percentage ?? 0}%` }" />
                    </div>
                    <div class="flex items-center gap-3 p-3">
                        <p :class="['flex-1 text-sm', !hasSource && !form.progress ? 'text-warning' : 'text-muted-foreground']">
                            <template v-if="form.progress">Envoi… {{ form.progress.percentage }} %</template>
                            <template v-else-if="!hasSource">Ajoutez un fichier ou un lien.</template>
                            <template v-else>{{ form.group_ids.length ? `Publié dans ${form.group_ids.length} groupe(s)` : 'Non publié' }}</template>
                        </p>
                        <Button type="submit" class="h-11 px-6 shadow-md shadow-primary/25" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="animate-spin" />
                            {{ item ? 'Enregistrer' : form.group_ids.length ? 'Publier' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>
            </form>
        </PageContainer>
    </AppLayout>
</template>
