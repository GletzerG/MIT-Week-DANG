<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Urutan penting: bank quest dulu, baru akun.
        $this->call([
            NewsQuestSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
