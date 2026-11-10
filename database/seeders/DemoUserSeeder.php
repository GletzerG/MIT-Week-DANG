<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun demo untuk mencoba game tanpa registrasi.
 * JANGAN dijalankan di produksi.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'guardian@genesis.test'],
            ['name' => 'Guardian Demo', 'password' => Hash::make('password')],
        );

        // Kolom ini tidak mass-assignable, jadi diset eksplisit.
        $user->forceFill(['email_verified_at' => now()])->save();
    }
}
