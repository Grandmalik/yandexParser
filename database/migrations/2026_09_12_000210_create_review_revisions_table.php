<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Каждая отличающаяся версия содержимого отзыва, включая первую; соседние строки дают «было → стало».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->uuid('sync_run_id')->nullable()->comment('Прогон сбора, заставший эту версию (модуль Sync)');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('text')->nullable();
            $table->text('business_reply')->nullable();
            $table->char('content_hash', 32);
            $table->timestampTz('captured_at');

            $table->index(['review_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_revisions');
    }
};
