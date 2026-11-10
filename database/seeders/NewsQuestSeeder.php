<?php

namespace Database\Seeders;

use App\Enums\QuestDifficulty;
use App\Enums\QuestVerdict;
use App\Models\NewsQuest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Bank quest awal. Semua media, instansi, dan tokoh di sini FIKTIF.
 * Jalankan: php artisan db:seed --class=NewsQuestSeeder
 */
class NewsQuestSeeder extends Seeder
{
    /** Nama penulis fiktif untuk berita yang kredibel. */
    private array $authors = [
        'Rania Kusuma', 'Bagas Prakoso', 'Nadia Halim', 'Yudha Setiawan',
        'Sari Wulandari', 'Dimas Anggara', 'Intan Permata', 'Rizky Mahendra',
    ];

    public function run(): void
    {
        foreach (array_merge($this->quests(), $this->extraQuests()) as $row) {
            $hash = hash('sha256', Str::lower($row['title']));

            if (NewsQuest::where('title_hash', $hash)->exists()) {
                continue;
            }

            $isHoax = $row['verdict'] === QuestVerdict::Hoax;

            // Nilai diturunkan dari hash judul, bukan random_int: seeder harus
            // idempoten, kalau tidak setiap re-seed mengubah skor kredibilitas.
            $seed = (int) hexdec(substr($hash, 0, 6));

            NewsQuest::create($row + [
                'public_id'         => (string) Str::ulid(),
                'title_hash'        => $hash,
                'locale'            => 'id',
                'origin'            => 'seeder',
                'is_active'         => true,
                'published_at'      => now()->subHours(random_int(1, 168)),
                // Hoax sengaja tanpa penulis — itu sendiri salah satu sinyal
                // yang ingin dilihat remaja di dashboard.
                'author'            => $isHoax ? null : $this->authors[$seed % count($this->authors)],
                'credibility'       => $isHoax ? 4 + ($seed % 19) : 74 + ($seed % 23),
                'fact_check_source' => $isHoax ? 'Cek Fakta Genesis' : 'Genesis Verifikasi',
                'fact_check_url'    => null,
            ]);
        }
    }

    private function quests(): array
    {
        return [
            [
                'title'      => 'AIR LEMON HANGAT TERBUKTI MEMBUNUH SEL KANKER DALAM 3 HARI!',
                'source_name'=> 'SehatAlami24',
                'excerpt'    => 'Pesan ini beredar luas di grup keluarga. Katanya, meminum air lemon hangat setiap pagi mampu membunuh sel kanker dalam tiga hari saja. "Sudah dibuktikan oleh dokter ternama, tidak perlu kemoterapi," tulis pesan tersebut tanpa menyebut nama dokter mana pun.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'kesehatan',
                'red_flags'  => [
                    ['type' => 'clickbait', 'label' => 'Judul Huruf Kapital', 'note' => 'Penulisan kapital semua dibuat untuk memancing emosi, bukan menyampaikan informasi.'],
                    ['type' => 'false_authority', 'label' => '"Dokter Ternama" Tanpa Nama', 'note' => 'Tidak ada nama, institusi, atau publikasi ilmiah yang bisa dicek.'],
                    ['type' => 'no_evidence', 'label' => 'Tanpa Data', 'note' => 'Klaim medis besar tanpa satu pun rujukan penelitian.'],
                    ['type' => 'harmful_advice', 'label' => 'Menyarankan Tinggalkan Pengobatan', 'note' => 'Menganjurkan tidak perlu kemoterapi — ini berbahaya bagi pasien sungguhan.'],
                ],
            ],
            [
                'title'      => 'Badan Meteorologi: Gerhana Bulan Total Bisa Diamati dari Indonesia Awal Bulan Depan',
                'source_name'=> 'Lembaga Cuaca Nasional',
                'excerpt'    => 'Lembaga Cuaca Nasional merilis jadwal gerhana bulan total yang akan berlangsung pada awal bulan depan. Fase total diperkirakan berlangsung sekitar 42 menit dan dapat diamati dengan mata telanjang dari wilayah barat hingga tengah Indonesia, dengan syarat langit tidak berawan.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'sains',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Sumber Lembaga Resmi', 'note' => 'Diterbitkan oleh lembaga yang memang berwenang soal cuaca dan antariksa.'],
                    ['type' => 'verifiable_data', 'label' => 'Angka Spesifik', 'note' => 'Durasi 42 menit dan cakupan wilayah bisa dicek dengan data astronomi.'],
                    ['type' => 'neutral_tone', 'label' => 'Nada Netral', 'note' => 'Tidak ada ajakan, ancaman, atau sensasi berlebihan.'],
                ],
            ],
            [
                'title'      => 'VIDEO: Bupati Bagikan Uang Tunai di Pinggir Jalan, Warga Berdesakan',
                'source_name'=> 'InfoViralNusa',
                'excerpt'    => 'Sebuah video berdurasi 47 detik menunjukkan seorang pria berbaju dinas membagikan uang tunai di pinggir jalan sambil dikerumuni warga. Video tidak menyebut lokasi maupun tanggal kejadian, dan wajah pria tersebut tidak pernah terlihat jelas menghadap kamera.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Seeker,
                'category'   => 'politik',
                'red_flags'  => [
                    ['type' => 'missing_date', 'label' => 'Tanpa Tanggal & Lokasi', 'note' => 'Peristiwa tanpa waktu dan tempat tidak bisa diverifikasi sama sekali.'],
                    ['type' => 'manipulated_image', 'label' => 'Wajah Tidak Pernah Terlihat', 'note' => 'Sudut pengambilan gambar menghindari identifikasi — pola umum video hasil suntingan.'],
                    ['type' => 'emotional_bait', 'label' => 'Memancing Kemarahan', 'note' => 'Dirancang memicu reaksi "pejabat korup" sebelum fakta diperiksa.'],
                ],
            ],
            [
                'title'      => 'Studi Kohort: Durasi Tidur di Bawah 6 Jam Berkaitan dengan Risiko Hipertensi',
                'source_name'=> 'Jurnal Kesehatan Komunitas',
                'excerpt'    => 'Penelitian yang melibatkan 4.820 responden dewasa selama lima tahun menemukan korelasi antara durasi tidur kurang dari enam jam per malam dan peningkatan risiko hipertensi sebesar 27 persen. Penulis utama, dr. Anindya Prasetya, menegaskan temuan ini bersifat korelasional, bukan sebab-akibat.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Knight,
                'category'   => 'kesehatan',
                'red_flags'  => [
                    ['type' => 'named_author', 'label' => 'Penulis Bernama Jelas', 'note' => 'Ada nama penulis utama yang bisa ditelusuri.'],
                    ['type' => 'verifiable_data', 'label' => 'Metodologi Transparan', 'note' => 'Jumlah sampel, durasi, dan persentase disebutkan.'],
                    ['type' => 'claim_calibrated', 'label' => 'Klaim Tidak Berlebihan', 'note' => 'Penulis sendiri menyatakan ini korelasi, bukan sebab-akibat — tanda tulisan jujur.'],
                ],
            ],
            [
                'title'      => 'SEBARKAN! Gas LPG 3 Kg Akan Dihapus Bulan Depan, Stok Mulai Ditarik',
                'source_name'=> 'Pesan Berantai WhatsApp',
                'excerpt'    => 'Pesan berantai berbunyi: "MOHON SEBARKAN KE SEMUA GRUP. Info dari orang dalam Pertamina: gas 3kg dihapus mulai bulan depan, mumpung masih ada buruan beli 10 tabung. Yang tidak sebar akan menyesal." Pesan ini tidak mencantumkan nomor surat, tanggal, atau pejabat yang mengeluarkan pernyataan.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Seeker,
                'category'   => 'keuangan',
                'red_flags'  => [
                    ['type' => 'chain_message', 'label' => 'Pesan Berantai', 'note' => 'Ajakan "sebarkan ke semua grup" adalah ciri khas hoax, bukan pengumuman resmi.'],
                    ['type' => 'false_authority', 'label' => '"Orang Dalam"', 'note' => 'Sumber anonim tanpa nama atau jabatan.'],
                    ['type' => 'urgency', 'label' => 'Mendesak & Menakut-nakuti', 'note' => 'Menciptakan panik agar orang membeli tanpa berpikir.'],
                    ['type' => 'no_evidence', 'label' => 'Tanpa Nomor Surat', 'note' => 'Kebijakan resmi selalu punya nomor, tanggal, dan penandatangan.'],
                ],
            ],
            [
                'title'      => 'Ilmuwan Ciptakan Baterai yang Bisa Menyala 100 Tahun Tanpa Diisi Ulang',
                'source_name'=> 'TeknoMasaDepan',
                'excerpt'    => 'Artikel mengklaim sebuah laboratorium telah menciptakan baterai revolusioner yang dapat menyuplai listrik selama satu abad tanpa pengisian. Satu-satunya rujukan adalah tautan ke unggahan media sosial pribadi peneliti, sementara data pengujian dan nama jurnal tidak disebutkan.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Knight,
                'category'   => 'teknologi',
                'red_flags'  => [
                    ['type' => 'extraordinary_claim', 'label' => 'Klaim Luar Biasa', 'note' => 'Klaim sebesar ini butuh bukti sebesar itu — dan tidak ada.'],
                    ['type' => 'missing_peer_review', 'label' => 'Tanpa Peer Review', 'note' => 'Tidak ada nama jurnal, DOI, atau proses telaah sejawat.'],
                    ['type' => 'cherry_picking', 'label' => 'Rujukan Dangkal', 'note' => 'Hanya menautkan unggahan media sosial, bukan publikasi ilmiah.'],
                ],
            ],
            [
                'title'      => 'Tarif Baru Angkutan Umum Kota Arunika Berlaku 1 Desember, Ini Rinciannya',
                'source_name'=> 'Dinas Perhubungan Kota Arunika',
                'excerpt'    => 'Dinas Perhubungan Kota Arunika menetapkan tarif baru angkutan umum perkotaan sebesar Rp4.000 untuk lima kilometer pertama dan Rp600 per kilometer berikutnya. Kebijakan tertuang dalam Peraturan Wali Kota nomor 47 tahun ini dan mulai berlaku 1 Desember.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Seeker,
                'category'   => 'politik',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Instansi Berwenang', 'note' => 'Diterbitkan langsung oleh dinas yang mengurus transportasi.'],
                    ['type' => 'verifiable_data', 'label' => 'Nomor Regulasi', 'note' => 'Ada nomor peraturan yang bisa dilacak di situs pemerintah.'],
                    ['type' => 'neutral_tone', 'label' => 'Bahasa Administratif', 'note' => 'Tidak memancing emosi, hanya memuat ketentuan.'],
                ],
            ],
            [
                'title'      => 'FOTO: Hiu Raksasa Sepanjang 18 Meter Terdampar di Pantai Selatan',
                'source_name'=> 'SerbaSerbiDunia',
                'excerpt'    => 'Unggahan berisi foto seekor hiu raksasa yang diklaim terdampar di sebuah pantai selatan. Foto tersebut memiliki pencahayaan yang tidak konsisten dengan latar belakang, dan bayangan ikan tidak jatuh ke arah yang sama dengan bayangan orang-orang di sekitarnya.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Seeker,
                'category'   => 'bencana',
                'red_flags'  => [
                    ['type' => 'manipulated_image', 'label' => 'Bayangan Tidak Konsisten', 'note' => 'Arah bayangan objek dan orang berbeda — indikasi hasil tempel.'],
                    ['type' => 'missing_date', 'label' => 'Tanpa Lokasi Pasti', 'note' => 'Hanya disebut "pantai selatan" tanpa nama tempat.'],
                    ['type' => 'reverse_image', 'label' => 'Cari Jejak Asli', 'note' => 'Foto jenis ini biasanya ditemukan lebih dulu di situs stok atau unggahan lama.'],
                ],
            ],
            [
                'title'      => 'Survei: 6 dari 10 Remaja Indonesia Memeriksa Sumber Sebelum Membagikan Berita',
                'source_name'=> 'Pusat Studi Literasi Digital',
                'excerpt'    => 'Survei terhadap 2.400 pelajar SMA di sembilan provinsi menemukan bahwa 61 persen responden memeriksa sumber berita sebelum membagikannya. Angka ini naik dari 43 persen pada survei serupa tiga tahun lalu. Laporan lengkap tersedia dalam format PDF di situs resmi pusat studi tersebut.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Knight,
                'category'   => 'teknologi',
                'red_flags'  => [
                    ['type' => 'verifiable_data', 'label' => 'Sampel & Metode Jelas', 'note' => 'Jumlah responden dan cakupan wilayah disebutkan.'],
                    ['type' => 'clear_source', 'label' => 'Laporan Bisa Diunduh', 'note' => 'Dokumen lengkap tersedia untuk diperiksa siapa pun.'],
                    ['type' => 'neutral_tone', 'label' => 'Tanpa Klaim Berlebih', 'note' => 'Menyajikan tren, tidak mengklaim sebab-akibat.'],
                ],
            ],
            [
                'title'      => 'Terungkap: Menara 5G Melepaskan Radiasi yang Membuat Warga Lupa Ingatan',
                'source_name'=> 'SuaraKonspirasi',
                'excerpt'    => 'Sebuah kanal anonim mengklaim menara 5G di kawasan pemukiman memancarkan radiasi yang menyebabkan warga kehilangan ingatan jangka pendek. Klaim didukung oleh "kesaksian puluhan warga", namun tidak ada pengukuran alat, sertifikasi laboratorium, atau penjelasan mekanisme biologis apa pun.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Grandmaster,
                'category'   => 'kesehatan',
                'red_flags'  => [
                    ['type' => 'logical_fallacy', 'label' => 'Korelasi Dijual sebagai Sebab-Akibat', 'note' => 'Lupa ingatan punya banyak penyebab lain; tidak ada kontrol variabel.'],
                    ['type' => 'false_authority', 'label' => '"Puluhan Warga"', 'note' => 'Kesaksian anonim tanpa identitas bukan bukti ilmiah.'],
                    ['type' => 'no_evidence', 'label' => 'Tanpa Pengukuran', 'note' => 'Tidak ada angka radiasi, alat ukur, atau laboratorium independen.'],
                    ['type' => 'conspiracy_framing', 'label' => '"Terungkap" & "Ditutupi"', 'note' => 'Narasi konspirasi memakai kata "terungkap" agar terasa seperti rahasia besar.'],
                ],
            ],
            [
                'title'      => 'Makan Mi Instan Setiap Hari Bikin Ginjal Rusak dalam Seminggu',
                'source_name'=> 'SehatKilat',
                'excerpt'    => 'Unggahan yang dibagikan ribuan kali menyebut bahwa mengonsumsi mi instan setiap hari dapat merusak ginjal hanya dalam tujuh hari. Tidak ada nama peneliti, rumah sakit, atau jurnal yang disebutkan sebagai sumber.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'kesehatan',
                'red_flags'  => [
                    ['type' => 'no_evidence', 'label' => 'Tanpa Sumber', 'note' => 'Klaim medis tanpa penelitian, rumah sakit, atau nama ahli.'],
                    ['type' => 'absolut_claim', 'label' => 'Kata "Setiap Hari" & "Seminggu"', 'note' => 'Klaim absolut dan spesifik waktu adalah ciri khas hoax kesehatan.'],
                ],
            ],
            [
                'title'      => 'Pemkot Arunika Buka Pendaftaran Beasiswa Pelajar, Kuota 500 Penerima',
                'source_name'=> 'Portal Resmi Pemkot Arunika',
                'excerpt'    => 'Pemerintah Kota Arunika membuka pendaftaran program beasiswa bagi pelajar SMA dengan kuota 500 penerima. Pendaftaran dibuka hingga akhir bulan melalui portal resmi, dengan syarat kartu keluarga dan rapor dua semester terakhir.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'politik',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Portal Resmi', 'note' => 'Informasi berasal dari kanal pemerintah yang bisa dicek langsung.'],
                    ['type' => 'verifiable_data', 'label' => 'Syarat Jelas', 'note' => 'Kuota, tenggat, dan dokumen disebutkan secara spesifik.'],
                ],
            ],
            [
                'title'      => 'AWAS! Air Galon Isi Ulang Mengandung Bakteri Pemakan Otak',
                'source_name'=> 'WaspadaHarian',
                'excerpt'    => 'Pesan berantai menyebut air galon isi ulang di sebuah kota mengandung "bakteri pemakan otak" dan meminta penerima menyebarkannya ke sepuluh orang. Tidak ada nama dinas kesehatan, laboratorium, atau tanggal pengujian.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'kesehatan',
                'red_flags'  => [
                    ['type' => 'chain_message', 'label' => 'Minta Disebarkan', 'note' => 'Instruksi "sebarkan ke sepuluh orang" adalah pola hoax, bukan peringatan resmi.'],
                    ['type' => 'emotional_bait', 'label' => 'Istilah Menakutkan', 'note' => '"Bakteri pemakan otak" dirancang memicu panik, bukan memberi informasi.'],
                    ['type' => 'missing_date', 'label' => 'Tanpa Tanggal & Instansi', 'note' => 'Tidak ada dinas kesehatan atau hasil uji laboratorium yang disebut.'],
                ],
            ],
            [
                'title'      => 'Peringatan Dini: Potensi Hujan Lebat di Tiga Provinsi Akhir Pekan Ini',
                'source_name'=> 'Badan Meteorologi Nasional',
                'excerpt'    => 'Badan Meteorologi Nasional mengeluarkan peringatan dini potensi hujan lebat disertai angin kencang di tiga provinsi pada akhir pekan. Masyarakat diimbau menghindari area rawan longsor dan memantau pembaruan resmi setiap enam jam.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'bencana',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Instansi Berwenang', 'note' => 'Dikeluarkan badan yang memang bertugas memantau cuaca.'],
                    ['type' => 'actionable', 'label' => 'Imbauan Masuk Akal', 'note' => 'Anjuran bersifat preventif, bukan jualan atau ajakan politik.'],
                ],
            ],
        ];
    }

    /**
     * Bank tambahan: kasus sehari-hari yang paling sering ditemui remaja.
     * Ditulis terpisah supaya bank utama tetap mudah dibaca.
     */
    private function extraQuests(): array
    {
        return [
            [
                'title'      => 'VAKSIN BARU DISUNTIKKAN CHIP PELACAK KE TUBUH! INI FAKTA YANG DISEMBUNYIKAN',
                'source_name'=> 'SuaraRakyatBebas',
                'excerpt'    => 'Pesan berantai menyebut vaksin terbaru mengandung mikrochip yang bisa melacak lokasi penerimanya. "Sudah dibuktikan oleh seorang ahli dari luar negeri, tapi media tidak mau memberitakan," tulis pesan itu tanpa menyebut nama ahli atau negara mana pun.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Seeker,
                'category'   => 'kesehatan',
                'red_flags'  => [
                    ['type' => 'anonymous_authority', 'label' => 'Ahli Tanpa Nama', 'note' => '"Seorang ahli dari luar negeri" tidak bisa dilacak siapa pun.'],
                    ['type' => 'conspiracy', 'label' => 'Media Katanya Menyembunyikan', 'note' => 'Narasi "media tidak mau memberitakan" dipakai agar klaim tidak dituntut bukti.'],
                    ['type' => 'shouting', 'label' => 'Judul Kapital', 'note' => 'Seluruh judul huruf kapital adalah teknik menaikkan tekanan emosi.'],
                ],
            ],
            [
                'title'      => 'Badan Pusat Statistik: Inflasi Bulan Lalu Tercatat 0,29 Persen',
                'source_name'=> 'Badan Pusat Statistik',
                'excerpt'    => 'Badan Pusat Statistik merilis data inflasi bulan lalu sebesar 0,29 persen secara bulanan, dengan kelompok makanan bergejolak sebagai penyumbang terbesar. Data lengkap per komoditas tersedia dalam publikasi resmi dan dapat diunduh publik.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Knight,
                'category'   => 'ekonomi',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Lembaga Resmi', 'note' => 'BPS adalah instansi yang memang menerbitkan statistik nasional.'],
                    ['type' => 'verifiable_data', 'label' => 'Angka Spesifik', 'note' => '0,29 persen adalah angka dengan presisi yang bisa dicek di publikasi resmi.'],
                    ['type' => 'no_emotion', 'label' => 'Nada Netral', 'note' => 'Tidak ada ajakan menyebarkan atau kata-kata pemicu panik.'],
                ],
            ],
            [
                'title'      => 'MULAI BULAN DEPAN LISTRIK GRATIS 6 BULAN UNTUK SEMUA RUMAH!',
                'source_name'=> 'InfoKilatNusantara',
                'excerpt'    => 'Sebuah gambar tangkapan layar berisi pengumuman "listrik gratis enam bulan" beredar di grup WhatsApp. Gambar itu menyertakan logo perusahaan listrik negara, namun tanpa nomor surat, tanpa tanggal, dan tanpa tautan ke situs resmi mana pun.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'politik',
                'red_flags'  => [
                    ['type' => 'screenshot_only', 'label' => 'Hanya Tangkapan Layar', 'note' => 'Gambar bisa disunting siapa saja; tidak ada surat resmi yang bisa dibuka.'],
                    ['type' => 'no_registry', 'label' => 'Tanpa Nomor & Tanggal', 'note' => 'Kebijakan resmi selalu punya nomor surat dan tanggal berlaku.'],
                    ['type' => 'too_good', 'label' => 'Terlalu Menguntungkan', 'note' => 'Kebijakan sebesar ini tidak pernah diumumkan lewat pesan berantai.'],
                ],
            ],
            [
                'title'      => 'Gempa Magnitudo 5,2 Guncang Sukabumi, Tidak Berpotensi Tsunami',
                'source_name'=> 'Badan Meteorologi Nasional',
                'excerpt'    => 'Gempa berkekuatan magnitudo 5,2 berpusat di kedalaman 34 kilometer barat daya Sukabumi pada 14 Maret 2026 pukul 04.12 WIB. Badan Meteorologi Nasional menyatakan gempa tidak berpotensi tsunami dan tidak ada laporan kerusakan signifikan.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'bencana',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Sumber Berwenang', 'note' => 'Data kegempaan berasal dari badan yang memantau gempa.'],
                    ['type' => 'details', 'label' => 'Detail Teknis Lengkap', 'note' => 'Magnitudo, kedalaman, koordinat, dan waktu disebutkan semua.'],
                ],
            ],
            [
                'title'      => 'AWAS! MI INSTAN DIRENDAM 30 MENIT BERUBAH JADI PLASTIK, JANGAN DIMAKAN LAGI!!',
                'source_name'=> 'KabarSehatTerpercaya',
                'excerpt'    => 'Video berdurasi satu menit memperlihatkan mi instan yang direndam air selama 30 menit lalu ditarik memanjang seperti karet. Narator menyimpulkan mi instan "mengandung lilin dan plastik", dan meminta penonton menyebarkan video itu ke semua grup keluarga.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'kesehatan',
                'red_flags'  => [
                    ['type' => 'false_conclusion', 'label' => 'Kesimpulan Melompat', 'note' => 'Mi mengembang dan kenyal karena gluten serta pati — bukan karena plastik.'],
                    ['type' => 'chain_message', 'label' => 'Diminta Disebarkan', 'note' => 'Ajakan menyebar ke semua grup adalah pola pesan berantai, bukan peringatan resmi.'],
                    ['type' => 'no_lab', 'label' => 'Tanpa Uji Laboratorium', 'note' => 'Tidak ada hasil uji dari laboratorium mana pun yang disebut.'],
                ],
            ],
            [
                'title'      => 'Kementerian Kesehatan: Kasus Demam Berdarah Naik 18 Persen di Musim Hujan',
                'source_name'=> 'Kementerian Kesehatan',
                'excerpt'    => 'Kementerian Kesehatan melaporkan kenaikan kasus demam berdarah sebesar 18 persen dibanding periode yang sama tahun lalu. Masyarakat diminta melakukan pemberantasan sarang nyamuk setiap pekan, terutama di lingkungan sekolah dan permukiman padat.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Seeker,
                'category'   => 'kesehatan',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Instansi Berwenang', 'note' => 'Kementerian Kesehatan adalah sumber resmi untuk data penyakit menular.'],
                    ['type' => 'actionable', 'label' => 'Anjuran Praktis', 'note' => 'Imbauan bisa langsung dijalankan dan tidak meminta uang atau data pribadi.'],
                ],
            ],
            [
                'title'      => 'KOPI HITAM TANPA GULA SEMBUHKAN DIABETES DALAM 7 HARI, DIJAMIN 100%!',
                'source_name'=> 'HerbalAjaibID',
                'excerpt'    => 'Sebuah akun menjual kopi herbal dengan klaim mampu menyembuhkan diabetes dalam sepekan. "Sudah terbukti pada ribuan pasien, tidak perlu lagi minum obat dokter," tulis iklan itu, disertai foto-foto testimoni tanpa nama lengkap.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Seeker,
                'category'   => 'kesehatan',
                'red_flags'  => [
                    ['type' => 'absolute_claim', 'label' => 'Klaim Dijamin 100%', 'note' => 'Tidak ada pengobatan yang bisa menjamin kesembuhan total.'],
                    ['type' => 'harmful_advice', 'label' => 'Menyuruh Berhenti Obat', 'note' => 'Anjuran berhenti minum obat dokter bisa berbahaya bagi pasien diabetes.'],
                    ['type' => 'selling', 'label' => 'Tujuan Berjualan', 'note' => 'Klaim kesehatan dipakai untuk menjual produk, bukan memberi informasi.'],
                ],
            ],
            [
                'title'      => 'Riset Universitas Nusantara Timur: Kualitas Air Sungai Kota Meningkat 12 Poin',
                'source_name'=> 'Universitas Nusantara Timur',
                'excerpt'    => 'Tim peneliti Universitas Nusantara Timur merilis hasil pemantauan kualitas air sungai kota selama enam bulan. Indeks kualitas air naik 12 poin dibanding pengukuran sebelumnya, meski kadar mikroplastik di hilir masih di atas ambang yang disarankan.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Knight,
                'category'   => 'sains',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Lembaga Akademik', 'note' => 'Riset datang dari universitas dengan metode dan periode yang disebutkan.'],
                    ['type' => 'balanced', 'label' => 'Mengakui Kekurangan', 'note' => 'Tidak menyembunyikan bahwa mikroplastik masih tinggi — tanda laporan jujur.'],
                ],
            ],
            [
                'title'      => 'FOTO: PAUS RAKSASA TERBANG DI LANGIT BOGOR SAAT HUJAN DERAS',
                'source_name'=> 'ViralHariIni',
                'excerpt'    => 'Sebuah foto memperlihatkan seekor paus besar seolah melayang di atas awan Kota Bogor. Unggahan itu mendapat ribuan komentar, dan caption-nya menyebut fenomena tersebut "akibat pemanasan global yang tidak pernah diberitakan televisi".',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'sains',
                'red_flags'  => [
                    ['type' => 'impossible', 'label' => 'Melanggar Fisika', 'note' => 'Paus tidak bisa terbang; tidak ada mekanisme biologis yang memungkinkan.'],
                    ['type' => 'manipulated_image', 'label' => 'Gambar Hasil Suntingan', 'note' => 'Foto seperti ini biasanya hasil manipulasi atau AI generatif.'],
                    ['type' => 'conspiracy', 'label' => '"Tidak Diberitakan TV"', 'note' => 'Klaim penutupan informasi dipakai agar pembaca tidak mencari pembanding.'],
                ],
            ],
            [
                'title'      => 'Otoritas Jasa Keuangan Blokir 412 Entitas Pinjaman Online Ilegal',
                'source_name'=> 'Otoritas Jasa Keuangan',
                'excerpt'    => 'Otoritas Jasa Keuangan mengumumkan pemblokiran 412 entitas pinjaman online ilegal pada kuartal ini. Masyarakat diminta memeriksa daftar penyelenggara resmi sebelum mengunduh aplikasi pinjaman, dan melaporkan penawaran mencurigakan ke kanal pengaduan.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Knight,
                'category'   => 'ekonomi',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Regulator Resmi', 'note' => 'OJK adalah lembaga pengawas jasa keuangan yang berwenang mengumumkan hal ini.'],
                    ['type' => 'verifiable_data', 'label' => 'Angka Konkret', 'note' => 'Jumlah 412 entitas bisa dicocokkan dengan rilis resmi mereka.'],
                ],
            ],
            [
                'title'      => 'SELAMAT! NOMOR ANDA MENANG UNDIAN Rp150 JUTA, KIRIM KODE OTP UNTUK PENCAIRAN',
                'source_name'=> 'Panitia Undian Nasional',
                'excerpt'    => 'Pesan masuk mengabarkan nomor penerima memenangi undian Rp150 juta. Untuk mencairkan hadiah, penerima diminta mengirimkan kode OTP yang baru saja dikirim ke ponselnya, disertai batas waktu 10 menit sebelum hadiah hangus.',
                'verdict'    => QuestVerdict::Hoax,
                'difficulty' => QuestDifficulty::Novice,
                'category'   => 'penipuan',
                'red_flags'  => [
                    ['type' => 'never_share_otp', 'label' => 'Meminta Kode OTP', 'note' => 'Tidak ada undian sah yang butuh OTP. OTP adalah kunci akun, bukan bukti pemenang.'],
                    ['type' => 'urgency', 'label' => 'Batas Waktu 10 Menit', 'note' => 'Tekanan waktu dipakai agar korban tidak sempat berpikir atau bertanya.'],
                    ['type' => 'unsolicited', 'label' => 'Tidak Pernah Ikut', 'note' => 'Hadiah dari undian yang tidak pernah diikuti adalah pola penipuan klasik.'],
                ],
            ],
            [
                'title'      => 'Peringatan Gelombang Tinggi Hingga Empat Meter di Perairan Selatan Jawa',
                'source_name'=> 'Badan Meteorologi Nasional',
                'excerpt'    => 'Badan Meteorologi Nasional mengeluarkan peringatan gelombang tinggi hingga empat meter di perairan selatan Jawa pada 9-11 November 2026. Nelayan dan operator kapal kecil diminta menunda pelayaran, sementara wisatawan dihindari berenang di pantai berbatu.',
                'verdict'    => QuestVerdict::Fact,
                'difficulty' => QuestDifficulty::Seeker,
                'category'   => 'bencana',
                'red_flags'  => [
                    ['type' => 'clear_source', 'label' => 'Instansi Berwenang', 'note' => 'Peringatan maritim memang kewenangan badan meteorologi.'],
                    ['type' => 'time_bound', 'label' => 'Tanggal Jelas', 'note' => 'Ada rentang tanggal yang bisa dicek ulang di kanal resmi.'],
                ],
            ],
        ];
    }
}
