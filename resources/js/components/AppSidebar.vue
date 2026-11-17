<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChartColumn, History, Sparkles, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { exactUsd, formatUsd } from '@/lib/money';
import { usage } from '@/routes/admin';
import { index as credits } from '@/routes/credits';
import { create, index as history } from '@/routes/generations';
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);

const mainNavItems = computed<NavItem[]>(() => [
    { title: 'Generate', href: create(), icon: Sparkles },
    { title: 'History', href: history(), icon: History },
    { title: 'Credits', href: credits(), icon: Wallet },
    ...(user.value.is_admin
        ? [{ title: 'Usage report', href: usage(), icon: ChartColumn }]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="create()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <Link
                :href="credits()"
                class="rounded-md px-2 py-1 text-sm group-data-[collapsible=icon]:hidden"
                :title="exactUsd(user.balance_nanos)"
            >
                <span class="text-muted-foreground">Balance</span>
                <span class="float-right font-medium">{{
                    formatUsd(user.balance_nanos, 2)
                }}</span>
            </Link>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
