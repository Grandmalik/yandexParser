<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Показатели рейтинга, снимаемые на каждом сборе, — из них строится «было → стало».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('sync_run_id')->nullable()->comment('Прогон сбора, снявший показатели (модуль Sync)');
            $table->decimal('rating', 3, 2);
            $table->unsignedInteger('ratings_count');
            $table->unsignedInteger('reviews_count');
            $table->timestampTz('captured_at');

            $table->index(['organization_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_snapshots');
    }
};
