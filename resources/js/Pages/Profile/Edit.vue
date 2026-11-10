<script setup lang="ts">
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import StatBar from '@/Components/Game/StatBar.vue';
import GuardianLayout from '@/Layouts/GuardianLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

type Guardian = {
    name: string;
    level: number;
    rank_label: string;
    xp: number;
    xp_into_level: number;
    xp_level_cost: number;
    xp_for_next: number;
    hp: number;
    max_hp: number;
    streak: number;
    best_streak: number;
    solved_count: number;
    accuracy: number;
    next_rank: { label: string; min_level: number } | null;
};

const page = usePage();
const user = computed(() => (page.props.auth as any).user as { name: string; email: string; email_verified_at?: string | null });
const guardian = computed(() => (page.props as any).guardian as Guardian | null);

const initial = computed(() => (user.value?.name ?? '?').charAt(0).toUpperCase());
const accuracyPct = computed(() => Math.round((guardian.value?.accuracy ?? 0) * 100));
const verified = computed(() => Boolean(user.value?.email_verified_at));
</script>

<template>
    <Head title="Profil" />

    <GuardianLayout active="profile" :guardian="guardian">
        <!-- ══ KARTU IDENTITAS ══ -->
        <section class="panel p-4 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <!-- blok avatar: huruf awal, bahasa visual ZZZ -->
                <div class="relative shrink-0">
                    <div class="flex h-24 w-24 items-center justify-center bg-acid-500 cut-md">
                        <span class="font-display text-5xl leading-none text-onacid">{{ initial }}</span>
                    </div>
                    <div class="absolute -bottom-2 -right-2 border border-line/20 bg-ink-950 px-2 py-0.5 cut-tag">
                        <span class="font-mono text-[10px] font-bold uppercase tracking-[0.16em] text-acid-400">
                            Lv{{ guardian?.level ?? 1 }}
                        </span>
                    </div>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="label-tag bg-acid-500/15 text-acid-400">Guardian</span>
                        <span class="label-tag border border-line/15 text-bone/50">{{ guardian?.rank_label ?? 'Novice' }}</span>
                        <span
                            v-if="verified"
                            class="label-tag border border-signal-400/40 text-signal-400"
                        >
                            Email terverifikasi
                        </span>
                        <span
                            v-else
                            class="label-tag border border-amber-400/40 text-amber-400"
                        >
                            Email belum diverifikasi
                        </span>
                    </div>

                    <h1 class="mt-2 truncate font-display text-2xl uppercase tracking-tight text-bone sm:text-3xl">
                        {{ user?.name }}
                    </h1>
                    <p class="truncate font-mono text-[11px] text-bone/40">{{ user?.email }}</p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <StatBar
                            label="Menuju level berikutnya"
                            :value="guardian?.xp_into_level ?? 0"
                            :max="guardian?.xp_level_cost ?? 100"
                            variant="xp"
                            accent="#c6f000"
                        />
                        <StatBar
                            label="Vitality"
                            :value="guardian?.hp ?? 0"
                            :max="guardian?.max_hp ?? 100"
                            variant="hp"
                            :accent="(guardian?.hp ?? 0) > 40 ? '#00b8dc' : '#ff1470'"
                        />
                    </div>
                </div>
            </div>

            <!-- strip statistik -->
            <div class="mt-5 grid grid-cols-2 gap-3 border-t border-line/10 pt-4 sm:grid-cols-4">
                <div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.18em] text-bone/35">Streak</div>
                    <div class="font-display text-xl leading-none text-bone">{{ guardian?.streak ?? 0 }}</div>
                </div>
                <div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.18em] text-bone/35">Rekor streak</div>
                    <div class="font-display text-xl leading-none text-bone">{{ guardian?.best_streak ?? 0 }}</div>
                </div>
                <div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.18em] text-bone/35">Trial selesai</div>
                    <div class="font-display text-xl leading-none text-bone">{{ guardian?.solved_count ?? 0 }}</div>
                </div>
                <div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.18em] text-bone/35">Akurasi</div>
                    <div class="font-display text-xl leading-none text-acid-400">{{ accuracyPct }}%</div>
                </div>
            </div>

            <p v-if="guardian?.xp_for_next" class="mt-4 font-cond text-xs text-bone/45">
                Butuh {{ guardian.xp_for_next }} XP lagi menuju Level {{ (guardian.level ?? 1) + 1 }}<span
                    v-if="guardian.next_rank"
                >. Rank berikutnya: {{ guardian.next_rank.label }} (Lv{{ guardian.next_rank.min_level }}).</span>
            </p>
        </section>

        <!-- ══ DATA AKUN ══ -->
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <section class="panel p-4 sm:p-6">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </section>

            <section class="panel p-4 sm:p-6">
                <UpdatePasswordForm />
            </section>
        </div>

        <section class="panel mt-5 border-magenta-500/25 p-4 sm:p-6">
            <DeleteUserForm />
        </section>
    </GuardianLayout>
</template>
