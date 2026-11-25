<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { vReveal } from '@/directives/reveal';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenCheck,
    ChartColumnBig,
    Check,
    ClipboardList,
    CloudUpload,
    FileText,
    GraduationCap,
    Library,
    LifeBuoy,
    Plane,
    Presentation,
    ShieldCheck,
    Smartphone,
    Sparkles,
    Target,
    UsersRound,
    WifiOff,
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{ heroPhoto?: string | null }>();
const page = usePage<SharedData>();

/** Free-licensed photos (Wikimedia Commons); credits are displayed in the footer. */
const photos = [
    {
        src: '/images/accueil/universite.webp',
        alt: "Entrée de l'Université de Djibouti",
        caption: 'Université de Djibouti',
        author: 'Abass Chirdon',
        license: 'CC BY 3.0',
        licenseUrl: 'https://creativecommons.org/licenses/by/3.0/deed.fr',
        source: 'https://commons.wikimedia.org/wiki/File:Universit%C3%A9_de_Djibouti,_Djibouti_-_panoramio.jpg',
    },
    {
        src: '/images/accueil/menelik.webp',
        alt: 'Place Ménélik à Djibouti-ville, façades à arcades',
        caption: 'Place Ménélik, Djibouti-ville',
        author: 'Francisco Anzola',
        license: 'CC BY 2.0',
        licenseUrl: 'https://creativecommons.org/licenses/by/2.0/deed.fr',
        source: 'https://commons.wikimedia.org/wiki/File:Place_Menelik_in_Djibouti_city_(25058480721).jpg',
    },
    {
        src: '/images/accueil/palais.webp',
        alt: 'Le Palais du Peuple à Djibouti-ville',
        caption: 'Palais du Peuple, Djibouti-ville',
        author: 'Skilla1st',
        license: 'CC BY-SA 4.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr',
        source: "https://commons.wikimedia.org/wiki/File:The_People's_Palace,_Djibouti_City.jpg",
    },
    {
        src: '/images/accueil/maskali.webp',
        alt: "Plage et eau turquoise de l'île Maskali",
        caption: 'Île Maskali',
        author: 'Clovis conchou',
        license: 'CC BY-SA 4.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr',
        source: 'https://commons.wikimedia.org/wiki/File:%C3%8Ele_maskali,_Djibouti.jpg',
    },
];

const steps = [
    {
        icon: Presentation,
        title: "L'enseignant crée un quiz",
        text: 'Quatre propositions par question, chaque question liée à une notion du cours. Cinq minutes suffisent.',
    },
    {
        icon: Smartphone,
        title: 'Les étudiants répondent',
        text: 'Sur leur téléphone, même si le réseau coupe en cours de route. La note sur 20 arrive tout de suite.',
    },
    {
        icon: ChartColumnBig,
        title: 'La compréhension apparaît',
        text: "Notion par notion : ce qui est acquis, ce qu'il faut réexpliquer, et qui a besoin d'aide.",
    },
];

const features = [
    {
        icon: BookOpenCheck,
        title: 'Quiz corrigés automatiquement',
        text: 'Note sur 20 immédiate ; les corrections sont publiées à la date choisie par l’enseignant.',
    },
    { icon: Target, title: 'Suivi par notion', text: 'Carte de compréhension du groupe et erreurs fréquentes, question par question.' },
    { icon: LifeBuoy, title: 'Étudiants en difficulté', text: 'Repérés automatiquement, avec la raison : moyenne basse ou notion non acquise.' },
    {
        icon: ClipboardList,
        title: 'Devoirs notés sur 20',
        text: 'Rendu en fichier ou en texte, retards signalés, note et commentaire de l’enseignant.',
    },
    { icon: FileText, title: 'Supports de cours', text: 'PDF, Word, PowerPoint ou liens, publiés dans les bons groupes, téléchargés en sécurité.' },
    { icon: Library, title: 'Bibliothèque', text: 'Vidéos, exercices et documents classés par matière et par niveau, de la L1 au M2.' },
];

// Hero: the phone goes offline and comes back, in a loop (illustration only).
const offline = ref(false);
let timer: number | undefined;
onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    timer = window.setInterval(() => (offline.value = !offline.value), 3200);
});
onUnmounted(() => window.clearInterval(timer));

const year = new Date().getFullYear();
</script>

<template>
    <Head title="Apprendre, réviser, progresser" />

    <div class="min-h-screen overflow-x-hidden bg-background text-foreground">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b border-transparent bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/70">
            <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <Link :href="route('home')" class="flex items-center gap-2" aria-label="FocusIA, accueil">
                    <span
                        class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#0b2a6b] via-primary to-[#6d3fd6] text-white shadow-md"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span class="text-xl font-extrabold tracking-tight text-[#0b2a6b] dark:text-white"
                        >Focus<span class="text-primary">IA</span></span
                    >
                </Link>
                <nav class="hidden items-center gap-6 text-sm font-medium text-muted-foreground md:flex" aria-label="Sections">
                    <a href="#fonctionnement" class="transition-colors hover:text-foreground">Fonctionnement</a>
                    <a href="#fonctionnalites" class="transition-colors hover:text-foreground">Fonctionnalités</a>
                    <a href="#hors-ligne" class="transition-colors hover:text-foreground">Hors ligne</a>
                    <a href="#djibouti" class="transition-colors hover:text-foreground">Djibouti</a>
                </nav>
                <div class="flex items-center gap-2">
                    <Button v-if="page.props.auth.user" as-child class="h-10">
                        <Link :href="route('dashboard')">Mon espace <ArrowRight /></Link>
                    </Button>
                    <template v-else>
                        <Button as-child variant="ghost" class="h-10"><Link :href="route('login')">Se connecter</Link></Button>
                        <Button as-child class="hidden h-10 shadow-md shadow-primary/25 sm:inline-flex"
                            ><Link :href="route('register')">Créer un compte</Link></Button
                        >
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative">
            <div class="pointer-events-none absolute inset-0 -z-0 overflow-hidden" aria-hidden="true">
                <div class="animate-blob absolute -left-24 -top-24 size-96 rounded-full bg-primary/20 blur-3xl" />
                <div class="animate-blob absolute -right-20 top-40 size-96 rounded-full bg-[#6d3fd6]/15 blur-3xl" style="animation-delay: -5s" />
                <div class="animate-blob absolute bottom-0 left-1/3 size-72 rounded-full bg-amber-300/20 blur-3xl" style="animation-delay: -9s" />
            </div>

            <div
                class="relative mx-auto grid w-full max-w-6xl items-center gap-12 px-4 pb-16 pt-10 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:pb-24 lg:pt-16"
            >
                <div class="space-y-6">
                    <p
                        class="animate-enter inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1.5 text-sm font-medium shadow-sm"
                    >
                        <!-- Flag drawn in SVG: Windows does not display flag emojis. -->
                        <svg viewBox="0 0 30 20" class="h-3.5 w-auto rounded-sm shadow-sm" aria-hidden="true">
                            <rect width="30" height="10" fill="#6ab2e7" />
                            <rect y="10" width="30" height="10" fill="#12ad2b" />
                            <path d="M0 0 L15 10 L0 20 Z" fill="#fff" />
                            <path d="M5 7.4 L5.65 9.3 H7.6 L6 10.5 L6.6 12.4 L5 11.2 L3.4 12.4 L4 10.5 L2.4 9.3 H4.35 Z" fill="#d7141a" />
                        </svg>
                        Pour les universités et instituts de Djibouti, publics et privés
                    </p>
                    <h1
                        class="animate-enter text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl"
                        style="animation-delay: 0.08s"
                    >
                        Apprendre, réviser,
                        <span class="text-brand-gradient">progresser</span>
                        <span class="block text-3xl font-bold text-muted-foreground sm:text-4xl lg:text-[2.6rem]">même quand le réseau coupe.</span>
                    </h1>
                    <p class="animate-enter max-w-xl text-lg text-muted-foreground" style="animation-delay: 0.16s">
                        FocusIA transforme chaque quiz en <strong class="text-foreground">diagnostic notion par notion</strong> : l'enseignant voit ce
                        qui est compris et qui décroche ; l'étudiant sait quoi réviser. Pensé pour les téléphones Android et les connexions
                        intermittentes.
                    </p>
                    <div class="animate-enter flex flex-col gap-3 sm:flex-row" style="animation-delay: 0.24s">
                        <Button v-if="page.props.auth.user" as-child class="h-12 px-6 text-base shadow-lg shadow-primary/30">
                            <Link :href="route('dashboard')">Ouvrir mon espace <ArrowRight /></Link>
                        </Button>
                        <template v-else>
                            <Button as-child class="h-12 px-6 text-base shadow-lg shadow-primary/30">
                                <Link :href="route('register', { role: 'student' })"><GraduationCap /> Je suis étudiant</Link>
                            </Button>
                            <Button as-child variant="outline" class="h-12 bg-background/80 px-6 text-base">
                                <Link :href="route('register', { role: 'teacher' })"><Presentation /> Je suis enseignant</Link>
                            </Button>
                        </template>
                    </div>
                    <ul class="animate-enter flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground" style="animation-delay: 0.32s">
                        <li
                            v-for="item in ['Fonctionne hors ligne', 'Note sur 20 immédiate', '100 % en français']"
                            :key="item"
                            class="flex items-center gap-1.5"
                        >
                            <span class="flex size-5 items-center justify-center rounded-full bg-success/15 text-success"
                                ><Check class="size-3.5"
                            /></span>
                            {{ item }}
                        </li>
                    </ul>
                </div>

                <!-- Visual: university photo + floating cards -->
                <div class="animate-enter relative mx-auto w-full max-w-lg" style="animation-delay: 0.2s">
                    <div class="relative overflow-hidden rounded-[2rem] border-4 border-background shadow-2xl shadow-primary/20">
                        <img
                            :src="props.heroPhoto ?? photos[0].src"
                            :alt="props.heroPhoto ? 'Campus de l’Université de Djibouti' : photos[0].alt"
                            width="1400"
                            height="960"
                            fetchpriority="high"
                            class="animate-kenburns aspect-[4/3] w-full object-cover"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0b2a6b]/70 via-transparent to-transparent" />
                        <p class="absolute bottom-4 left-4 text-sm font-medium text-white/90">
                            {{ props.heroPhoto ? "Campus de l'Université de Djibouti" : photos[0].caption }}
                        </p>
                    </div>

                    <!-- Grade card -->
                    <div
                        class="animate-float absolute -left-4 top-6 w-44 rounded-2xl border bg-background/95 p-3 shadow-xl backdrop-blur sm:-left-10"
                    >
                        <p class="text-xs text-muted-foreground">Quiz 3 · Tableaux</p>
                        <p class="mt-1 text-3xl font-extrabold tabular-nums text-success">
                            15,5<span class="text-sm font-medium text-muted-foreground">/20</span>
                        </p>
                        <p class="text-xs font-medium">🎉 Très bien !</p>
                    </div>

                    <!-- Notion card -->
                    <div
                        class="animate-float absolute -right-3 bottom-16 w-52 rounded-2xl border bg-background/95 p-3 shadow-xl backdrop-blur sm:-right-8"
                        style="animation-delay: -2s"
                    >
                        <p class="mb-2 text-xs font-semibold">Compréhension du groupe</p>
                        <div
                            v-for="(n, i) in [
                                { name: 'Variables', rate: 86 },
                                { name: 'Boucles', rate: 39 },
                            ]"
                            :key="n.name"
                            class="mb-1.5"
                        >
                            <div class="flex justify-between text-[11px]">
                                <span>{{ n.name }}</span
                                ><span :class="n.rate < 50 ? 'font-semibold text-warning' : ''">{{ n.rate }} %</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-muted">
                                <div
                                    :class="['bar-grow h-full rounded-full', n.rate < 50 ? 'bg-warning' : 'bg-primary']"
                                    :style="{ width: `${n.rate}%`, animationDelay: `${0.8 + i * 0.2}s` }"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Offline pill -->
                    <div
                        :class="[
                            'absolute -bottom-5 left-1/2 flex -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold shadow-lg transition-colors duration-500',
                            offline ? 'bg-warning text-warning-foreground' : 'bg-success text-success-foreground',
                        ]"
                        aria-hidden="true"
                    >
                        <Transition name="banner" mode="out-in">
                            <span v-if="offline" key="off" class="flex items-center gap-2"
                                ><WifiOff class="size-4" /> Hors ligne · réponses gardées</span
                            >
                            <span v-else key="on" class="flex items-center gap-2"><CloudUpload class="size-4" /> En ligne · réponses envoyées</span>
                        </Transition>
                    </div>
                </div>
            </div>
        </section>

        <!-- Photo strip -->
        <section id="djibouti" class="scroll-mt-20 border-y bg-muted/30 py-10" aria-label="Djibouti en images">
            <div class="mx-auto mb-6 max-w-6xl px-4 sm:px-6" v-reveal>
                <p class="text-sm font-semibold uppercase tracking-widest text-primary">Pensé à Djibouti, pour Djibouti</p>
                <h2 class="mt-1 text-2xl font-bold sm:text-3xl">De Djibouti-ville au campus, une plateforme adaptée au terrain</h2>
            </div>
            <div class="overflow-hidden">
                <ul class="animate-marquee flex w-max gap-4 px-4">
                    <li
                        v-for="(p, i) in [...photos, ...photos]"
                        :key="i"
                        class="group relative h-48 w-72 shrink-0 overflow-hidden rounded-2xl sm:h-56 sm:w-80"
                        :aria-hidden="i >= photos.length"
                    >
                        <img
                            :src="p.src"
                            :alt="i < photos.length ? p.alt : ''"
                            loading="lazy"
                            width="320"
                            height="224"
                            class="size-full object-cover transition-transform duration-700 group-hover:scale-110"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                        <p class="absolute bottom-3 left-3 text-sm font-semibold text-white">{{ p.caption }}</p>
                    </li>
                </ul>
            </div>
        </section>

        <!-- How it works -->
        <section id="fonctionnement" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6">
            <div class="mx-auto max-w-2xl text-center" v-reveal>
                <p class="text-sm font-semibold uppercase tracking-widest text-primary">Fonctionnement</p>
                <h2 class="mt-2 text-3xl font-bold sm:text-4xl">Trois étapes, aucun papier</h2>
            </div>
            <ol class="relative mt-12 grid gap-6 md:grid-cols-3">
                <li v-for="(s, i) in steps" :key="s.title" v-reveal="i * 120" class="lift relative rounded-3xl border bg-card p-6">
                    <span
                        class="absolute -top-4 left-6 flex size-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground shadow-md"
                        >{{ i + 1 }}</span
                    >
                    <span class="mb-4 mt-2 flex size-12 items-center justify-center rounded-2xl bg-secondary text-primary">
                        <component :is="s.icon" class="size-6" />
                    </span>
                    <h3 class="text-lg font-semibold">{{ s.title }}</h3>
                    <p class="mt-2 text-muted-foreground">{{ s.text }}</p>
                </li>
            </ol>
        </section>

        <!-- Features -->
        <section id="fonctionnalites" class="scroll-mt-20 bg-gradient-to-b from-muted/40 to-background py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center" v-reveal>
                    <p class="text-sm font-semibold uppercase tracking-widest text-primary">Fonctionnalités</p>
                    <h2 class="mt-2 text-3xl font-bold sm:text-4xl">Tout le suivi pédagogique au même endroit</h2>
                    <p class="mt-3 text-muted-foreground">Fini les copies perdues, les groupes WhatsApp et les clés USB.</p>
                </div>
                <ul class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="(f, i) in features" :key="f.title" v-reveal="(i % 3) * 100" class="lift group rounded-3xl border bg-card p-6">
                        <span
                            class="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-[#6d3fd6] text-white shadow-md shadow-primary/25 transition-transform duration-300 group-hover:-rotate-6 group-hover:scale-110"
                        >
                            <component :is="f.icon" class="size-6" />
                        </span>
                        <h3 class="mt-4 font-semibold">{{ f.title }}</h3>
                        <p class="mt-1.5 text-sm text-muted-foreground">{{ f.text }}</p>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Offline -->
        <section id="hors-ligne" class="mx-auto grid max-w-6xl scroll-mt-20 items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2">
            <div v-reveal class="space-y-5">
                <p class="text-sm font-semibold uppercase tracking-widest text-primary">Mode hors ligne</p>
                <h2 class="text-3xl font-bold sm:text-4xl">Le réseau coupe ? Le quiz continue.</h2>
                <p class="text-muted-foreground">
                    Une fois le quiz commencé, l'étudiant peut répondre sans connexion : chaque réponse est gardée sur le téléphone. Dès que le réseau
                    revient, tout part automatiquement et la note s'affiche.
                </p>
                <ul class="space-y-3">
                    <li
                        v-for="item in [
                            { icon: Plane, text: 'Mode avion, coupure, batterie à plat : aucune réponse perdue.' },
                            {
                                icon: ShieldCheck,
                                text: 'Correction sur le serveur : les bonnes réponses ne sont jamais sur le téléphone avant l’heure.',
                            },
                            { icon: Smartphone, text: 'S’installe comme une application, sans passer par un magasin d’applications.' },
                        ]"
                        :key="item.text"
                        class="flex gap-3"
                    >
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary"
                            ><component :is="item.icon" class="size-5"
                        /></span>
                        <span class="pt-1.5">{{ item.text }}</span>
                    </li>
                </ul>
            </div>

            <!-- Phone mock-up -->
            <div v-reveal="150" class="relative mx-auto">
                <div class="absolute inset-0 -z-10 rounded-full bg-gradient-to-br from-primary/25 to-[#6d3fd6]/20 blur-3xl" aria-hidden="true" />
                <div
                    class="w-64 rounded-[2.5rem] border-[10px] border-[#0b2a6b] bg-background p-4 shadow-2xl dark:border-slate-700"
                    aria-hidden="true"
                >
                    <div class="mx-auto mb-4 h-1.5 w-16 rounded-full bg-muted" />
                    <div class="flex items-center justify-between text-[11px] font-semibold">
                        <span>Quiz 4 – Révision</span>
                        <span class="rounded-full bg-muted px-2 py-0.5 font-mono">12:47</span>
                    </div>
                    <div class="mt-2 h-1 rounded-full bg-muted"><div class="h-full w-3/5 rounded-full bg-primary" /></div>
                    <div
                        :class="[
                            'mt-3 flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-[11px] font-medium transition-colors duration-500',
                            offline ? 'bg-warning/15 text-warning' : 'bg-success/10 text-success',
                        ]"
                    >
                        <WifiOff v-if="offline" class="size-3.5" /><CloudUpload v-else class="size-3.5" />
                        {{ offline ? 'Hors connexion · enregistré' : 'Connecté · synchronisé' }}
                    </div>
                    <p class="mt-3 text-sm font-semibold leading-snug">Combien de fois s'exécute « pour i de 1 à 5 » ?</p>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="(c, i) in ['4 fois', '5 fois', '6 fois', 'Une infinité']"
                            :key="c"
                            :class="[
                                'flex items-center gap-2 rounded-xl border-2 p-2 text-xs',
                                i === 1 ? 'border-primary bg-primary/5' : 'border-border',
                            ]"
                        >
                            <span
                                :class="[
                                    'flex size-6 items-center justify-center rounded-lg text-[10px] font-bold',
                                    i === 1 ? 'bg-primary text-primary-foreground' : 'bg-muted',
                                ]"
                            >
                                <Check v-if="i === 1" class="size-3.5" /><template v-else>{{ 'ABCD'[i] }}</template>
                            </span>
                            {{ c }}
                        </div>
                    </div>
                    <div class="mt-4 rounded-xl bg-primary py-2 text-center text-xs font-semibold text-primary-foreground">Suivant</div>
                </div>
            </div>
        </section>

        <!-- Audiences -->
        <section class="mx-auto grid max-w-6xl gap-6 px-4 pb-20 sm:px-6 md:grid-cols-2">
            <div v-reveal class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0b2a6b] to-primary p-8 text-white">
                <Presentation class="absolute -right-6 -top-6 size-40 opacity-10" aria-hidden="true" />
                <h3 class="text-2xl font-bold">Pour les enseignants</h3>
                <ul class="mt-4 space-y-2 text-white/90">
                    <li
                        v-for="t in [
                            'Groupes avec code d’accès',
                            'Quiz en quelques minutes',
                            'Résultats et erreurs fréquentes',
                            'Étudiants à accompagner',
                        ]"
                        :key="t"
                        class="flex items-center gap-2"
                    >
                        <Check class="size-4 shrink-0" /> {{ t }}
                    </li>
                </ul>
                <p class="mt-5 text-sm text-white/70">L'inscription enseignant demande le code remis par votre établissement.</p>
            </div>
            <div v-reveal="120" class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary to-[#6d3fd6] p-8 text-white">
                <GraduationCap class="absolute -right-6 -top-6 size-40 opacity-10" aria-hidden="true" />
                <h3 class="text-2xl font-bold">Pour les étudiants</h3>
                <ul class="mt-4 space-y-2 text-white/90">
                    <li
                        v-for="t in [
                            'Rejoindre son groupe avec un code',
                            'Quiz même hors connexion',
                            'Sa compréhension par notion',
                            'Cours, devoirs et bibliothèque',
                        ]"
                        :key="t"
                        class="flex items-center gap-2"
                    >
                        <Check class="size-4 shrink-0" /> {{ t }}
                    </li>
                </ul>
                <p class="mt-5 text-sm text-white/70">Gratuit pour les étudiants, sur n'importe quel téléphone.</p>
            </div>
        </section>

        <!-- Final call -->
        <section class="relative overflow-hidden">
            <img src="/images/accueil/maskali.webp" alt="" loading="lazy" class="animate-kenburns absolute inset-0 size-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-r from-[#0b2a6b]/95 via-[#0b2a6b]/85 to-[#6d3fd6]/70" />
            <div class="relative mx-auto flex max-w-6xl flex-col items-center gap-6 px-4 py-20 text-center text-white sm:px-6" v-reveal>
                <img
                    src="/images/focusia-logo.webp"
                    alt="Logo FocusIA"
                    width="900"
                    height="600"
                    loading="lazy"
                    class="w-56 rounded-3xl bg-white p-2 shadow-2xl sm:w-64"
                />
                <h2 class="max-w-2xl text-3xl font-bold sm:text-4xl">Une plateforme éducative intelligente, au service des étudiants de Djibouti</h2>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button v-if="!page.props.auth.user" as-child class="h-12 bg-white px-6 text-base text-[#0b2a6b] hover:bg-white/90">
                        <Link :href="route('register')"><Sparkles /> Créer mon compte</Link>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        class="h-12 border-white/40 bg-transparent px-6 text-base text-white hover:bg-white/10 hover:text-white"
                    >
                        <Link :href="page.props.auth.user ? route('dashboard') : route('login')"
                            ><UsersRound /> {{ page.props.auth.user ? 'Mon espace' : 'Se connecter' }}</Link
                        >
                    </Button>
                </div>
            </div>
        </section>

        <!-- Footer + photo credits (required by the licences) -->
        <footer class="border-t bg-muted/30">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 text-sm text-muted-foreground sm:px-6">
                <p class="font-medium text-foreground">© {{ year }} FocusIA · Apprendre · Réviser · Progresser</p>
                <details class="group">
                    <summary class="cursor-pointer select-none hover:text-foreground">Crédits photos (Wikimedia Commons)</summary>
                    <ul class="mt-2 space-y-1 text-xs">
                        <li v-for="p in photos" :key="p.src">
                            <a :href="p.source" target="_blank" rel="noopener noreferrer" class="underline-offset-2 hover:underline">{{
                                p.caption
                            }}</a>
                            — {{ p.author }},
                            <a :href="p.licenseUrl" target="_blank" rel="noopener noreferrer" class="underline-offset-2 hover:underline">{{
                                p.license
                            }}</a>
                        </li>
                        <li>
                            Photos recadrées et redimensionnées pour le web. FocusIA n'est affilié à aucun des lieux ou établissements photographiés.
                        </li>
                    </ul>
                </details>
            </div>
        </footer>
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
