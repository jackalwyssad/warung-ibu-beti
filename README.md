# Layanan Katalog, Stok, dan Pembukuan — Warung Ibu Beti

**Mata Kuliah:** Integrasi Sistem dan Komputasi Awan (25IF1504)  
**Institusi:** STMIK Bandung  
**Kelompok:** Intel  

### Anggota Kelompok:
1. **Jacky Firmansah** (1224010)
2. **Arya Kusuma Dinata** (1224005)
3. **Mochamad Aldi Ardiansyah** (1224020)
4. **Andhi Yudha Permana** (1224017)

---

## 📌 Ringkasan Sistem

Repositori ini mengimplementasikan sistem backend terintegrasi untuk **Warung Ibu Beti** dengan dua gaya integrasi utama:
1. **Remote Procedure Invocation (REST API Level 2)** sesuai Modul Praktikum 1:
   - Desain API-first dengan spesifikasi **OpenAPI 3.1.0** (tervalidasi bersih 0 error di Spectral).
   - Penanganan error standar **RFC 9457 Problem Details** (`application/problem+json`).
   - Kontrol konkurensi stok menggunakan transaksi database ACID dan *pessimistic row-locking* (`SELECT ... FOR UPDATE`).
2. **Asynchronous Messaging & Background Processing (RabbitMQ & Laravel Queue)** sesuai Modul Praktikum 2:
   - Pola Publish-Subscribe berbasis Domain Event `StokDisesuaikan`.
   - Topic Exchange `warung.events` dengan dynamic routing keys (`stok.penjualan`, `stok.restok`, `stok.koreksi`).
   - Consumer Idempoten (`pembukuan:konsumsi`) yang mencatat mutasi ke tabel `jurnal_penjualan` menggunakan `message_id` unik (`firstOrCreate`).
   - Penanganan kegagalan pesan menggunakan **Dead Letter Exchange (DLQ)** `warung.dlx` dengan `x-dead-letter-exchange`.
   - Consumer Notifikasi Realtime (`notifikasi:stok`) dengan filter batas minimum stok (*low stock threshold*).
   - Background Job asynchronous (`SinkronkanStokWhatsApp`) melalui Laravel Queue driver RabbitMQ pada antrean `notifikasi-wa`.

---

## 🏗️ 1. Diagram Arsitektur & Topologi Sistem

Alur arsitektur sistem disusun berurutan dari kiri ke kanan (**Klien $\rightarrow$ API $\rightarrow$ RabbitMQ Broker $\rightarrow$ Konsumen & Pembukuan**) agar arah dan tujuannya sangat mudah dipahami:

```mermaid
flowchart LR
    %% Definisi Kolom Subgraf Berurutan dari Kiri ke Kanan
    subgraph S1 ["1. Klien & Pengguna"]
        direction TB
        Pembeli["👤 Pembeli / Pelanggan"]
        Kasir["💻 Kasir (POS)"]
        BotWA["📱 Bot WhatsApp"]
    end

    subgraph S2 ["2. Layanan Inti (API & DB)"]
        direction TB
        API["⚙️ REST API Laravel<br/>(Port 8000)"]
        DB[("🗄️ MySQL: produk<br/>(Pessimistic Lock)")]
        Worker["🔄 Background Worker<br/>(Sinkron WhatsApp)"]
    end

    subgraph S3 ["3. Message Broker (RabbitMQ)"]
        direction TB
        ExEvents[["📢 Topic Exchange:<br/>warung.events"]]
        QPenjualan[("📥 Antrean:<br/>pembukuan.penjualan")]
        QNotif[("📥 Antrean:<br/>notifikasi.stok")]
        QWA[("📥 Antrean:<br/>notifikasi-wa")]
        ExDLX[["⚠️ Fanout DLX:<br/>warung.dlx"]]
        QDLQ[("🛑 Antrean DLQ:<br/>pembukuan.penjualan.dlq")]
    end

    subgraph S4 ["4. Konsumen & Output"]
        direction TB
        ConsBuku["📑 Consumer Pembukuan<br/>(CatatPenjualan)"]
        DBJurnal[("💰 MySQL:<br/>jurnal_penjualan")]
        ConsNotif["🔔 Consumer Notifikasi<br/>(Cek Stok Menipis)"]
        Alert["⚠️ Peringatan Stok!"]
    end

    %% Hubungan Kolom 1 ke Kolom 2
    Pembeli -->|1a. Belanja langsung| Kasir
    Pembeli -->|1b. Pesan online| BotWA
    Kasir -->|2a. POST penyesuaian-stok| API
    BotWA -->|2b. POST penyesuaian-stok| API
    API <-->|3. Update stok atomik| DB

    %% Hubungan Kolom 2 ke Kolom 3
    API -->|4a. Terbitkan Domain Event| ExEvents
    API -->|4b. Antrekan Sync WA| QWA
    QWA --> Worker

    %% Routing di dalam Broker (Kolom 3)
    ExEvents -->|Routing: stok.penjualan| QPenjualan
    ExEvents -->|Routing: stok.*| QNotif
    QPenjualan -.->|Pesan Rusak / NACK| ExDLX
    ExDLX --> QDLQ

    %% Hubungan Kolom 3 ke Kolom 4
    QPenjualan -->|5a. Konsumsi pesan| ConsBuku
    ConsBuku -->|6a. Simpan idempoten| DBJurnal
    QNotif -->|5b. Konsumsi pesan| ConsNotif
    ConsNotif -->|6b. Jika stok tipis| Alert
```

---

## 🔄 2. Diagram Alur Transaksi & Messaging (Sequence Diagram)

Diagram berikut membagi alur transaksi menjadi **4 Tahapan Jelas** dengan penomoran urut langkah:

```mermaid
sequenceDiagram
    autonumber
    actor Pembeli as 👤 Pembeli
    actor Kasir as 💻 Kasir
    participant API as ⚙️ REST API
    participant DB as 🗄️ Database
    participant RMQ as 📢 RabbitMQ
    participant Worker as 📑 Consumer Pembukuan
    participant Jurnal as 💰 Jurnal Penjualan
    participant Notif as 🔔 Consumer Notifikasi

    rect rgb(240, 248, 255)
    Note over Pembeli,DB: TAHAP 1: Transaksi Kasir (Sinkron & Atomik)
    Pembeli->>Kasir: 1. Beli barang (contoh: 3 pcs Indomie)
    Kasir->>API: 2. Request POST penyesuaian-stok (-3)
    activate API
    API->>DB: 3. Kunci baris data (SELECT FOR UPDATE)
    alt Stok Cukup
        API->>DB: 4. Kurangi stok dan simpan (COMMIT)
        API-->>Kasir: 5. Response 200 OK (Stok Berhasil Berkurang)
        Kasir-->>Pembeli: 6. Serahkan barang dan struk belanja
    else Stok Tidak Cukup
        API->>DB: Batalkan transaksi (ROLLBACK)
        API-->>Kasir: 5. Response 409 Conflict (Stok Kurang)
        Kasir-->>Pembeli: 6. Beritahu pembeli stok tidak mencukupi
    end
    end

    rect rgb(255, 250, 240)
    Note over API,RMQ: TAHAP 2: Penerbitan Event ke Broker
    API->>RMQ: 7. Publish Event StokDisesuaikan ke warung.events
    deactivate API
    end

    rect rgb(245, 255, 245)
    Note over RMQ,Jurnal: TAHAP 3: Pembukuan Otomatis (Asinkron)
    RMQ->>Worker: 8. Teruskan pesan ke antrean pembukuan.penjualan
    activate Worker
    Worker->>Jurnal: 9. Cek message_id dan simpan penjualan
    alt Belum Ada (Valid)
        Jurnal-->>Worker: Data berhasil tersimpan
        Worker->>RMQ: 10. Konfirmasi selesai (basic_ack)
    else Duplikat (Pernah Diterima)
        Worker->>RMQ: 10. Abaikan duplikat dan konfirmasi (basic_ack)
    else Format Rusak / Gagal
        Worker->>RMQ: 10. Tolak pesan (basic_nack dialihkan ke DLQ)
    end
    deactivate Worker
    end

    rect rgb(255, 245, 245)
    Note over RMQ,Notif: TAHAP 4: Peringatan Stok Menipis (Asinkron)
    RMQ->>Notif: 11. Teruskan pesan ke antrean notifikasi.stok
    activate Notif
    opt Sisa Stok di Bawah Batas Minimum
        Notif->>Notif: 12. Cetak Peringatan Stok Menipis di Layar
    end
    Notif->>RMQ: 13. Konfirmasi pesan (basic_ack)
    deactivate Notif
    end
```

---

## 🎯 3. Alur Idempotensi & Dead Letter Queue (DLQ)

```mermaid
flowchart TD
    Start([1. Pesan Diterima Consumer]) --> ParseJSON{2. Validasi Format JSON?}
    
    ParseJSON -- Format Tidak Valid / Rusak --> NackDLQ["basic_nack(requeue: false)"]
    NackDLQ --> DLX["Exchange: warung.dlx"]
    DLX --> QueueDLQ[("Queue: pembukuan.penjualan.dlq<br/>(Tersimpan untuk Audit / Replay)")]
    
    ParseJSON -- Format Valid --> CekMsgId{"3. Cari message_id<br/>di tabel jurnal_penjualan?"}
    CekMsgId -- Sudah Ada (Pesan Duplikat) --> AckDup["Log: Pesan duplikat dilewati (Idempoten)<br/>basic_ack()"]
    CekMsgId -- Belum Ada (Pesan Baru) --> InsertDB["4. Insert ke jurnal_penjualan<br/>(sku, jumlah, total, message_id)"]
    InsertDB --> AckSukses["5. basic_ack() (Hapus dari antrean)"]
```

---

## 📋 4. Tabel Kontrak Event RabbitMQ

| Nama Event | Exchange / Routing Key | Payload (Field & Tipe) | Penerbit | Penerima |
|---|---|---|---|---|
| `StokDisesuaikan` | `warung.events`<br>• `stok.penjualan`<br>• `stok.restok`<br>• `stok.koreksi` | `sku` (string)<br>`perubahan` (int)<br>`alasan` (string)<br>`stok_sesudah` (int)<br>`harga` (int)<br>`terjadi_pada` (ISO 8601 string)<br>`message_id` (UUID v4) | Layanan Katalog & Stok | 1. Consumer Pembukuan (`pembukuan.penjualan`)<br>2. Consumer Notifikasi (`notifikasi.stok`) |

> **Mengapa alur ini lebih tepat asinkron daripada panggilan REST langsung antar service?**  
> Penyesuaian stok harus merespons transaksi kasir dan bot WhatsApp secepat mungkin (< 50ms) tanpa dibebani keterlambatan sistem pembukuan atau pengiriman pesan WhatsApp. Jika sistem pembukuan lambat atau sedang maintenance, kasir tetap dapat melayani pelanggan tanpa gangguan (*loose coupling & high availability*).

---

## 🔌 5. Pemetaan Port & Layanan Docker

| Layanan | Container Name | Port Host | Keterangan & Akses |
|---|---|---|---|
| **REST API (Laravel)** | `warung-ibu-beti-laravel.test-1` | `8000` | Endpoint: `http://localhost:8000/api/v1` |
| **Swagger UI** | `warung-ibu-beti-swagger-ui-1` | `8082` | Dokumentasi Interaktif: `http://localhost:8082` |
| **RabbitMQ Management** | `warung-ibu-beti-rabbitmq-1` | `15674` | Dashboard: `http://localhost:15674` (`guest`/`guest`) |
| **RabbitMQ AMQP** | `warung-ibu-beti-rabbitmq-1` | `5674` | Protokol pesan AMQP 0-9-1 |
| **MySQL Database** | `warung-ibu-beti-mysql-1` | `3308` | Database: `warung_ibu_beti` (`sail`/`password`) |

---

## 🚀 6. Panduan Instalasi & Menjalankan Proyek

### Prasyarat
- Docker Desktop & Docker Compose
- WSL 2 (Ubuntu) / Linux Environment
- Git

### Langkah Menjalankan:
1. **Clone repositori**:
   ```bash
   git clone https://github.com/jackalwyssad/warung-ibu-beti.git
   cd warung-ibu-beti
   ```

2. **Salin Environment**:
   ```bash
   cp .env.example .env
   ```

3. **Jalankan Container Docker via Laravel Sail**:
   ```bash
   ./vendor/bin/sail up -d
   ```

4. **Jalankan Migrasi Database**:
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

5. **Inisialisasi Topologi RabbitMQ**:
   ```bash
   ./vendor/bin/sail artisan warung:topologi
   ```
   *Perintah ini akan mendeklarasikan exchange `warung.events`, `warung.dlx`, antrean, dan binding secara otomatis.*

6. **Menjalankan Consumer Pembukuan (Terminal 1)**:
   ```bash
   ./vendor/bin/sail artisan pembukuan:konsumsi
   ```

7. **Menjalankan Consumer Notifikasi Stok Menipis (Terminal 2)**:
   ```bash
   ./vendor/bin/sail artisan notifikasi:stok --batas=5
   ```

8. **Menjalankan Laravel Queue Worker (Terminal 3)**:
   ```bash
   ./vendor/bin/sail artisan queue:work rabbitmq --queue=notifikasi-wa
   ```

---

## 🧪 7. Pengujian Otomatis

### A. Menjalankan Unit & Feature Test (PHPUnit)
Semua skenario pengujian REST API, validasi RFC 9457, penyesuaian stok, pengiriman event RabbitMQ, dan idempotensi pembukuan telah diotomatisasi:
```bash
./vendor/bin/sail artisan test
```
*Status: **17 tests passed (52 assertions)**.*

### B. Validasi Kontrak OpenAPI dengan Spectral
Linter otomatis untuk memastikan kepatuhan spesifikasi OpenAPI 3.1.0:
```bash
npx spectral lint openapi.yaml
```
*Status: **0 errors / 0 warnings**.*

---

## 👥 Kontribusi Kelompok Intel
- **Modul 1 (REST API & OpenAPI):** Perancangan kontrak `openapi.yaml`, validasi Spectral, implementasi endpoint katalog & penyesuaian stok berstatus atomik (`lockForUpdate`), serta penanganan RFC 9457 Problem Details.
- **Modul 2 (Asynchronous Messaging):** Konfigurasi RabbitMQ (Topic Exchange, DLQ, Queue Bindings), Domain Event `StokDisesuaikan`, Consumer Pembukuan Idempoten (`jurnal_penjualan`), Notifikasi Realtime batas stok, dan Background Queue Worker WhatsApp.
