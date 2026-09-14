/**
 * Статичные тексты интерфейса. Всё, что описывает данные — статусы, сообщения об ошибках, названия площадок, —
 * приходит с бэкенда, а не отсюда.
 */
export const ru = {
    login: {
        title: 'Вход',
        email: 'Email',
        password: 'Пароль',
        remember: 'Запомнить меня',
        submit: 'Войти',
        submitting: 'Входим…',
    },
    header: {
        signOut: 'Выйти',
    },
    organizations: {
        title: 'Организации',
        empty: 'Пока нет подключённых организаций.',
        total: 'Всего организаций: {total}',
        connect: {
            title: 'Подключить организацию',
            url: 'Ссылка на карточку организации',
            placeholder: 'https://yandex.ru/maps/org/…',
            submit: 'Подключить',
            submitting: 'Подключаем…',
        },
        card: {
            noFigures: 'Данные ещё не собраны',
            stored: 'Собрано отзывов',
            open: 'Открыть',
        },
    },
    organization: {
        back: 'К списку организаций',
        rating: 'Средняя оценка',
        ratingsCount: 'Оценок',
        reviewsCount: 'Отзывов на площадке',
        stored: 'Отзывов собрано',
        updatedAt: 'Данные на',
        sourceLink: 'Карточка на Яндекс Картах',
        collectAgain: 'Собрать заново',
        collecting: 'Идёт сбор…',
    },
    sync: {
        title: 'Сбор данных',
        never: 'Сбор ещё не выполнялся.',
        progress: 'Собрано {current} из {total}',
        progressUnknown: 'Собрано {current}',
        latestOnly: 'Собраны последние {current} отзывов',
        attempt: 'Попытка {number}',
        finishedAt: 'Завершён',
        live: 'Обновляется автоматически',
        stats: {
            created: 'новых',
            updated: 'изменённых',
            unchanged: 'без изменений',
            removed: 'удалённых площадкой',
        },
    },
    reviews: {
        title: 'Отзывы',
        empty: 'Отзывов пока нет.',
        anonymous: 'Аноним',
        noRating: 'Без оценки',
        ratingLabel: 'Оценка {rating} из 5',
        reply: 'Ответ организации',
        total: 'Всего отзывов: {total}',
    },
    pagination: {
        page: 'Страница {current} из {last}',
        goToPage: 'Страница {page}',
        previous: 'Назад',
        next: 'Вперёд',
    },
    history: {
        title: 'История показателей',
        empty: 'История появится после первого сбора.',
        capturedAt: 'Дата',
        rating: 'Оценка',
        ratingsCount: 'Оценок',
        reviewsCount: 'Отзывов',
    },
    common: {
        loading: 'Загрузка…',
        retry: 'Повторить',
    },
    errors: {
        network: 'Не удалось связаться с сервером. Проверьте соединение.',
        unknown: 'Что-то пошло не так. Попробуйте ещё раз.',
    },
} as const;
