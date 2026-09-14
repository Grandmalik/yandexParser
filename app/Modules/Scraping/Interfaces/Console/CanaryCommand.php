<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Interfaces\Console;

use App\Modules\Scraping\Application\Canary;
use App\Modules\Scraping\Application\CanaryResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

final class CanaryCommand extends Command
{
    protected $signature = 'scraping:canary {--url= : Card to check instead of the configured reference ones}';

    protected $description = 'Reads a reference organization card of every platform live and reports whether they still match their parsers.';

    public function handle(Canary $canary): int
    {
        $failed = 0;

        foreach ($this->cardsToCheck() as $url) {
            $result = $canary->check($url);
            $this->report($result);

            $failed += $result->passed ? 0 : 1;
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * По эталонной карточке на каждую настроенную площадку — если только карточка не названа прямо в командной
     * строке. Площадка без неё — ошибка конфигурации, а не повод её пропустить: иначе здоровье продолжало бы
     * докладывать «healthy», хотя площадку никто ни разу не проверил.
     *
     * @return list<string>
     */
    private function cardsToCheck(): array
    {
        $named = $this->stringOption('url');

        if ($named !== null) {
            return [$named];
        }

        $cards = [];

        foreach (config()->array('scraping.platforms') as $platform => $settings) {
            $url = is_array($settings) ? $settings['canary_url'] ?? null : null;

            if (! is_string($url) || $url === '') {
                throw new UnexpectedValueException("scraping.platforms.{$platform}.canary_url must name a reference card.");
            }

            $cards[] = $url;
        }

        return $cards;
    }

    private function report(CanaryResult $result): void
    {
        $context = [
            'url' => $result->url,
            'source' => $result->source,
            'duration_ms' => $result->durationMs,
            'reviews_reported' => $result->reviewsReported,
            'reviews_available' => $result->reviewsAvailable,
            'reviews_first_page' => $result->reviewsOnFirstPage,
            'error' => $result->errorCode,
        ];

        if (! $result->passed) {
            Log::channel('scraping')->critical('Canary failed: the reference card could not be read.', $context);
            $this->components->error(sprintf('%s — %s (%d ms)', $result->errorCode, $result->message, $result->durationMs));

            return;
        }

        // Карточка ответила, но отзывов не отдала — значит, парсер отзывов так и не был задействован.
        $result->readReviews()
            ? Log::channel('scraping')->info('Canary passed.', $context)
            : Log::channel('scraping')->warning('Canary read the card but no reviews.', $context);

        $this->components->info(sprintf(
            '%s: %d reviews reported, %d available, %d on the first page (%d ms).',
            (string) $result->name,
            $result->reviewsReported,
            $result->reviewsAvailable,
            $result->reviewsOnFirstPage,
            $result->durationMs,
        ));
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
