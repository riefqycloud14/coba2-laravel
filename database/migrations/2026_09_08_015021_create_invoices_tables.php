<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambahkan baris ini untuk menghapus tabel lama yang bentrok
        Schema::dropIfExists('invoices');

Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_no')->index();
    $table->string('bu')->index();
    $table->date('tglfak')->nullable();
    $table->date('tglax')->nullable();
    $table->string('type')->default('SALES');
    $table->string('accountnum')->nullable();
    
    // KOLOM PENGAYAAN / ENRICHMENT DETAILS
    $table->string('namlan')->nullable();  // Menyimpan Nama Pelanggan (c.name)
    $table->string('sekolah')->nullable(); // Menyimpan Nama Sekolah (c.agr_schoolid)
    
    $table->string('salesunit')->nullable();
    $table->string('sumberdana')->nullable();
    $table->string('program')->nullable();
    $table->string('swa')->nullable();
    $table->string('periode')->default('A2A SAMA');
    $table->decimal('subtot', 18, 2)->default(0);
    $table->decimal('total', 18, 2)->default(0);
    $table->decimal('tr', 8, 2)->default(0);
    $table->decimal('trnum', 18, 2)->default(0);
    $table->decimal('totaltr', 18, 2)->default(0);
    $table->decimal('trbiaya', 8, 2)->default(0);
    $table->decimal('totalbiaya', 18, 2)->default(0);
    $table->decimal('netto', 18, 2)->default(0);
    $table->decimal('nettbiaya', 18, 2)->default(0);
    $table->timestamps();

    $table->unique(['invoice_no', 'bu']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};