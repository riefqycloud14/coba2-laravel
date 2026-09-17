<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AxaptaSyncService;

class SyncAxaptaCommand extends Command
{
    // Pastikan {tgl_awal} dan {tgl_akhir} terdaftar tepat di sini
    protected $signature = 'app:sync-axapta {tgl_awal} {tgl_akhir}';

    protected $description = 'Tarik data Invoice dan Customer dari Axapta Pontianak & Samarinda';

    public function handle(AxaptaSyncService $service)
    {
        // Mengambil nilai argumen dari terminal
        $tglAwal = $this->argument('tgl_awal');
        $tglAkhir = $this->argument('tgl_akhir');

        $this->info("Sedang menarik data dari periode {$tglAwal} sampai {$tglAkhir}...");

        // Jalankan proses penarikan di service
        $service->syncAllBranches($tglAwal, $tglAkhir);

        $this->info("Proses tarik data berhasil selesai!");
    }
}