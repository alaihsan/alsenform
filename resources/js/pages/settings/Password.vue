<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import {
    alsenCard,
    alsenCardDescription,
    alsenCardTitle,
    alsenHint,
    alsenInput,
    alsenLabel,
    alsenPrimaryButton,
    alsenSuccessText,
} from '@/constants/alsenform-ui';
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Eye, EyeOff, KeyRound, LogOut, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const mustChangePassword = computed(() => Boolean(user.value?.must_change_password));

const passwordInput = ref<HTMLInputElement>();
const currentPasswordInput = ref<HTMLInputElement>();
const showPasswords = ref(false);

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
                passwordInput.value?.focus();
            }

            if (errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <Head :title="mustChangePassword ? 'Aktivasi Akun' : 'Password'" />

    <!-- Mandatory password change on first login -->
    <AlsenformLayout v-if="mustChangePassword" minimal>
        <template #header-actions>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
            >
                <LogOut class="h-4 w-4" />
                Keluar
            </Link>
        </template>

        <main class="flex justify-center px-4 py-8 sm:py-14">
            <section class="w-full max-w-lg overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="h-3 bg-indigo-600"></div>
                <div class="p-6 sm:p-8">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                            <KeyRound class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Langkah pertama</p>
                            <h1 class="text-2xl font-black leading-tight text-slate-900">Buat Password Baru</h1>
                        </div>
                    </div>

                    <p class="mt-4 text-sm leading-relaxed text-slate-600">
                        Selamat datang<span v-if="user?.name">, <strong class="text-slate-900">{{ user.name }}</strong></span>! Sebelum mulai mengerjakan
                        ujian, ganti dulu password sementara dari sekolah dengan password pribadi Anda.
                    </p>

                    <div class="mt-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                        <p class="leading-relaxed">Gunakan minimal 8 karakter dan jangan beri tahu password Anda kepada teman.</p>
                    </div>

                    <form class="mt-6 space-y-5" @submit.prevent="updatePassword">
                        <div class="space-y-2">
                            <label for="current_password" :class="alsenLabel">Password saat ini (dari sekolah)</label>
                            <input
                                id="current_password"
                                ref="currentPasswordInput"
                                v-model="form.current_password"
                                :type="showPasswords ? 'text' : 'password'"
                                :class="alsenInput"
                                autocomplete="current-password"
                                placeholder="Masukkan password sementara"
                            />
                            <InputError :message="form.errors.current_password" />
                        </div>

                        <div class="space-y-2">
                            <label for="password" :class="alsenLabel">Password baru</label>
                            <input
                                id="password"
                                ref="passwordInput"
                                v-model="form.password"
                                :type="showPasswords ? 'text' : 'password'"
                                :class="alsenInput"
                                autocomplete="new-password"
                                placeholder="Minimal 8 karakter"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <label for="password_confirmation" :class="alsenLabel">Ulangi password baru</label>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showPasswords ? 'text' : 'password'"
                                :class="alsenInput"
                                autocomplete="new-password"
                                placeholder="Ketik ulang password baru"
                            />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-indigo-700"
                            @click="showPasswords = !showPasswords"
                        >
                            <EyeOff v-if="showPasswords" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                            {{ showPasswords ? 'Sembunyikan password' : 'Tampilkan password' }}
                        </button>

                        <button type="submit" :disabled="form.processing" :class="[alsenPrimaryButton, 'w-full py-3.5']">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Password & Mulai' }}
                        </button>
                    </form>
                </div>
            </section>
        </main>
    </AlsenformLayout>

    <!-- Regular password settings -->
    <SettingsLayout v-else>
        <div :class="alsenCard">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <KeyRound class="h-5 w-5" />
                </div>
                <div>
                    <h2 :class="alsenCardTitle">Ganti Password</h2>
                    <p :class="alsenCardDescription">Gunakan password yang panjang dan sulit ditebak agar akun tetap aman.</p>
                </div>
            </div>

            <form class="mt-6 max-w-xl space-y-5" @submit.prevent="updatePassword">
                <div class="space-y-2">
                    <label for="current_password" :class="alsenLabel">Password saat ini</label>
                    <input
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        :type="showPasswords ? 'text' : 'password'"
                        :class="alsenInput"
                        autocomplete="current-password"
                        placeholder="Password saat ini"
                    />
                    <InputError :message="form.errors.current_password" />
                </div>

                <div class="space-y-2">
                    <label for="password" :class="alsenLabel">Password baru</label>
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        :type="showPasswords ? 'text' : 'password'"
                        :class="alsenInput"
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-2">
                    <label for="password_confirmation" :class="alsenLabel">Ulangi password baru</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showPasswords ? 'text' : 'password'"
                        :class="alsenInput"
                        autocomplete="new-password"
                        placeholder="Ketik ulang password baru"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                    <p :class="alsenHint">Password baru langsung berlaku untuk login berikutnya.</p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-indigo-700"
                    @click="showPasswords = !showPasswords"
                >
                    <EyeOff v-if="showPasswords" class="h-4 w-4" />
                    <Eye v-else class="h-4 w-4" />
                    {{ showPasswords ? 'Sembunyikan password' : 'Tampilkan password' }}
                </button>

                <div class="flex flex-wrap items-center gap-4 pt-1">
                    <button type="submit" :disabled="form.processing" :class="alsenPrimaryButton">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Password' }}
                    </button>

                    <TransitionRoot
                        :show="form.recentlySuccessful"
                        enter="transition ease-in-out"
                        enter-from="opacity-0"
                        leave="transition ease-in-out"
                        leave-to="opacity-0"
                    >
                        <span :class="alsenSuccessText"><CheckCircle2 class="h-4 w-4" /> Password berhasil diganti.</span>
                    </TransitionRoot>
                </div>
            </form>
        </div>
    </SettingsLayout>
</template>
