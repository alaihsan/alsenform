<script setup lang="ts">
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, KeyRound, Palette, UserRound, Users } from 'lucide-vue-next';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        /** Wider content area for the user and cohort management tables. */
        wide?: boolean;
    }>(),
    {
        wide: false,
    },
);

const page = usePage<any>();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => Boolean(user.value?.is_admin || user.value?.role === 'admin'));
const isStudent = computed(() => {
    const u = user.value;
    if (!u) return false;
    return u.role === 'siswa' || u.role === 'murid' || (!u.is_admin && u.role !== 'guru' && !!u.nis);
});

const sidebarNavItems = computed(() => [
    { title: 'Profil', description: 'Foto & data diri', href: '/settings/profile', icon: UserRound },
    { title: 'Password', description: 'Keamanan akun', href: '/settings/password', icon: KeyRound },
    { title: 'Tampilan', description: 'Tema aplikasi', href: '/settings/appearance', icon: Palette },
    // Teachers manage student accounts; admins also manage teacher and admin accounts.
    ...(isStudent.value
        ? []
        : [
              {
                  title: 'Pengguna',
                  description: isAdmin.value ? 'Admin, guru & murid' : 'Akun murid',
                  href: isAdmin.value ? '/users' : '/students',
                  icon: Users,
              },
              { title: 'Cohort & Kelas', description: 'Kelompok belajar', href: '/cohorts', icon: BookOpen },
          ]),
]);

const currentPath = window.location.pathname;
const isActive = (href: string): boolean => currentPath === href || currentPath.startsWith(`${href}/`);
</script>

<template>
    <AlsenformLayout>
        <main :class="['mx-auto px-4 py-6 sm:px-6 sm:py-8', wide ? 'max-w-7xl' : 'max-w-6xl']">
            <Link
                :href="route('dashboard')"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-indigo-700"
            >
                <ArrowLeft class="h-4 w-4" />
                Kembali ke Beranda
            </Link>

            <div class="mt-3">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Pengaturan</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{
                        isStudent
                            ? 'Kelola profil, password, dan tampilan akun Anda.'
                            : 'Kelola akun Anda, pengguna, serta cohort dan kelas.'
                    }}
                </p>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
                <aside>
                    <nav class="flex gap-2 overflow-x-auto pb-1 lg:sticky lg:top-20 lg:flex-col lg:overflow-visible lg:pb-0">
                        <Link
                            v-for="item in sidebarNavItems"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'flex shrink-0 items-center gap-3 rounded-2xl border px-4 py-3 transition',
                                isActive(item.href)
                                    ? 'border-indigo-200 bg-white text-indigo-700 shadow-sm'
                                    : 'border-transparent text-slate-600 hover:bg-white/70 hover:text-slate-900',
                            ]"
                            :aria-current="isActive(item.href) ? 'page' : undefined"
                        >
                            <span
                                :class="[
                                    'flex h-9 w-9 items-center justify-center rounded-xl',
                                    isActive(item.href) ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 shadow-sm',
                                ]"
                            >
                                <component :is="item.icon" class="h-4 w-4" />
                            </span>
                            <span class="flex flex-col">
                                <span class="text-sm font-bold">{{ item.title }}</span>
                                <span class="hidden text-xs font-medium text-slate-400 lg:block">{{ item.description }}</span>
                            </span>
                        </Link>
                    </nav>
                </aside>

                <section class="min-w-0 space-y-6">
                    <slot />
                </section>
            </div>
        </main>
    </AlsenformLayout>
</template>
