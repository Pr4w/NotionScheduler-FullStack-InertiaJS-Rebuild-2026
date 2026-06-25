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
            $table->foreign('userid')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('notion_databases', function (Blueprint $table) {
            $table->foreign('userid')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('token_id')
                ->references('id')
                ->on('notion_access_tokens')
                ->cascadeOnDelete();
        });

        Schema::table('notion_posts', function (Blueprint $table) {
            $table->foreign('userid')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // $table->foreign('database_id')
            //     ->references('id')
            //     ->on('notion_databases')
            //     ->cascadeOnDelete();

            $table->foreign('account_id')
                ->references('id')
                ->on('notion_social_accounts')
                ->cascadeOnDelete();
        });

        Schema::table('notion_social_accounts', function (Blueprint $table) {
            $table->foreign('userid')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('token_id')
                ->references('id')
                ->on('notion_social_accounts_tokens')
                ->cascadeOnDelete();

            // $table->foreign('database_id')
            //     ->references('id')
            //     ->on('notion_databases')
            //     ->cascadeOnDelete();
        });

        Schema::table('notion_social_accounts_tokens', function (Blueprint $table) {
            $table->foreign('userid')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notion_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['userid']);
        });

        Schema::table('notion_databases', function (Blueprint $table) {
            $table->dropForeign(['userid']);
            $table->dropForeign(['token_id']);
        });

        Schema::table('notion_posts', function (Blueprint $table) {
            $table->dropForeign(['userid']);
            // $table->dropForeign(['database_id']);
            $table->dropForeign(['account_id']);
        });

        Schema::table('notion_social_accounts', function (Blueprint $table) {
            $table->dropForeign(['userid']);
            $table->dropForeign(['token_id']);
            // $table->dropForeign(['database_id']);
        });

        Schema::table('notion_social_accounts_tokens', function (Blueprint $table) {
            $table->dropForeign(['userid']);
        });
    }
};
