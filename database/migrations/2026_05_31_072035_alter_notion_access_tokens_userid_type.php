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
        Schema::table('notion_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('userid')->change();
        });

        Schema::table('notion_databases', function (Blueprint $table) {
            $table->unsignedBigInteger('userid')->change();
            $table->unsignedBigInteger('token_id')->change();
        });

        Schema::table('notion_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('userid')->change();
            $table->unsignedBigInteger('database_id')->change();
            $table->unsignedBigInteger('account_id')->change();
        });

        Schema::table('notion_social_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('userid')->change();
            $table->unsignedBigInteger('database_id')->nullable()->change();
            $table->unsignedBigInteger('token_id')->change();
        });

        Schema::table('notion_social_accounts_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('userid')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notion_access_tokens', function (Blueprint $table) {
            $table->integer('userid')->change();
        });

        Schema::table('notion_databases', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->integer('token_id')->change();
        });

         Schema::table('notion_posts', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->integer('database_id')->change();
            $table->integer('account_id')->change();
        });

        Schema::table('notion_social_accounts', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->integer('database_id')->nullable()->change();
            $table->integer('token_id')->change();
        });

        Schema::table('notion_social_accounts_tokens', function (Blueprint $table) {
            $table->integer('userid')->change();
        });
        
    }
};
