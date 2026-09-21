<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('itemid')->unique();
            $table->string('itemname')->nullable();
            $table->string('agr_pengarang')->nullable();
            $table->string('agr_itemcurriculum')->nullable();
            $table->string('agr_itemlevel')->nullable();
            $table->string('agr_itemsegment')->nullable();
            $table->string('agr_itemseriesid')->nullable();
            $table->string('agr_jilid')->nullable();
            $table->string('agr_packingtypeid')->nullable();
            $table->decimal('agr_quantity', 20, 5)->default(0);
            $table->date('agr_publishdate')->nullable();
            $table->integer('agr_publishyear')->nullable();
            $table->string('erl_klbid')->nullable();
            $table->string('erl_bidang')->nullable();
            $table->string('erl_nampel')->nullable();
            $table->string('agr_taxbupelid')->nullable();
            $table->decimal('grossdepth', 20, 5)->default(0);
            $table->decimal('grossheight', 20, 5)->default(0);
            $table->decimal('grosswidth', 20, 5)->default(0);
            $table->decimal('netweight', 20, 5)->default(0);
            $table->string('agr_brandname')->nullable();
            $table->string('agr_gradename')->nullable();
            $table->decimal('amount', 20, 5)->default(0);
            $table->string('dimension2_')->nullable();
            $table->string('editor_area')->nullable();
            $table->string('namealias')->nullable();
            $table->string('kategori')->nullable();
            $table->string('KOORD_EDT')->nullable();
            $table->string('CE_EDT')->nullable();
            $table->smallInteger('erl_isdigital')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};