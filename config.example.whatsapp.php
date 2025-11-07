<?php
/*
 * CONTOH KONFIGURASI WHATSAPP
 * Copy file ini dan sesuaikan dengan gateway Anda
 */

// ============================================
// KONFIGURASI FONNTE.COM
// ============================================
/*
define('WHATSAPP_GATEWAY', 'fonnte');
define('FONNTE_TOKEN', 'abc123xyz789');  // Ganti dengan token Anda
define('WHATSAPP_ENABLED', true);
define('WHATSAPP_FORMAT_62', true);
*/

// ============================================
// KONFIGURASI WABLAS.COM
// ============================================
/*
define('WHATSAPP_GATEWAY', 'wablas');
define('WABLAS_API_URL', 'https://pati.wablas.com/api/send-message');  // Ganti dengan domain Anda
define('WABLAS_TOKEN', 'abc123xyz789');  // Ganti dengan token Anda
define('WHATSAPP_ENABLED', true);
define('WHATSAPP_FORMAT_62', true);
*/

// ============================================
// KONFIGURASI WOOWA.ID
// ============================================
/*
define('WHATSAPP_GATEWAY', 'woowa');
define('WOOWA_TOKEN', 'abc123xyz789');  // Ganti dengan token Anda
define('WHATSAPP_ENABLED', true);
define('WHATSAPP_FORMAT_62', true);
*/

// ============================================
// KONFIGURASI CUSTOM GATEWAY
// ============================================
/*
define('WHATSAPP_GATEWAY', 'custom');
define('CUSTOM_API_URL', 'https://api.gateway-anda.com/send');
define('CUSTOM_API_TOKEN', 'abc123xyz789');
define('WHATSAPP_ENABLED', true);
define('WHATSAPP_FORMAT_62', true);
*/

// ============================================
// WEBHOOK URL
// ============================================
/*
Daftarkan webhook URL ini di dashboard gateway Anda:

http://YOUR_DOMAIN/mikhmon/api/whatsapp_webhook.php

Atau jika localhost (untuk testing):
http://localhost/mikhmon/api/whatsapp_webhook.php

CATATAN: Untuk production, gunakan domain yang bisa diakses dari internet
*/

// ============================================
// CARA SETUP
// ============================================
/*
1. Pilih gateway yang akan digunakan
2. Uncomment (hapus tanda slash) konfigurasi gateway tersebut
3. Ganti token dengan token Anda
4. Simpan file ini
5. Atau lebih mudah, gunakan halaman settings:
   http://localhost/mikhmon/settings/whatsapp_settings.php
*/

// ============================================
// FORMAT NOMOR WHATSAPP
// ============================================
/*
WHATSAPP_FORMAT_62 = true   → Format: 628123456789
WHATSAPP_FORMAT_62 = false  → Format: 08123456789

Sesuaikan dengan requirement gateway Anda
*/

// ============================================
// TESTING
// ============================================
/*
Setelah konfigurasi:
1. Buka: http://localhost/mikhmon/settings/whatsapp_settings.php
2. Scroll ke bagian "Test WhatsApp"
3. Masukkan nomor WhatsApp Anda
4. Klik "Kirim Pesan Test"
5. Cek WhatsApp Anda

Jika berhasil, konfigurasi sudah benar! ✅
*/
