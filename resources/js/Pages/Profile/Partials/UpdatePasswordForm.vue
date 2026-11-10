<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header class="mb-5 border-b border-line/10 pb-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg uppercase tracking-tight text-bone">Kata Sandi</h2>
                <span class="font-mono text-[9px] uppercase tracking-[0.18em] text-bone/30">Data 02</span>
            </div>
            <p class="mt-0.5 font-cond text-sm text-bone/50">
                Gunakan sandi acak yang panjang untuk menjaga keamanan akun.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="space-y-4">
            <div>
                <InputLabel for="current_password" value="Kata Sandi Saat Ini" />

                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1"
                    autocomplete="current-password"
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-1.5"
                />
            </div>

            <div>
                <InputLabel for="password" value="Kata Sandi Baru" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-1.5" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Ulangi Kata Sandi Baru"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1"
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-1.5"
                />
            </div>

            <div class="flex items-center gap-3 pt-1">
                <PrimaryButton class="!w-auto" :disabled="form.processing">
                    Perbarui Sandi
                </PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <span
                        v-if="form.recentlySuccessful"
                        class="font-mono text-[10px] uppercase tracking-[0.18em] text-acid-400"
                    >
                        Tersimpan ✓
                    </span>
                </Transition>
            </div>
        </form>
    </section>
</template>
