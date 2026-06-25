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

        if (!Schema::hasColumn('users', 'completed_wizard')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('completed_wizard')->default(false);
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('stripe_accounts');
    }
};