<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex;

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceNotFound;
use App\Modules\Shared\Domain\Source\SourceReference;
use JsonException;

/**
 * Достаёт организацию из JSON-состояния, которое страница карточки встраивает для своего клиентского приложения
 * (`<script class="state-view">`, см. ai/research/yandex-source.md §1).
 */
final class OrganizationPageParser
{
    private const string STATE_SCRIPT_PATTERN = '#<script[^>]*\bclass="state-view"[^>]*>(.*?)</script>#s';

    private const string NOT_FOUND_ERROR = 'not-found';

    /**
     * Карточка организации из HTML страницы. Каждое несовпадение со схемой — drift с указанием точного места,
     * а не молчаливый null.
     *
     * @throws SourceNotFound
     * @throws SourceDrift
     */
    public function organization(string $html, SourceReference $source): PayloadReader
    {
        if (preg_match(self::STATE_SCRIPT_PATTERN, $html, $matches) !== 1) {
            throw SourceDrift::at(DriftStage::OrganizationPage, 'the page must embed a state-view script', $html);
        }

        try {
            $state = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw SourceDrift::at(DriftStage::OrganizationPage, 'state-view must be valid JSON', $html);
        }

        if (! is_array($state)) {
            throw SourceDrift::at(DriftStage::OrganizationPage, 'state-view must be an object', $html);
        }

        $stack = (new PayloadReader($state, DriftStage::OrganizationPage, $html))->objects('stack');
        $card = $stack[0] ?? throw SourceDrift::at(DriftStage::OrganizationPage, 'stack must not be empty', $html);

        if ($card->optionalString('error') === self::NOT_FOUND_ERROR) {
            throw SourceNotFound::for($source);
        }

        $items = $card->object('results')->objects('items');

        return $items[0] ?? throw SourceDrift::at(DriftStage::OrganizationPage, 'stack.0.results.items must not be empty', $html);
    }
}
