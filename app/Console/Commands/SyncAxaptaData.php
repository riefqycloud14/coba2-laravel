<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AxaptaSyncService;
use Carbon\Carbon;

class SyncAxaptaData extends Command
{
    // Bisa dipanggil otomatis (tanpa opsi) atau manual misal: php artisan axapta:sync --tgl_awal=2026-09-01 --tgl_akhir=2026-09-18
    protected $signature = 'axapta:sync {--tgl_awal=} {--tgl_akhir=}';
    protected $description = 'Sinkronisasi otomatis transaksi harian dan master customer dari Axapta';

    public function handle()
    {
        $this->info("Mulai sinkronisasi data Axapta...");

        $sync = new AxaptaSyncService();

        // 1. Sync Master Item / Buku
        $this->info("SINKRONISASI MASTER ITEM...");
        if (method_exists($sync, 'syncItems')) {
            $sync->syncItems();
        }

        // 2. Sync Master Customer
        $this->info("SINKRONISASI MASTER CUSTOMER...");
        $sync->syncCustomers();

        // 3. Penentuan Tanggal Sync Transaksi
        // Jika opsi tgl_awal/tgl_akhir diisi saat jalankan command, gunakan opsi tersebut. Jika kosong, default kemarin s/d hari ini.
        $tglAwal  = $this->option('tgl_awal') ?? Carbon::yesterday()->format('Y-m-d');
        $tglAkhir = $this->option('tgl_akhir') ?? Carbon::today()->format('Y-m-d');

        // 4. Sync Transaksi Invoice & Enrichment
        $this->info("SINKRONISASI TRANSAKSI INVOICE ($tglAwal s/d $tglAkhir)...");
        $sync->syncTransactionAndCustomer($tglAwal, $tglAkhir);

        $this->info("Sinkronisasi data Axapta berhasil diselesaikan!");
    }
}