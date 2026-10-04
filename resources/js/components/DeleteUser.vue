<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// Components
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { alsenCard, alsenCardDescription, alsenCardTitle, alsenDangerButton, alsenInput, alsenSecondaryButton } from '@/constants/alsenform-ui';
import { ShieldAlert, Trash2 } from 'lucide-vue-next';

interface Props {
    canDelete?: boolean;
}

withDefaults(defineProps<Props>(), {
    canDelete: true,
});

const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const deleteUser = (e: Event) => {
    e.preventDefault();

    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <div :class="alsenCard">
        <h2 :class="alsenCardTitle">Hapus Akun</h2>
        <p :class="alsenCardDescription">Penghapusan akun bersifat permanen.</p>

        <div v-if="!canDelete" class="mt-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                <ShieldAlert class="h-5 w-5" />
            </div>
            <div>
                <p class="text-sm font-bold text-amber-900">Akun siswa dilindungi</p>
                <p class="mt-1 text-sm leading-relaxed text-amber-800">
                    Akun siswa dikelola oleh sekolah dan tidak dapat dihapus sendiri, agar riwayat nilai ujian tetap utuh. Hubungi administrator sekolah
                    bila akun perlu diubah.
                </p>
            </div>
        </div>

        <div v-else class="mt-5 space-y-4 rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-sm leading-relaxed text-rose-800">
                Menghapus akun akan menghapus seluruh kuis, rekap jawaban, dan pengaturan yang telah Anda buat secara permanen.
            </p>
            <Dialog>
                <DialogTrigger as-child>
                    <button type="button" :class="[alsenDangerButton, 'py-2.5']"><Trash2 class="h-4 w-4" /> Hapus Akun Saya</button>
                </DialogTrigger>
                <DialogContent class="rounded-3xl border-slate-200 bg-white p-6 sm:p-8">
                    <form class="space-y-5" @submit="deleteUser">
                        <DialogHeader class="space-y-2">
                            <DialogTitle class="text-lg font-bold text-slate-900">Yakin ingin menghapus akun?</DialogTitle>
                            <DialogDescription class="text-sm text-slate-500">
                                Seluruh kuis, bank soal, dan riwayat Anda akan dihapus permanen. Masukkan password akun untuk konfirmasi.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="space-y-2">
                            <label for="password" class="sr-only">Password</label>
                            <input
                                id="password"
                                ref="passwordInput"
                                v-model="form.password"
                                type="password"
                                name="password"
                                :class="alsenInput"
                                placeholder="Password saat ini"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <button type="button" :class="alsenSecondaryButton" @click="closeModal">Batal</button>
                            </DialogClose>
                            <button type="submit" :disabled="form.processing" :class="alsenDangerButton">Hapus Akun Permanen</button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
