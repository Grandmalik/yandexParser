<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Один сбор данных организации: жизненный цикл, прогресс и итог.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('trigger', 16);
            $table->string('status', 16);
            $table->unsignedInteger('progress_current')->default(0)->comment('Сколько отзывов собрано к этому моменту');
            $table->unsignedInteger('progress_total')->nullable()->comment('Сколько ожидается — как только площадка это сообщит');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error_code', 64)->nullable();
            // Ключ перевода, а не готовое сообщение: текст собирается на языке того, кто его читает.
            $table->string('error_message_key', 128)->nullable();
            $table->jsonb('stats')->nullable()->comment('Счётчики отзывов: создано / изменено / без изменений / удалено');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
