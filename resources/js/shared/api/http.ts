import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { ApiError } from './error';

type RetriedRequest = InternalAxiosRequestConfig & { csrfRetried?: boolean };

/** Сессия живёт в cookie (Sanctum), поэтому в хранилище браузера не кладётся ничего. */
export const http = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json' },
});

let csrfCookie: Promise<void> | null = null;

export function ensureCsrfCookie(): Promise<void> {
    csrfCookie ??= axios
        .get('/sanctum/csrf-cookie', { withCredentials: true })
        .then(() => undefined);

    return csrfCookie;
}

http.interceptors.response.use(
    (response) => response,
    async (failure: AxiosError) => {
        const request = failure.config as RetriedRequest | undefined;

        // CSRF-токен протух: обновляем его один раз и повторяем запрос, вместо того чтобы беспокоить пользователя.
        if (failure.response?.status === 419 && request && !request.csrfRetried) {
            csrfCookie = null;
            await ensureCsrfCookie();
            request.csrfRetried = true;

            return http.request(request);
        }

        throw ApiError.from(failure);
    },
);

/** Разворачивает конверт `{ data: … }`, в который каждый эндпоинт заворачивает ответ. */
export async function get<T>(url: string, params?: Record<string, unknown>): Promise<T> {
    return (await http.get<{ data: T }>(url, { params })).data.data;
}

export async function post<T>(url: string, payload?: unknown): Promise<T> {
    await ensureCsrfCookie();

    return (await http.post<{ data: T }>(url, payload)).data.data;
}
