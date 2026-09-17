<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            Schema::create('raw_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no');
            $table->string('bu',10)->index();
            $table->date('tglfak')->index();
            $table->date('tglax')->nullable();
            $table->string('type')->default('SALES');  //sales atau return

            //data customer
            $table->string('accountnum')->index();
            $table->string('customer_name')->nullable();  //nama customer dari axapta
            $table->string('sekolah')->nullable();  //nama sekolah dari axapta

            //detail buku 
            $table->string('kodbuk')->nullable()->index();  //ITEMID
            $table->string('judbuk')->nullable();           //ITEMNAME
            $table->string('pengarang')->nullable();        //AGR_PENGAR
            $table->string('jenjang')->nullable();         //AGR_GRADEN
            $table->string('mapel')->nullable();          //ERL_BIDANG / NAMPEL
            $table->string('thnterbit')->nullable();          //AGR_PUBLI2
            $table->decimal('harga_buku', 15, 2)->default(0);          //AMOUNT

            //data sales 
            $table->string('salesunit')->nullable();
            $table->string('sumberdana')->nullable();
            $table->string('program')->nullable();

            //data uang mentah
            $table->decimal('subtot', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('tr', 8, 2)->default(0);
            $table->decimal('discpercent', 8, 2)->default(0);

            $table->timestamps();

            $table->unique(['invoice_no', 'bu', 'kodbuk']);


            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_invoices');
    }
};
