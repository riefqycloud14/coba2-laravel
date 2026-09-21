<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('bu', 10);                   // '59', '61', '81'
            $table->string('inventlocationid', 20);     // Kode Gudang (WH)
            $table->string('itemid', 50);               // Kode Barang / Buku
            $table->decimal('stok_physical', 15, 2)->default(0);  // ERL_STOCKPOSITIONDB
            $table->decimal('stok_transit', 15, 2)->default(0);   // QUARANTINEORDER
            $table->decimal('po_outstanding', 15, 2)->default(0); // POINTERNALOUTSTANDING
            $table->timestamps();

            // Unique Index gabungan 3 kolom agar tidak terjadi duplikasi data
            $table->unique(['bu', 'inventlocationid', 'itemid'], 'stocks_bu_wh_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};