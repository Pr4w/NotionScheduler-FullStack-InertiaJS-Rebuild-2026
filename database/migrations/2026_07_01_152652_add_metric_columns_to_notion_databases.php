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
        Schema::table('notion_databases', function (Blueprint $table) {
            // Notion property IDs for the analytics columns we push post metrics back into.
            // Mirrors the other column_* fields — populated by CorrectNotionDatabaseScaffolding.
            $table->string('column_metric_views', length: 20)->nullable();
            $table->string('column_metric_likes', length: 20)->nullable();
            $table->string('column_metric_comments', length: 20)->nullable();
            $table->string('column_metric_shares', length: 20)->nullable();
            $table->string('column_metric_saves', length: 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notion_databases', function (Blueprint $table) {
            $table->dropColumn([
                'column_metric_views',
                'column_metric_likes',
                'column_metric_comments',
                'column_metric_shares',
                'column_metric_saves',
            ]);
        });
    }
};
