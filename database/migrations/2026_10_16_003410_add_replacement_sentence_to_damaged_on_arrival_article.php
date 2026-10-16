<?php

use App\Services\DamagedOnArrivalArticle;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(DamagedOnArrivalArticle::class)->ensureReplacementSentence();
    }

    public function down(): void
    {
        app(DamagedOnArrivalArticle::class)->removeReplacementSentence();
    }
};
