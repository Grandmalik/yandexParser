<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сырые ответы площадки, которые хранятся ограниченное время для разбора поломок парсера (смены формата).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_payloads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('sync_run_id')->nullable()->index()->comment('Прогон сбора, получивший этот ответ (модуль Sync)');
            $table->string('platform', 32);
            $table->string('kind', 32)->comment('На какой запрос это ответ, например organization_page, reviews_page');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->longText('body');
            $table->timestampTz('created_at')->index()->comment('По нему удаляются устаревшие ответы');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_payloads');
    }
};
