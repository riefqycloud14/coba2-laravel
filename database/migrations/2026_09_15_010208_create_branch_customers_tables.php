<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skema kolom master customer DBF
     */
    private function getCustomerSchema(Blueprint $table)
    {
        $table->id();
        $table->string('accountnum')->nullable()->index();
        $table->string('name')->nullable();
        $table->text('address')->nullable();
        $table->string('inventsite')->nullable();
        $table->string('agr_school')->nullable();
        $table->string('agr_consig')->nullable();
        $table->string('agr_schoo2')->nullable();
        $table->string('county')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('creditmax')->nullable();
        $table->string('companycha')->nullable();
        $table->string('zipcode')->nullable();
        $table->string('custclassi')->nullable();
        $table->string('agr_gradei')->nullable();
        $table->string('dimension')->nullable();
        $table->string('segmentid')->nullable();
        $table->string('erl_be_id')->nullable();
        $table->string('custgroup')->nullable();
        $table->string('subgroupid')->nullable();
        $table->string('subsegment')->nullable();
        $table->string('modifiedda')->nullable();
        $table->string('createddat')->nullable();
        $table->string('smmsalesun')->nullable();
        $table->string('invoiceacc')->nullable();
        $table->string('phone')->nullable();
        $table->string('cellularph')->nullable();
        $table->string('nikum')->nullable();
        $table->string('npsn')->nullable();
        $table->timestamps();
    }

    public function up(): void
    {
        // Tabel khusus Samarinda
        Schema::create('customers_samarinda', function (Blueprint $table) {
            $this->getCustomerSchema($table);
        });

        // Tabel khusus Pontianak
        Schema::create('customers_pontianak', function (Blueprint $table) {
            $this->getCustomerSchema($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers_samarinda');
        Schema::dropIfExists('customers_pontianak');
    }
};