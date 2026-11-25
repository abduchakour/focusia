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
        <AppSidebar />
        <AppContent variant="sidebar" class="pb-safe-nav md:pb-0">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <OfflineBanner />
            <FlashMessage />
            <slot />
        </AppContent>
        <MobileNav />
    </AppShell>
</template>
