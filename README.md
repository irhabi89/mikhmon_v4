# MIKHMON v4 (PHP 8.x & RouterOS v6 / v7 Compatible)

MIKHMON (MikroTik Hotspot Monitor) adalah aplikasi berbasis web untuk manajemen Hotspot MikroTik, pembuatan dan cetak voucher, pemantauan pengguna aktif, laporan penjualan, dan manajemen masa aktif pengguna (expired monitor).

Repositori ini merupakan versi yang telah didecode, dimodernisasi, serta disesuaikan agar kompatibel penuh dengan **PHP 8.0 - 8.5+** serta mendukung **RouterOS v6 (v6.48, v6.49+)** dan **RouterOS v7 (v7.1 s/d v7.24+ baik CHR maupun hardware)**.

---

## ✨ Fitur & Peningkatan Utama

### 1. Kompatibilitas Penuh PHP 8.x (Hingga PHP 8.5+)
- **RouterOS API Stream Handling**: Memperbaiki pengecekan resource stream socket sebelum pemanggilan `stream_set_timeout()` dan `stream_get_meta_data()` untuk mencegah *Fatal TypeError* pada PHP 8.5.
- **Safe Template Fallback**: Menangani penanganan template voucher agar tidak memicu `ValueError: Path must not be empty` pada PHP 8.x ketika parameter template tidak diisi.
- **Validasi Timezone**: Menggunakan modul timezone validator (`core/timezone.php`) yang memetakan identifier jam RouterOS ke timezone PHP resmi tanpa memicu warning/exception.

### 2. Dukungan Penuh RouterOS v6 & v7 (Dual-Engine Support)
- **Normalisasi Tanggal Otomatis**: Menangani perbedaan output format tanggal antara RouterOS v6 (`nov/16/2026`) dan RouterOS v7 (`2026-11-16`) pada seluruh alur kedaluwarsa dan laporan penjualan.
- **Single-Line Script Generator (`core/mikhmon_scripts.php`)**: Script `on-login` dikonstruksi dalam satu baris (*single-line*) untuk mencegah pemotongan (*splitting*) parameter oleh driver API MikroTik.
- **Laporan Penjualan (Sales Report)**: Skrip laporan penjualan otomatis menyimpan tanggal standar legasi dan menyesuaikan kepemilikan (`owner`) sesuai bulan berjalan (`nov2026`). Termasuk penanganan otomatis untuk RouterOS 7.24+ yang menjalankan hotspot daemon di bawah konteks `*sys`.
- **Dukungan CHR (Cloud Hosted Router)**: Fallback otomatis untuk mendeteksi `board-name` melalui `/system/resource` saat `/system/routerboard/print` tidak tersedia pada RouterOS 7 CHR.

### 3. Expired Monitor Handal (`Mikhmon-Expire-Monitor`)
- Scheduler berjalan periodik setiap menit untuk mengecek masa aktif voucher:
  - **Mode Remove (`X`)**: Pengguna yang habis masa aktifnya otomatis dihapus dari database Hotspot.
  - **Mode Notice (`N`)**: Pengguna yang habis masa aktifnya dibatasi (`limit-uptime=1s`) dan sesi aktifnya langsung diputus.
- Script monitor mendukung parsing format tanggal RouterOS v6 dan RouterOS v7 dengan lingkup variabel terisolasi (*isolated scope functions*).

### 4. Generator User & Voucher yang Lebih Stabil
- Memvalidasi parameter opsional (`limit-uptime`, `mac-address`, `limit-bytes-total`). Nilai kosong tidak akan dikirimkan sebagai string kosong, menghindari penolakan perintah oleh RouterOS (`invalid time value` / `invalid mac-address`).
- Cetak voucher dan QR Code dinamis menggunakan DNS / IP Gateway hotspot masing-masing sesi untuk kemudahan login pengguna.

---

## 🚀 Persyaratan Sistem

- **PHP**: Versi 8.0, 8.1, 8.2, 8.3, 8.4, atau 8.5+
- **Ekstensi PHP**: `curl`, `json`, `mbstring`, `sockets`
- **RouterOS**:
  - RouterOS v6 (tested on v6.49.17)
  - RouterOS v7 (tested on v7.22.1 & v7.24.6)
  - Arsitektur: CHR (x86_64), RouterBOARD MIPSBE, ARM, ARM64, TILE.

---

## 🛠️ Cara Menjalankan

### Menggunakan PHP Built-in Server (Development / Local)
```bash
php -S 0.0.0.0:8081
```
Buka browser di `http://127.0.0.1:8081`.

### Menggunakan Web Server (Apache / Nginx)
1. Salin seluruh isi folder ke direktori web root (contoh: `/var/www/html/mikhmon`).
2. Pastikan izin akses file dan folder terbaca oleh web server (`www-data` / `nginx`).
3. Akses URL server Anda melalui peramban.

---

## ⚙️ Kredensial Default

- **Username MIKHMON**: `mikhmon`
- **Password MIKHMON**: `1234`

*(Dapat diubah melalui menu Settings di dalam Mikhmon).*

---

## 📋 Konfigurasi Router MikroTik

Pastikan Service API aktif pada router MikroTik:
```routeros
/ip service enable api
/ip service set api port=8728
```

Aktifkan metode autentikasi yang diinginkan pada profile hotspot (misal: `http-chap` dan `http-pap`):
```routeros
/ip hotspot profile set [find name="hsprof"] login-by=http-chap,http-pap
```

Pasang scheduler Expired Monitor melalui tombol **Set Expired Monitor** pada menu Quick Setup Mikhmon, atau biarkan Mikhmon memasangnya otomatis saat pembuatan profil pertama.

---

## 📜 Lisensi & Catatan
- Mikhmon dibuat oleh Laksamadi Guko.
- Recode dan penyesuaian kompatibilitas PHP 8.x & RouterOS 7 oleh tim pengembang dan komunitas.
