<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { KeyRound, ShieldCheck } from 'lucide-vue-next';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';

interface Props {
    className?: string;
}

defineProps<Props>();

const page = usePage();
const mustChangePassword = computed(() => Boolean((page.props.auth as any)?.user?.must_change_password));

const breadcrumbItems = computed<BreadcrumbItem[]>(() => {
    if (mustChangePassword.value) {
        return [
            {
                title: 'Aktivasi Akun',
                href: '/settings/password',
            },
        ];
    }

    return [
        {
            title: 'Password settings',
            href: '/settings/password',
        },
    ];
});

const passwordInput = ref<HTMLInputElement>();
const currentPasswordInput = ref<HTMLInputElement>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: any) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                if (passwordInput.value instanceof HTMLInputElement) {
                    passwordInput.value.focus();
                }
            }

            if (errors.current_password) {
                form.reset('current_password');
                if (currentPasswordInput.value instanceof HTMLInputElement) {
                    currentPasswordInput.value.focus();
                }
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="mustChangePassword ? 'Aktivasi Akun & Ganti Password' : 'Password settings'" />

        <!-- Onboarding View for First-time / Mandatory Password Change -->
        <div v-if="mustChangePassword" class="mx-auto max-w-xl py-8 px-4">
            <div class="overflow-hidden rounded-3xl border border-indigo-100 bg-white shadow-sm ring-1 ring-slate-900/5">
                <div class="bg-gradient-to-r from-indigo-600 via-indigo-700 to-violet-700 px-6 py-6 text-white sm:px-8">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                            <KeyRound class="h-6 w-6 text-white" />
                        </div>
                        <div>
                            <h1 class="text-xl font-bold">Selamat Datang di Alsenform!</h1>
                            <p class="text-xs text-indigo-100">Langkah Awal: Buat Password Pribadi Baru</p>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8 space-y-6">
                    <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-xs text-amber-900">
                        <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                        <p class="leading-relaxed">
                            Demi keamanan akun Anda, silakan ubah password sementara yang diberikan pihak sekolah sebelum mulai mengakses kuis dan fitur lainnya.
                        </p>
                    </div>

                    <form @submit.prevent="updatePassword" class="space-y-5">
                        <div class="grid gap-2">
                            <Label for="current_password">Password Saat Ini (Password Sementara)</Label>
                            <Input
                                id="current_password"
                                ref="currentPasswordInput"
                                v-model="form.current_password"
                                type="password"
                                class="mt-1 block w-full rounded-xl"
                                autocomplete="current-password"
                                placeholder="Masukkan password saat ini"
                            />
                            <InputError :message="form.errors.current_password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">Password Baru</Label>
                            <Input
                                id="password"
                                ref="passwordInput"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full rounded-xl"
                                autocomplete="new-password"
                                placeholder="Minimal 8 karakter"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation">Konfirmasi Password Baru</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full rounded-xl"
                                autocomplete="new-password"
                                placeholder="Ulangi password baru Anda"
                            />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>

                        <Button :disabled="form.processing" class="w-full rounded-xl bg-indigo-600 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                            {{ form.processing ? 'Menyimpan Password...' : 'Simpan Password & Mulai' }}
                        </Button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Normal Settings Layout -->
        <SettingsLayout v-else>
            <div class="space-y-6">
                <HeadingSmall title="Update password" description="Ensure your account is using a long, random password to stay secure" />

                <form @submit.prevent="updatePassword" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="current_password">Current Password</Label>
                        <Input
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            placeholder="Current password"
                        />
                        <InputError :message="form.errors.current_password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <Input
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="New password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirm password</Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Confirm password"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save password</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">Saved</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
