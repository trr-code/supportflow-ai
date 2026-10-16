<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replacement_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('demo_session_id')->unique();
            $table->string('conversation_id')->nullable();
            $table->unsignedInteger('generation')->default(1);
            $table->unsignedInteger('visitor_messages')->default(0);
            $table->string('run_state')->nullable();
            $table->timestamps();

            $table->foreign('demo_session_id')->references('id')->on('demo_sessions')->cascadeOnDelete();
        });

        Schema::create('replacement_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('replacement_case_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->unsignedInteger('on_hand');
            $table->timestamps();

            $table->unique(['replacement_case_id', 'sku']);
        });

        Schema::create('replacement_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('replacement_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('generation');
            $table->string('tool_call_id')->unique();
            $table->string('argument_hash', 64);
            $table->string('status');
            $table->string('order_number');
            $table->string('sku');
            $table->string('item_name');
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->string('action');
            $table->json('snapshot');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['replacement_case_id', 'status']);
        });

        Schema::create('replacement_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('replacement_case_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('proposal_id')->unique()->constrained('replacement_proposals')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('order_number');
            $table->string('sku');
            $table->string('item_name');
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('replacement_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('replacement_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('generation');
            $table->string('role', 25);
            $table->text('body');
            $table->timestamps();

            $table->index(['replacement_case_id', 'generation']);
        });

        Schema::create('replacement_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('replacement_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('generation');
            $table->string('summary');
            $table->timestamps();

            $table->index(['replacement_case_id', 'generation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacement_activities');
        Schema::dropIfExists('replacement_messages');
        Schema::dropIfExists('replacement_requests');
        Schema::dropIfExists('replacement_proposals');
        Schema::dropIfExists('replacement_stock');
        Schema::dropIfExists('replacement_cases');
    }
};
