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
        // NOTE - This table contains all of the actual processed payments
        Schema::create('payments_processed', function (Blueprint $table) {
            $table->id();

            $table->integer('userid')->index();
            // $table->integer('referral_by')->index()->nullable();

            $table->set("mode", ['payment', 'subscription']);

            $table->string('subscription', length: 100)->nullable();
            $table->string('payment_intent', length: 100)->nullable();
            $table->string('payment_invoice', length: 100)->nullable();

            $table->string('customer', length: 100);

            $table->decimal('payment_total', total: 8, places: 2);
            $table->decimal('payment_tax', total: 8, places: 2);
            $table->decimal('payment_net', total: 8, places: 2);
            $table->string('payment_currency', length: 100)->default('eur');
            
            $table->string('payer_tax_exempt', length: 100)->nullable();
            $table->string('payer_email', length: 100);
            $table->string('payer_fullname', length: 100);
            $table->string('payer_address1', length: 200)->nullable();
            $table->string('payer_address2', length: 200)->nullable();
            $table->string('payer_city', length: 100)->nullable();
            $table->string('payer_postcode', length: 100)->nullable();
            $table->string('payer_country_code', length: 100)->nullable();
            $table->string('payer_country', length: 100)->nullable();
            $table->string('payer_state', length: 100)->nullable();

            $table->datetime('payment_date');

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments_processed');
    }
};
