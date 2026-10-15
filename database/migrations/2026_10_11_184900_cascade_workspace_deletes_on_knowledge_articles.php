<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });
    }
};
