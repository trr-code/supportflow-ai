<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('purged_at')->nullable();
            $table->string('tone')->default('professional');
            $table->string('answer_length')->default('standard');
            $table->timestamps();
        });

        Schema::create('workspace_browser_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamps();
        });

        Schema::create('workspace_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime');
            $table->string('extension', 8);
            $table->unsignedInteger('byte_size');
            $table->string('status');
            $table->uuid('index_generation');
            $table->string('failure_reason')->nullable();
            $table->unsignedInteger('extracted_characters')->nullable();
            $table->foreignId('knowledge_article_id')->nullable()->constrained('knowledge_articles')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });

        Schema::create('workspace_guidances', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('category');
            $table->string('body', 800);
            $table->unsignedTinyInteger('position');
            $table->timestamps();
        });

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->foreignUuid('workspace_id')->nullable()->after('id')->constrained('workspaces')->nullOnDelete();
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->foreignUuid('workspace_id')->nullable()->after('id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('demo_session_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
            $table->string('demo_session_id')->nullable(false)->change();
        });

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::dropIfExists('workspace_guidances');
        Schema::dropIfExists('workspace_documents');
        Schema::dropIfExists('workspace_browser_tokens');
        Schema::dropIfExists('workspaces');
    }
};
