<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'thlap')) $table->smallInteger('thlap')->nullable();
            if (!Schema::hasColumn('invoices', 'kodcab')) $table->string('kodcab', 10)->nullable();
            if (!Schema::hasColumn('invoices', 'kodbuk')) $table->string('kodbuk', 20)->nullable();
            if (!Schema::hasColumn('invoices', 'cvaccount')) $table->string('cvaccount', 20)->nullable();
            if (!Schema::hasColumn('invoices', 'agr_invoicedatereturn')) $table->date('agr_invoicedatereturn')->nullable();
            if (!Schema::hasColumn('invoices', 'blfak')) $table->smallInteger('blfak')->nullable();
            if (!Schema::hasColumn('invoices', 'hrfak')) $table->smallInteger('hrfak')->nullable();
            if (!Schema::hasColumn('invoices', 'pricegroupid')) $table->string('pricegroupid', 20)->nullable();
            if (!Schema::hasColumn('invoices', 'soid')) $table->string('soid', 20)->nullable();
            if (!Schema::hasColumn('invoices', 'kodsal')) $table->string('kodsal', 10)->nullable();
            if (!Schema::hasColumn('invoices', 'nosp')) $table->string('nosp', 20)->nullable();
            if (!Schema::hasColumn('invoices', 'alasan')) $table->string('alasan', 50)->nullable();
            if (!Schema::hasColumn('invoices', 'customerref')) $table->string('customerref', 100)->nullable();
            if (!Schema::hasColumn('invoices', 'payment')) $table->string('payment', 20)->nullable();
            if (!Schema::hasColumn('invoices', 'kwantum')) $table->decimal('kwantum', 15, 2)->default(0);
            if (!Schema::hasColumn('invoices', 'harga')) $table->decimal('harga', 15, 2)->default(0);
            if (!Schema::hasColumn('invoices', 'discpercent')) $table->decimal('discpercent', 8, 2)->default(0);
            if (!Schema::hasColumn('invoices', 'discamount')) $table->decimal('discamount', 15, 2)->default(0);
            if (!Schema::hasColumn('invoices', 'discrp')) $table->decimal('discrp', 15, 2)->default(0);
            if (!Schema::hasColumn('invoices', 'orderaccount')) $table->string('orderaccount', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'thlap', 'kodcab', 'kodbuk', 'cvaccount', 'agr_invoicedatereturn', 
                'blfak', 'hrfak', 'pricegroupid', 'soid', 'kodsal', 'nosp', 
                'alasan', 'customerref', 'payment', 'kwantum', 'harga', 
                'discpercent', 'discamount', 'discrp', 'orderaccount'
            ]);
        });
    }
};