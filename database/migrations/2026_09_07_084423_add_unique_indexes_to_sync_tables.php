<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Indeks unik sudah dibuat langsung di tabel utama (create_sync_tables)
    }

    public function down(): void
    {
        //
    }
};