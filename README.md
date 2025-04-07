# WordPress Database Optimizer

Plugin WordPress untuk melakukan optimasi database secara otomatis setiap awal bulan. Plugin ini dirancang dengan pendekatan modular untuk memudahkan pemeliharaan dan pengembangan.

## 📋 Fitur

- ⏱️ **Optimasi Otomatis** - Berjalan setiap awal bulan (tanggal 1 pukul 01:00)
- 🧩 **Arsitektur Modular** - Mudah dipelihara dan dikembangkan
- 🔧 **Optimasi Database Komprehensif**:
  - Optimasi dan perbaikan tabel
  - Pembersihan revisi post
  - Penghapusan auto draft
  - Pembersihan post dan komentar di tempat sampah
  - Pembersihan komentar spam
  - Penghapusan transient yang kedaluwarsa
- 📧 **Notifikasi Email** - Laporan hasil optimasi
- 📝 **Sistem Logging** - Pencatatan aktivitas lengkap
- 🖥️ **Panel Admin** - Antarmuka yang user-friendly

## 🚀 Instalasi

### Metode 1: Via GitHub

```bash
# Masuk ke direktori plugins WordPress
cd /path/to/wp-content/plugins/

# Clone repositori
git clone https://github.com/username/wp-db-optimizer.git

# Atur izin file
chmod -R 755 wp-db-optimizer
```

### Metode 2: Upload Manual

1. Download repositori ini sebagai file ZIP
2. Login ke admin WordPress
3. Pergi ke Plugins > Add New > Upload Plugin
4. Upload file ZIP dan klik "Install Now"
5. Aktifkan plugin

## 📖 Cara Penggunaan

1. Setelah mengaktifkan plugin, akses menu **Tools > DB Optimizer**
2. Konfigurasi pengaturan sesuai kebutuhan Anda:
   - Pilih tugas optimasi yang ingin dijalankan
   - Atur pengiriman notifikasi email
   - Atur periode penyimpanan log
3. Plugin akan otomatis menjalankan optimasi pada tanggal 1 setiap bulan
4. Anda juga bisa menjalankan optimasi secara manual melalui tab Status

## 🏗️ Struktur Plugin

```
wp-db-optimizer/
├── wp-db-optimizer.php (File utama plugin)
├── includes/
│   ├── class-admin.php (Antarmuka admin)
│   ├── class-optimizer.php (Fungsi optimasi)
│   ├── class-scheduler.php (Penjadwalan)
│   └── class-logger.php (Pencatatan aktivitas)
```

## 📷 Screenshot

*(Tambahkan screenshot Anda di sini)*

## 📝 Changelog

### 1.0.0
- Versi awal dari plugin

## 📜 Lisensi

Distribusi plugin ini diatur di bawah lisensi GPL-2.0. Lihat file `LICENSE` untuk detail lebih lanjut.

## 👥 Kontribusi

Kontribusi, isu, dan permintaan fitur sangat diterima. Jangan ragu untuk memeriksa [halaman issues](https://github.com/username/wp-db-optimizer/issues) jika Anda ingin berkontribusi.

## 🔌 Kompatibilitas

- WordPress 5.0 atau yang lebih baru
- PHP 7.2 atau yang lebih baru
- MySQL 5.6 atau yang lebih baru / MariaDB 10.0 atau yang lebih baru
