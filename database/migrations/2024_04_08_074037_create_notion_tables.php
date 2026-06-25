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

        Schema::create('notion_access_tokens', function (Blueprint $table) {
            $table->id();

            $table->integer('userid')->index();

            $table->string('notion_user_id', length:100);
            $table->string('workspace_id', length:100);
            $table->string('nickname', length:100);
            $table->string('token', length: 255);
            $table->datetime('expiry_date');
            $table->datetime('last_check_scan')->useCurrent();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_valid')->default(true);

            $table->timestamps();
        });

        Schema::create('notion_databases', function (Blueprint $table) {
            $table->id();

            $table->integer('userid')->index();
            $table->integer('token_id')->index();

            $table->string('database_id', length: 100);
            $table->string('database_parent_page', length: 100);
            $table->string('database_name', length: 100);
            // $table->string('page_icon', length: 100);

            $table->string('column_media', length: 20)->nullable();
            $table->string('column_media_thumbnail', length: 20)->nullable();
            $table->string('column_post_as_story', length: 20)->nullable();
            $table->string('column_is_ready', length: 20)->nullable();
            $table->string('column_social_account', length: 20)->nullable();
            $table->string('column_post_date', length: 20)->nullable();
            $table->string('column_ns_comments', length: 20)->nullable();
            $table->string('column_ns_status', length: 20)->nullable();
            

            $table->datetime('last_check_scan')->useCurrent();
            $table->datetime('last_check_scaffolding_scan')->useCurrent();
            $table->datetime('last_check_for_new_posts')->useCurrent();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_valid')->default(true);

            $table->string('error_message', length: 1024)->nullable();

            $table->timestamps();
        });

        Schema::create('notion_posts', function (Blueprint $table) {
            $table->id();

            $table->integer('userid')->index();
            $table->integer('database_id')->index();
            $table->string('post_page_id', length: 255)->index();
            $table->integer('account_id')->index();

            $table->string('post_name', length: 155)->nullable();

            $table->string('platform', length: 100);
            $table->boolean('platform_is_story')->default(false);
            $table->string('status', length: 50)->nullable();
            $table->boolean('in_flight')->default(false);
            $table->datetime('in_flight_start')->nullable();
            $table->datetime('scheduled_date');
            $table->datetime('posted_date')->nullable();

            $table->string('posted_foreign_id', length: 255)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_valid')->default(true);

            $table->timestamps();
        });

        Schema::create('notion_social_accounts', function (Blueprint $table) {
            $table->id();

            $table->integer('userid')->index();
            $table->integer('token_id')->index();

            $table->string('account_id', length: 100)->index();
            $table->string('account_full_identifier', length:255)->nullable();
            $table->integer('database_id')->index()->nullable();
            $table->string('option_select_id', length: 100)->index()->nullable();

            $table->string('platform', length: 100);
            $table->string('name', length: 256);
            $table->string('profile_picture', length: 512)->nullable();

            $table->datetime('last_token_check_scan')->useCurrent();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_valid')->default(true);

            $table->timestamps();
        });

        Schema::create('notion_social_accounts_tokens', function (Blueprint $table) {
            $table->id();

            $table->integer('userid')->index();
            $table->string('platform', length: 100);
            $table->string('access_token', length: 1024);
            $table->string('access_token_page', length: 1024)->nullable();
            $table->string('refresh_token', length: 1024)->nullable();
            $table->datetime('expiry_date')->nullable();
            $table->datetime('refresh_token_expiry_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_valid')->default(true);

            $table->timestamps();
        });





    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notion_access_tokens');
        Schema::dropIfExists('notion_databases');
        Schema::dropIfExists('notion_posts');
        Schema::dropIfExists('notion_social_accounts');
        Schema::dropIfExists('notion_social_accounts_tokens');
    }
};