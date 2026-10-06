<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { router, usePage } from '@inertiajs/vue3';
import { BookOpen, Home, LogOut, UserRound, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Runs before leaving the page, e.g. to save pending changes first. */
        leave?: (go: () => void) => void;
    }>(),
    {
        leave: (go: () => void) => go(),
    },
);

const page = usePage<any>();
const currentUser = computed(() => page.props.auth?.user);
const avatarUrl = computed<string>(() => currentUser.value?.avatar_url || '');
const roleLabel = computed<string>(() => {
    const user = currentUser.value;
    if (!user) {
        return '';
    }
    if (user.is_admin || user.role === 'admin') {
        return 'Admin';
    }
    return user.role === 'guru' ? 'Guru' : 'Siswa';
});

const isAdmin = computed<boolean>(() => roleLabel.value === 'Admin');
const isStudent = computed<boolean>(() => {
    const user = currentUser.value;
    if (!user) {
        return false;
    }
    return user.role === 'siswa' || user.role === 'murid' || (!user.is_admin && user.role !== 'guru' && !!user.nis);
});

const goHome = () => props.leave(() => router.visit(route('dashboard')));
// Teachers manage student accounts; admins manage every account.
const goToUsers = () => props.leave(() => router.visit(route(isAdmin.value ? 'users.index' : 'students.index')));
const goToCohorts = () => props.leave(() => router.visit(route('cohorts.index')));
const goToProfile = () => props.leave(() => router.visit(route('profile.edit')));
const logout = () => props.leave(() => router.post(route('logout')));
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <button
                type="button"
                class="rounded-full transition hover:ring-4 hover:ring-indigo-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200 data-[state=open]:ring-4 data-[state=open]:ring-indigo-100"
                aria-label="Menu akun"
                :title="currentUser?.name"
            >
                <Avatar class="h-10 w-10 overflow-hidden rounded-full border border-slate-200">
                    <AvatarImage v-if="avatarUrl" :src="avatarUrl" :alt="currentUser?.name" />
                    <AvatarFallback class="flex h-full w-full items-center justify-center bg-slate-100 text-slate-400">
                        <UserRound class="h-6 w-6" />
                    </AvatarFallback>
                </Avatar>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="z-50 mt-1.5 w-60 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
            <div class="flex items-center gap-3 rounded-xl px-2.5 py-2">
                <Avatar class="h-9 w-9 shrink-0 overflow-hidden rounded-full border border-slate-200">
                    <AvatarImage v-if="avatarUrl" :src="avatarUrl" :alt="currentUser?.name" />
                    <AvatarFallback class="flex h-full w-full items-center justify-center bg-slate-100 text-slate-400">
                        <UserRound class="h-5 w-5" />
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-slate-900">{{ currentUser?.name }}</p>
                    <p v-if="currentUser?.email" class="truncate text-xs text-slate-500">{{ currentUser.email }}</p>
                    <span
                        v-if="roleLabel"
                        class="mt-1 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700"
                    >
                        {{ roleLabel }}
                    </span>
                </div>
            </div>
            <DropdownMenuSeparator class="my-1 border-t border-slate-100" />
            <DropdownMenuItem
                class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
                @select="goHome"
            >
                <Home class="h-4 w-4 text-slate-500" />
                <span>Home</span>
            </DropdownMenuItem>
            <DropdownMenuItem
                class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
                @select="goToProfile"
            >
                <UserRound class="h-4 w-4 text-slate-500" />
                <span>Profile</span>
            </DropdownMenuItem>
            <template v-if="!isStudent">
                <DropdownMenuItem
                    class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    @select="goToUsers"
                >
                    <Users class="h-4 w-4 text-slate-500" />
                    <span>Pengguna</span>
                </DropdownMenuItem>
                <DropdownMenuItem
                    class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    @select="goToCohorts"
                >
                    <BookOpen class="h-4 w-4 text-slate-500" />
                    <span>Cohort & Kelas</span>
                </DropdownMenuItem>
            </template>
            <!-- Extra page-specific items, e.g. on the dashboard -->
            <slot />
            <DropdownMenuSeparator class="my-1 border-t border-slate-100" />
            <DropdownMenuItem
                class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold text-rose-600 transition-colors hover:bg-rose-50 hover:text-rose-700"
                @select="logout"
            >
                <LogOut class="h-4 w-4 text-rose-500" />
                <span>Logout</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
