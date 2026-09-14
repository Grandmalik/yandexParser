<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Карточки организаций. Уникальны по паре «площадка + внешний id»: одну и ту же карточку разные пользователи
 * подключают к общей записи.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('platform', 32);
            $table->string('external_id', 128)->comment('Идентификатор организации на площадке');
            $table->string('source_url', 2048)->comment('Канонический адрес карточки, собранный из внешнего id');
            $table->string('name')->nullable()->comment('Заполняется первым успешным сбором');
            $table->string('address')->nullable();
            $table->decimal('rating', 3, 2)->nullable()->comment('Средняя оценка по данным площадки');
            $table->unsignedInteger('ratings_count')->nullable()->comment('Количество оценок по данным площадки');
            $table->unsignedInteger('reviews_count')->nullable()->comment('Количество отзывов по данным площадки');
            $table->timestampTz('metrics_updated_at')->nullable();
            $table->timestampsTz();

            $table->unique(['platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
