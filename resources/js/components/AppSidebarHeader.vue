<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    { breadcrumbs: () => [] },
);
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/90 px-4 backdrop-blur transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:static md:h-16 md:bg-transparent md:backdrop-blur-none"
    >
        <!-- Phones: logo only (navigation is in the bottom bar). -->
        <Link :href="route('dashboard')" class="flex items-center md:hidden" aria-label="Accueil">
            <AppLogo />
        </Link>

        <div class="hidden items-center gap-2 md:flex">
            <SidebarTrigger class="-ml-1" />
            <Breadcrumb v-if="breadcrumbs.length > 0">
                <BreadcrumbList>
                    <template v-for="(item, index) in breadcrumbs" :key="index">
                        <BreadcrumbItem>
                            <BreadcrumbPage v-if="index === breadcrumbs.length - 1">{{ item.title }}</BreadcrumbPage>
                            <BreadcrumbLink v-else as-child>
                                <Link :href="item.href">{{ item.title }}</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                    </template>
                </BreadcrumbList>
            </Breadcrumb>
        </div>
    </header>
</template>
