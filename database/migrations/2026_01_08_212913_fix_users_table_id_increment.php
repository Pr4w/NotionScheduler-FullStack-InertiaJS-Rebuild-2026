<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // This re-declares the column as an auto-incrementing big integer
            $table->bigIncrements('id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // To undo, we't typically just leave it as a big integer
            $table->unsignedBigInteger('id')->change();
        });
    }
};
