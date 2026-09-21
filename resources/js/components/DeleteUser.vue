<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
    <div class="space-y-6">
        <HeadingSmall title="Hapus Akun" description="Pengaturan penghapusan akun pengguna dari sistem" />

        <div v-if="!canDelete" class="space-y-3 rounded-xl border border-amber-200 bg-amber-50/70 p-5 dark:border-amber-900/40 dark:bg-amber-950/20">
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-medium text-amber-900 dark:text-amber-200">Akun Siswa Dilindungi</h4>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                        Akun siswa dikelola oleh pihak sekolah dan tidak dapat dihapus secara mandiri demi menjaga integritas data presensi dan riwayat nilai ujian. Hubungi administrator sekolah bila memerlukan perubahan akun.
                    </p>
                </div>
            </div>
        </div>

        <div v-else class="space-y-4 rounded-xl border border-red-100 bg-red-50 p-5 dark:border-red-900/40 dark:bg-red-950/20">
            <div class="relative space-y-0.5 text-red-700 dark:text-red-200">
                <p class="font-medium">Perhatian Penting</p>
                <p class="text-sm">Menghapus akun akan menghapus seluruh data kuis, rekap respon, dan konfigurasi yang telah Anda buat secara permanen.</p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive">Hapus Akun Saya</Button>
                </DialogTrigger>
                <DialogContent>
                    <form class="space-y-6" @submit="deleteUser">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Apakah Anda yakin ingin menghapus akun?</DialogTitle>
                            <DialogDescription>
                                Seluruh data kuis, bank soal, dan riwayat yang Anda miliki akan dihapus secara permanen. Masukkan kata sandi akun Anda untuk mengonfirmasi tindakan ini.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">Password</Label>
                            <Input id="password" type="password" name="password" ref="passwordInput" v-model="form.password" placeholder="Kata sandi saat ini" />
                            <InputError :message="form.errors.password" />
                        </div>

                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="secondary" @click="closeModal"> Batal </Button>
                            </DialogClose>

                            <Button variant="destructive" :disabled="form.processing">
                                Hapus Akun Secara Permanen
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
