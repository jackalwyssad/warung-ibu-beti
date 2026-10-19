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

Arsitektur sistem memisahkan proses transaksi kasir yang sinkron dengan proses analitik/pembukuan dan notifikasi yang asinkron:

```mermaid
flowchart TD
    subgraph Klien ["Klien & Antarmuka"]
        Kasir["Aplikasi Kasir / POS"]
        BotWA["Bot WhatsApp Pelanggan"]
        Swagger["Swagger UI (Port 8082)"]
    end

    subgraph CoreService ["Layanan Inti: Katalog & Stok (Port 8000)"]
        API["REST API Controller<br/>(ProdukController)"]
        DB[("MySQL Database<br/>Table: produk")]
        Event["Domain Event:<br/>StokDisesuaikan"]
        Publisher["RabbitPublisher"]
        QueueWorker["Laravel Queue Worker<br/>(SinkronkanStokWhatsApp)"]
    end

    subgraph Broker ["Message Broker: RabbitMQ (Port 5674 / 15674)"]
        ExEvents[["Topic Exchange:<br/>warung.events"]]
        ExDLX[["Fanout DLX:<br/>warung.dlx"]]
        
        QPenjualan[("Queue: pembukuan.penjualan<br/>(x-dead-letter: warung.dlx)")]
        QNotif[("Queue: notifikasi.stok")]
        QDLQ[("Queue: pembukuan.penjualan.dlq")]
        QWA[("Queue: notifikasi-wa")]
    end

    subgraph SubSistem ["Layanan Konsumen & Integrasi"]
        ConsPembukuan["Consumer Pembukuan<br/>(pembukuan:konsumsi)"]
        DBJurnal[("MySQL Database<br/>Table: jurnal_penjualan")]
        ConsNotif["Consumer Notifikasi<br/>(notifikasi:stok)"]
        Alert["Alert WhatsApp / Layar Kasir<br/>(Peringatan Stok Menipis)"]
    end

    %% Flow REST
    Kasir -->|HTTP POST penyesuaian-stok| API
    BotWA -->|HTTP POST penyesuaian-stok| API
    Swagger -->|Uji Coba Interaktif| API
    API -->|lockForUpdate & Update| DB
    API -->|Dispatch| Event
    Event --> Publisher
    Publisher -->|Publish JSON & message_id| ExEvents

    %% Queue Job
    API -->|Push background job| QWA
    QWA --> QueueWorker

    %% Routing RabbitMQ
    ExEvents -->|Routing Key: stok.penjualan| QPenjualan
    ExEvents -->|Routing Key: stok.*| QNotif
    QPenjualan -.->|NACK / Reject / Rusak| ExDLX
    ExDLX --> QDLQ

    %% Consumer
    QPenjualan --> ConsPembukuan
    ConsPembukuan -->|firstOrCreate by message_id| DBJurnal
    QNotif --> ConsNotif
    ConsNotif -->|Cek stok_sesudah <= batas| Alert
```

---

## 🔄 2. Diagram Alur Transaksi & Messaging (Sequence Diagram)

Diagram berikut mengilustrasikan siklus lengkap dari kasir melakukan transaksi hingga pencatatan pembukuan dan deteksi stok menipis:

```mermaid
sequenceDiagram
    autonumber
    actor Kasir as Kasir / POS
    participant API as REST API (Laravel)
    participant DB as MySQL (produk)
    participant RMQ as RabbitMQ (warung.events)
    participant ConsBuku as Consumer Pembukuan
    participant Jurnal as MySQL (jurnal_penjualan)
    participant ConsNotif as Consumer Notifikasi

    Kasir->>API: POST /api/v1/produk/{sku}/penyesuaian-stok<br/>(perubahan: -3, alasan: penjualan, referensi: TRX-101)
    
    activate API
    API->>DB: BEGIN TRANSACTION & SELECT ... FOR UPDATE
    alt Stok Cukup (misal 10 -> 7)
        API->>DB: UPDATE produk SET stok = stok - 3; COMMIT
        API->>RMQ: Publish Event (Routing Key: stok.penjualan)<br/>Payload: sku, perubahan, alasan, stok_sesudah: 7, message_id
        API-->>Kasir: HTTP 200 OK (Data Produk & Stok Terkini)
    else Stok Tidak Cukup (misal stok = 2, minta -3)
        API->>DB: ROLLBACK
        API-->>Kasir: HTTP 409 Conflict (RFC 9457 Problem Details:<br/>detail: "Stok produk tidak mencukupi untuk pengurangan 3...")
    end
    deactivate API

    par Asynchronous: Pembukuan Otomatis
        RMQ->>ConsBuku: Deliver message (stok.penjualan)
        activate ConsBuku
        ConsBuku->>Jurnal: Cek & Simpan (firstOrCreate message_id)
        alt Pesan Valid & Belum Pernah Dicatat
            Jurnal-->>ConsBuku: Row tersimpan
            ConsBuku->>RMQ: basic_ack (Hapus dari antrean)
        else Pesan Duplikat (Idempoten)
            ConsBuku->>RMQ: basic_ack (Abaikan & ACK)
        else Pesan Korup / Format Salah
            ConsBuku->>RMQ: basic_nack(requeue=false) -> Masuk DLQ (pembukuan.penjualan.dlq)
        end
        deactivate ConsBuku
    and Asynchronous: Notifikasi Stok Menipis
        RMQ->>ConsNotif: Deliver message (stok.*)
        activate ConsNotif
        opt stok_sesudah <= batas_minimum (misal <= 5)
            ConsNotif->>ConsNotif: Cetak Notifikasi Realtime / Alert Peringatan
        end
        ConsNotif->>RMQ: basic_ack
        deactivate ConsNotif
    end
```

---

## 🎯 3. Alur Idempotensi & Dead Letter Queue (DLQ)

```mermaid
flowchart TD
    Start([Pesan Diterima Consumer]) --> ParseJSON{Validasi Format JSON}
    
    ParseJSON -- Tidak Valid / Rusak --> NackDLQ["basic_nack(requeue: false)"]
    NackDLQ --> DLX["Exchange: warung.dlx"]
    DLX --> QueueDLQ[("Queue: pembukuan.penjualan.dlq<br/>(Tersimpan untuk Audit / Replay)")]
    
    ParseJSON -- Valid --> CekMsgId{"Cari message_id<br/>di jurnal_penjualan"}
    CekMsgId -- Sudah Ada (Duplikat) --> AckDup["Log: Pesan duplikat dilewati<br/>(Idempoten)<br/>basic_ack()"]
    CekMsgId -- Belum Ada --> InsertDB["Insert ke jurnal_penjualan<br/>(sku, jumlah, total, message_id)"]
    InsertDB --> AckSukses["basic_ack()"]
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
   git clone <URL_REPOSITORY_GITHUB>
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
