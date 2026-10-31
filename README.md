# 🛡️ Guardian Genesis (MIT-Week-DANG)

Proyek VILT Stack (Laravel 10, Inertia.js, Vue 3, Tailwind CSS).

---

## 🚀 Panduan Setup Awal (Setelah Clone)

Buka terminal di dalam folder proyek, lalu jalankan perintah ini secara berurutan:

bash
# 1. Install semua library PHP backend
composer install

# 2. Install semua library Node.js frontend
npm install

# 3. Copy file konfigurasi environment
cp .env.example .env

# 4. Buat key keamanan Laravel
php artisan key:generate


---

## 💻 Cara Menjalankan Aplikasi

Buka *dua tab terminal secara bersamaan* di folder proyek ini:

### 🛠️ Terminal 1: Jalankan Backend Laravel
bash
php artisan serve

Aplikasi otomatis berjalan di URL: http://127.0.0.1:8000

### 🎨 Terminal 2: Jalankan Frontend Vite
bash
npm run dev

Biarkan terminal ini tetap menyala agar kompilasi Vue 3 dan Tailwind CSS berjalan real-time.

---

## 📂 Lokasi File Utama
* Halaman Vue 3 ➡️ resources/js/Pages/
* Route Laravel ➡️ routes/web.php
* Style Tailwind ➡️ resources/css/app.css
*
