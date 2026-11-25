<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { fileTone, formatBytes, linkHost, type ContentItem } from '@/lib/content';
import { date } from '@/lib/format';
import { Download, ExternalLink, Link2, PlayCircle, UsersRound } from 'lucide-vue-next';

/** One material / library resource: file badge, details, download and link buttons. */
defineProps<{ item: ContentItem; showGroups?: boolean; hideSubject?: boolean }>();
</script>

<template>
    <article class="lift flex h-full flex-col gap-4 rounded-2xl border bg-card p-5">
        <div class="flex items-start gap-3">
            <div
                v-if="item.file"
                :class="[
                    'flex size-12 shrink-0 items-center justify-center rounded-xl text-xs font-bold tracking-wide',
                    fileTone(item.file.extension),
                ]"
            >
                {{ item.file.extension }}
            </div>
            <div v-else class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary">
                <PlayCircle v-if="item.type === 'video'" class="size-6" />
                <Link2 v-else class="size-6" />
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                    <span class="rounded-full bg-secondary px-2 py-0.5 font-medium text-secondary-foreground">{{ item.type_label }}</span>
                    <span v-if="item.subject && !hideSubject" class="font-semibold uppercase tracking-wide text-primary">{{ item.subject }}</span>
                    <span v-if="item.level" class="text-muted-foreground">· {{ item.level }}</span>
                </div>
                <h2 class="mt-1.5 font-semibold leading-snug">{{ item.title }}</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    <template v-if="item.teacher">{{ item.teacher }} · </template>{{ date(item.created_at) }}
                </p>
            </div>
            <slot name="menu" />
        </div>

        <p v-if="item.description" class="line-clamp-3 whitespace-pre-line text-sm text-muted-foreground">{{ item.description }}</p>

        <div v-if="showGroups" class="flex flex-wrap gap-1.5">
            <span
                v-for="g in item.groups"
                :key="g.id"
                class="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-0.5 text-xs text-secondary-foreground"
            >
                <UsersRound class="size-3" /> {{ g.name }}
            </span>
            <span v-if="item.groups.length === 0" class="rounded-md bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">Non publié</span>
        </div>

        <slot />

        <div class="mt-auto flex flex-col gap-2 sm:flex-row">
            <!-- A normal link: the server checks the rights and sends a temporary download link. -->
            <Button v-if="item.file" as-child class="press h-11 flex-1">
                <a :href="item.file.download">
                    <Download /> Télécharger <span class="font-normal opacity-80">· {{ formatBytes(item.file.size) }}</span>
                </a>
            </Button>
            <Button v-if="item.url" as-child :variant="item.file ? 'outline' : 'default'" class="press h-11 min-w-0 flex-1">
                <a :href="item.url" target="_blank" rel="noopener noreferrer" :title="item.url">
                    <ExternalLink /> <span class="truncate">{{ linkHost(item.url) }}</span>
                </a>
            </Button>
        </div>
    </article>
</template>
