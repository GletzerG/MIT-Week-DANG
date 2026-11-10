<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => {
            form.reset();
        },
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section>
        <header class="mb-4 border-b border-magenta-500/20 pb-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg uppercase tracking-tight text-magenta-400">Zona Bahaya</h2>
                <span class="font-mono text-[9px] uppercase tracking-[0.18em] text-magenta-400/50">Data 03</span>
            </div>
            <p class="mt-0.5 font-cond text-sm text-bone/50">
                Menghapus akun akan menghapus seluruh XP, streak, riwayat trial, dan
                hasil analisis secara permanen. Tidak ada tombol undo.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Hapus Akun Permanen</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <span class="label-tag bg-magenta-500/15 text-magenta-300">Konfirmasi</span>

                <h2 class="mt-3 font-display text-xl uppercase tracking-tight text-bone">
                    Yakin hapus akun ini?
                </h2>

                <p class="mt-2 font-cond text-sm text-bone/55">
                    Semua data guardianmu — XP, level, streak, riwayat trial — hilang
                    permanen. Masukkan sandi untuk konfirmasi.
                </p>

                <div class="mt-5">
                    <InputLabel for="password" value="Kata Sandi" class="sr-only" />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1"
                        placeholder="Kata sandi akunmu"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-1.5" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">
                        Batal
                    </SecondaryButton>

                    <DangerButton
                        :class="{ 'opacity-40': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Ya, hapus permanen
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
