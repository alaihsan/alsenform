# Falcon Forms

**Formulir dan ujian tetap jalan, walau internet putus.**

Falcon Forms · dikembangkan oleh Falcon Education Partner

Falcon Forms adalah aplikasi formulir dan ujian dari Falcon Education Partner untuk sekolah, madrasah, bimbel, dan lembaga pelatihan. Pasang di satu komputer di jaringan lokal Anda: peserta tersambung lewat Wi-Fi atau kabel LAN, tetap bisa mengerjakan, dan jawabannya tetap tersimpan saat internet putus. Tanpa sewa server. Janji "tetap jalan walau internet putus" berlaku untuk pemasangan di jaringan lokal seperti ini; bila dipasang di hosting online, peserta tetap membutuhkan internet.

Sudah dipakai untuk ujian serentak sekitar 200 siswa dengan satu komputer server. Kapasitas di lembaga Anda bergantung pada komputer server dan jaringan Wi-Fi; komputer kecil seperti Raspberry Pi 3 tidak disarankan untuk ujian serentak.

Falcon Forms lahir dari kebutuhan sekolah yang ujiannya sering terganggu karena internet putus, sementara data nilai siswa tidak ingin disimpan di layanan pihak lain. Guru menulis soal di editor atau langsung dari Microsoft Word, siswa mengerjakan dari HP atau komputer lab, dan nilai keluar otomatis begitu jawaban dikirim.

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
- [Kode galat halaman](#kode-galat-halaman)
- [Pengembangan](#pengembangan)
- [Kontribusi](#kontribusi)
- [Lisensi](#lisensi)

---

## Fitur

### Menyusun soal

Editor soal berbentuk formulir yang mudah dikenali, dibuat untuk ujian sekolah. Ada 11 tipe pertanyaan:

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

- **Format teks**: sorot teks di soal, deskripsi, pilihan jawaban, baris/kolom kisi, bagian, judul & deskripsi, atau pembahasan, lalu pop-up muncul dengan tombol **Tebal**, **Miring**, **Garis bawah**, **spidol** lima warna (kuning, hijau, biru, merah muda, oranye), hapus warna, dan hapus semua format. Pintasan Ctrl+B / Ctrl+I / Ctrl+U juga bisa. Berlaku juga untuk teks Arab, dan tampil sama di halaman siswa, hasil, dan penilaian. Teks yang ditempel masuk tanpa format. Di HP pop-up muncul di bawah teks yang disorot.
- **Rumus** ditulis dengan sintaks KaTeX di antara tanda dolar, misalnya `$x^2 + 2x + 1$`, dan rumus kimia dengan mhchem, misalnya `$\ce{H2SO4}$` atau `$\ce{N2 + 3H2 <=> 2NH3}$`. Katalog rumus siap klik tersedia sebagai dua add-on: **Rumus Matematika** dan **Rumus IPA** (lihat Add-on).
- **Bahasa Arab dan Al-Qur'an**: font Amiri Quran, Scheherazade New, dan Noto Naskh Arabic, lengkap dengan panduan pintasan keyboard untuk harakat dan tanda waqaf. Kolom yang isinya sebagian besar huruf Arab otomatis ditulis dari kanan ke kiri; tombol RTL untuk mengatur arahnya sendiri tersedia sebagai add-on **Teks Arab (RTL)**.
- **Gambar dan video** di soal maupun pilihan jawaban. Gambar otomatis diperkecil dan diubah ke WebP supaya ringan dibuka dari HP siswa.
- **Bagian**: tombol "Tambah bagian" di bilah samping memecah ujian menjadi halaman. Setiap bagian punya judul dan deskripsi; siswa pindah bagian dengan Selanjutnya/Sebelumnya atau panel Nomor Soal. Bila "Acak urutan soal" aktif, soal diacak di dalam bagiannya saja.
- **Pindah ke bagian lain**: di akhir setiap bagian ada pilihan "Setelah bagian N": lanjut ke bagian berikutnya, buka bagian tertentu, atau langsung kirim formulir. Soal pilihan ganda dan drop-down punya tombol "Bagian sesuai jawaban", sehingga setiap pilihan jawaban bisa membuka bagian yang berbeda (misalnya tes peminatan IPA/IPS). Tombol Selanjutnya, Sebelumnya, dan Kirim Jawaban mengikuti jalur siswa. Soal di bagian yang terlewati tidak wajib diisi, tidak dikirim, dan tidak dihitung di nilai maksimal siswa itu; aturannya diterapkan juga di server, termasuk saat ujian dikirim otomatis karena waktu habis.
- **Judul & deskripsi**: teks saja (petunjuk, bacaan, cerita), tidak dijawab, tidak bernomor, dan tidak dinilai. Bagian dan judul baru disisipkan di bawah kartu yang sedang dipilih.
- **Bobot nilai per soal** (bawaan 1 poin) dan kunci jawaban untuk koreksi otomatis.
- **Label soal**: mapel, kelas, topik, dan tingkat kesulitan (mudah, sedang, sulit) lewat tombol **Label** di tiap soal. Label hanya untuk guru dan tidak pernah dikirim ke siswa.
- **Templat siap pakai**: Pilihan Ganda PTS/PAS, PAI & Bahasa Arab, Matematika & Eksakta, Esai & Uraian, dan Refleksi Belajar.
- **Folder, pencarian, duplikat, dan tempat sampah** dengan fitur pulihkan.
- **Kolaborasi**: beberapa guru bisa menyunting ujian yang sama. Kalau dua orang menyimpan dari salinan yang berbeda, perubahan yang lebih baru tidak akan tertimpa diam-diam.
- **Draf lokal**: perubahan disimpan juga di browser, jadi soal yang belum tersimpan tidak hilang saat listrik padam.

### Impor dan ekspor

- **Impor dari Microsoft Word (.docx).** Templat Word bisa diunduh dari aplikasi, berisi contoh untuk setiap tipe soal. Cukup ketik nomor soal, pilihan A–E, dan baris `Jawaban:`. Gambar dan rumus ikut terbaca. Baris tambahan `Tipe:`, `Poin:`, `Wajib:`, dan `Skala:` bersifat opsional. Soal yang perlu dicek ulang ditandai setelah impor.
- **Impor dari ExamView** lewat ekspor Blackboard 6.0–9.0 (.zip), termasuk gambar soal.
- **Bank soal.** Setiap soal yang ditulis guru tersimpan otomatis sebagai cadangan, juga setelah soal atau ujiannya dihapus. Di **Import Soal → Bank Soal**, guru mencari soal lamanya (teks soal atau pilihan jawaban), menyaring per tipe atau asal ujian, mencentang yang dibutuhkan, lalu menambahkannya lengkap dengan kunci, poin, dan gambar. Soal yang sudah ada di ujian yang sedang dibuka ditandai. Bank soal hanya terlihat oleh guru pemiliknya.
- **Halaman Bank Soal** (menu akun atau dasbor) untuk mengelola semua soal: saring per mapel, kelas, topik, tingkat kesulitan, tipe, atau asal ujian; lihat soal beserta kuncinya; beri label ke banyak soal sekaligus; arsipkan soal yang tidak dipakai lagi; dan buat ujian baru langsung dari soal yang dicentang.
- **Impor data siswa** dari CSV atau tempel langsung dari Excel. Pemisah koma, titik koma, atau tab dikenali otomatis.
- **Penilaian esai & isian** (tab Jawaban → "Nilai esai & isian"): satu soal untuk semua siswa sekaligus, dengan tombol cepat 0 / ½ / Penuh, nilai desimal, komentar untuk siswa, mode **penilaian buta** (nama disembunyikan) dan saringan "belum dinilai". Setiap nilai tersimpan sendiri. Esai dan berkas yang belum dinilai tidak dihitung dulu (nilai ditandai "sementara"); setelah dinilai, poinnya ikut dalam nilai, rekap, Excel ("Nilai guru: 3/4") dan Remedial. Jawaban isian singkat yang ditolak kunci karena beda penulisan (misalnya "Kota Bandung") bisa diterima manual.
- **Soal Unggah berkas**: siswa mengunggah foto pekerjaan di kertas, PDF, Word, Excel atau PowerPoint (paling banyak 5 berkas per soal, ukuran mengikuti "Batas ukuran berkas"). Berkas tersimpan di server sekolah (bukan di folder publik) dan hanya dapat dibuka guru ujian itu, langsung dari layar penilaian.
- **Hasil & pembahasan untuk siswa**: setiap soal punya kolom Pembahasan (tidak pernah dikirim ke siswa selama ujian). Pengaturan → "Tampilkan hasil ke siswa": nilai saja, atau lengkap dengan jawaban benar, pembahasan dan komentar guru. Siswa membukanya dari layar selesai, kartu ujian di beranda ("Lihat Hasil"), atau halaman ujian setelah kesempatan habis.
- **Jadwal ujian** (Pengaturan → Keamanan & Batas): waktu buka dan tutup. Sebelum dibuka siswa melihat hitung mundur dan halaman terbuka sendiri tepat waktu; sesudah ditutup tidak ada yang bisa mulai. Waktu siswa yang sedang mengerjakan habis paling lambat saat ujian ditutup, lalu jawabannya terkirim otomatis. Kartu "Menerima jawaban" di tab Jawaban dan beranda siswa menunjukkan jadwalnya. Guru pemilik dan kolaborator tetap dapat mencoba ujian di luar jadwal.
- **Tombol Ragu-ragu** di setiap soal ujian: nomornya menjadi kuning di panel Nomor Soal, dan sebelum mengirim siswa diingatkan soal mana yang masih ragu-ragu. Tanda ini tersimpan bersama draf di server (ikut pindah perangkat) dan dikirim bersama jawaban, juga saat waktu habis atau ujian terkunci.
- **Peta Kelas** di tab Keamanan & Kunci: semua siswa yang sedang ujian dalam satu layar, diperbarui tiap 5 detik. Setiap kartu menunjukkan jumlah soal terjawab, jumlah soal ragu-ragu, pindah tab, dan sisa waktu. Siswa yang perlu dilihat tampil paling depan: Terkunci (bisa dibuka langsung dari kartunya), Keluar halaman, Terputus (tidak ada kontak lebih dari 1 menit), dan Diam (tidak ada jawaban baru selama 5 menit). Kartu bisa disaring menurut status.
- **Ekspor nilai ke Excel (.xlsx)**: rekap nilai (skor, nilai 0–100, status tuntas sesuai KKM, waktu selesai) dan jawaban tiap siswa dengan warna benar/salah serta persentase jawaban benar per soal, ditambah lembar dari add-on yang aktif (misalnya Analisis Butir Soal).
- **Ekspor laporan aktivitas** ke Excel.
- **Cadangan semua ujian** milik guru dalam satu berkas JSON.

### Add-on

Kemampuan tambahan dipasang sekali, lalu dinyalakan atau dimatikan per ujian. Rekap Kuis, Rumus Matematika, Rumus IPA dan Teks Arab (RTL) berbeda: add-on itu ada selama terpasang, tanpa sakelar per ujian.

- **Galeri add-on**: tombol puzzle di editor (Jelajahi add-on), atau menu akun → Add-on. Add-on tampil sebagai kartu dalam grid (bisa disaring: semua, terpasang, belum dipasang); tombol **Detail** menjelaskan apa yang ditambahkannya dan data apa yang dibacanya. Setiap pemasangan dan pelepasan diminta konfirmasi dulu.
- **Pasang** untuk diri sendiri; **admin** juga bisa memasang untuk semua guru (hanya admin yang bisa melepasnya).
- **Per ujian**: tombol puzzle di editor menampilkan add-on yang terpasang dengan sakelar "Aktif di ujian ini". Yang menentukan adalah pemilik ujian: kolaborator melihat hasil yang sama, termasuk di berkas Excel.
- **Melepas** add-on mematikannya di semua ujian pemiliknya tanpa menghapus data; saat dipasang lagi, pilihan tiap ujian kembali seperti semula.
- Add-on pertama, **Analisis Butir Soal**, menambah panel analisis di tab Jawaban, lembar ketiga di berkas Excel, dan tombol "Jadikan label kesulitan". Saat tidak aktif, ketiganya hilang dan analisis tidak dihitung sama sekali; di tab Jawaban muncul petunjuk kecil untuk memasang atau mengaktifkannya.
- **Token Ujian**: siswa harus mengetik token 6 huruf dari pengawas di layar konfirmasi sebelum "Kerjakan Sekarang". Token tampil di tab Keamanan & Kunci, lengkap dengan hitung mundur dan tampilan layar penuh untuk proyektor. Token berganti setiap 15 menit (menit :00, :15, :30, :45) dan dihitung dari ujian serta jam server, jadi tidak tersimpan di mana pun; token sebelumnya masih diterima 5 menit lagi. Huruf besar/kecil dan spasi diabaikan, huruf I dan O tidak dipakai. Token salah dibatasi 10 kali per menit per siswa. Siswa yang sudah mulai tidak perlu token lagi saat memuat ulang, dan guru pemilik/kolaborator dapat mencoba ujiannya tanpa token. Add-on ini tidak langsung aktif setelah dipasang: nyalakan per ujian di tab Keamanan & Kunci atau tombol puzzle.
- **Peta Miskonsepsi** (metode CRI): dari tombol Ragu-ragu, setiap jawaban dikelompokkan menjadi Paham (benar & yakin), Menebak (benar tapi ragu), Belum paham (salah & ragu, atau kosong), dan Miskonsepsi (salah tapi yakin). Soal yang ≥ 25% pesertanya salah tapi yakin (minimal 5 peserta) ditandai, lengkap dengan jawaban keliru yang paling diyakini siswa. Memakai percobaan pertama tiap siswa; jawaban lama tanpa data ragu tidak dihitung. Bila kurang dari 30% siswa memakai tombol Ragu-ragu, panel memperingatkan bahwa angka miskonsepsi bisa terlalu tinggi. Laporan Excel mendapat lembar "Peta Miskonsepsi" per soal dan per siswa.
- **Desainer Formulir**: tombol palet di editor membuka desainer dengan pratinjau langsung. Ada 12 tema siap pakai (Klasik Falcon, Ungu Tua, Mode Malam, Madrasah, Ceria SD, Kertas Ujian, Biru Tegas, Samudra, Senja, Hutan, Minimalis, Kontras Tinggi), lalu semuanya bisa diatur: warna aksen, latar, kartu dan teks (dengan peringatan bila teks sulit dibaca), kekeruhan kartu, gambar header 1600 × 400 di atas judul, logo sekolah (kiri/tengah/kanan), foto latar dengan peredup, huruf judul/halaman/soal/jawaban, ukuran judul dan teks soal, rata judul, sudut dan bayangan kartu, bentuk tombol, lebar halaman, serta CSS sendiri lewat kelas `.af-*` (misalnya `.af-question-title`). Desain tampil di editor guru, di semua halaman siswa (layar konfirmasi, soal, selesai, hasil, halaman tunggu) dan di kartu ujian di beranda guru maupun siswa. CSS sendiri hanya berlaku di halaman ujian itu dan dibersihkan di server: `@import`, `expression()`, dan gambar dari luar server sekolah dibuang. Mematikan add-on mengembalikan tampilan biasa tanpa menghapus desainnya.
- **Remedial & Pengayaan**: panel di tab Jawaban membagi siswa menjadi Belum Tuntas dan Tuntas menurut KKM (dari Profil) memakai nilai terbaik tiap siswa. Tombol "Buat ujian remedial" membuat draf ujian dari soal yang dijawab salah oleh siswa terpilih, atau dari soal lain setopik di Bank Soal (sebanyak soal yang salah per topik); "Buat ujian pengayaan" mengambil soal setopik berlabel sulit (lalu sedang), paling banyak 10, atau ujian kosong untuk ditulis sendiri. Ujian tindak lanjut memakai aturan ujian asli, hanya dapat dibuka siswa yang dipilih (dan tampil di beranda mereka saja), lalu nilainya dicatat di ujiannya sendiri sehingga nilai asli tidak berubah. Berkas Excel ujian asli mendapat lembar "Remedial & Pengayaan": nilai asli, nilai remedial/pengayaan, dan nilai akhir (nilai remedial paling tinggi sama dengan KKM, tidak pernah di bawah nilai asli). Siswa tamu (tanpa akun) tidak dapat dibatasi, jadi ujian untuk mereka terbuka bagi siapa pun yang punya tautannya.
- **Rekap Kuis**: setelah dipasang, ikon Rekap Kuis muncul di menu Aplikasi di Beranda guru (dan hilang lagi bila add-on dilepas). Halamannya menampilkan nilai siswa dari semua kuis milik guru itu dalam satu tabel: kolom No, Nama, lalu satu kolom per kuis (urut dari kuis paling lama) berisi nilai terbaik tiap siswa (0–100); kosong bila belum mengerjakan, bertanda * bila masih ada esai atau berkas yang belum dinilai. Filter kelas menampilkan semua siswa kelas itu, termasuk yang belum mengerjakan. Tombol **Unduh Excel** menyimpan tabel yang sama (ditambah kolom Kelas) sebagai berkas XLSX. Formulir yang tidak dinilai (survei) tidak ikut.
- **Rumus Matematika**: tombol Rumus Matematika muncul di bilah alat setiap soal di editor bagi guru yang memasangnya. Katalognya berisi 650 rumus LaTeX dalam 11 topik: Bilangan & Operasi, Aljabar (identitas, persamaan linear dan kuadrat, nilai mutlak, suku banyak, eksponen dan logaritma, bilangan kompleks), Fungsi, Trigonometri, Geometri (bangun datar dan ruang, dimensi tiga, geometri analitik, irisan kerucut, transformasi), Barisan & Deret (termasuk matematika keuangan), Kalkulus (limit, turunan, integral), Matriks & Vektor, Statistika & Peluang, Himpunan & Logika, serta Simbol & Notasi (huruf Yunani, kurung, panah, aksen, teks, spasi dan warna). Setiap rumus tampil dengan pratinjau KaTeX, nama, keterangan dan kode LaTeX-nya; ada pencarian, tombol pensil untuk mengubah rumus sebelum disisipkan, dan kotak uji LaTeX bebas dengan pratinjau langsung.
- **Rumus IPA**: tombol Rumus IPA di bilah alat setiap soal membuka 581 rumus Fisika (besaran dan satuan, gerak lurus, parabola dan melingkar, dinamika, usaha dan energi, momentum, elastisitas, rotasi dan kesetimbangan, gravitasi, fluida statis dan dinamis, suhu dan kalor, termodinamika, getaran dan gelombang, bunyi, optik geometri dan fisis, listrik statis dan dinamis, medan magnet, induksi dan arus bolak-balik, fisika modern, fisika inti), Kimia (penulisan rumus dengan `\ce{...}`, persamaan reaksi dan tanda panah, struktur atom, stoikiometri, konsentrasi, sifat koligatif, termokimia, laju reaksi, kesetimbangan, asam basa, penyangga dan hidrolisis, Ksp, redoks dan elektrokimia, kimia organik, kimia unsur) dan Biologi (fotosintesis dan respirasi, enzim, genetika, genetika populasi, ekologi, fisiologi, sel dan pertumbuhan). Setiap rumus disertai keterangan simbol dan satuannya. Reaksi kimia memakai mhchem, jadi indeks, muatan ion, wujud zat, endapan/gas dan panah kesetimbangan tertulis benar.
- Kedua add-on rumus hanya menambah katalog di editor: rumus yang sudah ditulis di soal tetap tampil untuk guru dan siswa walaupun add-on dilepas, dan `$...$` tetap bisa diketik langsung tanpa add-on. Admin dapat memasangnya untuk semua guru sekaligus.
- **Teks Arab (RTL)**: tombol RTL muncul di bilah alat setiap soal di editor bagi guru yang memasangnya. Tombol itu membalik arah kolom pertanyaan menjadi kanan ke kiri selama menyunting (tombolnya berwarna hijau saat aktif), sehingga kalimat Arab, harakat dan tanda waqaf tersusun benar walau bercampur huruf Latin; tekan lagi untuk kembali. Tanpa add-on ini kolom yang sebagian besar berisi huruf Arab tetap otomatis kanan ke kiri, dan teks Arab tetap tampil dengan font Mushaf Madinah untuk guru dan siswa. Bila tidak ada add-on rumus maupun Teks Arab (RTL) yang terpasang, bilah alat di atas soal tidak ditampilkan.

- **Filter Cerdas**: tombol **Buka Filter Cerdas** di tab Jawaban membuka hasil ujian dalam mode Excel layar penuh, dengan kolom dan isi yang sama dengan berkas Excel: No, Nama, NIS, Kelas, Email, nilai, skor, keterangan tuntas, status, waktu selesai, percobaan, lalu jawaban setiap soal (hijau benar, merah salah, abu-abu tidak dinilai otomatis atau soal yang tidak didapat siswa). Kotak **Cari** (Ctrl+F) mencari di semua kolom yang tampil tanpa membedakan huruf besar/kecil maupun tanda aksen. Tombol di setiap judul kolom membuka menu urutkan (naik/turun) dan saringan: berisi teks, centang nilai tertentu (dengan pencarian dan "hanya ini"), rentang angka atau tanggal, serta jawaban benar/salah/belum dinilai. Saringan yang berlaku tampil sebagai label yang bisa dihapus satu per satu; menu **Kolom** menyembunyikan kolom yang tidak perlu. Klik sel untuk membaca isinya utuh (misalnya jawaban esai), lalu bilah bawah menghitung rata-rata, nilai tertinggi, terendah dan jumlah tuntas dari baris yang tampil. Tombol **CSV** mengunduh baris dan kolom yang sedang tampil (terbuka rapi di Excel versi Indonesia). Kolom No dan Nama tetap terlihat saat tabel digeser. Data baru dimuat saat tabel dibuka dan hanya baris yang terlihat yang digambar, jadi tetap ringan untuk ratusan siswa; saringan, urutan dan kolom tersembunyi diingat di browser itu untuk setiap ujian. Yang dapat membukanya hanya pemilik ujian, kolaborator dan admin.

### Pelaksanaan ujian

- Siswa membaca ringkasan ujian (jumlah soal, soal wajib, total poin, durasi) lalu menekan **Kerjakan Sekarang**. Timer baru berjalan sejak tombol itu ditekan.
- **Timer dijaga server.** Batas waktu dihitung di server, bukan dari jam HP siswa. Saat waktu habis, jawaban yang sudah diisi terkirim otomatis.
- **Jawaban aman walau layar mati atau offline.** Selama waktu belum habis, jawaban tetap tersimpan (di perangkat dan di server) dan siswa bisa melanjutkan kapan saja. Ujian baru dikirim saat waktunya habis: oleh halaman ujian itu sendiri, atau oleh server satu menit setelah waktu habis bila halamannya tertutup atau offline (statusnya "Otomatis" di rekap nilai). Bila perangkat siswa tersambung lagi, jawaban di perangkat itu melengkapi jawaban yang terkirim.
- Ujian **tanpa batas waktu** tidak pernah dikirim otomatis: jawaban tersimpan sampai siswa menekan **Kirim Jawaban**.
- **Jawaban tersimpan berlapis**: di perangkat siswa dan di server. Kalau Wi-Fi putus, HP mati, atau siswa pindah perangkat, jawaban bisa dilanjutkan setelah login lagi.
- **Kirim ulang otomatis sampai berhasil** saat koneksi lemah atau server sibuk, selama halaman terbuka. Saat halaman ditutup, kiriman yang belum sampai dan jawaban yang belum tersimpan di server tetap dikirim. Kiriman yang terulang dikenali sebagai kiriman yang sama, jadi tidak tercatat dua kali.
- Saat koneksi putus, muncul pemberitahuan kecil di layar; siswa tetap bisa mengerjakan, dan jawaban dikirim otomatis begitu koneksi pulih.
- Panel nomor soal dengan warna status, beberapa soal per halaman, dan indikator kemajuan.
- **Acak urutan soal dan urutan pilihan jawaban** per siswa. Urutannya ditentukan server dan tetap sama saat halaman dimuat ulang atau dibuka di perangkat lain. Pilihan seperti "Semua jawaban benar" atau "A dan C" tidak ikut diacak karena bergantung pada posisinya.
- **Soal acak per siswa**: tiap siswa hanya mendapat sebagian soal, diambil acak dari ujian. Bisa berdasarkan jumlah soal, atau per tingkat kesulitan (misalnya 5 mudah, 3 sedang, 2 sulit) dari label soal. Nilai dihitung dari soal yang didapat masing-masing siswa.
- Tampilan disusun untuk layar HP: pilihan berhuruf yang besar, kisi soal berubah menjadi kartu, dan timer di bagian atas layar.
- **Formulir publik tanpa akun.** Saat mempublikasikan, guru memilih data yang wajib diisi peserta di layar konfirmasi: Nama Lengkap, Kelas (7A–9D), dan Email. Setelah ujian dimulai, data itu dikunci. Nama peserta tamu tampil di rekap nilai, berkas Excel, panel buka kunci, dan laporan aktivitas. Nama dan kelas yang sama pada lebih dari satu jawaban diberi tanda "Nama ganda".
- **Batas pengerjaan per siswa** (Settings → Kesempatan mengerjakan): 1 kali, beberapa kali (sampai 10), atau tanpa batas. Setiap pengerjaan disimpan dengan jawaban dan nilainya sendiri, jadi nilai lama dan terbaru sama-sama ada di rekap (ditandai "Percobaan 1/2", "2/2") dan di berkas Excel (kolom Percobaan). Siswa melihat sisa kesempatannya setelah mengirim jawaban. Analisis butir soal memakai percobaan pertama tiap siswa.
- Bisa dipakai untuk **survei atau angket** tanpa timer dan tanpa login. Ada opsi pengumpulan email, batas pengisian, pesan konfirmasi, dan ringkasan hasil.

### Pengawasan dan anti-curang

- **Kunci saat pindah tab.** Begitu siswa meninggalkan halaman ujian, sesinya dikunci di server dan setiap perpindahan dicatat beserta jamnya.
- Siswa yang terkunci langsung muncul di tab **Keamanan & Kunci** milik guru, lengkap dengan jumlah pindah tab dan jamnya. Guru membuka kunci satu per satu atau sekaligus; halaman siswa terbuka sendiri dalam beberapa detik. Selama terkunci, jawaban tidak hilang, waktu tetap berjalan, dan jawaban terkirim otomatis bila waktu habis.
- Kunci bisa dinyalakan atau dimatikan **sebelum maupun selama ujian**, dan langsung berlaku tanpa pemberitahuan ke siswa: begitu dinyalakan, saat siswa berikutnya meninggalkan halaman ujian ia terkunci; mematikannya membuka semua siswa yang terkunci dalam beberapa detik.
- Jawaban **dibekukan saat terkunci**: yang dihitung adalah jawaban di halaman pada saat siswa meninggalkannya. Perubahan di balik layar kunci (misalnya lewat developer tools) tidak disimpan dan tidak ikut dinilai.
- Pindah tab, membuka aplikasi lain, atau meminimalkan browser **langsung mengunci**, juga saat Wi-Fi putus: layar kunci muncul seketika di perangkat siswa dan kuncinya dilaporkan ke server begitu koneksi pulih (jawaban yang dihitung tetap jawaban saat ia pergi).
- **Muat ulang halaman (reload) tidak dikunci**, asalkan halaman yang sama dimuat ulang dan kembali dalam 20 detik. Menutup tab lalu membuka ujian lagi, membuka situs lain di tab yang sama lalu menekan Kembali, atau pergi lebih lama tetap mengunci sesinya.
- Selama kunci aktif, teks soal **tidak bisa disalin, dipotong, diseret, atau dicetak**, dan menu klik kanan dimatikan.
- **Pembatasan peserta per kelompok**: ujian hanya bisa dibuka oleh siswa di kelas atau kelompok yang dipilih.
- **Laporan aktivitas** (lihat di bawah) menunjukkan perangkat dan browser yang dipakai saat mulai dan mengirim ujian, jadi siswa yang berganti perangkat di tengah ujian ikut terlihat.

### Penilaian

- Koreksi otomatis untuk semua tipe soal yang punya kunci jawaban. Uraian tidak dinilai otomatis, tetapi jawabannya bisa dibaca di rekap dan di berkas Excel (ditandai abu-abu).
- Nilai akhir selalu dalam rentang **0–100**: jumlah poin benar dibagi poin maksimal soal yang dinilai otomatis.
- **Kunci jawaban bisa diperbaiki setelah ujian.** Nilai seluruh siswa langsung dihitung ulang dari kunci yang baru, termasuk di berkas Excel.
- Rekap di aplikasi menampilkan poin dan nilai setiap siswa serta ringkasan jawaban per soal. Rata-rata, KKM, dan status tuntas ada di berkas Excel.
- **Rekap nilai** bisa diurutkan menurut setiap kolom (klik judul kolom: naik, klik lagi: turun), termasuk nama depan atau nama belakang. Tombol **A–Z** membuka saringan huruf awal nama depan dan nama belakang seperti di Moodle; huruf tanpa siswa diredupkan, dan saringan yang aktif tampil sebagai label kecil yang bisa dihapus. Di HP, urutan dipilih dari menu Urutkan.
- Tab **Responses** menampilkan jumlah siswa yang sudah mengirim jawaban (tiap siswa dihitung sekali) dan yang sedang mengerjakan. Angkanya diperbarui langsung setiap 5 detik, dan rekap nilai ikut bertambah tanpa memuat ulang halaman.
- **Analisis butir soal** (add-on, lihat di bawah) di tab hasil dan di berkas Excel: tingkat kesukaran (P), daya pembeda (D, kelompok atas dan bawah 27%), dan pengecoh yang tidak berfungsi (dipilih kurang dari 5% siswa), lalu saran **Dipakai**, **Revisi**, atau **Ganti** beserta alasannya untuk tiap soal. Hasilnya bisa dijadikan label tingkat kesulitan di Bank Soal dengan satu klik.
  - Tiap soal dihitung dari siswa yang mendapatnya. Daya pembeda, pengecoh, dan keputusan baru muncul setelah minimal 10 siswa mengerjakan soal itu; di bawah itu hanya tingkat kesukaran yang ditampilkan, karena hasilnya masih untung-untungan.
  - Siswa bernilai sama di batas kelompok atas atau bawah dihitung sebagian, jadi hasilnya selalu sama, tidak bergantung pada urutan masuknya jawaban, dan sama persis antara aplikasi dan berkas Excel.

### Laporan aktivitas

Menu **Laporan** mencatat setiap login, logout, mulai ujian, dan kirim jawaban:

- Jam kejadian dalam zona waktu sekolah (WIB secara bawaan).
- Jenis perangkat (HP, tablet, komputer) dan merek serta model HP, misalnya Samsung SM-A536E, OPPO CPH2591, atau Xiaomi Redmi Note 11.
- Sistem operasi dan nama browser lengkap dengan versinya, misalnya Android 14 dengan Chrome 129.0.6668.100, atau Windows 11 dengan Microsoft Edge 129.0.2792.79.
- Alamat IP perangkat di jaringan sekolah (juga saat server mendengarkan di IPv6).

Chrome dan Edge menyembunyikan merek HP, versi Android/Windows, dan versi lengkap browser dari User-Agent. Aplikasi memintanya lewat User-Agent Client Hints, tetapi browser hanya mengirimnya lewat **HTTPS**. Di server LAN biasa (HTTP), data yang disembunyikan dicatat sebagai tidak diketahui, misalnya "HP Android (merek tidak dikirim browser)", tidak pernah ditebak. Browser lain seperti Samsung Internet, Mi Browser, Opera, dan browser di dalam aplikasi (WhatsApp) tetap menyebutkan modelnya. iPad tercatat sebagai iPad dengan iPadOS, bukan Mac, walaupun Safari atau Chrome di iPad membuka versi desktop (bawaan sejak iPadOS 13); iPhone yang meminta versi desktop tercatat sebagai iPhone. Tablet Android dalam mode desktop dan Brave juga dikenali, di HTTP maupun HTTPS.

Data bisa disaring per tanggal, jenis aktivitas, ujian, peran, atau dicari berdasarkan nama, NIS, NIP, dan IP. Selama periode yang dipilih mencakup hari ini, halaman ini diperbarui otomatis tiap 30 detik, jadi bisa dipakai memantau siswa yang sudah masuk saat ujian dimulai. Admin melihat semua aktivitas. Guru melihat login siswa dan aktivitas di ujian miliknya.

### Pengguna dan kelas

- Tiga peran: **admin**, **guru**, dan **siswa**. Admin masuk dengan email, guru dengan NIP, dan siswa dengan NIS.
- Guru mengelola akun siswa. Admin mengelola semua akun, termasuk guru dan admin lain.
- Siswa baru mendapat password awal dari 6 digit terakhir NIS dan **wajib menggantinya saat pertama kali login**.
- **Kelompok**: kelompok siswa yang bisa dibuat manual atau disinkronkan dari data kelas.
- Halaman profil dengan foto, daftar sesi login aktif di perangkat lain beserta tombol untuk mengeluarkannya, dan preferensi bawaan untuk ujian baru.

### Server untuk jaringan lokal

Perintah `php artisan lan:serve` menyiapkan semuanya untuk hari ujian:

- Menjalankan server dengan banyak worker (bawaan 24) supaya siswa yang mengirim jawaban bersamaan tidak mengantre.
- Berjalan di port **2026** dengan alamat intranet **`http://falcon.lan:2026`**. Saat menyala, perintah ini memeriksa apakah DNS router sudah mengarahkan `falcon.lan` ke server; bila belum (atau masih ke IP lama), ia menuliskan entri DNS yang perlu ditambahkan di router.
- Menampilkan juga alamat IP untuk setiap jaringan aktif (Wi-Fi 5 GHz, 2,4 GHz, kabel LAN) sebagai cadangan. Nama berakhiran `.local` (misalnya `alsenform.local`) tidak dipakai lagi.
- Bila server Falcon Forms lama masih berjalan di port 8000 (alamat lama seperti `http://alsenform.local:8000`), perintah ini menawarkan untuk menghentikannya, supaya siswa tidak tertinggal di alamat lama.
- Memperingatkan pengaturan `.env` yang membuat login gagal (419) lewat `http://falcon.lan:2026`: `SESSION_DOMAIN` yang terisi dan `SESSION_SECURE_COOKIE=true`.
- Di macOS, mencegah komputer tertidur selama server berjalan (caffeinate).
- Memberi tahu alamat baru kalau IP server berubah di tengah ujian.
- Memperbarui database dan cache secara otomatis setelah aplikasi di-update.
- Memperingatkan bila server hanya tersambung lewat Wi-Fi (sebaiknya kabel LAN) dan bila AirDrop/Handoff (AWDL) aktif di Mac server, karena keduanya membuat halaman siswa lambat atau terputus.
- Bisa berjalan di latar belakang (`--daemon`), dengan `lan:status` dan `lan:stop` untuk mengelolanya. Kalau port sedang dipakai, perintah ini menawarkan untuk menghentikan proses lama atau pindah ke port lain.

Supaya halaman tetap cepat di jaringan yang padat:

- Aset dikompresi gzip dan disimpan di cache browser, dan alamat media tidak terikat pada IP server.
- Font dari internet baru diambil setelah halaman selesai dimuat. Di intranet tanpa internet, permintaan ke Google Fonts bisa menggantung lama; dulu itu membuat halaman terus "loading". Sekarang halaman langsung selesai dengan font bawaan perangkat.
- Pindah halaman di dalam aplikasi menunggu paling lama 15 detik (kirim formulir, termasuk login, 20 detik). Kalau koneksi Wi-Fi tersendat, halaman dicoba lagi otomatis dua kali sambil menampilkan "Koneksi lambat, mencoba lagi...", lalu ada tombol Coba Lagi. Login yang gagal tersambung tidak menghapus password, jadi siswa cukup menekan Masuk lagi.
- **Cek Koneksi** (`/cek-koneksi`, juga ditautkan dari halaman login dan Bantuan): dibuka di perangkat yang terasa lambat, tes 10 detik mengukur waktu respons, permintaan yang gagal, dan kecepatan unduh, lalu memisahkan waktu di server dari waktu di jaringan. Hasilnya langsung menunjukkan apakah masalahnya di server, di Wi-Fi, atau di pengaturan perangkat (proxy, VPN, antivirus), beserta sarannya.
- `php artisan exam:optimize` menurunkan `BCRYPT_ROUNDS` di `.env` menjadi 10 (bawaan PHP). Pemeriksaan password saat login turun dari sekitar 230 ms menjadi 60 ms waktu prosesor, sehingga satu kelas yang login bersamaan tidak membuat server sibuk. Password lama otomatis disimpan ulang dengan pengaturan baru saat pemiliknya login.

### Hal kecil yang membantu

- **Pusat bantuan** di dalam aplikasi, dengan panduan terpisah untuk guru dan siswa, termasuk aturan lengkap impor Word.
- **Halaman error berbahasa Indonesia** dengan petunjuk yang jelas, misalnya saat sesi habis, akses ditolak, berkas terlalu besar, atau server sedang pemeliharaan.
- **Pemberitahuan saat server tidak bisa dihubungi**, lengkap dengan tombol coba lagi, supaya siswa tidak bingung ketika Wi-Fi putus.
- **Tidak ada lagi halaman putih kosong.** Bila halaman tidak dapat ditampilkan, layar menunjukkan kode, artinya, dan langkah yang perlu dilakukan (lihat [Kode galat halaman](#kode-galat-halaman)). Kejadiannya tercatat di Laporan Aktivitas sebagai "Halaman gagal dimuat", lengkap dengan perangkat, browser, halaman, dan pesan galatnya, jadi admin bisa melihat siapa yang terkena dan mengapa.
- **Jalan di perangkat lama**: tampilan dibuat untuk Chrome 73, Safari/iOS 12.2, Firefox 68, Edge 79, dan yang lebih baru. Fungsi baru yang belum ada di browser lama dilengkapi oleh halaman itu sendiri, browser yang menolak penyimpanan lokal (mode privat, cookie diblokir) tetap bisa dipakai, dan berkas tampilan yang gagal terunduh dicoba sekali lagi otomatis. Build gagal dengan pesan jelas bila ada kode yang memakai regex *lookbehind*, yang dulu membuat halaman putih di iPhone/iPad dengan iOS di bawah 16.4.

---

## Keamanan

Data ujian dan nilai siswa adalah data sensitif. Berikut perlindungan yang sudah ada di Falcon Forms:

**Soal dan kunci jawaban**

- Kunci jawaban tidak pernah dikirim ke browser siswa. Soal yang dikirim ke siswa sudah dibersihkan dari kunci jawaban.
- Soal baru dikirim setelah siswa menekan Kerjakan Sekarang, sehingga tidak bisa diintip dari kode halaman sebelum ujian dimulai.
- Skor dihitung di server dari jawaban yang tersimpan, bukan dari angka yang dikirim browser.
- Batas waktu dicek di server (dengan toleransi 60 detik untuk koneksi lambat). Jawaban yang masuk setelah batas itu ditandai "waktu habis".
- Jawaban yang masuk lebih dari 2 menit setelah waktu habis, dari halaman yang masih tersambung saat waktu habis, tidak dipakai: yang dinilai adalah jawaban yang sudah tersimpan di server. Dari perangkat yang memang offline saat waktu habis, jawabannya diterima tetapi ditandai "Terlambat" beserta lamanya.
- Penyimpanan jawaban sementara ditolak setelah waktu habis, dan ukuran kiriman jawaban dibatasi.
- Jawaban dari sesi yang sedang terkunci ditolak oleh server.
- Ujian hanya bisa dikirim dari sesi yang sudah dimulai lewat tombol Kerjakan Sekarang. Setiap pengerjaan punya sesi sendiri, jadi jawaban tidak bisa dikirim berulang kali dari sesi yang sudah selesai atau dengan identitas peserta yang dipalsukan.
- Siswa hanya menerima soal yang didapatnya; jawaban untuk soal lain diabaikan server.
- Kode buka kunci berupa 40 karakter acak yang tidak bisa ditebak.

**Akun dan login**

- Password disimpan dengan bcrypt.
- Tidak ada pendaftaran akun terbuka. Semua akun dibuat oleh guru atau admin.
- Password awal siswa wajib diganti saat login pertama. Sampai password diganti, siswa hanya bisa membuka halaman ganti password dan halaman ujian.
- Percobaan login dibatasi 5 kali sebelum harus menunggu.
- Sesi disimpan di database dan bisa dicabut dari halaman profil (keluar dari perangkat lain).
- Setiap login dan logout tercatat di laporan aktivitas beserta perangkat dan alamat IP.

**Hak akses**

- Halaman guru (editor, data siswa, kelompok, laporan) tertutup untuk akun siswa. Halaman manajemen pengguna hanya untuk admin.
- Guru hanya bisa menyunting dan mengunduh nilai ujian miliknya atau ujian tempat ia menjadi kolaborator.
- Sesi ujian terikat ke akun siswa. Siswa lain tidak bisa melanjutkan atau mengirim jawaban dari sesi orang lain, termasuk di komputer lab yang dipakai bergantian.

**Perlindungan permintaan**

- Semua formulir dilindungi token CSRF Laravel.
- Pembatasan jumlah permintaan (rate limit) dihitung per siswa, bukan per IP. Satu kelas di belakang router yang sama tidak ikut terblokir karena ulah satu perangkat, tetapi tetap ada batas per IP untuk perangkat yang bermasalah.
- Peserta tamu dikenali lewat cookie terenkripsi yang tidak bisa dipalsukan.
- Proxy hanya dipercaya dari alamat lokal (misalnya terowongan Expose), sehingga header IP palsu dari luar diabaikan.

**Unggahan berkas**

- Media soal hanya menerima gambar (JPG, PNG, GIF, WebP) dan video (MP4, WebM, MOV), dan dicek dari isi berkasnya, bukan hanya ekstensinya. Gambar diproses ulang sebelum disimpan.
- Batas ukuran: Word 10 MB, paket ExamView 64 MB, CSV siswa 5 MB.
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

Sudah dipakai untuk ujian serentak sekitar 200 siswa dengan satu komputer server; kapasitas di lembaga lain bergantung pada komputer server dan jaringan Wi-Fi (Raspberry Pi 3 tidak disarankan untuk ujian serentak). Linux dan Windows juga bisa. Fitur anti-sleep khusus untuk macOS. Di macOS, [Laravel Herd](https://herd.laravel.com) adalah cara termudah untuk memasang PHP dan database sekaligus.

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

Seeder membuat akun admin `superadmin@falconforms.test` dengan password `superadmin` (instalasi yang dibuat sebelum berganti nama memakai `superadmin@alsenform.test`). Masuk dengan akun itu, **segera ganti passwordnya**, lalu tambahkan guru dan siswa dari menu Pengguna.

---

## Menjalankan di jaringan sekolah

```bash
php artisan lan:serve
```

Siswa membuka **`http://falcon.lan:2026`**. Alamat IP server juga muncul di layar, misalnya `http://192.168.1.10:2026`, dan selalu bisa dipakai. Pastikan perangkat siswa tersambung ke router yang sama dan fitur *AP Isolation* di router dimatikan.

### Mengaktifkan alamat falcon.lan

Nama `falcon.lan` dijawab oleh DNS router, jadi router perlu diberi tahu sekali:

1. Atur **DHCP Reservation** (IP tetap) untuk komputer server di router, misalnya `192.168.1.10`.
2. Tambahkan **DNS statis** di router: `falcon.lan` → `192.168.1.10`. Letaknya tergantung merek router, misalnya MikroTik: *IP → DNS → Static*; OpenWrt: *Network → DHCP and DNS → Hostnames*; pfSense/OPNsense: *Services → DNS Resolver → Host Overrides*; banyak router rumahan: *LAN → DHCP/DNS → Static DNS* atau *Local DNS*.
3. Jalankan `php artisan lan:serve`. Bila DNS sudah benar, layar menulis `http://falcon.lan:2026 (alamat intranet → 192.168.1.10)`; bila belum, layar menulis entri DNS yang masih perlu ditambahkan.

Perangkat harus memakai DNS dari router (bawaan Wi-Fi sekolah). Perangkat yang memakai DNS lain (misalnya 8.8.8.8, "DNS Pribadi" di Android, "DNS aman" di Chrome yang diatur ke Google/Cloudflare, VPN, atau iCloud Private Relay) tidak mengenal `falcon.lan` dan tetap bisa memakai alamat IP. Nama lain bisa dipakai dengan `LAN_HOST` di berkas `.env` (misalnya `LAN_HOST=ujian.sekolah.lan`); kosongkan untuk hanya menampilkan alamat IP.

Di berkas `.env` server, isi `APP_URL=http://falcon.lan:2026` dan biarkan `SESSION_DOMAIN=null`. Ketik alamatnya lengkap dengan `http://` dan port saat pertama kali (`http://falcon.lan:2026`), lalu simpan sebagai bookmark; tanpa itu sebagian browser menganggap `falcon.lan` sebagai kata pencarian. Data yang disimpan browser (draf jawaban di perangkat, filter, pilihan tampilan) terikat pada alamat, jadi pindah alamat sebaiknya tidak di tengah ujian; jawaban yang sudah tersimpan di server tetap aman.

| Perintah | Fungsi |
| --- | --- |
| `php artisan lan:serve` | Menjalankan server di port 2026 (`http://falcon.lan:2026`) |
| `php artisan lan:serve --port=2027` | Memakai port lain |
| `php artisan lan:serve --workers=32` | Menambah worker untuk peserta yang lebih banyak |
| `php artisan lan:serve --daemon` | Berjalan di latar belakang |
| `php artisan lan:status` | Melihat status dan alamat server latar belakang |
| `php artisan lan:stop` | Menghentikan server |
| `php artisan lan:check` | Memeriksa mengapa `http://falcon.lan:2026` belum berfungsi, beserta cara memperbaikinya |
| `php artisan exam:optimize --production` | Menyiapkan cache dan mode produksi sebelum ujian besar |
| `php artisan exam:submit-expired` | Mengirim jawaban ujian yang waktunya habis tetapi belum terkirim. `lan:serve` menjalankannya tiap menit, dan ujian yang dibuka guru selalu diperiksa dulu, jadi biasanya tidak perlu dijalankan manual |

### Bila alamat baru belum berfungsi

Jalankan `php artisan lan:check` di komputer server. Perintah ini memeriksa satu per satu dan menulis cara memperbaiki setiap tanda ✗:

- **Tampilan**: `public/build` sudah di-build sesuai kode terbaru (`npm run build`).
- **`public/hot`**: berkas sisa `npm run dev` / `composer dev`. Selama berkas ini ada, setiap halaman mengambil tampilan dari server pengembangan yang sudah mati, sehingga tampil putih (FF-201). `lan:serve` kini menghapusnya sendiri.
- **`.env`**: `APP_URL`, `LAN_HOST`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`.
- **Server**: berjalan di port 2026, menjawab di alamat IP-nya, dan tidak ada server lama di port 8000.
- **Firewall macOS**: tidak memblokir koneksi masuk ke PHP.
- **Router**: ditanya langsung seperti perangkat siswa bertanya, apakah mengenal `falcon.lan` dan mengarahkannya ke server ini; juga apakah DNS yang dibagikan DHCP mengenalnya.

Yang tidak ikut diperbarui oleh `git pull` (dan harus diubah sendiri): berkas `.env`, hasil build (`npm run build`), `public/hot`, skrip atau pintasan lain yang menjalankan server dengan `--port=8000`, serta pengaturan DNS dan DHCP di router.

Tips untuk hari ujian:

- Atur **DHCP reservation** (IP tetap) untuk komputer server di router, supaya alamat yang dibagikan ke siswa tidak berubah dan `falcon.lan` tetap mengarah ke server.
- Pakai Wi-Fi 5 GHz bila bisa. Kabel LAN adalah pilihan paling stabil untuk komputer server.
- Jangan me-restart server di tengah ujian. Jawaban siswa memang tetap aman, tetapi mereka tidak bisa mengirim selama server mati.

---

## Memperbarui aplikasi

```bash
git pull origin main
composer install
npm install
npm run build
```

Setelah itu, hentikan server (Ctrl+C) lalu jalankan lagi `php artisan lan:serve`. Perintah ini otomatis menjalankan migrasi database, memperbarui cache, dan membuat ulang tampilan (`npm run build`) bila kode tampilannya lebih baru daripada hasil build terakhir; tanpa itu, browser tetap memakai tampilan lama dan tombol baru (misalnya Add-ons) tidak berfungsi. Kalau aplikasi dijalankan dengan cara lain (misalnya lewat Herd), jalankan `php artisan migrate --force` dan `npm run build` secara manual.

Kalau langkah itu terlewat, aplikasi memperbaikinya sendiri. Begitu ada halaman yang gagal karena tabel baru belum ada, database langsung diperbarui dan halaman dibuka ulang.

---

## Kode galat halaman

Bila halaman tidak dapat ditampilkan, layar menunjukkan salah satu kode berikut, bukan halaman putih. Setiap kejadian (kecuali FF-101) tercatat di **Laporan Aktivitas** dengan aktivitas "Halaman gagal dimuat", lengkap dengan perangkat, browser, halaman, dan pesan galatnya.

| Kode | Arti | Yang dilakukan |
| --- | --- | --- |
| FF-101 | JavaScript dimatikan di browser | Nyalakan JavaScript, atau pakai browser lain |
| FF-102 | Browser terlalu lama (di bawah Chrome 73, Safari/iOS 12.2, Firefox 68, Edge 79) | Perbarui browser atau pakai Chrome terbaru |
| FF-201 | Berkas tampilan gagal diunduh (Wi-Fi terputus, server sangat sibuk) | Halaman dicoba sekali lagi otomatis; periksa Wi-Fi, buka Cek Koneksi |
| FF-202 | Berkas tampilan tidak ada di server (HTTP 404), biasanya saat aplikasi sedang diperbarui | Muat ulang; bila tetap, jalankan `npm run build` di server lalu nyalakan ulang server |
| FF-301 | Galat di browser saat membuka halaman | Muat ulang; pesan galat di layar dan di Laporan menunjukkan penyebabnya |
| FF-302 | Galat di browser yang mengosongkan halaman setelah terbuka | Muat ulang |
| FF-401 | Halaman belum tampil setelah 20 detik (jaringan sangat lambat) | Tunggu (pesan hilang sendiri begitu halaman tampil) atau muat ulang |

### Menangani FF-301 dan FF-302

Keduanya berarti JavaScript halaman berhenti karena galat di browser: FF-301 saat halaman baru dibuka, FF-302 setelah halaman sempat tampil. Panelnya menampilkan pesan galat di kotak abu-abu, dan pesan yang sama tercatat di **Laporan Aktivitas** (aktivitas "Halaman gagal dimuat") beserta perangkat, browser, halaman, dan waktunya.

1. Di perangkat: tekan **Muat ulang**. Bila hilang, penyebabnya sesaat (memori perangkat penuh, koneksi putus di tengah).
2. Bila muncul lagi, buka Laporan Aktivitas lalu cari polanya:
   - **Banyak perangkat dengan browser yang sama** (misalnya Safari iOS lama atau WebView Android lama): browser itu kurang lengkap. Perbarui browser atau pakai Chrome terbaru.
   - **Semua perangkat, satu halaman yang sama**: galat di halaman itu. Kirimkan kode, alamat halaman, dan pesan galatnya ke pengembang.
   - **Hanya satu perangkat**: penyebabnya di perangkat itu. Matikan pemblokir iklan, mode hemat data, atau ekstensi untuk alamat ini, tutup aplikasi lain, atau coba browser lain.
3. Arti pesan yang sering muncul:
   - `QuotaExceededError`: penyimpanan browser penuh, biasanya di tablet yang dipakai bergantian. Hapus data situs `falcon.lan` di pengaturan browser setelah ujian.
   - `insertBefore`, `removeChild`, atau `not a child of this node`: isi halaman diubah dari luar oleh terjemahan otomatis atau ekstensi. Terjemahan kini dimatikan untuk semua halaman; sisanya biasanya ekstensi.
   - `SyntaxError` atau `is not a function`: browser terlalu lama untuk sebagian kode. Perbarui browser.
   - `Failed to fetch dynamically imported module` atau `Importing a module script failed`: berkas tampilan gagal diunduh, biasanya saat aplikasi sedang diperbarui. Jalankan `npm run build`, nyalakan ulang server, lalu muat ulang.

Pencegahan:

- Perbarui aplikasi di luar jam ujian, lalu jalankan `npm run build` dan nyalakan ulang server sebelum siswa membuka halaman.
- Sehari sebelum ujian, buka halaman ujian dan **Cek Koneksi** sekali di setiap jenis perangkat siswa (tablet sekolah, HP Android, iPad). Galat yang muncul langsung terlihat di Laporan Aktivitas.
- Pastikan browser perangkat sekolah diperbarui otomatis (Chrome lewat Play Store, Safari lewat pembaruan iOS), dan jangan pasang pemblokir iklan atau ekstensi di perangkat ujian.
- Di tablet yang dipakai bergantian, hapus data situs secara berkala (misalnya tiap akhir semester). Draf jawaban terhapus sendiri setelah dikirim, tetapi ujian yang ditinggalkan tetap menyimpan drafnya.

### Menangani FF-401

FF-401 berarti halaman belum tampil setelah 20 detik. Pesannya hilang sendiri begitu halaman selesai dimuat, jadi biasanya cukup menunggu.

1. **Hanya satu atau dua perangkat**: sinyal Wi-Fi perangkat itu lemah. Buka **Cek Koneksi** di perangkat tersebut; dekatkan ke router, pilih Wi-Fi 5 GHz, atau matikan VPN, proxy, dan "DNS Pribadi".
2. **Banyak perangkat sekaligus, biasanya saat ujian dimulai**: server atau access point kewalahan. Lihat terminal `lan:serve` (peringatan server lewat Wi-Fi saja atau AirDrop/Handoff aktif), dan bandingkan waktu kejadian di Laporan Aktivitas dengan jumlah siswa yang membuka halaman bersamaan.
3. Bila hasil Cek Koneksi menunjukkan waktu server yang tinggi, server sedang sibuk. Jalankan `php artisan exam:optimize --production` sebelum ujian berikutnya, dan tambah `--workers` bila peserta lebih dari 200.

Pencegahan:

- Sambungkan server ke router atau access point dengan **kabel LAN**, matikan AirDrop dan Handoff di Mac server, dan atur DHCP Reservation agar IP-nya tetap.
- Sediakan access point yang cukup: kira-kira satu access point untuk 30–40 perangkat, dengan Wi-Fi 5 GHz untuk siswa.
- Minta siswa membuka halaman ujian 5–10 menit sebelum mulai. Pembukaan pertama mengunduh sekitar 330 KB; pembukaan berikutnya hanya sekitar 20 KB karena sisanya dari cache, jadi tidak semua perangkat mengunduh penuh pada detik yang sama.
- Selama ujian, jangan jalankan pekerjaan berat di server (build, unggah video besar, cadangan Time Machine), dan jangan perbarui aplikasi.

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
| `app/Http/Controllers/QuizResponseController.php` | Alur ujian siswa: membuka, mulai, menyimpan draf, mengirim |
| `app/Support/QuizScoring.php` | Koreksi otomatis dan perhitungan nilai 0–100 |
| `app/Services/DocxImportService.php` | Pembaca soal dari Word |
| `app/Services/ExamViewImportService.php` | Pembaca paket ExamView/Blackboard |
| `app/Services/QuizResultsReport.php` | Berkas Excel rekap nilai |
| `app/Support/UserAgent.php` | Pengenal perangkat dan browser untuk laporan aktivitas |
| `app/Console/Commands/ServeLanCommand.php` | Perintah `lan:serve` |
| `resources/js/pages/PublicQuiz.vue` | Halaman ujian siswa |
| `resources/js/pages/FormEditor.vue` | Editor soal |

---

## Kontribusi

Laporan bug, usulan fitur, dan pull request sangat diterima. Beberapa hal yang membantu:

- Untuk bug, sertakan langkah untuk mengulanginya, perangkat atau browser yang dipakai, dan potongan `storage/logs/laravel.log` bila ada.
- Untuk pull request, sertakan tes untuk perubahan yang dibuat, lalu pastikan `php artisan test`, `vendor/bin/pint`, dan `npm run lint` berjalan bersih.
- Teks yang dilihat guru dan siswa ditulis dalam bahasa Indonesia yang baku dan mudah dipahami.

Kalau lembaga Anda memakai Falcon Forms, kabari kami lewat issue. Masukan dari ruang kelas sangat berharga.

---

## Lisensi

Falcon Forms dikembangkan oleh Falcon Education Partner dan dirilis dengan [Lisensi MIT dengan atribusi](LICENSE), hak cipta © 2026 Falcon Education Partner. Teks lengkapnya, dalam bahasa Indonesia dan Inggris, juga ada di halaman **Lisensi** aplikasi (`/lisensi`, ditautkan dari footer).

- **Boleh**: dipakai gratis oleh lembaga mana pun, disalin, dipasang sendiri, diubah, digabung, dibagikan, dan dijual, baik asli maupun versi ubahan.
- **Wajib**: menyertakan pemberitahuan hak cipta dan teks lisensi di setiap salinan, dan tetap menampilkan Falcon Education Partner sebagai pengembang (misalnya "Dikembangkan oleh Falcon Education Partner" di footer atau halaman Tentang). Keterangan itu tidak boleh dihapus atau diganti dengan pengembang lain.
- **Tanpa jaminan**: perangkat lunak disediakan apa adanya.

### Merek

Nama Falcon Forms, nama Falcon Education Partner, dan logo elang adalah merek milik Falcon Education Partner. Lisensi berlaku untuk kode, bukan untuk merek. Versi yang Anda ubah dan bagikan sendiri memakai nama dan logo lain, dengan keterangan "Berbasis Falcon Forms, dikembangkan oleh Falcon Education Partner".

### Layanan

Memasang dan memakai Falcon Forms sendiri tetap gratis. Pelatihan guru dan admin, instalasi dan pengaturan server, serta pendampingan saat ujian oleh Falcon Education Partner berbayar; biayanya menjadi kontribusi untuk pengembangan Falcon Forms selanjutnya. Layanan ini terpisah dari lisensi.
