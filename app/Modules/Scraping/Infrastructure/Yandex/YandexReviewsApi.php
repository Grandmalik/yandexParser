<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex;

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Infrastructure\Http\ScrapeRequest;
use App\Modules\Scraping\Infrastructure\Http\ScrapeSessionFactory;
use App\Modules\Scraping\Infrastructure\Http\Transport\Transport;
use App\Modules\Scraping\Infrastructure\Yandex\Signing\RequestSigner;

/**
 * Прямые подписанные обращения к внутреннему эндпоинту `fetchReviews`, тому же, что дёргает сама карточка
 * (ai/research/yandex-source.md §2–5).
 */
final readonly class YandexReviewsApi
{
    public function __construct(
        private Transport $transport,
        private ScrapeSessionFactory $sessions,
        private RequestSigner $signer,
        private ReviewsResponseClassifier $responses,
        private YandexSettings $settings,
    ) {}

    /**
     * Открывает сессию: первый запрос без токена — площадка в ответ выдаёт токен и ставит cookies.
     */
    public function openSession(): YandexSession
    {
        $session = new YandexSession($this->sessions->open());
        [$body, $raw] = $this->request($session, $this->baseParameters('', 1));

        $this->acceptToken($session, $body, $raw);

        return $session;
    }

    /**
     * Узел `data` запрошенной страницы или null, если API эту страницу не отдаёт (вышли за предел выдачи).
     *
     * @throws SourceDrift
     */
    public function page(YandexSession $session, string $businessId, int $page): ?PayloadReader
    {
        $parameters = $this->baseParameters($businessId, $page);
        [$body, $raw] = $this->request($session, [...$parameters, ReviewsResponseClassifier::TOKEN_FIELD => $session->token()]);

        if ($this->responses->isTokenRenewal($body)) {
            // Токен протух: вместо данных API выдал новый. Повторяем запрос один раз уже с ним.
            $this->acceptToken($session, $body, $raw);
            [$body, $raw] = $this->request($session, [...$parameters, ReviewsResponseClassifier::TOKEN_FIELD => $session->token()]);
        }

        return $this->responses->data($body, $raw);
    }

    /**
     * Забирает токен из ответа; пустой токен — это смена формата, а не «просто нет данных».
     *
     * @param  array<array-key, mixed>  $body
     */
    private function acceptToken(YandexSession $session, array $body, string $raw): void
    {
        $token = (new PayloadReader($body, DriftStage::Token, $raw))->string(ReviewsResponseClassifier::TOKEN_FIELD);

        if ($token === '') {
            throw SourceDrift::at(DriftStage::Token, 'csrfToken must not be empty', $raw);
        }

        $session->renew($token);
    }

    /**
     * Подписывает параметры, отправляет запрос и возвращает разобранное тело вместе с сырым. HTTP 400 на
     * корректный по схеме запрос трактуется как смена алгоритма подписи.
     *
     * @param  array<string, string>  $parameters
     * @return array{0: array<array-key, mixed>, 1: string}
     */
    private function request(YandexSession $session, array $parameters): array
    {
        $url = $this->settings->reviewsApiUrl().'?'.$this->signer->signedQuery($parameters);
        $response = $this->transport->send(new ScrapeRequest($url), $session->http);

        if ($response->status() === 400) {
            throw SourceDrift::at(DriftStage::Signature, 'a signed request must be accepted', $response->body(), 400);
        }

        if ($response->status() !== 200) {
            throw SourceDrift::at(DriftStage::ReviewsRequest, 'the API must answer with HTTP 200', $response->body(), $response->status());
        }

        return [$this->responses->decode($response->body(), $response->status()), $response->body()];
    }

    /**
     * Параметры запроса страницы отзывов — те же, что отправляет сама карточка.
     *
     * @return array<string, string>
     */
    private function baseParameters(string $businessId, int $page): array
    {
        return [
            'ajax' => '1',
            'businessId' => $businessId,
            'locale' => $this->settings->locale,
            'page' => (string) $page,
            'pageSize' => (string) $this->settings->pageSize,
            'ranking' => $this->settings->ranking,
        ];
    }
}
