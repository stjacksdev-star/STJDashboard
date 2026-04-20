<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '../composables/useTheme';

const page = usePage();
const sidebarOpen = ref(false);
const openGroups = ref(new Set());
const { isDark, toggleTheme } = useTheme();

const user = computed(() => page.props.auth?.user || {});
const navigation = computed(() => page.props.navigation || []);

const iconPaths = {
    activity: 'M22 12h-4l-3 9L9 3l-3 9H2',
    bookmark: 'M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z',
    chart: 'M3 3v18h18M8 17V9M13 17V5M18 17v-6',
    clipboard: 'M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M9 2h6v4H9z',
    dot: 'M12 12h.01',
    flag: 'M4 22V4M4 4h13l-1 5 1 5H4',
    grid: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
    home: 'M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z',
    layers: 'M12 2 2 7l10 5 10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
    list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
    receipt: 'M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2zM8 7h8M8 12h8M8 17h5',
    refresh: 'M21 12a9 9 0 0 1-15.5 6.2M3 12A9 9 0 0 1 18.5 5.8M18 2v4h-4M6 22v-4h4',
    report: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5',
    search: 'M21 21l-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z',
    settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.6V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.6h.1a1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.6 1h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.6 1z',
    tag: 'M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8zM7 7h.01',
    truck: 'M10 17h4V5H2v12h3M14 17h1M15 17h4M14 8h4l4 4v5h-3M5 17a2 2 0 1 0 4 0 2 2 0 0 0-4 0zM17 17a2 2 0 1 0 4 0 2 2 0 0 0-4 0z',
    users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
};

const isActive = (href) => (href === '/' ? page.url === '/' : page.url.startsWith(href));
const groupIsActive = (item) => item.children?.some((child) => isActive(child.href));
const iconPath = (name) => iconPaths[name] || iconPaths.dot;

const toggleGroup = (label) => {
    const next = new Set(openGroups.value);

    if (next.has(label)) {
        next.delete(label);
    } else {
        next.add(label);
    }

    openGroups.value = next;
};

const groupIsOpen = (item) => openGroups.value.has(item.label) || groupIsActive(item);
</script>

<template>
    <div class="app-bg min-h-screen">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <aside
            :class="[
                'app-surface fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r transition-transform lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="app-header flex h-16 items-center px-6">
                <Link href="/" class="flex items-end gap-2">
                    <span class="text-2xl font-bold tracking-tight">st.jack's</span>
                    <span class="app-header-muted mb-1 text-[9px] font-semibold uppercase tracking-[0.24em]">
                        admin
                    </span>
                </Link>
            </div>

            <div class="app-border-soft border-b px-5 py-5">
                <div class="flex items-center gap-3">
                    <div class="app-surface-soft app-border app-text-soft flex h-12 w-12 items-center justify-center rounded-full border text-xl font-semibold">
                        {{ (user.nombre || 'ST').slice(0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <p class="app-text truncate text-sm font-semibold">{{ user.nombre || 'Usuario STJ' }}</p>
                        <p class="app-muted truncate text-xs">{{ user.correo || user.usuario || 'Sesion activa' }}</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <div v-for="section in navigation" :key="section.label" class="mb-6">
                    <p class="app-primary-text px-3 text-[11px] font-semibold uppercase tracking-[0.2em]">
                        {{ section.label }}
                    </p>

                    <div class="mt-2 space-y-1">
                        <template v-for="item in section.items" :key="item.label">
                            <Link
                                v-if="item.type === 'item'"
                                :href="item.href"
                                :class="[
                                    'flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium transition',
                                    isActive(item.href)
                                        ? 'app-primary shadow-sm shadow-blue-600/20'
                                        : 'app-text-soft',
                                ]"
                                :style="!isActive(item.href) ? { background: 'transparent' } : null"
                                @mouseenter="$event.currentTarget.style.background = isActive(item.href) ? 'var(--stj-primary)' : 'var(--stj-surface-hover)'"
                                @mouseleave="$event.currentTarget.style.background = isActive(item.href) ? 'var(--stj-primary)' : 'transparent'"
                                @click="sidebarOpen = false"
                            >
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path :d="iconPath(item.icon)" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span class="truncate">{{ item.label }}</span>
                            </Link>

                            <div v-else>
                                <button
                                    type="button"
                                    :class="[
                                        'flex h-10 w-full items-center gap-3 rounded-md px-3 text-left text-sm font-medium transition',
                                        groupIsActive(item)
                                            ? 'app-primary-text'
                                            : 'app-text-soft',
                                    ]"
                                    :style="{ background: groupIsActive(item) ? 'var(--stj-primary-soft)' : 'transparent' }"
                                    @mouseenter="$event.currentTarget.style.background = groupIsActive(item) ? 'var(--stj-primary-soft)' : 'var(--stj-surface-hover)'"
                                    @mouseleave="$event.currentTarget.style.background = groupIsActive(item) ? 'var(--stj-primary-soft)' : 'transparent'"
                                    @click="toggleGroup(item.label)"
                                >
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path :d="iconPath(item.icon)" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                                    <svg
                                        :class="['h-4 w-4 transition', groupIsOpen(item) ? 'rotate-180' : '']"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>

                                <div v-if="groupIsOpen(item)" class="mt-1 space-y-1 pl-7">
                                    <Link
                                        v-for="child in item.children"
                                        :key="child.href"
                                        :href="child.href"
                                        :class="[
                                            'block rounded-md px-3 py-2 text-sm transition',
                                            isActive(child.href)
                                                ? 'app-primary'
                                                : 'app-muted',
                                        ]"
                                        :style="{ background: isActive(child.href) ? 'var(--stj-primary)' : 'transparent' }"
                                        @mouseenter="$event.currentTarget.style.background = isActive(child.href) ? 'var(--stj-primary)' : 'var(--stj-surface-hover)'"
                                        @mouseleave="$event.currentTarget.style.background = isActive(child.href) ? 'var(--stj-primary)' : 'transparent'"
                                        @click="sidebarOpen = false"
                                    >
                                        {{ child.label }}
                                    </Link>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </nav>
        </aside>

        <div class="lg:pl-64">
            <header class="app-header sticky top-0 z-20 flex h-16 items-center justify-between px-4 shadow-md shadow-blue-950/10 sm:px-6">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-white/12 ring-1 ring-white/20 lg:hidden"
                    @click="sidebarOpen = true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="hidden items-end gap-2 lg:flex">
                    <span class="text-2xl font-bold tracking-tight">st.jack's</span>
                    <span class="app-header-muted mb-1 text-[9px] font-semibold uppercase tracking-[0.24em]">
                        dashboard
                    </span>
                </div>

                <div class="flex items-center gap-4">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold">{{ user.nombre || 'Usuario STJ' }}</p>
                        <p class="app-header-muted text-xs">{{ user.pais || 'N/D' }} · {{ user.tiendas || '00000' }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-md bg-white/12 px-3 text-sm font-semibold text-white ring-1 ring-white/20 transition hover:bg-white/20"
                        title="Cambiar tema"
                        @click="toggleTheme"
                    >
                        <svg v-if="isDark" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="4" />
                            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke-linecap="round" />
                        </svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8z" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="hidden sm:inline">Tema</span>
                    </button>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50"
                    >
                        Salir
                    </Link>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>
