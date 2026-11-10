<?php

/*
|--------------------------------------------------------------------------
| Guardian Genesis — Game & AI Configuration
|--------------------------------------------------------------------------
| Semua "magic number" progresi dan AI tinggal di sini supaya balance game
| bisa di-tune tanpa menyentuh service layer.
*/

return [

    'ai' => [
        // openai | gemini | null  (null = matikan AI, pakai fallback deterministik)
        'driver' => env('GUARDIAN_AI_DRIVER', 'openai'),

        'timeout' => (int) env('GUARDIAN_AI_TIMEOUT', 20),

        // Model visual lebih lambat: satu gambar = ribuan token. Jangan pakai
        // timeout 20 detik yang sama dengan teks.
        'vision_timeout' => (int) env('GUARDIAN_AI_VISION_TIMEOUT', 120),

        // Feedback per (quest, pilihan) di-cache lama: LLM mahal & jawabannya stabil.
        'cache_ttl' => (int) env('GUARDIAN_AI_CACHE_TTL', 60 * 60 * 24 * 90),

        'openai' => [
            'key'      => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model'    => env('OPENAI_MODEL', 'gpt-4o-mini'),

            // Model multimodal. Dikosongkan = fitur scan gambar dimatikan dan
            // UI akan mengatakannya terus terang, bukan pura-pura bisa.
            'vision_model' => env('OPENAI_VISION_MODEL'),
        ],

        'gemini' => [
            'key'          => env('GEMINI_API_KEY'),
            'base_url'     => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model'        => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'vision_model' => env('GEMINI_VISION_MODEL', 'gemini-2.0-flash'),
        ],
    ],

    /*
    | Scan gambar & periksa tautan. Batasnya sengaja ketat: ini endpoint publik
    | yang memanggil jaringan luar dan model mahal.
    */
    'inspection' => [
        'image' => [
            'max_kb'        => 6144,
            'max_dimension' => 1600,   // gambar lebih besar tidak menambah akurasi, cuma token
            'mimes'         => ['png', 'jpeg', 'webp'],
        ],

        'link' => [
            'timeout'       => 12,
            'max_bytes'     => 3 * 1024 * 1024,
            'max_redirects' => 3,
            'user_agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 GuardianGenesis/1.0',
        ],
    ],

    'progression' => [
        'max_level'              => 60,
        'base_level_cost'        => 100, // XP untuk L1 -> L2
        'level_cost_step'        => 50,  // tiap level, biaya naik segini
        'max_hp'                 => 100,
        'hp_regen_minutes'       => 30,
        'hp_regen_amount'        => 10,
        'streak_bonus_per_step'  => 0.10,
        'streak_bonus_cap'       => 1.00,
        'wrong_answer_hp_cost'   => 20,
        'wrong_answer_xp_penalty'=> 0.30, // 30% dari reward, dibulatkan
    ],

    'rewards' => [
        'novice'      => 30,
        'seeker'      => 50,
        'knight'      => 80,
        'grandmaster' => 120,
    ],

    'generator' => [
        'daily_batch' => 8,
        'ai_ratio'    => 0.7, // sisanya diambil dari bank statis
    ],
];
