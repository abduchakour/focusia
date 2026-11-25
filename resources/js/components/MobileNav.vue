<script setup lang="ts">
import { useNavigation } from '@/composables/useNavigation';
import { Link } from '@inertiajs/vue3';

// App-like bottom bar on phones (hidden from md and up, where the sidebar is shown).
const { mobileItems, isActive } = useNavigation();
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur supports-[backdrop-filter]:bg-background/80 md:hidden"
        aria-label="Navigation principale"
    >
        <ul class="flex">
            <li v-for="item in mobileItems" :key="item.title" class="flex-1">
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
                    {{ item.title }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
