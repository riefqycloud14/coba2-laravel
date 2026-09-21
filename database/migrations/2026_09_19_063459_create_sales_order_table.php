<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('bu', 10);                     // '59', '61', '81'
            $table->string('sales_id', 50);               // Nomor SO / Sales Order
            $table->string('accountnum', 50)->nullable(); // Kode Customer
            $table->string('inventlocationid', 20)->nullable(); // Gudang (WH)
            $table->string('itemid', 50);                 // Kode Buku / Item
            $table->decimal('qty_order', 15, 2)->default(0);      // Jumlah Pesanan
            $table->decimal('qty_outstanding', 15, 2)->default(0);// Sisa SO Belum Terkirim
            $table->date('created_date')->nullable();     // Tanggal SO
            $table->timestamps();

            // Unique index gabungan agar data tidak duplikat
            $table->unique(['bu', 'sales_id', 'itemid'], 'so_bu_sales_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};