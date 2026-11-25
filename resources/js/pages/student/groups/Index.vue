<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { GraduationCap, LoaderCircle, LogIn, UsersRound } from 'lucide-vue-next';

const props = defineProps<{
    groups: { id: number; name: string; subject: string; level: string; teacher: string }[];
    code: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mes groupes', href: route('student.groups.index') }];

const form = useForm({ code: props.code });

const join = () => {
    form.post(route('student.groups.join'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Mes groupes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader title="Mes groupes" description="Rejoignez un groupe avec le code donné par votre enseignant." />

            <form class="animate-stagger rounded-2xl border bg-card p-5 shadow-sm" style="--i: 1" @submit.prevent="join">
                <label for="code" class="mb-3 block font-semibold">Rejoindre un groupe</label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input
                        id="code"
                        v-model="form.code"
                        maxlength="7"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        placeholder="ABC123"
                        aria-describedby="code-help"
                        class="h-14 flex-1 rounded-xl border border-input bg-background px-4 text-center font-mono text-2xl font-bold uppercase tracking-[0.4em] shadow-sm placeholder:text-muted-foreground/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <Button type="submit" class="h-14 px-6 text-base" :disabled="form.processing || form.code.trim().length < 6">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        <LogIn v-else />
                        Rejoindre
                    </Button>
                </div>
                <p id="code-help" class="mt-2 text-xs text-muted-foreground">6 caractères, majuscules ou minuscules.</p>
                <InputError :message="form.errors.code" class="mt-2" />
            </form>

            <EmptyState
                v-if="groups.length === 0"
                :icon="UsersRound"
                title="Vous n'êtes inscrit dans aucun groupe"
                description="Demandez le code d'accès à votre enseignant, puis saisissez-le ci-dessus."
            />

            <ul v-else class="grid gap-4 sm:grid-cols-2">
                <li
                    v-for="(group, i) in groups"
                    :key="group.id"
                    class="animate-stagger lift flex items-center gap-4 rounded-2xl border bg-card p-5"
                    :style="{ '--i': i + 2 }"
                >
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <GraduationCap class="size-6" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold">{{ group.name }}</h2>
                        <p class="truncate text-sm text-muted-foreground">{{ group.subject }} · {{ group.level }}</p>
                        <p class="truncate text-sm text-muted-foreground">{{ group.teacher }}</p>
                    </div>
                </li>
            </ul>
        </PageContainer>
    </AppLayout>
</template>
