<?php

declare(strict_types=1);

return [

    /*
    | Отзывов на страницу. Клиент читает это число из `meta` ответа, а не зашивает у себя.
    */
    'per_page' => (int) env('REVIEWS_PER_PAGE', 50),

];
