<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * С какой страницы продолжается прерванный сбор. Без этого поля повтор перечитывал бы все уже сохранённые
 * страницы, а на крупной карточке это минуты запросов к площадке за данными, которые у нас и так есть.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_runs', function (Blueprint $table): void {
            $table->unsignedInteger('next_page')->default(1)->after('progress_total')
                ->comment('Страница, которую запросить у площадки при возобновлении прогона');
        });
    }

    public function down(): void
    {
        Schema::table('sync_runs', function (Blueprint $table): void {
            $table->dropColumn('next_page');
        });
    }
};
