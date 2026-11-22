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
        Schema::table('chat_messages', function (Blueprint $table) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->boolean('is_stopped')->default(false);
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->dropColumn('is_stopped');
            });
        });
    }
};
