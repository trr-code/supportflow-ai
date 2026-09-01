<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('demo_agent')->after('email');
        });

        Schema::create('demo_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('public_token', 64)->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('subject');
            $table->text('description');
            $table->string('product')->nullable();
            $table->string('status');
            $table->string('category')->nullable();
            $table->string('priority')->nullable();
            $table->string('sentiment')->nullable();
            $table->string('department')->nullable();
            $table->string('ai_category')->nullable();
            $table->string('ai_priority')->nullable();
            $table->string('ai_sentiment')->nullable();
            $table->string('ai_department')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('decision_factors')->nullable();
            $table->decimal('classification_confidence', 5, 4)->nullable();
            $table->decimal('retrieval_similarity', 5, 4)->nullable();
            $table->boolean('injection_suspected')->default(false);
            $table->boolean('needs_human')->default(false);
            $table->boolean('is_seeded')->default(false);
            $table->string('source');
            $table->string('demo_session_id')->nullable()->index();
            $table->string('scenario_key')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('visibility');
            $table->string('author_type');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('actor')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('ai_run_id')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category');
            $table->longText('body');
            $table->boolean('is_published')->default(true);
            $table->boolean('is_seeded')->default(true);
            $table->timestamps();
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_article_id')->constrained()->cascadeOnDelete();
            $table->string('heading')->nullable();
            $table->text('body');
            $table->unsignedInteger('token_count')->default(0);
            $table->vector('embedding', dimensions: (int) config('supportflow.embeddings.dimensions', 1536))
                ->nullable()
                ->index();
            $table->timestamps();
        });

        Schema::create('suggested_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('grounded')->default(false);
            $table->string('status');
            $table->json('cited_chunk_ids')->nullable();
            $table->text('refusal_reason')->nullable();
            $table->foreignId('regenerated_from_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->string('feature');
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('openai');
            $table->string('model')->nullable();
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->text('error')->nullable();
            $table->json('retrieved_chunk_ids')->nullable();
            $table->json('payload')->nullable();
            $table->string('request_hash')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('demo_session_id')->index();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('body');
            $table->json('cited_chunk_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('suggested_replies');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('demo_sessions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
