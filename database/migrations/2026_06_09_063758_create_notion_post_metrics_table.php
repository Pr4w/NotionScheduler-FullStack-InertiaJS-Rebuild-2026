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
        Schema::create('notion_post_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('notion_posts')->cascadeOnDelete();
            $table->string('platform');
            $table->timestamp('recorded_at');
            
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('saves')->nullable();
            
            $table->json('raw_payload')->nullable();
            
            $table->timestamps();
            
            $table->index(['content_id', 'platform', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notion_post_metrics');
    }
};
