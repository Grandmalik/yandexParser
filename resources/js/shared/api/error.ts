import type { AxiosError } from 'axios';
import { ru } from '@/shared/i18n/ru';

export type FieldErrors = Record<string, string[]>;

interface ErrorBody {
    code?: string;
    message?: string;
    fields?: FieldErrors | null;
    details?: Record<string, unknown> | null;
    trace_id?: string | null;
}

/** На ошибку API всегда отвечает `{ error: … }`; всё остальное пришло не от него. */
function errorBody(payload: unknown): ErrorBody | null {
    if (typeof payload !== 'object' || payload === null || !('error' in payload)) {
        return null;
    }

    const body = (payload as { error: unknown }).error;

    return typeof body === 'object' && body !== null ? (body as ErrorBody) : null;
}

/** Запрос до API вообще не дошёл: нет сети, не разрешился домен, запрос прерван. */
export const NETWORK_ERROR = 'network';

/**
 * Неудачный вызов API в том виде, который задаёт бэкенд: стабильный код для ветвления логики и готовое
 * сообщение для показа. Тексты ошибок фронтенд никогда не составляет сам.
 */
export class ApiError extends Error {
    constructor(
        readonly status: number,
        readonly code: string,
        message: string,
        readonly fields: FieldErrors | null = null,
        readonly details: Record<string, unknown> | null = null,
        readonly traceId: string | null = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }

    static from(failure: AxiosError): ApiError {
        const body = failure.response ? errorBody(failure.response.data) : null;

        if (!failure.response || !body) {
            return new ApiError(failure.response?.status ?? 0, NETWORK_ERROR, ru.errors.network);
        }

        return new ApiError(
            failure.response.status,
            body.code ?? NETWORK_ERROR,
            body.message ?? ru.errors.unknown,
            body.fields ?? null,
            body.details ?? null,
            body.trace_id ?? null,
        );
    }

    /** Заворачивает что угодно, брошенное внутри асинхронной операции, чтобы наверху был один тип ошибки. */
    static wrap(failure: unknown): ApiError {
        return failure instanceof ApiError
            ? failure
            : new ApiError(0, NETWORK_ERROR, ru.errors.unknown);
    }

    is(code: string): boolean {
        return this.code === code;
    }

    /** Первое сообщение валидации для поля формы, если бэкенд его прислал. */
    fieldError(field: string): string | undefined {
        return this.fields?.[field]?.[0];
    }
}
