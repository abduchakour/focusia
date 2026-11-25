<script setup lang="ts">
import ContentCard from '@/components/content/ContentCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { kindText, type ContentItem, type ContentKind } from '@/lib/content';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { FileText, Library, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ kind: ContentKind; items: ContentItem[] }>();

const text = kindText[props.kind];
const isResource = props.kind === 'ressource';
const breadcrumbs: BreadcrumbItem[] = [{ title: text.title, href: route(isResource ? 'student.resources.index' : 'student.materials.index') }];

const query = ref('');
const type = ref('all');
const types = computed(() => [...new Map(props.items.map((i) => [i.type, i.type_label])).entries()]);
const normalize = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const visible = computed(() =>
    props.items.filter(
        (i) =>
            (type.value === 'all' || i.type === type.value) &&
            normalize(`${i.title} ${i.description ?? ''} ${i.subject ?? ''} ${i.teacher ?? ''}`).includes(normalize(query.value.trim())),
    ),
);

/** Sections per subject, the subjects in alphabetical order. */
const sections = computed(() => {
    const map = new Map<string, ContentItem[]>();
    visible.value.forEach((i) => {
        const key = i.subject ?? 'Autres';
        map.set(key, [...(map.get(key) ?? []), i]);
    });
    return [...map.entries()].sort(([a], [b]) => a.localeCompare(b, 'fr'));
});
</script>

<template>
    <Head :title="text.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader :title="text.title" :description="text.studentIntro" />

            <EmptyState
                v-if="items.length === 0"
                :icon="isResource ? Library : FileText"
                title="Rien pour le moment"
                :description="text.studentEmpty"
            />

            <template v-else>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative sm:w-72">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="query"
                            type="search"
                            placeholder="Rechercher…"
                            aria-label="Rechercher"
                            class="h-11 w-full rounded-xl border border-input bg-background pl-9 pr-3 text-base shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
                        />
                    </div>
                    <div v-if="types.length > 1" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0 sm:pb-0">
                        <button
                            v-for="[value, label] in [['all', 'Tout'], ...types]"
                            :key="value"
                            type="button"
                            :aria-pressed="type === value"
                            :class="[
                                'press h-10 shrink-0 rounded-full border px-4 text-sm font-medium transition-colors',
                                type === value ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-muted',
                            ]"
                            @click="type = value"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>

                <p v-if="visible.length === 0" class="animate-enter py-10 text-center text-muted-foreground">Aucun résultat.</p>

                <section v-for="[subject, list] in sections" :key="subject" class="space-y-3">
                    <h2 class="flex items-center gap-2 text-lg font-semibold">
                        {{ subject }}
                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">{{ list.length }}</span>
                    </h2>
                    <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <li v-for="(item, i) in list" :key="item.id" class="animate-stagger" :style="{ '--i': Math.min(i, 8) }">
                            <ContentCard :item="item" hide-subject />
                        </li>
                    </ul>
                </section>
            </template>
        </PageContainer>
    </AppLayout>
</template>
