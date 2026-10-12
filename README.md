# Alsenform

Aplikasi ujian berbasis komputer (CBT) dan formulir untuk sekolah. Satu komputer di ruang guru cukup untuk melayani ratusan murid lewat Wi-Fi atau kabel LAN, tanpa perlu internet dan tanpa server sewaan.

Alsenform lahir dari kebutuhan sekolah yang ujiannya sering terganggu karena internet putus, sementara data nilai murid tidak ingin disimpan di layanan pihak lain. Guru menulis soal di editor atau langsung dari Microsoft Word, murid mengerjakan dari HP atau komputer lab, dan nilai keluar otomatis begitu jawaban dikirim.

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue-3-42B883?logo=vuedotjs&logoColor=white)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14%2B-4169E1?logo=postgresql&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![Lisensi MIT](https://img.shields.io/badge/lisensi-MIT-blue)

---

## Daftar isi

- [Fitur](#fitur)
- [Keamanan](#keamanan)
- [Kebutuhan sistem](#kebutuhan-sistem)
- [Instalasi](#instalasi)
- [Menjalankan di jaringan sekolah](#menjalankan-di-jaringan-sekolah)
- [Memperbarui aplikasi](#memperbarui-aplikasi)
- [Pengembangan](#pengembangan)
- [Kontribusi](#kontribusi)
- [Lisensi](#lisensi)

---

## Fitur

### Menyusun soal

Editor soal mirip Google Forms, tetapi dibuat untuk ujian sekolah. Ada 11 tipe pertanyaan:

| Tipe | Kegunaan di sekolah |
| --- | --- |
| Pilihan ganda | Soal PG biasa, termasuk benar/salah |
| Kotak centang | PG kompleks dengan lebih dari satu jawaban benar |
| Drop-down | Pilihan dari daftar |
| Isian singkat | Jawaban pendek, bisa berupa angka atau rentang angka |
| Paragraf | Uraian atau esai (dinilai manual) |
| Kisi pilihan ganda | Menjodohkan, atau benar/salah untuk beberapa pernyataan sekaligus |
| Kisi kotak centang | Menjodohkan dengan lebih dari satu pasangan |
| Skala linear | Angket atau penilaian diri, 0–10 |
| Rating | Bintang |
| Tanggal | Isian tanggal |
| Waktu | Isian jam |

Fitur editor lainnya:

- **Rumus matematika** ditulis dengan sintaks KaTeX di antara tanda dolar, misalnya `$x^2 + 2x + 1$`. Ada tombol f(x) untuk pecahan, akar, pangkat, dan simbol.
- **Bahasa Arab dan Al-Qur'an**: font Amiri Quran, Scheherazade New, dan Noto Naskh Arabic, lengkap dengan panduan pintasan keyboard untuk harakat dan tanda waqaf.
- **Gambar dan video** di soal maupun pilihan jawaban. Gambar otomatis diperkecil dan diubah ke WebP supaya ringan dibuka dari HP murid.
- **Bobot nilai per soal** (bawaan 1 poin) dan kunci jawaban untuk koreksi otomatis.
- **Template siap pakai**: Pilihan Ganda PTS/PAS, PAI & Bahasa Arab, Matematika & Eksakta, Esai & Uraian, dan Refleksi Belajar.
- **Folder, pencarian, duplikat, dan tempat sampah** dengan fitur pulihkan.
- **Kolaborasi**: beberapa guru bisa menyunting ujian yang sama. Kalau dua orang menyimpan dari salinan yang berbeda, perubahan yang lebih baru tidak akan tertimpa diam-diam.
- **Draf lokal**: perubahan disimpan juga di browser, jadi soal yang belum tersimpan tidak hilang saat listrik padam.

### Impor dan ekspor

- **Impor dari Microsoft Word (.docx).** Templat Word bisa diunduh dari aplikasi, berisi contoh untuk setiap tipe soal. Cukup ketik nomor soal, pilihan A–E, dan baris `Jawaban:`. Gambar dan rumus ikut terbaca. Baris tambahan `Tipe:`, `Poin:`, `Wajib:`, dan `Skala:` bersifat opsional. Soal yang perlu dicek ulang ditandai setelah impor.
- **Impor dari ExamView** lewat ekspor Blackboard 6.0–9.0 (.zip), termasuk gambar soal.
- **Impor data murid** dari CSV atau tempel langsung dari Excel. Pemisah koma, titik koma, atau tab dikenali otomatis.
- **Ekspor nilai ke Excel (.xlsx)** dengan dua lembar: rekap nilai (skor, nilai 0–100, status tuntas sesuai KKM, waktu selesai) dan jawaban tiap murid dengan warna benar/salah serta persentase jawaban benar per soal.
- **Ekspor laporan aktivitas** ke Excel.
- **Cadangan semua ujian** milik guru dalam satu berkas JSON.

### Pelaksanaan ujian

- Murid membaca ringkasan ujian (jumlah soal, soal wajib, total poin, durasi) lalu menekan **Kerjakan Sekarang**. Timer baru berjalan sejak tombol itu ditekan.
- **Timer dijaga server.** Batas waktu dihitung di server, bukan dari jam HP murid. Saat waktu habis, jawaban yang sudah diisi terkirim otomatis.
- **Jawaban aman walau layar mati atau offline.** Selama waktu belum habis, jawaban tetap tersimpan (di perangkat dan di server) dan murid bisa melanjutkan kapan saja. Ujian baru dikirim saat waktunya habis: oleh halaman ujian itu sendiri, atau oleh server satu menit setelah waktu habis bila halamannya tertutup atau offline (statusnya "Otomatis" di rekap nilai). Bila perangkat murid tersambung lagi, jawaban di perangkat itu melengkapi jawaban yang terkirim.
- Ujian **tanpa batas waktu** tidak pernah dikirim otomatis: jawaban tersimpan sampai murid menekan **Kirim Jawaban**.
- **Jawaban tersimpan berlapis**: di perangkat murid dan di server. Kalau Wi-Fi putus, HP mati, atau murid pindah perangkat, jawaban bisa dilanjutkan setelah login lagi.
- **Kirim ulang otomatis** saat koneksi lemah. Untuk ujian yang dibatasi satu kali pengisian, kiriman yang terulang dikenali sebagai kiriman yang sama.
- Panel nomor soal dengan warna status, acak urutan soal, beberapa soal per halaman, dan indikator kemajuan.
- Tampilan disusun untuk layar HP: pilihan berhuruf yang besar, kisi soal berubah menjadi kartu, dan timer di bagian atas layar.
- **Kuis publik tanpa akun.** Saat mempublikasikan, guru memilih data yang wajib diisi peserta di layar konfirmasi: Nama Lengkap, Kelas (7A–9D), dan Email. Setelah ujian dimulai, data itu dikunci. Nama peserta tamu tampil di rekap nilai, berkas Excel, panel buka kunci, dan laporan aktivitas. Nama dan kelas yang sama pada lebih dari satu jawaban diberi tanda "Nama ganda".
- Bisa dipakai untuk **survei atau angket** tanpa timer dan tanpa login. Ada opsi pengumpulan email, batas satu kali pengisian, pesan konfirmasi, dan ringkasan hasil.

### Pengawasan dan anti-curang

- **Kunci saat pindah tab (lock on blur).** Begitu murid meninggalkan halaman ujian, sesinya dikunci di server dan setiap perpindahan dicatat beserta jamnya.
- Murid yang terkunci langsung muncul di tab **Keamanan & Kunci** milik guru, lengkap dengan jumlah pindah tab dan jamnya. Guru membuka kunci satu per satu atau sekaligus; halaman murid terbuka sendiri dalam beberapa detik. Selama terkunci, jawaban tidak hilang, waktu tetap berjalan, dan jawaban terkirim otomatis bila waktu habis.
- Kunci bisa dinyalakan atau dimatikan **sebelum maupun selama ujian**. Murid yang sedang mengerjakan ikut berubah dalam ±15 detik; mematikannya membuka semua murid yang terkunci.
- **Pembatasan peserta per kelompok (cohort)**: ujian hanya bisa dibuka oleh murid di kelas atau kelompok yang dipilih.
- **Laporan aktivitas** (lihat di bawah) menunjukkan perangkat dan browser yang dipakai saat mulai dan mengirim ujian, jadi murid yang berganti perangkat di tengah ujian ikut terlihat.

### Penilaian

- Koreksi otomatis untuk semua tipe soal yang punya kunci jawaban. Uraian tidak dinilai otomatis, tetapi jawabannya bisa dibaca di rekap dan di berkas Excel (ditandai abu-abu).
- Nilai akhir selalu dalam rentang **0–100**: jumlah poin benar dibagi poin maksimal soal yang dinilai otomatis.
- **Kunci jawaban bisa diperbaiki setelah ujian.** Nilai seluruh murid langsung dihitung ulang dari kunci yang baru, termasuk di berkas Excel.
- Rekap di aplikasi menampilkan poin dan nilai setiap murid serta ringkasan jawaban per soal. Rata-rata, KKM, dan status tuntas ada di berkas Excel.

### Laporan aktivitas

Menu **Laporan** mencatat setiap login, logout, mulai ujian, dan kirim jawaban:

- Jam kejadian dalam zona waktu sekolah (WIB secara bawaan).
- Jenis perangkat (HP, tablet, komputer) dan model HP bila browser menyebutkannya, misalnya Samsung SM-A536E atau Xiaomi Redmi Note 11.
- Sistem operasi dan nama browser lengkap dengan versinya, misalnya Android 13 dengan Samsung Internet 26, atau Windows 10/11 dengan Microsoft Edge 129.
- Alamat IP di jaringan sekolah.

Data bisa disaring per tanggal, jenis aktivitas, ujian, peran, atau dicari berdasarkan nama, NIS, NIP, dan IP. Selama periode yang dipilih mencakup hari ini, halaman ini diperbarui otomatis tiap 30 detik, jadi bisa dipakai memantau murid yang sudah masuk saat ujian dimulai. Admin melihat semua aktivitas. Guru melihat login murid dan aktivitas di ujian miliknya.

### Pengguna dan kelas

- Tiga peran: **admin**, **guru**, dan **murid**. Admin masuk dengan email, guru dengan NIP, dan murid dengan NIS.
- Guru mengelola akun murid. Admin mengelola semua akun, termasuk guru dan admin lain.
- Murid baru mendapat password awal dari 6 digit terakhir NIS dan **wajib menggantinya saat pertama kali login**.
- **Cohort**: kelompok murid yang bisa dibuat manual atau disinkronkan dari data kelas.
- Halaman profil dengan foto, daftar sesi login aktif di perangkat lain beserta tombol untuk mengeluarkannya, dan preferensi bawaan untuk ujian baru.

### Server untuk jaringan lokal

Perintah `php artisan lan:serve` menyiapkan semuanya untuk hari ujian:

- Menjalankan server dengan banyak worker (bawaan 24) supaya ratusan murid tidak mengantre.
- Menampilkan alamat yang bisa dibuka murid untuk setiap jaringan aktif (Wi-Fi 5 GHz, 2,4 GHz, kabel LAN), termasuk nama Bonjour/mDNS untuk perangkat Apple.
- Di macOS, mencegah komputer tertidur selama server berjalan (caffeinate).
- Memberi tahu alamat baru kalau IP server berubah di tengah ujian.
- Memperbarui database dan cache secara otomatis setelah aplikasi di-update.
- Bisa berjalan di latar belakang (`--daemon`), dengan `lan:status` dan `lan:stop` untuk mengelolanya. Kalau port sedang dipakai, perintah ini menawarkan untuk menghentikan proses lama atau pindah ke port lain.

Supaya halaman tetap cepat di jaringan yang padat, aset dikompresi gzip dan disimpan di cache browser, font dari internet tidak menghambat tampilan saat offline, dan alamat media tidak terikat pada IP server.

### Hal kecil yang membantu

- **Pusat bantuan** di dalam aplikasi, dengan panduan terpisah untuk guru dan murid, termasuk aturan lengkap impor Word.
- **Halaman error berbahasa Indonesia** dengan petunjuk yang jelas, misalnya saat sesi habis, akses ditolak, berkas terlalu besar, atau server sedang pemeliharaan.
- **Pemberitahuan saat server tidak bisa dihubungi**, lengkap dengan tombol coba lagi, supaya murid tidak bingung ketika Wi-Fi putus.

---

## Keamanan

Data ujian dan nilai murid adalah data sensitif. Berikut perlindungan yang sudah ada di Alsenform:

**Soal dan kunci jawaban**

- Kunci jawaban tidak pernah dikirim ke browser murid. Soal yang dikirim ke murid sudah dibersihkan dari kunci jawaban.
- Soal baru dikirim setelah murid menekan Kerjakan Sekarang, sehingga tidak bisa diintip dari kode halaman sebelum ujian dimulai.
- Skor dihitung di server dari jawaban yang tersimpan, bukan dari angka yang dikirim browser.
- Batas waktu dicek di server (dengan toleransi 60 detik untuk koneksi lambat). Jawaban yang masuk setelah batas itu ditandai "waktu habis".
- Jawaban dari sesi yang sedang terkunci ditolak oleh server.

**Akun dan login**

- Password disimpan dengan bcrypt.
- Tidak ada pendaftaran akun terbuka. Semua akun dibuat oleh guru atau admin.
- Password awal murid wajib diganti saat login pertama. Sampai password diganti, murid hanya bisa membuka halaman ganti password dan halaman ujian.
- Percobaan login dibatasi 5 kali sebelum harus menunggu.
- Sesi disimpan di database dan bisa dicabut dari halaman profil (keluar dari perangkat lain).
- Setiap login dan logout tercatat di laporan aktivitas beserta perangkat dan alamat IP.

**Hak akses**

- Halaman guru (editor, data murid, cohort, laporan) tertutup untuk akun murid. Halaman manajemen pengguna hanya untuk admin.
- Guru hanya bisa menyunting dan mengunduh nilai ujian miliknya atau ujian tempat ia menjadi kolaborator.
- Sesi ujian terikat ke akun murid. Murid lain tidak bisa melanjutkan atau mengirim jawaban dari sesi orang lain, termasuk di komputer lab yang dipakai bergantian.

**Perlindungan permintaan**

- Semua formulir dilindungi token CSRF Laravel.
- Pembatasan jumlah permintaan (rate limit) dihitung per murid, bukan per IP. Satu kelas di belakang router yang sama tidak ikut terblokir karena ulah satu perangkat, tetapi tetap ada batas per IP untuk perangkat yang bermasalah.
- Peserta tamu dikenali lewat cookie terenkripsi yang tidak bisa dipalsukan.
- Proxy hanya dipercaya dari alamat lokal (misalnya terowongan Expose), sehingga header IP palsu dari luar diabaikan.

**Unggahan berkas**

- Media soal hanya menerima gambar (JPG, PNG, GIF, WebP) dan video (MP4, WebM, MOV), dan dicek dari isi berkasnya, bukan hanya ekstensinya. Gambar diproses ulang sebelum disimpan.
- Batas ukuran: Word 10 MB, paket ExamView 64 MB, CSV murid 5 MB.
- Berkas ZIP ExamView dibaca langsung dari memori tanpa diekstrak ke disk, jadi isinya tidak bisa menulis ke folder lain. XML di dalamnya diproses tanpa akses jaringan, sehingga aman dari serangan XXE.

**Halaman error dan log**

- Dalam mode produksi (`APP_DEBUG=false`), halaman error tidak pernah menampilkan pesan teknis, query database, atau isi exception. Yang tampil hanya penjelasan dan waktu kejadian untuk dicocokkan dengan log server.
- Data pribadi di laporan aktivitas (IP, perangkat) hanya bisa dilihat guru dan admin, sesuai batas hak akses di atas.

**Yang perlu dilakukan pengelola server**

- Ganti password akun admin awal segera setelah instalasi.
- Pastikan `APP_ENV=production` dan `APP_DEBUG=false` di berkas `.env`. Perintah `php artisan exam:optimize --production` bisa mengaturnya.
- Kalau server dibuka ke internet (bukan hanya jaringan sekolah), pasang HTTPS di depannya.
- Cadangkan database secara berkala, misalnya dengan `pg_dump` (PostgreSQL) atau `mysqldump` (MySQL).

Kalau menemukan celah keamanan, mohon laporkan secara pribadi ke pengelola repositori lebih dulu, jangan lewat issue publik.

---

## Kebutuhan sistem

- PHP 8.2 atau lebih baru, dengan ekstensi `pdo_pgsql` atau `pdo_mysql`, `zip`, `gd`, `dom`, dan `mbstring`
- PostgreSQL 14 atau lebih baru, atau MySQL 8
- Node.js 20 atau lebih baru (hanya untuk build aset)
- Composer 2

Sudah dipakai di Mac Mini M2 untuk ujian serentak sekitar 200 murid. Linux dan Windows juga bisa. Fitur anti-sleep khusus untuk macOS. Di macOS, [Laravel Herd](https://herd.laravel.com) adalah cara termudah untuk memasang PHP dan database sekaligus.

---

## Instalasi

```bash
git clone https://github.com/alaihsan/alsenform.git
cd alsenform

composer install
npm install
npm run build

cp .env.example .env
php artisan key:generate
```

Buat database kosong, lalu sesuaikan bagian ini di `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=alsenform
DB_USERNAME=postgres
DB_PASSWORD=

APP_DISPLAY_TIMEZONE=Asia/Jakarta
```

Untuk MySQL, ganti `DB_CONNECTION=mysql` dan `DB_PORT=3306`, lalu sesuaikan nama pengguna dan passwordnya.

Lalu buat tabel dan akun awal:

```bash
php artisan migrate --seed
php artisan storage:link
```

Seeder membuat akun admin `superadmin@alsenform.test` dengan password `superadmin`. Masuk dengan akun itu, **segera ganti passwordnya**, lalu tambahkan guru dan murid dari menu Pengguna.

---

## Menjalankan di jaringan sekolah

```bash
php artisan lan:serve
```

Alamat untuk murid akan muncul di layar, misalnya `http://192.168.1.10:8000`. Pastikan perangkat murid tersambung ke router yang sama dan fitur *AP Isolation* di router dimatikan.

| Perintah | Fungsi |
| --- | --- |
| `php artisan lan:serve` | Menjalankan server di port 8000 |
| `php artisan lan:serve --port=8001` | Memakai port lain |
| `php artisan lan:serve --workers=32` | Menambah worker untuk peserta yang lebih banyak |
| `php artisan lan:serve --daemon` | Berjalan di latar belakang |
| `php artisan lan:status` | Melihat status dan alamat server latar belakang |
| `php artisan lan:stop` | Menghentikan server |
| `php artisan exam:optimize --production` | Menyiapkan cache dan mode produksi sebelum ujian besar |
| `php artisan exam:submit-expired` | Mengirim jawaban ujian yang waktunya habis tetapi belum terkirim. `lan:serve` menjalankannya tiap menit, dan ujian yang dibuka guru selalu diperiksa dulu, jadi biasanya tidak perlu dijalankan manual |

Tips untuk hari ujian:

- Atur **DHCP reservation** (IP tetap) untuk komputer server di router, supaya alamat yang dibagikan ke murid tidak berubah.
- Pakai Wi-Fi 5 GHz bila bisa. Kabel LAN adalah pilihan paling stabil untuk komputer server.
- Jangan me-restart server di tengah ujian. Jawaban murid memang tetap aman, tetapi mereka tidak bisa mengirim selama server mati.

---

## Memperbarui aplikasi

```bash
git pull origin main
composer install
npm install
npm run build
```

Setelah itu, hentikan server (Ctrl+C) lalu jalankan lagi `php artisan lan:serve`. Perintah ini otomatis menjalankan migrasi database dan memperbarui cache. Kalau aplikasi dijalankan dengan cara lain (misalnya lewat Herd), jalankan `php artisan migrate --force` secara manual.

Kalau langkah itu terlewat, aplikasi memperbaikinya sendiri. Begitu ada halaman yang gagal karena tabel baru belum ada, database langsung diperbarui dan halaman dibuka ulang.

---

## Pengembangan

Dibangun dengan Laravel 12, Inertia.js 2, Vue 3 (TypeScript), Tailwind CSS 3, dan PostgreSQL atau MySQL. Tes memakai Pest.

```bash
composer dev                         # server, queue, dan Vite dengan hot reload
php artisan test                     # menjalankan semua tes
vendor/bin/pint                      # merapikan kode PHP
npm run lint && npm run format       # merapikan kode frontend
```

Tes membutuhkan database PostgreSQL terpisah bernama `alsenform_testing` (lihat `phpunit.xml`).

Beberapa bagian kode yang menarik untuk dibaca:

| Lokasi | Isi |
| --- | --- |
| `app/Http/Controllers/QuizResponseController.php` | Alur ujian murid: membuka, mulai, menyimpan draf, mengirim |
| `app/Support/QuizScoring.php` | Koreksi otomatis dan perhitungan nilai 0–100 |
| `app/Services/DocxImportService.php` | Pembaca soal dari Word |
| `app/Services/ExamViewImportService.php` | Pembaca paket ExamView/Blackboard |
| `app/Services/QuizResultsReport.php` | Berkas Excel rekap nilai |
| `app/Support/UserAgent.php` | Pengenal perangkat dan browser untuk laporan aktivitas |
| `app/Console/Commands/ServeLanCommand.php` | Perintah `lan:serve` |
| `resources/js/pages/PublicQuiz.vue` | Halaman ujian murid |
| `resources/js/pages/FormEditor.vue` | Editor soal |

---

## Kontribusi

Laporan bug, usulan fitur, dan pull request sangat diterima. Beberapa hal yang membantu:

- Untuk bug, sertakan langkah untuk mengulanginya, perangkat atau browser yang dipakai, dan potongan `storage/logs/laravel.log` bila ada.
- Untuk pull request, sertakan tes untuk perubahan yang dibuat, lalu pastikan `php artisan test`, `vendor/bin/pint`, dan `npm run lint` berjalan bersih.
- Teks yang dilihat guru dan murid ditulis dalam bahasa Indonesia yang baku dan mudah dipahami.

Kalau sekolah Anda memakai Alsenform, kabari kami lewat issue. Masukan dari ruang kelas sangat berharga.

---

## Lisensi

Alsenform dirilis dengan lisensi [MIT](https://opensource.org/licenses/MIT). Bebas dipakai, diubah, dan dibagikan, termasuk untuk sekolah lain.
