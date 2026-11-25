<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { useNavigation } from '@/composables/useNavigation';
import { clearCachedPages } from '@/lib/offline/setup';
import { Link, router } from '@inertiajs/vue3';
import { ChevronRight, CircleEllipsis, LogOut, UserRound } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

// App-like bottom bar on phones (hidden from md and up, where the sidebar is shown).
const { mobileItems, moreItems, isActive } = useNavigation();
const open = ref(false);

const moreActive = computed(() => moreItems.value.some((i) => isActive(i.href)) || isActive(route('profile.edit')));

// Close the panel as soon as a page is chosen.
const stop = router.on('start', () => (open.value = false));
onUnmounted(stop);

function logout() {
    void clearCachedPages();
    router.post(route('logout'));
}
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur supports-[backdrop-filter]:bg-background/80 md:hidden"
        aria-label="Navigation principale"
    >
        <ul class="flex">
            <li v-for="item in mobileItems" :key="item.title" class="min-w-0 flex-1">
                <Link
                    :href="item.href"
                    :class="[
                        'press relative flex flex-col items-center gap-1 py-2.5 text-xs font-medium transition-colors',
                        isActive(item.href) ? 'text-primary' : 'text-muted-foreground',
                    ]"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                >
                    <span
                        :class="[
                            'absolute top-0 h-0.5 w-10 rounded-full bg-primary transition-all duration-300',
                            isActive(item.href) ? 'opacity-100' : 'scale-x-0 opacity-0',
                        ]"
                    />
                    <component :is="item.icon" class="size-6" />
                    <span class="max-w-full truncate px-1">{{ item.title }}</span>
                </Link>
            </li>
            <li class="min-w-0 flex-1">
                <button
                    type="button"
                    :class="[
                        'press relative flex w-full flex-col items-center gap-1 py-2.5 text-xs font-medium transition-colors',
                        moreActive ? 'text-primary' : 'text-muted-foreground',
                    ]"
                    :aria-expanded="open"
                    @click="open = true"
                >
                    <span
                        :class="[
                            'absolute top-0 h-0.5 w-10 rounded-full bg-primary transition-all duration-300',
                            moreActive ? 'opacity-100' : 'scale-x-0 opacity-0',
                        ]"
                    />
                    <CircleEllipsis class="size-6" />
                    Plus
                </button>
            </li>
        </ul>
    </nav>

    <Sheet v-model:open="open">
        <SheetContent side="bottom" class="rounded-t-3xl px-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))] pt-3">
            <div class="mx-auto mb-3 h-1.5 w-12 rounded-full bg-muted" aria-hidden="true" />
            <SheetTitle class="px-2 text-base">Plus</SheetTitle>
            <SheetDescription class="sr-only">Autres pages de FocusIA</SheetDescription>
            <ul class="mt-2 space-y-1">
                <li v-for="(item, i) in [...moreItems, { title: 'Mon profil', href: route('profile.edit'), icon: UserRound }]" :key="item.title">
                    <Link
                        :href="item.href"
                        :class="[
                            'press animate-stagger flex h-14 items-center gap-3 rounded-xl px-3 font-medium',
                            isActive(item.href) ? 'bg-secondary text-primary' : 'hover:bg-muted',
                        ]"
                        :style="{ '--i': i }"
                    >
                        <span class="flex size-10 items-center justify-center rounded-xl bg-secondary text-primary">
                            <component :is="item.icon" class="size-5" />
                        </span>
                        <span class="flex-1">{{ item.title }}</span>
                        <ChevronRight class="size-4 text-muted-foreground" />
                    </Link>
                </li>
                <li>
                    <button
                        type="button"
                        class="press animate-stagger flex h-14 w-full items-center gap-3 rounded-xl px-3 font-medium text-destructive hover:bg-destructive/5"
                        :style="{ '--i': moreItems.length + 1 }"
                        @click="logout"
                    >
                        <span class="flex size-10 items-center justify-center rounded-xl bg-destructive/10"><LogOut class="size-5" /></span>
                        Se déconnecter
                    </button>
                </li>
            </ul>
        </SheetContent>
    </Sheet>
</template>
