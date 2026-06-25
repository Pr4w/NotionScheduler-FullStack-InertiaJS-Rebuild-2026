<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    CreditCard,
    Gift,
    LayoutGrid,
    LifeBuoy,
    Rocket,
    Shield,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const isAdmin = computed<boolean>(() =>
    Boolean(
        (page.props.auth as { user?: { is_admin?: boolean } } | undefined)?.user
            ?.is_admin,
    ),
);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Get started', href: '/app/setup', icon: Rocket },
        { title: 'Pricing', href: '/app/pricing', icon: CreditCard },
        { title: 'Affiliates', href: '/app/affiliates', icon: Gift },
        { title: 'Support', href: '/app/support', icon: LifeBuoy },
    ];

    if (isAdmin.value) {
        // /admin is the Filament panel (not an Inertia page) → full-page anchor.
        items.push({
            title: 'Admin panel',
            href: '/admin',
            icon: Shield,
            external: true,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
