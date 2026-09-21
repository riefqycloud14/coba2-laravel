<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Hapus index unik lama yang hanya 2 kolom
            $table->dropUnique('invoices_invoice_no_bu_unique');

            // Tambahkan index unik baru gabungan 3 kolom (+ kodbuk)
            $table->unique(['invoice_no', 'bu', 'kodbuk'], 'invoices_invoice_no_bu_kodbuk_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_invoice_no_bu_kodbuk_unique');
            $table->unique(['invoice_no', 'bu'], 'invoices_invoice_no_bu_unique');
        });
    }
};