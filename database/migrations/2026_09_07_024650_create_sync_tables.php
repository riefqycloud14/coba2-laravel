<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tabel lokal untuk menampung Invoice
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->nullable();
            $table->string('bu', 10);
            $table->date('tglfak')->nullable();
            $table->date('tglax')->nullable();
            $table->string('type', 20)->nullable();
            $table->string('accountnum', 50)->nullable();
            $table->string('salesunit', 50)->nullable();
            $table->string('sumberdana', 50)->nullable();
            $table->string('program', 50)->nullable();
            $table->string('swa', 20)->nullable();
            $table->string('periode', 50)->nullable();
            
            // Nominal dan Kalkulasi Transaksi
            $table->decimal('subtot', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('tr', 8, 2)->default(0);
            $table->decimal('trnum', 18, 2)->default(0);
            $table->decimal('totaltr', 18, 2)->default(0);
            $table->decimal('trbiaya', 8, 2)->default(0);
            $table->decimal('totalbiaya', 18, 2)->default(0);
            $table->decimal('netto', 18, 2)->default(0);
            $table->decimal('nettbiaya', 18, 2)->default(0);

            // Detail Informasi Produk & Pelanggan
            $table->string('judbuk', 255)->nullable();
            $table->string('namrang', 255)->nullable();
            $table->string('jenjang', 50)->nullable();
            $table->date('tglterbit')->nullable();
            $table->integer('thnterbit')->nullable();
            $table->string('grup', 50)->nullable();
            $table->string('namlan', 255)->nullable();
            $table->string('sekolah', 255)->nullable();
            $table->string('namsal', 255)->nullable();
            $table->date('createddat')->nullable();

            $table->timestamps();

            // Unique key agar upsert() berjalan lancar tanpa duplikasi
            $table->unique(['invoice_no', 'bu']);
        });

        // Tabel lokal untuk menampung Master Pelanggan
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('accountnum');
            $table->string('name')->nullable();
            $table->string('dimension', 10);
            $table->string('smmsalesun')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('customers');
    }
};