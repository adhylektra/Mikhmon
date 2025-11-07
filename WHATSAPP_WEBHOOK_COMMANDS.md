# 📱 WhatsApp Webhook Commands - Dokumentasi Lengkap

## 📋 Daftar Isi

1. [Webhook untuk Customer/Public](#1-webhook-untuk-customerpublic)
2. [Webhook untuk Agent/Admin](#2-webhook-untuk-agentadmin)
3. [Format Response](#format-response)

---

## 1. Webhook untuk Customer/Public

**File:** `api/whatsapp_webhook.php`  
**Endpoint:** `http://your-domain/mikhmon/api/whatsapp_webhook.php`

### Perintah yang Tersedia

#### 1.1. BELI - Membeli Voucher

**Format:**
```
BELI <NAMA_PROFILE>
```

**Contoh:**
```
BELI 1JAM
BELI 3JAM
BELI 1HARI
BELI 12JAM
```

**Response Sukses:**
```
✅ *VOUCHER ANDA*

━━━━━━━━━━━━━━━━━━━━

Hotspot: [Nama Hotspot]
Profile: 1JAM
Username: 1jamabc123
Password: xyz789
Time Limit: 01:00:00
Validity: 1d

Login URL:
http://your-domain/login?username=1jamabc123&password=xyz789

━━━━━━━━━━━━━━━━━━━━

💳 *INFORMASI PEMBAYARAN*
Silakan transfer ke:
BCA: 1234567890
a.n. Nama Pemilik

Konfirmasi pembayaran:
WA: 08123456789
```

**Response Error - Profile Tidak Ditemukan:**
```
Paket *3JAM* tidak ditemukan.
Ketik *HARGA* untuk melihat daftar paket.
```

**Response Error - Sistem Maintenance:**
```
Sistem sedang maintenance. Silakan coba lagi nanti.
```

**Response Error - Gagal Terhubung:**
```
Gagal terhubung ke server. Silakan coba lagi nanti.
```

---

#### 1.2. HARGA / PAKET / LIST - Lihat Daftar Harga

**Format:**
```
HARGA
PAKET
LIST
```

**Response:**
```
📋 *DAFTAR PAKET WIFI*
*Nama Hotspot Anda*
━━━━━━━━━━━━━━━━━━━━

*1JAM*
Validity: 1d
Harga: Rp 3,000

*3JAM*
Validity: 1d
Harga: Rp 5,000

*12JAM*
Validity: 1d
Harga: Rp 8,000

*1HARI*
Validity: 1d
Harga: Rp 10,000

━━━━━━━━━━━━━━━━━━━━
Cara order:
Ketik: *BELI <NAMA_PAKET>*
Contoh: *BELI 1JAM*
```

**Response Error - Sistem Maintenance:**
```
Sistem sedang maintenance.
```

---

#### 1.3. HELP / BANTUAN - Bantuan

**Format:**
```
HELP
BANTUAN
```

**Response:**
```
🤖 *BANTUAN BOT VOUCHER*
━━━━━━━━━━━━━━━━━━━━

*Perintah yang tersedia:*

📋 *HARGA* atau *PAKET*
Melihat daftar paket dan harga

🛒 *BELI <NAMA_PAKET>*
Membeli voucher
Contoh: BELI 1JAM

❓ *HELP*
Menampilkan bantuan ini

━━━━━━━━━━━━━━━━━━━━
_Hubungi admin jika ada kendala_
```

---

#### 1.4. Perintah Tidak Dikenali

**Response:**
```
Perintah tidak dikenali.
Ketik *HELP* untuk bantuan.
```

---

## 2. Webhook untuk Agent/Admin

**File:** `api/whatsapp_agent_webhook.php`  
**Endpoint:** `http://your-domain/mikhmon/api/whatsapp_agent_webhook.php`

**Catatan:** Perlu terdaftar sebagai agent atau admin untuk menggunakan perintah ini.

---

### Perintah untuk Agent

#### 2.1. GEN / GENERATE - Generate Voucher (Agent)

**Format:**
```
GEN <PROFILE> <QUANTITY>
GENERATE <PROFILE> <QUANTITY>
```

**Contoh:**
```
GEN 3JAM 5
GENERATE 1HARI 10
GEN 12JAM 1
```

**Response Sukses:**
```
✅ *VOUCHER BERHASIL DI-GENERATE*

Profile: 3JAM
Jumlah: 5 voucher
Total: Rp 15,000

━━━━━━━━━━━━━━━━━━━━

*Voucher #1*
Username: `AG12AB34CD`
Password: `XY56ZW`

*Voucher #2*
Username: `AG98EF76GH`
Password: `MN12OP`

*Voucher #3*
Username: `AG45IJ89KL`
Password: `QR34ST`

*Voucher #4*
Username: `AG67MN01PQ`
Password: `UV56WX`

*Voucher #5*
Username: `AG23YZ45AB`
Password: `CD78EF`

━━━━━━━━━━━━━━━━━━━━
💰 Saldo Anda: Rp 85,000
```

**Response Error - Saldo Tidak Cukup:**
```
❌ *SALDO TIDAK CUKUP*

Saldo Anda: Rp 10,000
Dibutuhkan: Rp 15,000
Kurang: Rp 5,000

Silakan topup saldo terlebih dahulu.
```

**Response Error - Harga Belum Diset:**
```
❌ Harga untuk profile *3JAM* belum diset.
Hubungi admin.
```

**Response Error - Agent Tidak Aktif:**
```
❌ Akun agent Anda tidak aktif.
Hubungi admin untuk informasi lebih lanjut.
```

**Response Error - Sistem Maintenance:**
```
❌ Sistem sedang maintenance. Coba lagi nanti.
```

---

#### 2.2. SALDO / BALANCE / CEK SALDO - Cek Saldo

**Format:**
```
SALDO
BALANCE
CEK SALDO
```

**Response:**
```
💰 *INFORMASI SALDO*

Agent: John Doe
Kode: AG0001
Level: Silver

━━━━━━━━━━━━━━━━━━━━

💵 Saldo: *Rp 100,000*

📊 *Statistik:*
• Total Voucher: 50
• Voucher Terpakai: 35
• Total Topup: Rp 500,000
• Total Pengeluaran: Rp 400,000
```

---

#### 2.3. TRANSAKSI / HISTORY / RIWAYAT - Riwayat Transaksi

**Format:**
```
TRANSAKSI
TRANSAKSI <JUMLAH>
HISTORY
HISTORY <JUMLAH>
RIWAYAT
RIWAYAT <JUMLAH>
```

**Contoh:**
```
TRANSAKSI
TRANSAKSI 20
HISTORY 50
```

**Response:**
```
📋 *RIWAYAT TRANSAKSI*
(10 transaksi terakhir)

━━━━━━━━━━━━━━━━━━━━

*01/11 14:30* | Generate
- Rp 3,000
Profile: 3JAM
User: AG12AB34CD

*01/11 10:15* | Topup
+ Rp 50,000

*31/10 16:45* | Generate
- Rp 5,000
Profile: 1HARI
User: AG98EF76GH

*31/10 14:20* | Generate
- Rp 3,000
Profile: 3JAM
User: AG45IJ89KL

*30/10 18:30* | Topup
+ Rp 100,000

━━━━━━━━━━━━━━━━━━━━
💰 Saldo: Rp 85,000
```

**Response - Belum Ada Transaksi:**
```
📋 Belum ada transaksi.
```

---

#### 2.4. HARGA / PRICE / PAKET - Daftar Harga Agent

**Format:**
```
HARGA
PRICE
PAKET
```

**Response:**
```
💵 *DAFTAR HARGA AGENT*

Agent: John Doe
Kode: AG0001

━━━━━━━━━━━━━━━━━━━━

*3JAM*
Harga Beli: Rp 3,000
Harga Jual: Rp 5,000
Profit: Rp 2,000

*1HARI*
Harga Beli: Rp 5,000
Harga Jual: Rp 7,000
Profit: Rp 2,000

*12JAM*
Harga Beli: Rp 4,000
Harga Jual: Rp 6,000
Profit: Rp 2,000

━━━━━━━━━━━━━━━━━━━━
Cara generate:
*GEN <PROFILE> <QTY>*
Contoh: GEN 3JAM 5
```

**Response Error - Harga Belum Diset:**
```
❌ Harga belum diset. Hubungi admin.
```

---

#### 2.5. TOPUP <JUMLAH> - Request Topup

**Format:**
```
TOPUP <JUMLAH>
```

**Contoh:**
```
TOPUP 100000
TOPUP 50000
```

**Response Sukses:**
```
✅ *REQUEST TOPUP DIKIRIM*

Jumlah: Rp 100,000

━━━━━━━━━━━━━━━━━━━━

Silakan transfer ke:
BCA: 1234567890
a.n. Nama Pemilik

Setelah transfer, kirim bukti ke admin.
Request Anda akan diproses segera.
```

**Response Error - Minimal Topup:**
```
❌ Minimal topup Rp 10,000
```

---

#### 2.6. LAPORAN / REPORT / SALES - Laporan Penjualan

**Format:**
```
LAPORAN
LAPORAN <PERIOD>
REPORT
REPORT <PERIOD>
SALES
SALES <PERIOD>
```

**Period:** `today`, `week`, `month`

**Contoh:**
```
LAPORAN
LAPORAN WEEK
REPORT MONTH
```

**Response:**
```
📊 *LAPORAN PENJUALAN*

Periode: Hari Ini

━━━━━━━━━━━━━━━━━━━━

Total Voucher: 15
Voucher Terjual: 12
Voucher Tersisa: 3

Total Penjualan: Rp 75,000
Total Modal: Rp 45,000
Total Profit: Rp 30,000

━━━━━━━━━━━━━━━━━━━━
💰 Saldo: Rp 85,000
```

---

#### 2.7. BROADCAST <PESAN> - Broadcast Pesan

**Format:**
```
BROADCAST <PESAN>
```

**Contoh:**
```
BROADCAST Promo hari ini!
BROADCAST Voucher diskon 50% untuk pembelian hari ini
```

**Response Sukses:**
```
✅ *BROADCAST TERKIRIM*

Total Customer: 150
Terkirim: 148
Gagal: 2
```

**Response Error:**
```
❌ [Pesan error]
```

---

#### 2.8. HELP / BANTUAN - Bantuan Agent

**Format:**
```
HELP
BANTUAN
?
```

**Response:**
```
🤖 *BANTUAN AGENT BOT*

━━━━━━━━━━━━━━━━━━━━

*Perintah yang tersedia:*

🎫 *GEN <PROFILE> <QTY>*
Generate voucher
Contoh: GEN 3JAM 5

💰 *SALDO*
Cek saldo dan statistik

📋 *TRANSAKSI <JUMLAH>*
Lihat riwayat transaksi
Contoh: TRANSAKSI 20

💵 *HARGA*
Lihat daftar harga

💳 *TOPUP <JUMLAH>*
Request topup saldo
Contoh: TOPUP 100000

📊 *LAPORAN <PERIOD>*
Lihat laporan penjualan
Period: TODAY, WEEK, MONTH
Contoh: LAPORAN WEEK

📢 *BROADCAST <PESAN>*
Kirim pesan ke semua customer
Contoh: BROADCAST Promo hari ini!

❓ *HELP*
Tampilkan bantuan ini

━━━━━━━━━━━━━━━━━━━━
_Hubungi admin jika ada kendala_
```

---

### Perintah untuk Admin

#### 2.9. GEN / GENERATE - Generate Voucher (Admin)

**Format:**
```
GEN <PROFILE> <QUANTITY>
GENERATE <PROFILE> <QUANTITY>
```

**Catatan:** Admin generate **tanpa pemotongan saldo**.

**Response:**
```
✅ *VOUCHER ADMIN*

Profile: 3JAM
Jumlah: 5 voucher

━━━━━━━━━━━━━━━━━━━━

*Voucher #1*
Username: `AG12AB34CD`
Password: `XY56ZW`

*Voucher #2*
Username: `AG98EF76GH`
Password: `MN12OP`

*Voucher #3*
Username: `AG45IJ89KL`
Password: `QR34ST`

*Voucher #4*
Username: `AG67MN01PQ`
Password: `UV56WX`

*Voucher #5*
Username: `AG23YZ45AB`
Password: `CD78EF`

━━━━━━━━━━━━━━━━━━━━
🔑 *ADMIN ACCESS* - No balance deduction
```

---

#### 2.10. SALDO - Info Admin Access

**Format:**
```
SALDO
```

**Response:**
```
👑 *ADMIN ACCESS*

Status: ✅ Active
Privilege: Unlimited

━━━━━━━━━━━━━━━━━━━━

Anda dapat generate voucher tanpa batas.
Tidak ada pemotongan saldo.

Ketik *HELP* untuk perintah.
```

---

#### 2.11. HARGA - Daftar Profile (Admin)

**Format:**
```
HARGA
PRICE
PAKET
```

**Response:**
```
📋 *DAFTAR PROFILE*

• 3JAM
• 1HARI
• 12JAM
• 3HARI
• 1MINGGU
• 1BULAN

━━━━━━━━━━━━━━━━━━━━
Cara generate:
*GEN <PROFILE> <QTY>*
```

---

#### 2.12. HELP - Bantuan Admin

**Format:**
```
HELP
BANTUAN
?
```

**Response:**
```
👑 *BANTUAN ADMIN BOT*

━━━━━━━━━━━━━━━━━━━━

*Perintah Admin:*

🎫 *GEN <PROFILE> <QTY>*
Generate voucher (tanpa potong saldo)
Contoh: GEN 3JAM 10

💰 *SALDO*
Info admin access

💵 *HARGA*
Lihat semua profile

❓ *HELP*
Tampilkan bantuan ini

━━━━━━━━━━━━━━━━━━━━
🔑 *ADMIN ACCESS ACTIVE*
```

---

### Response untuk Nomor Tidak Terdaftar

**Response:**
```
❌ Nomor Anda belum terdaftar sebagai agent.

Silakan hubungi admin untuk pendaftaran.
```

---

### Response untuk Perintah Tidak Dikenali

**Response:**
```
❌ Perintah tidak dikenali.

Ketik *HELP* untuk melihat daftar perintah.
```

---

## Format Response

### Emoji yang Digunakan

- ✅ Sukses
- ❌ Error
- 📋 Daftar/List
- 💰 Saldo/Balance
- 💵 Harga/Price
- 🎫 Voucher
- 📊 Laporan/Report
- 📢 Broadcast
- 🤖 Bot
- 👑 Admin
- 💳 Payment
- ❓ Help

### Format Pesan

- **Bold Text:** `*text*`
- **Code/Username:** `` `text` ``
- **Separator:** `━━━━━━━━━━━━━━━━━━━━`
- **Line Break:** `\n`

---

## Gateway Support

Webhook mendukung format dari berbagai gateway:

### Fonnte
```json
{
  "sender": "628123456789",
  "message": "HELP"
}
```

### Wablas
```json
{
  "phone": "628123456789",
  "message": "HELP"
}
```

### WooWA
```json
{
  "from": "628123456789",
  "message": "HELP"
}
```

### Custom
```json
{
  "phone": "628123456789",
  "message": "HELP"
}
```

---

## Testing

### Test Customer Webhook

1. Kirim pesan ke nomor WhatsApp bot:
   ```
   HELP
   ```
2. Harus dapat response bantuan

3. Kirim:
   ```
   HARGA
   ```
4. Harus dapat daftar harga

5. Kirim:
   ```
   BELI 1JAM
   ```
6. Harus dapat voucher (jika sistem berjalan)

### Test Agent Webhook

1. Pastikan nomor terdaftar sebagai agent
2. Kirim:
   ```
   HELP
   ```
3. Harus dapat response bantuan agent

4. Kirim:
   ```
   SALDO
   ```
5. Harus dapat info saldo

6. Kirim:
   ```
   GEN 3JAM 1
   ```
7. Harus dapat voucher (jika saldo cukup)

---

## Troubleshooting

### Command tidak dikenali
- Cek format command (case insensitive)
- Pastikan tidak ada typo
- Cek spasi di command

### Nomor tidak terdaftar
- Agent: Daftar via admin panel
- Admin: Tambahkan ke admin numbers via setup page

### Saldo tidak cukup
- Topup saldo via admin panel
- Atau hubungi admin

### Voucher tidak ter-generate
- Cek koneksi ke MikroTik
- Cek saldo agent
- Cek harga sudah diset
- Cek log error

---

## Log Files

- Customer Webhook: `logs/webhook_log.txt`
- Agent Webhook: `logs/agent_webhook_log.txt`

---

**Version:** 1.0  
**Last Updated:** November 2024  
**Status:** ✅ Production Ready

