<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_order_outstandings')) {
            Schema::create('sales_order_outstandings', function (Blueprint $table) {
                $table->id();

                // Kolom Tambahan Hasil Perhitungan FoxPro
                $table->integer('bu')->comment('59 atau 61');
                $table->integer('so_eks')->default(0);
                $table->decimal('so_amount', 18, 4)->default(0);
                $table->decimal('so_nett', 18, 4)->default(0);
                $table->string('swa', 5)->nullable();
                $table->string('jenis_so', 20)->nullable();

                // 41 Kolom Lengkap dari AX
                $table->string('sales_id')->index();
                $table->string('sales_responsible')->nullable();
                $table->integer('sales_status')->nullable();
                $table->string('sales_unit_id')->nullable();
                $table->string('agr_sales_resp_name')->nullable();
                $table->dateTime('created_date_time1')->nullable();
                $table->string('dimension')->nullable();
                $table->string('dimension2_')->nullable();
                $table->string('dimension3_')->nullable();
                $table->integer('tcn_sotype')->nullable();
                $table->integer('approval_status')->nullable();
                $table->string('customer_ref')->nullable();
                $table->string('purch_order_form_num')->nullable();
                $table->string('dataareaid')->nullable();
                $table->bigInteger('recid')->nullable();
                $table->string('dataareaid_2')->nullable();
                $table->string('item_id')->nullable();
                $table->decimal('qty_ordered', 18, 4)->default(0);
                $table->decimal('sales_price', 18, 4)->default(0);
                $table->decimal('line_disc', 18, 4)->default(0);
                $table->string('sales_group')->nullable();
                $table->decimal('line_amount', 18, 4)->default(0);
                $table->string('invent_dim_id')->nullable();
                $table->decimal('line_percent', 18, 4)->default(0);
                $table->string('dataareaid_3')->nullable();
                $table->string('account_num')->nullable();
                $table->string('agr_school_id')->nullable();
                $table->string('dataareaid_4')->nullable();
                $table->text('item_name')->nullable();
                $table->string('agr_pengarang')->nullable();
                $table->string('agr_brand_name')->nullable();
                $table->string('agr_grade_name')->nullable();
                $table->string('agr_item_curriculum')->nullable();
                $table->string('agr_item_segment')->nullable();
                $table->dateTime('agr_publish_date')->nullable();
                $table->integer('agr_publish_year')->nullable();
                $table->string('dataareaid_5')->nullable();
                $table->decimal('qty', 18, 4)->default(0);
                $table->integer('status_issue')->nullable();
                $table->string('dataareaid_6')->nullable();
                $table->string('erl_invent_location_id')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_outstandings');
    }
};