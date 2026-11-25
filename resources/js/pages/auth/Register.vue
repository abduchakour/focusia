<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import type { Role } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { GraduationCap, LoaderCircle, Presentation } from 'lucide-vue-next';

const form = useForm({
    role: '' as Role | '',
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const roles = [
    { value: 'student' as const, label: 'Je suis étudiant', icon: GraduationCap },
    { value: 'teacher' as const, label: 'Je suis enseignant', icon: Presentation },
];

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Créer un compte" description="Quelques informations suffisent">
        <Head title="Inscription" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Vous êtes…</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            v-for="role in roles"
                            :key="role.value"
                            :class="[
                                'flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 text-center text-sm font-medium transition-colors',
                                form.role === role.value ? 'border-primary bg-primary/5' : 'border-input hover:bg-muted',
                            ]"
                        >
                            <input type="radio" name="role" class="sr-only" :value="role.value" v-model="form.role" required />
                            <component :is="role.icon" class="size-7" />
                            {{ role.label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.role" />
                </fieldset>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">Prénom</Label>
                        <Input id="first_name" type="text" required autocomplete="given-name" v-model="form.first_name" class="h-11" />
                        <InputError :message="form.errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Nom</Label>
                        <Input id="last_name" type="text" required autocomplete="family-name" v-model="form.last_name" class="h-11" />
                        <InputError :message="form.errors.last_name" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="email">Adresse e-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autocomplete="email"
                        inputmode="email"
                        v-model="form.email"
                        placeholder="prenom.nom@exemple.dj"
                        class="h-11"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Mot de passe</Label>
                    <Input id="password" type="password" required autocomplete="new-password" v-model="form.password" class="h-11" />
                    <p class="text-xs text-muted-foreground">Au moins 8 caractères, avec des lettres et des chiffres.</p>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmer le mot de passe</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        class="h-11"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="h-11 w-full text-base" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Créer mon compte
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Déjà un compte ?
                <TextLink :href="route('login')">Se connecter</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
