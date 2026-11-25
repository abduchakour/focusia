<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpenCheck, ChartColumnBig, FolderOpen, TriangleAlert, WifiOff } from 'lucide-vue-next';

const page = usePage<SharedData>();

const features = [
    { icon: BookOpenCheck, title: 'Quiz corrigés automatiquement', text: 'Note sur 20 immédiate, corrections publiées à la date choisie.' },
    { icon: ChartColumnBig, title: 'Compréhension notion par notion', text: 'Repérez en un coup d’œil ce qui n’est pas compris, et qui décroche.' },
    { icon: WifiOff, title: 'Même sans bonne connexion', text: 'Les étudiants répondent hors ligne ; tout se synchronise ensuite.' },
    { icon: FolderOpen, title: 'Tout au même endroit', text: 'Supports, devoirs et bibliothèque, au lieu de WhatsApp et des clés USB.' },
];

// Illustrative preview of the per-notion view (static, decorative).
const preview = [
    { notion: 'Variables', rate: 86 },
    { notion: 'Conditions', rate: 64 },
    { notion: 'Tableaux', rate: 62 },
    { notion: 'Boucles', rate: 46 },
];
</script>

<template>
    <Head title="Bienvenue" />
    <div class="min-h-screen overflow-x-hidden bg-background">
        <header class="mx-auto flex w-full max-w-6xl items-center justify-between p-4 sm:p-6">
            <span class="flex items-center gap-2">
                <span class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                    ><AppLogoIcon class="size-5"
                /></span>
                <span class="text-xl font-bold tracking-tight">Focus<span class="text-primary">IA</span></span>
            </span>
            <nav class="flex gap-2">
                <Button v-if="page.props.auth.user" as-child>
                    <Link :href="route('dashboard')">Mon espace</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child><Link :href="route('login')">Connexion</Link></Button>
                    <Button as-child class="hidden sm:inline-flex"><Link :href="route('register')">Inscription</Link></Button>
                </template>
            </nav>
        </header>

        <main>
            <section class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 pb-16 pt-8 sm:px-6 lg:grid-cols-2 lg:pt-16">
                <div class="pointer-events-none absolute -top-24 right-0 size-[30rem] rounded-full bg-primary/10 blur-3xl" />

                <div class="relative space-y-6">
                    <p
                        class="animate-stagger inline-flex items-center gap-2 rounded-full bg-secondary px-3 py-1 text-sm font-medium text-secondary-foreground"
                        style="--i: 0"
                    >
                        Pour l'enseignement supérieur à Djibouti
                    </p>
                    <h1 class="animate-stagger text-4xl font-bold leading-tight tracking-tight sm:text-5xl" style="--i: 1">
                        Suivez la compréhension de chaque étudiant, <span class="text-primary">notion par notion.</span>
                    </h1>
                    <p class="animate-stagger max-w-xl text-lg text-muted-foreground" style="--i: 2">
                        Créez un groupe et un quiz en moins de 5 minutes. FocusIA corrige, calcule la note sur 20 et vous montre où vos étudiants
                        bloquent.
                    </p>
                    <div class="animate-stagger flex flex-col gap-3 sm:flex-row" style="--i: 3">
                        <Button as-child size="lg" class="h-12 text-base shadow-lg shadow-primary/25">
                            <Link :href="route('register')">Créer un compte gratuit</Link>
                        </Button>
                        <Button as-child size="lg" variant="outline" class="h-12 text-base">
                            <Link :href="route('login')">J'ai déjà un compte</Link>
                        </Button>
                    </div>
                </div>

                <!-- Decorative preview card -->
                <div class="animate-stagger relative" style="--i: 3">
                    <div class="rounded-2xl border bg-card p-6 shadow-2xl shadow-primary/10">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground">L1 Informatique – TD1</p>
                                <p class="font-semibold">Taux de réussite par notion</p>
                            </div>
                            <span class="rounded-full bg-success/10 px-3 py-1 text-sm font-semibold text-success">12,3 / 20</span>
                        </div>
                        <ul class="space-y-4">
                            <li v-for="(row, i) in preview" :key="row.notion" class="space-y-1.5">
                                <div class="flex justify-between text-sm">
                                    <span class="font-medium">{{ row.notion }}</span>
                                    <span :class="row.rate < 50 ? 'font-semibold text-destructive' : 'text-muted-foreground'">{{ row.rate }} %</span>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="bar-grow h-full rounded-full"
                                        :class="row.rate < 50 ? 'bg-destructive' : row.rate < 70 ? 'bg-warning' : 'bg-success'"
                                        :style="{ width: `${row.rate}%`, animationDelay: `${400 + i * 120}ms` }"
                                    />
                                </div>
                            </li>
                        </ul>
                        <p class="mt-5 flex items-center gap-2 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                            <TriangleAlert class="size-4 shrink-0" /> « Boucles » à retravailler avant l'examen
                        </p>
                    </div>
                </div>
            </section>

            <section class="bg-secondary/40 py-16">
                <div class="mx-auto grid max-w-6xl gap-4 px-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                    <div
                        v-for="(feature, i) in features"
                        :key="feature.title"
                        class="animate-stagger lift rounded-2xl border bg-card p-6"
                        :style="{ '--i': i + 4 }"
                    >
                        <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <component :is="feature.icon" class="size-6" />
                        </div>
                        <h2 class="mb-1 font-semibold">{{ feature.title }}</h2>
                        <p class="text-sm text-muted-foreground">{{ feature.text }}</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="py-8 text-center text-sm text-muted-foreground">FocusIA — Djibouti</footer>
    </div>
</template>

<style scoped>
.bar-grow {
    transform-origin: left;
    animation: bar-grow 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes bar-grow {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}
</style>
