<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import type { Role } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { GraduationCap, KeyRound, LoaderCircle, Presentation } from 'lucide-vue-next';

const form = useForm({
    // "Je suis enseignant" on the home page opens this form with ?role=teacher.
    role: (['student', 'teacher'].includes(new URLSearchParams(window.location.search).get('role') ?? '')
        ? new URLSearchParams(window.location.search).get('role')
        : '') as Role | '',
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    teacher_code: '',
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

                <!-- Teacher accounts: code given by the institution, so a student cannot become a teacher. -->
                <Transition name="banner">
                    <div v-if="form.role === 'teacher'" class="grid gap-2 rounded-xl border border-primary/25 bg-primary/5 p-4">
                        <Label for="teacher_code" class="flex items-center gap-1.5"><KeyRound class="size-4 text-primary" /> Code enseignant</Label>
                        <Input
                            id="teacher_code"
                            v-model="form.teacher_code"
                            class="h-11 font-mono uppercase tracking-widest placeholder:font-sans placeholder:normal-case placeholder:tracking-normal"
                            autocomplete="off"
                            autocapitalize="characters"
                            placeholder="Ex. PROF-XXXX"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Ce code est remis aux enseignants par l'administration de leur université ou institut.
                        </p>
                        <InputError :message="form.errors.teacher_code" />
                    </div>
                </Transition>

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
