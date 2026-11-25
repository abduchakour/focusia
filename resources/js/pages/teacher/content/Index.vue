<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ContentCard from '@/components/content/ContentCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { kindText, type ContentItem, type ContentKind } from '@/lib/content';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, EllipsisVertical, FileText, Library, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ kind: ContentKind; items: ContentItem[] }>();

const text = kindText[props.kind];
const isResource = props.kind === 'ressource';
const routes = isResource
    ? {
          index: 'teacher.resources.index',
          create: 'teacher.resources.create',
          edit: 'teacher.resources.edit',
          destroy: 'teacher.resources.destroy',
          param: 'resource',
      }
    : {
          index: 'teacher.materials.index',
          create: 'teacher.materials.create',
          edit: 'teacher.materials.edit',
          destroy: 'teacher.materials.destroy',
          param: 'material',
      };
const breadcrumbs: BreadcrumbItem[] = [{ title: text.title, href: route(routes.index) }];

// Search + type filter (client side: lists stay small).
const query = ref('');
const type = ref('all');
const types = computed(() => [...new Map(props.items.map((i) => [i.type, i.type_label])).entries()]);
const normalize = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const visible = computed(() =>
    props.items.filter(
        (i) =>
            (type.value === 'all' || i.type === type.value) &&
            normalize(`${i.title} ${i.description ?? ''} ${i.subject ?? ''}`).includes(normalize(query.value.trim())),
    ),
);

const toDelete = ref<ContentItem | null>(null);
const deleting = ref(false);
const deleteDescription = computed(() => {
    const linked = toDelete.value?.assignments_count ?? 0;
    const base = 'Le fichier sera supprimé et les étudiants n’y auront plus accès.';
    // Rule 19: homework is kept, only the link disappears.
    return linked > 0 ? `${base} ${linked > 1 ? `Les ${linked} devoirs liés sont conservés` : 'Le devoir lié est conservé'}, sans ce support.` : base;
});
function destroy() {
    if (!toDelete.value) return;
    deleting.value = true;
    router.delete(route(routes.destroy, { [routes.param]: toDelete.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            toDelete.value = null;
        },
    });
}
</script>

<template>
    <Head :title="text.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader :title="text.title" :description="text.teacherIntro">
                <template #actions>
                    <Button as-child class="h-11 shadow-md shadow-primary/25">
                        <Link :href="route(routes.create)"><Plus /> {{ text.add }}</Link>
                    </Button>
                </template>
            </PageHeader>

            <EmptyState
                v-if="items.length === 0"
                :icon="isResource ? Library : FileText"
                :title="`Aucun${isResource ? 'e ressource' : ' support'} pour le moment`"
                :description="text.empty"
            >
                <Button as-child class="h-11"
                    ><Link :href="route(routes.create)"><Plus /> {{ text.add }}</Link></Button
                >
            </EmptyState>

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
                    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0 sm:pb-0">
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

                <TransitionGroup tag="ul" name="fade-list" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <li v-for="(item, i) in visible" :key="item.id" class="animate-stagger" :style="{ '--i': Math.min(i, 8) }">
                        <ContentCard :item="item" show-groups>
                            <template #menu>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="-mr-2 -mt-1 size-10 shrink-0"
                                            :aria-label="`Actions pour ${item.title}`"
                                        >
                                            <EllipsisVertical />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="route(routes.edit, { [routes.param]: item.id })" class="w-full"
                                                ><Pencil class="mr-2 size-4" /> Modifier</Link
                                            >
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="text-destructive focus:text-destructive" @select="toDelete = item">
                                            <Trash2 class="mr-2 size-4" /> Supprimer
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </template>
                            <p v-if="item.assignments_count" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <ClipboardList class="size-3.5" /> Lié à {{ item.assignments_count }} devoir{{
                                    item.assignments_count > 1 ? 's' : ''
                                }}
                            </p>
                        </ContentCard>
                    </li>
                </TransitionGroup>
            </template>
        </PageContainer>

        <ConfirmDialog
            :open="toDelete !== null"
            :title="`Supprimer « ${toDelete?.title ?? ''} » ?`"
            :description="deleteDescription"
            confirm-label="Supprimer"
            destructive
            :processing="deleting"
            @update:open="(v) => !v && (toDelete = null)"
            @confirm="destroy"
        />
    </AppLayout>
</template>

<style scoped>
.fade-list-move {
    transition: transform 0.35s ease;
}
.fade-list-leave-active {
    transition: opacity 0.2s ease;
    position: absolute;
}
.fade-list-leave-to {
    opacity: 0;
}
</style>
