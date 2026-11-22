<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harbor_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('demo_session_id');
            $table->foreign('demo_session_id')->references('id')->on('demo_sessions')->cascadeOnDelete();
            $table->string('order_number');
            $table->string('reference')->unique();
            $table->timestamp('created_at');
            $table->unique(['demo_session_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_returns');
    }
};
