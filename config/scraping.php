<?php

declare(strict_types=1);

use App\Modules\Scraping\Domain\Yandex\YandexUrlParser;
use App\Modules\Scraping\Infrastructure\Yandex\YandexHttpGateway;

return [

    'http' => [
        'timeout_seconds' => (int) env('SCRAPING_HTTP_TIMEOUT', 15),
        'connect_timeout_seconds' => (int) env('SCRAPING_HTTP_CONNECT_TIMEOUT', 5),
    ],

    /*
    | Хранилище кэша с анти-бан-состоянием, общим для всех воркеров (троттлинг, баны, circuit breaker).
    | Должно поддерживать атомарные блокировки; null — хранилище по умолчанию.
    */
    'state_store' => env('SCRAPING_STATE_STORE'),

    /*
    | Пауза между запросами к одному хосту с одного маршрута (IP): минимальный интервал плюс случайный разброс.
    */
    'throttle' => [
        'min_interval_ms' => (int) env('SCRAPING_THROTTLE_MIN_INTERVAL_MS', 1500),
        'jitter_ms' => (int) env('SCRAPING_THROTTLE_JITTER_MS', 1000),
        'lock_seconds' => 30,
    ],

    /*
    | После стольких сигналов бана подряд запросы к хосту приостанавливаются на open_seconds.
    */
    'circuit_breaker' => [
        'failure_threshold' => (int) env('SCRAPING_BREAKER_FAILURE_THRESHOLD', 3),
        'open_seconds' => (int) env('SCRAPING_BREAKER_OPEN_SECONDS', 900),
    ],

    /*
    | Исходящие маршруты через запятую, например «http://user:pass@1.2.3.4:8080,http://…». Пусто — IP сервера.
    | Маршрут, получивший капчу, отдыхает ban_seconds; после ограничения скорости — не меньше cooldown.
    */
    'proxies' => [
        'urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('SCRAPING_PROXIES', ''))))),
        'ban_seconds' => (int) env('SCRAPING_PROXY_BAN_SECONDS', 1800),
        'rate_limit_cooldown_seconds' => (int) env('SCRAPING_RATE_LIMIT_COOLDOWN_SECONDS', 300),
    ],

    /*
    | Согласованные профили браузера; один выбирается на сессию и держится во всех её запросах.
    */
    'browser_profiles' => [
        [
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
            'headers' => [
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                'sec-ch-ua' => '"Not;A=Brand";v="99", "Google Chrome";v="139", "Chromium";v="139"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Windows"',
            ],
        ],
        [
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
            'headers' => [
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
                'sec-ch-ua' => '"Not;A=Brand";v="99", "Google Chrome";v="139", "Chromium";v="139"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"macOS"',
            ],
        ],
        [
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0',
            'headers' => [
                'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
            ],
        ],
    ],

    /*
    | Фрагменты страниц капчи и бана в нижнем регистре (адрес редиректа, итоговый URL или HTML).
    */
    'blocked_markers' => ['showcaptcha', 'smartcaptcha', 'captcha-page'],

    /*
    | Паузы между повторами работы с площадкой (экспоненциальный рост с разбросом и потолком).
    */
    'retry' => [
        'base_seconds' => (int) env('SCRAPING_RETRY_BASE_SECONDS', 30),
        'blocked_base_seconds' => (int) env('SCRAPING_RETRY_BLOCKED_BASE_SECONDS', 600),
        'max_seconds' => (int) env('SCRAPING_RETRY_MAX_SECONDS', 3600),
    ],

    'platforms' => [

        'yandex' => [
            /*
            | Адаптер, читающий площадку (реализация ReviewSourceGateway).
            */
            'gateway' => YandexHttpGateway::class,

            /*
            | Превращает вставленную ссылку в ссылку на организацию площадки (PlatformUrlParser).
            */
            'url_parser' => YandexUrlParser::class,

            /*
            | Карточка, которую `scraping:canary` читает по расписанию, чтобы заметить смену формата раньше пользователей.
            */
            'canary_url' => env('YANDEX_CANARY_URL', 'https://yandex.ru/maps/org/1703836794/'),

            'url' => [
                /*
                | Хосты (без «www.»), ссылки на которые принимаются. Запросы уходят только на них.
                */
                'hosts' => [
                    'yandex.ru', 'yandex.com', 'yandex.kz', 'yandex.by', 'yandex.uz', 'yandex.com.tr',
                    'yandex.az', 'yandex.com.am', 'yandex.com.ge', 'yandex.co.il', 'yandex.tj', 'yandex.tm',
                    'yandex.lt', 'yandex.lv', 'yandex.ee', 'yandex.md', 'yandex.fr', 'maps.yandex.ru',
                ],
                'short_link_path_prefix' => '/maps/-/',
                'canonical_url' => env('YANDEX_CANONICAL_URL', 'https://yandex.ru/maps/org/{external_id}/'),
            ],

            /*
            | Адреса, см. ai/research/yandex-source.md.
            */
            'http' => [
                'base_url' => env('YANDEX_BASE_URL', 'https://yandex.ru'),
                'organization_page_path' => '/maps/org/{external_id}/',
                'reviews_api_path' => '/maps/api/business/fetchReviews',
            ],

            'reviews' => [
                'locale' => env('YANDEX_REVIEWS_LOCALE', 'ru_RU'),
                'ranking' => 'by_time',
                /* Страницы больше API не отдаёт. */
                'page_size' => 50,
                /* API отдаёт только последние отзывы организации, а не все. */
                'max_available' => (int) env('YANDEX_REVIEWS_MAX_AVAILABLE', 600),
            ],
        ],

    ],

    'short_links' => [
        'max_hops' => (int) env('SCRAPING_SHORT_LINK_MAX_HOPS', 3),
    ],

    /*
    | Состояние площадки считается по последним `window` чтениям; от доли `degraded_share` сбоев адаптер
    | «деградировал». Одной смены формата достаточно самой по себе.
    */
    'health' => [
        'window' => (int) env('SCRAPING_HEALTH_WINDOW', 20),
        'degraded_share' => (float) env('SCRAPING_HEALTH_DEGRADED_SHARE', 0.3),
        'ttl_seconds' => (int) env('SCRAPING_HEALTH_TTL_SECONDS', 86400),
    ],

    /*
    | Сырые ответы для разбора поломок парсера удаляет `model:prune` через столько дней.
    */
    'payloads' => [
        'retention_days' => (int) env('SCRAPING_PAYLOAD_RETENTION_DAYS', 14),
    ],

];
