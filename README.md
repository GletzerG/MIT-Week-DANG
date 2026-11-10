# Guardian Genesis

Stasiun verifikasi berita bergaya **Zenless Zone Zero** untuk remaja 12-18 tahun.
Tiga hal hidup berdampingan:

1. **Feed Terverifikasi** — berita yang *sudah* dicek. Verdict, skor kredibilitas,
   dan daftar sinyal manipulatifnya terbuka. Tujuannya mengajari pola.
2. **Detektor Klaim** — tempel teks apa pun (pesan berantai, caption, judul berita),
   mesin memberi verdict + alasan yang menunjuk bagian teksmu.
3. **Trial Quest** — arena ujian. Semua bantuan dimatikan. Benar menambah XP,
   salah menguras HP.

**Stack:** Laravel 11 · Inertia.js · Vue 3 (Composition API + TypeScript) · Tailwind CSS

---

## 0. Jalanin (paling cepat)

**Repo ini isinya kode saja.** `vendor/` (dependency PHP), `node_modules/`, dan
`public/build/` (aset hasil build) sengaja tidak di-commit — itu artefak mesin,
bukan sumber. Jadi di clone yang masih bersih, sekali setup dulu:

```bash
composer install                 # dependency PHP
cp .env.example .env             # lalu isi APP_KEY
php artisan key:generate
touch database/database.sqlite   # SQLite kosong
php artisan migrate --seed       # skema + 26 berita contoh + akun demo
npm install && npm run build     # aset frontend
```

Setelah itu, **dobel-klik `start.bat`**. Dia cari `php.exe` dengan path lengkap
(tidak bergantung PATH Windows), cek `vendor/`, bikin database kalau hilang,
lalu menyalakan server dan membuka browser.

Atau lewat terminal:

```bash
php artisan serve
```

Kalau `vendor/` sudah ada (mis. kamu clone dari mesin ini), cukup
`npm install && npm run build && php artisan migrate --seed` sekali, lalu seterusnya
tinggal `start.bat`.

Buka **http://127.0.0.1:8000** → login dengan akun demo:

| Email | Password |
|---|---|
| `guardian@genesis.test` | `password` |

Setelah login kamu mendarat di `/dashboard` (feed terverifikasi). Navigasi atas:
**01 Beranda · 02 Deteksi · 03 Trial**.

> Aset frontend dibangun dengan `npm run build` (wajib sekali setelah `npm install`).
> Kalau mau mengubah Vue dan melihat perubahannya langsung, jalankan `npm run dev`
> di terminal terpisah.

### Kalau `php` / `composer` tidak dikenali

Laragon tidak memasukkan PHP ke PATH global. Pakai path lengkap:

```bash
export PATH="/c/laragon/bin/php/php-8.4.11-nts-Win32-vs17-x64:/c/laragon/bin/composer:$PATH"
```

(atau tambahkan permanen lewat *Laragon → Menu → Preferences → Environment*).

---

## 1. Perintah yang sering dipakai

```bash
php artisan migrate:fresh --seed               # reset DB + data contoh
php artisan db:seed --class=NewsQuestSeeder    # isi ulang bank berita
php artisan db:seed --class=DemoUserSeeder     # buat ulang akun demo
php artisan test                               # 57 test, 282 assertion
npm run build                                  # build aset produksi
npm run dev                                    # dev server + HMR
```

> `phpunit.xml` menunjuk ke sqlite `:memory:` **dan** memaksa
> `GUARDIAN_AI_DRIVER=` kosong, jadi test selalu deterministik dan offline —
> tidak pernah memanggil LLM dan tidak pernah menghapus `database/database.sqlite`.

---

## 2. Deteksi hoax: dua lapis, satu jawaban

```
teks ──► Lapis 1  HeuristicClaimScanner   (selalu jalan · 0 token · tanpa jaringan)
      └► Lapis 2  LLM                    (bila dikonfigurasi)
                          │
                          ▼
              validasi enum ClaimVerdict  ← model bisa "yakin" pada hal yang tidak ada
                          │
                          ▼
                     ClaimAnalysis (cache by sha256 teks)
```

**Lapis 1 — `app/Support/HeuristicClaimScanner.php`.** Membaca sinyal linguistik
dan struktural: kapital berlebihan, tanda seru beruntun, ajakan "sebarkan
sebelum dihapus", otoritas tanpa nama, janji mukjizat, institusi yang bisa
dicek, tanggal terbit, bahasa terukur. Skor 0..100 mulai dari 50.

| Skor | Verdict |
|---|---|
| ≥ 65 | **Fakta** (acid) |
| 36-64 | **Belum Pasti** (amber) |
| ≤ 35 | **Hoax** (magenta) |

**Lapis 2 — LLM.** Diminta menilai konteks, sarkasme, dan klaim yang butuh
pengetahuan dunia. Keluarannya **selalu** divalidasi ulang ke enum; verdict di
luar `fact|hoax|uncertain`, atau model yang tidak memberi alasan sama sekali,
dibuang dan diganti hasil Lapis 1. Sinyal Lapis 1 tetap ditempel di samping
alasan model — dua sudut pandang, bukan saling menimpa.

**"Belum Pasti" itu jawaban yang jujur.** Memaksa mesin memilih fakta/hoax pada
teks tanpa bukti adalah sumber kesalahan paling umum — dan justru melatih remaja
jadi terlalu percaya pada mesin.

**Cache by hash teks.** Pesan berantai yang sama tidak membakar token dua kali
(hit rate tinggi di dunia nyata), bisa diaudit, dan limiter per-user jadi lapis
kedua. Kuota XP: `8 XP × maksimal 5 analisis/hari` supaya alat ini tidak bisa
dipakai farming.

### Tiga pintu masuk, satu mesin penilai

```
teks   ──────────────┐
gambar ─► dibaca     ├──► ClaimAnalysisService ──► verdict + alasan
tautan ─► diekstrak  ┘        (sama, tidak diduplikasi)
```

- **Teks** — pesan berantai, caption, potongan artikel. Jalur asli.
- **Gambar** — tangkapan layar berita/postingan/chat. Model visual **hanya
  membaca** (teks + sinyal visual: tanda air aplikasi, tanpa tanggal, tanpa nama
  media, huruf kapital semua). Ia dilarang menilai benar/salah — penilaian tetap
  di `ClaimAnalysisService`, jadi tidak pernah ada dua definisi "hoax".
- **Tautan** — halaman diambil, isinya diekstrak (`ArticleExtractor`), domain
  dinilai kecil (`DomainReputation`: ±10 poin, menilai *ada proses editorial*,
  bukan *isinya benar*). Hanya http/https port 80/443, alamat lokal ditolak, dan
  redirect diikuti manual hop-per-hop — tautan publik yang 302 ke `127.0.0.1`
  adalah pintu SSRF paling klasik.

**"Tidak terbaca" adalah jawaban sah.** Gambar buram atau halaman yang isinya
dimuat lewat JavaScript ditolak dengan pesan jelas (HTTP 422), bukan ditebak.

**Privasi gambar:** gambar ditulis ulang lewat GD sebelum dikirim (ukuran
diseragamkan, **EXIF/GPS dibuang**), lalu disimpan di `storage/app/local`.

### Menyalakan Lapis 2

Cukup endpoint yang **OpenAI-compatible** — termasuk gateway lokal:

```dotenv
GUARDIAN_AI_DRIVER=openai
OPENAI_BASE_URL=http://127.0.0.1:20128/v1     # 9Router / LM Studio / Ollama / vLLM
OPENAI_API_KEY=whatever-the-gateway-wants
OPENAI_MODEL=my-combo
GUARDIAN_AI_TIMEOUT=20

# Model multimodal, pisah dari model teks. Kosong = tab Gambar dimatikan
# dan UI mengatakannya terus terang (tidak pura-pura bisa).
OPENAI_VISION_MODEL=oc/jev-1.13-free
GUARDIAN_AI_VISION_TIMEOUT=120
```

`php artisan config:clear`, lalu buka `/analisis` — badge kanan atas berubah dari
**Mesin: aturan** menjadi **AI · my-combo**, dan tombol **Gambar** hidup.

Kalau model visual membalas dengan skema JSON yang berbeda dari yang diminta,
`ImageInspector::extractText()` tetap mengambil teksnya; kalau JSON-nya
terpotong karena kehabisan token, `JsonBlock` menutup string/kurawal yang
menggantung daripada membuang seluruh hasil bacaan.

Driver yang didukung: `openai`, `gemini`. Biarkan `GUARDIAN_AI_DRIVER=` kosong
untuk mematikan Lapis 2; deteksi tetap jalan penuh lewat Lapis 1.

> **Jangan commit `.env`.** Kalau memakai gateway lokal, `OPENAI_API_KEY` di situ
> adalah kunci gateway milikmu, bukan kunci publik.

---

## 3. Arsitektur

```
app/
├── Actions/ResolveQuestAttempt.php      # satu-satunya jalur mutasi XP/HP
├── Enums/
│   ├── GuardianRank.php                 # Novice Guardian → Grandmaster
│   ├── QuestDifficulty.php              # 1..4 + ceilingForLevel()
│   ├── QuestVerdict.php                 # fact | hoax  (mode trial)
│   └── ClaimVerdict.php                 # fact | hoax | uncertain (detektor)
├── Http/
│   ├── Controllers/
│   │   ├── FeedController.php           # /dashboard — etalase bukti
│   │   ├── AnalysisController.php       # /analisis — stasiun deteksi
│   │   └── QuestController.php          # /quest — arena trial
│   ├── Requests/SubmitAttemptRequest.php
│   └── Resources/
│       ├── NewsQuestResource.php        # TANPA verdict (trial)
│       ├── VerifiedNewsResource.php     # DENGAN verdict (feed)
│       └── GuardianResource.php
├── Models/{NewsQuest,UserAttempt,ClaimAnalysis,User}.php
├── Services/
│   ├── ClaimAnalysisService.php         # orkestrasi 2 lapis + cache
│   ├── NewsAnalysisService.php          # feedback trial, cache 1x/quest
│   ├── Ai/{LlmManager, OpenAiClient, GeminiClient, JsonBlock, AiFeedback, ...}.php
│   ├── Inspection/{ImageInspector, LinkInspector, InspectionException}.php
│   └── QuestGeneratorService.php
└── Support/
    ├── HeuristicClaimScanner.php        # lapis 1, tanpa token
    ├── UrlGuard.php                     # anti-SSRF: ip privat, port, redirect
    ├── ArticleExtractor.php             # HTML → judul/penulis/tanggal/isi
    ├── DomainReputation.php             # reputasi domain, bobot kecil
    └── ProgressionCalculator.php        # single source of truth rumus level

resources/js/
├── Components/
│   ├── ZZZ/{VerdictBadge, StatMeter, NewsTile}.vue     # feed + detektor
│   └── Game/{NewsCard, VerdictPanel, QuestHud, StatBar,
│             FloatingReward, AiFeedbackPanel}.vue     # trial
├── Layouts/{GuardianLayout, GuestLayout}.vue
├── Pages/{Welcome, Dashboard, Analysis/Index, Quest/Battle}.vue
├── types/{feed, guardian}.ts
└── composables/useGuardian.ts
```

### Keputusan yang menentukan

**1. Dua resource, bukan satu dengan flag.**
`NewsQuestResource` **tidak** memuat `verdict`; `VerifiedNewsResource` memuatnya.
Dipisah supaya tidak ada developer yang "lupa" dan menumpang verdict ke halaman
trial. `EntryPointTest::test_feed_exposes_the_verdict_but_trial_quest_does_not`
mengunci pemisahan ini.

**2. Detektor tidak boleh mati hanya karena LLM mati.**
Lapis 1 selalu jalan. `LlmManager::safeJson()` mengembalikan `null` alih-alih
melempar exception, dan `ClaimAnalysisService` jatuh ke pemindai aturan. Tidak
ada layar kosong, tidak ada error 500.

**3. Verdict tidak pernah datang mentah dari model.**
`ClaimVerdict::tryFrom()` pada keluaran LLM. Nilai bebas = dibuang.

**4. Satu quest = maksimal satu panggilan LLM seumur hidup kontennya.**
Penjelasan kanonik di-cache di `news_quests.ai_explanation`. Personalisasi
("Tepat!" / "Meleset.") disusun di PHP. 10.000 pemain = tetap 1 panggilan.

**5. Progresi dipusatkan di `ProgressionCalculator`.**
`levelFromXp()` memakai binary search — aman walau XP sudah jutaan.

**6. Submit jawaban & analisis lewat JSON, bukan Inertia redirect.**
Animasi +XP melayang, shake kartu, slam verdict, dan bar keyakinan butuh state
lokal yang tidak boleh terbuang oleh full page reload.

**7. `lockForUpdate()` saat resolve attempt.**
Mencegah double-submit (tombol terklik dua kali di mobile) menaikkan XP dua kali.

---

## 4. Bahasa visual

Zenless Zone Zero, diterjemahkan ke tiga aturan:

1. **Hitam hampir murni** sebagai kanvas (`ink-950 #08080a`) — bukan abu, bukan
   gelap kebiruan.
2. **Satu warna sinyal dominan** — `acid #c6f000` — sisanya aksen langka:
   `magenta #ff1470` (hoax/rugi), `signal #2fd8f5` (info), `amber` (belum pasti).
   Kalau magenta dipakai di mana-mana, tidak ada yang terasa mendesak.
3. **Sudut dipotong, bukan rounded.** `clip-path` lewat utility `cut-sm` /
   `cut-md` / `cut-lg` / `cut-tag`. Lengkung = lembut; potongan miring = poster
   jalanan.

Tipografi: `Archivo Black` (display, semua kapital), `Barlow Condensed` (body),
`JetBrains Mono` (label teknis, angka). Tekstur: grid tipis, grain, halftone,
strip diagonal — semuanya opacity rendah, tidak pernah jadi hiasan utama.

### Gelap & terang: satu set token, bukan dua stylesheet

Semua warna dibaca dari CSS variable (`resources/css/app.css`) lewat
`tailwind.config.js`, jadi mode terang **tidak** dibikin dengan menyalin kelas
jadi pasangan `dark:` / `light:` di tiap file. Tukar satu blok variable, seluruh
app ikut — termasuk halaman Breeze (login, daftar, reset sandi, profil, modal)
yang sebelumnya masih putih polos dengan tombol ungu.

```css
:root                { --c-ink-900: 13 13 16;    --c-bone: 244 241 232; }
[data-theme='light'] { --c-ink-900: 255 255 255;  --c-bone: 17 17 14;   }
```

Tiga peran token:

| Peran | Token | Di mode terang |
|---|---|---|
| **Permukaan** (kanvas → kartu → track) | `ink-950 · 900 · 800 · 700` | jadi kertas/putih |
| **Tulisan** | `bone` (+ `/40`, `/55`, …) | jadi hitam |
| **Garis & overlay** | `line` | jadi hitam transparan |

Dua pengecualian yang disengaja:

- `onacid` — tulisan yang duduk di atas isian neon (tombol acid, badge, avatar).
  Wajib **gelap di dua mode**, karena lime terang tidak mungkin jadi latar
  tulisan terang. Ini pengganti `text-ink-950` khusus pasangan `bg-acid-500`.
- Warna sinyal yang dipakai sebagai **teks** (acid-300/400, magenta-300/400,
  signal-400, amber-400) digelapkan di mode terang. Langkah **500 tetap** warna
  brand karena ia dipakai sebagai isian, bukan sebagai tulisan.

Tombol ganti mode ada di header (`Components/ZZZ/ThemeToggle.vue`), tersimpan di
`localStorage['guardian-theme']`, ikut `prefers-color-scheme` selama user belum
memilih sendiri, dan **dipasang inline di `<head>` sebelum cat pertama** — tanpa
itu, user mode terang melihat kedipan gelap di setiap muat halaman.


---

## 5. Endpoint

| Method | Path | Fungsi |
|---|---|---|
| GET | `/` | Landing untuk tamu · Guardian login → `/dashboard` |
| GET | `/dashboard` | **Feed terverifikasi** — filter `type=all\|fact\|hoax`, cari `q` |
| GET | `/analisis` | Stasiun deteksi klaim |
| POST | `/analisis` | Analisis klaim — `mode=text` (default) · `image` (multipart) · `link` (JSON) · throttle 20/menit |
| GET | `/quest` | Arena trial (Inertia) |
| POST | `/quest/attempt` | Resolve jawaban (JSON, throttle 30/menit) |
| GET/PATCH/DELETE | `/profile` | Profil guardian — identitas, sandi, zona bahaya |

`POST /analisis` → 200:

```json
{
  "verdict": { "value": "hoax", "label": "Hoax", "headline": "SINYAL MANIPULATIF", "tone": "magenta", "icon": "cross" },
  "confidence": 92,
  "summary": "Teks memakai sumber anonim dan ajakan viralkan...",
  "reasons": ["Klaim inti disampaikan lewat frasa 'diam-diam'...", "..."],
  "red_flags": [{ "label": "Sumber anonim", "note": "Tidak ada nama, jabatan..." }],
  "recommendation": "Cek pengumuman resmi dan media kredibel...",
  "signals": [{ "key": "urgency", "label": "Desakan Waktu Palsu", "tone": "risk", "note": "..." }],
  "source": {
    "type": "image",
    "label": "screenshot-berita.png",
    "reputation": null,
    "cues": ["tidak ada nama media", "tanpa tanggal terbit"],
    "text": "VIRAL!!! VAKSIN BARU ...", "chars": 519, "cached": false
  },
  "engine": "ai",
  "model": "my-combo",
  "from_fallback": false,
  "xp_awarded": 8,
  "guardian": { "level": 1, "xp": 24, "hp": 100, "...": "..." },
  "quota": { "used": 3, "cap": 5 }
}
```

`engine` bernilai `"ai"` atau `"rules"` — UI memakainya untuk jujur soal siapa
yang menjawab.

`source` menjelaskan **dari mana** klaim itu datang dan **apa yang dibaca mesin**
(untuk gambar: hasil salinan teks + sinyal visual; untuk tautan: domain,
reputasi, penulis, tanggal). `source.text` sengaja dikirim balik supaya user bisa
menyalahkan pembacaan mesin yang salah, bukan cuma verdict-nya.

Kegagalan yang bisa dimengerti user datang sebagai **422** dengan `message`
Indonesia — gambar tak terbaca, halaman 404, situs menolak robot, alamat lokal
diblokir. Bukan 500, bukan stack trace.

---

## 6. Rumus Progresi

| Hal | Rumus |
|---|---|
| Biaya naik level | `100 + (level - 1) * 50` |
| Total XP di level *n* | `100(n-1) + 50·(n-1)(n-2)/2` |
| Level dari XP | binary search pada kurva di atas |
| Reward benar | `base(difficulty) · (1 + 0.1 × streak)`, cap +100% |
| Penalti salah | `-30% × base(difficulty)`, minimum 5, XP tidak bisa < 0 |
| HP | `-20` saat salah, regen `+10` / 30 menit (lazy, tanpa cron) |
| Reward dasar | Novice 30 · Seeker 50 · Knight 80 · Grandmaster 120 |

| Level | Rank |
|---|---|
| 1-4 | Novice Guardian |
| 5-9 | Seeker |
| 10-19 | Truth Knight |
| 20-34 | Inquisitor of Genesis |
| 35+ | Grandmaster of Genesis |

Difficulty terbuka saat `minLevel <= level + 3`
(`QuestDifficulty::ceilingForLevel()`). Aturan yang sama dipakai untuk memfilter
pool **dan** memvalidasi submit — user tidak pernah melihat quest yang akan ditolak.

---

## 7. Test

```bash
php artisan test
# Tests: 57 passed (282 assertions)
```

- `tests/Feature/EntryPointTest.php` — landing tamu, redirect `/` untuk Guardian
  login, `/dashboard` benar-benar feed (bukan layar kosong Breeze),
  **verdict terbuka di feed tapi tidak di trial**, login memakai tata letak
  Guardian, pendaftar baru tidak tertahan verifikasi email.
- `tests/Feature/AnalysisStationTest.php` — tamu tidak bisa akses detektor,
  potongan <24 karakter ditolak (bukan ditebak), klaim hoax → verdict hoax +
  alasan, klaim faktual → verdict fakta, teks identik dilayani dari cache,
  kuota XP harian berhenti di 5, verdict selalu salah satu nilai enum.
- `tests/Feature/SourceScanTest.php` — sepuluh test untuk pintu masuk baru:
  tanpa model visual aplikasi **mengaku** (bukan pura-pura), gambar dibaca lalu
  **verdict tetap dari mesin penilai**, gambar tanpa teks → 422, berkas bukan
  gambar ditolak, `127.0.0.1` / `169.254.169.254` / `localhost` ditolak sebelum
  menyentuh jaringan, redirect publik → lokal ditolak, artikel asli diekstrak +
  domain dinilai, 404 & halaman tanpa isi dijelaskan, dan teks sama dari gambar
  vs tempelan **tidak** berbagi satu baris cache.
- `tests/Feature/QuestFlowTest.php` — trial: verdict tidak bocor, jawaban benar
  menambah XP/streak/level, mengulang = 0 XP, jawaban salah memotong XP & HP,
  XP tidak di bawah nol, quest di atas level ditolak 403, verdict di luar enum = 422.

---

## 8. Belum ada / langkah berikutnya

- Riwayat analisis per-user (sekarang `recent` masih global)
- Pembersihan berkas `storage/app/private/inspections` lama (belum ada retensi)
- Leaderboard mingguan (index sudah disiapkan)
- Achievement & badge
- Moderasi admin untuk klaim yang dilaporkan user
- Mode swipe gesture di mobile
- Multi-bahasa (kolom `locale` sudah ada)