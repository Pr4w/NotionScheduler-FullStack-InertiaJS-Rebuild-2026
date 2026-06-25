<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users',
            'user_affiliates',
            'subscriptions',
            'notion_social_accounts_tokens',
            'notion_social_accounts'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                // We use autoIncrement() to add the attribute to the existing PK
                $table->unsignedBigInteger('id')->autoIncrement()->change();
            });
        }
    }

    public function down(): void
    {
        // Reversing this would mean removing auto-increment, 
        // which is rarely needed, but here is the logic:
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('id')->autoIncrement(false)->change();
            });
        }
    }
};
