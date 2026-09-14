import type { AxiosError } from 'axios';
import { describe, expect, it } from 'vitest';
import { ApiError, NETWORK_ERROR } from './error';
import { ru } from '@/shared/i18n/ru';

function failure(status: number, data: unknown): AxiosError {
    return { response: { status, data } } as AxiosError;
}

describe('ApiError', () => {
    it('takes the code, message and trace id from the API envelope', () => {
        const error = ApiError.from(
            failure(409, {
                error: {
                    code: 'sync.already_running',
                    message: 'Данные уже собираются.',
                    trace_id: 'abc-123',
                },
            }),
        );

        expect(error.status).toBe(409);
        expect(error.code).toBe('sync.already_running');
        expect(error.message).toBe('Данные уже собираются.');
        expect(error.traceId).toBe('abc-123');
        expect(error.is('sync.already_running')).toBe(true);
    });

    it('exposes the first validation message of a field', () => {
        const error = ApiError.from(
            failure(422, {
                error: {
                    code: 'validation.failed',
                    message: 'Проверьте поля.',
                    fields: { url: ['Это не похоже на ссылку.', 'Второе сообщение'] },
                },
            }),
        );

        expect(error.fieldError('url')).toBe('Это не похоже на ссылку.');
        expect(error.fieldError('email')).toBeUndefined();
    });

    it('reports a request that never reached the API', () => {
        const error = ApiError.from({} as AxiosError);

        expect(error.code).toBe(NETWORK_ERROR);
        expect(error.message).toBe(ru.errors.network);
    });

    it('wraps anything thrown into one error type', () => {
        const original = new ApiError(500, 'internal', 'Ошибка сервера');

        expect(ApiError.wrap(original)).toBe(original);
        expect(ApiError.wrap(new TypeError('boom')).code).toBe(NETWORK_ERROR);
    });
});
