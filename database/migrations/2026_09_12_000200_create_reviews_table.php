<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Текущее состояние каждого когда-либо встреченного отзыва; повторный сбор обновляет строки на месте
 * (уникальность по паре «организация + внешний id»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 128)->comment('Идентификатор отзыва на площадке');
            $table->string('author_name')->nullable()->comment('null, если площадка скрывает автора (у старых отзывов)');
            $table->unsignedTinyInteger('rating')->nullable()->comment('1..5; null, если у отзыва нет оценки');
            $table->text('text')->nullable();
            $table->text('business_reply')->nullable();
            $table->timestampTz('published_at');
            $table->char('content_hash', 32)->comment('xxh128 от оценки, текста и ответа; его смена порождает ревизию');
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('removed_at')->nullable()->comment('Ставится, когда полный сбор больше не возвращает отзыв');
            $table->timestampsTz();

            $table->unique(['organization_id', 'external_id']);
            $table->index(['organization_id', 'removed_at', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
