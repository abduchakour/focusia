<script setup lang="ts">
import { dismiss, needsLogin, onSynced, online, pending } from '@/lib/offline/sync';
import { formatScore } from '@/lib/score';
import { Link } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, CloudUpload, LoaderCircle, WifiOff, X } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

/**
 * Connection status for students and teachers: offline notice, answers waiting to be
 * sent, refused answers, and a confirmation when a queued answer reaches the server.
 */
const waiting = computed(() => pending.value.filter((p) => !p.error));
const refused = computed(() => pending.value.filter((p) => p.error));

const sent = ref<{ title: string; score: number; url: string } | null>(null);
let hide: number | undefined;
const stop = onSynced((entry, result) => {
    sent.value = { title: entry.quizTitle, score: result.score, url: result.result_url };
    window.clearTimeout(hide);
    hide = window.setTimeout(() => (sent.value = null), 8000);
});
onUnmounted(() => {
    stop();
    window.clearTimeout(hide);
});
</script>

<template>
    <div class="space-y-2 px-4 pt-3 empty:hidden" aria-live="polite">
        <Transition name="banner">
            <div
                v-if="!online"
                class="mx-auto flex max-w-5xl items-center gap-3 rounded-xl border border-warning/30 bg-warning/10 px-3 py-2.5 text-sm text-warning"
                role="status"
            >
                <WifiOff class="size-4 shrink-0" />
                <p class="flex-1">
                    <strong>Hors connexion.</strong>
                    <span class="text-foreground/80"> Les quiz commencés restent disponibles ; vos réponses partiront au retour du réseau.</span>
                </p>
            </div>
        </Transition>

        <Transition name="banner">
            <div
                v-if="online && waiting.length > 0"
                class="mx-auto flex max-w-5xl items-center gap-3 rounded-xl border border-primary/25 bg-primary/5 px-3 py-2.5 text-sm"
                role="status"
            >
                <template v-if="needsLogin">
                    <CircleAlert class="size-4 shrink-0 text-warning" />
                    <p class="flex-1">Votre session a expiré : reconnectez-vous pour envoyer vos réponses en attente.</p>
                    <Link :href="route('login')" class="shrink-0 font-semibold text-primary underline-offset-4 hover:underline">Se connecter</Link>
                </template>
                <template v-else>
                    <LoaderCircle class="size-4 shrink-0 animate-spin text-primary" />
                    <p class="flex-1">Envoi de vos réponses en attente…</p>
                </template>
            </div>
        </Transition>

        <div
            v-for="entry in refused"
            :key="entry.key"
            class="animate-enter mx-auto flex max-w-5xl items-start gap-3 rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm"
            role="alert"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0 text-destructive" />
            <p class="flex-1">
                <strong>{{ entry.quizTitle }} :</strong> {{ entry.error }}
            </p>
            <button type="button" class="press shrink-0 rounded-md px-2 py-0.5 font-semibold text-destructive" @click="dismiss(entry)">OK</button>
        </div>

        <Transition name="banner">
            <div
                v-if="sent"
                class="mx-auto flex max-w-5xl items-center gap-3 rounded-xl border border-success/30 bg-success/10 px-3 py-2.5 text-sm"
                role="status"
            >
                <CloudUpload class="size-4 shrink-0 text-success" />
                <p class="flex-1">
                    <CircleCheck class="mr-1 inline size-4 text-success" />Réponses envoyées : <strong>{{ sent.title }}</strong> —
                    {{ formatScore(sent.score) }}/20
                </p>
                <Link :href="sent.url" class="shrink-0 font-semibold text-success underline-offset-4 hover:underline">Voir</Link>
                <button type="button" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Fermer" @click="sent = null">
                    <X class="size-4" />
                </button>
            </div>
        </Transition>
    </div>
</template>
