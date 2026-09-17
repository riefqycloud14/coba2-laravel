<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('accountnum')->index();
                $table->string('bu')->index();
                $table->string('name')->nullable();
                $table->text('address')->nullable();
                $table->string('inventsiteid')->nullable();
                $table->string('agr_schoolid')->nullable();
                $table->string('agr_consignmentid')->nullable();
                $table->string('agr_schooltypeid')->nullable();
                $table->string('county')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->decimal('creditmax', 18, 2)->default(0);
                $table->string('companychainid')->nullable();
                $table->string('zipcode')->nullable();
                $table->string('custclassificationid')->nullable();
                $table->string('agr_gradeid')->nullable();
                $table->string('dimension')->nullable();
                $table->string('segmentid')->nullable();
                $table->string('erl_be_id')->nullable();
                $table->string('custgroup')->nullable();
                $table->string('subgroupid')->nullable();
                $table->string('subsegmentid')->nullable();
                $table->timestamp('modifieddatetime')->nullable();
                $table->timestamp('createddatetime')->nullable();
                $table->string('swsalesunitid')->nullable();
                $table->string('invoiceaccount')->nullable();
                $table->string('phone')->nullable();
                $table->string('cellularphone')->nullable();
                $table->string('nikum')->nullable();
                $table->string('npsn')->nullable();
                $table->timestamps();

                $table->unique(['accountnum', 'bu']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
