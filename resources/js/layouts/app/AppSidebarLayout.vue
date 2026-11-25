<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import MobileNav from '@/components/MobileNav.vue';
import OfflineBanner from '@/components/OfflineBanner.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <!-- Keyboard users jump straight to the page content. -->
        <a
            href="#contenu"
            class="sr-only z-50 rounded-lg bg-primary px-4 py-2 font-medium text-primary-foreground focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
            >Aller au contenu</a
        >
        <AppSidebar />
        <AppContent variant="sidebar" class="pb-safe-nav md:pb-0">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <OfflineBanner />
            <FlashMessage />
            <div id="contenu" tabindex="-1" class="flex flex-1 flex-col outline-none">
                <slot />
            </div>
        </AppContent>
        <MobileNav />
    </AppShell>
</template>
