import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type {
    ChannelAuthorizationCallback,
    ChannelAuthorizationData,
} from 'pusher-js/types/src/core/auth/options';
import { http } from '@/shared/api/http';
import type { BroadcastingConfigData } from '@/types/generated/api';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

type Connection = Echo<'reverb'>;

let connection: Connection | null = null;

// Reverb-брокер Echo построен на клиенте протокола Pusher и ищет его в глобальной области.
window.Pusher = Pusher;

/**
 * Открывает единственное websocket-соединение приложения, целиком описанное тем, что опубликовал бэкенд.
 * Возвращает null, если websocket-сервер не настроен, — тогда вызывающий код переходит на опрос API.
 */
export function realtime(config: BroadcastingConfigData): Connection | null {
    if (!config.enabled || !config.key) {
        return null;
    }

    connection ??= new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host ?? window.location.hostname,
        wsPort: config.port ?? undefined,
        wssPort: config.port ?? undefined,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // Приватные каналы авторизуются сессионной cookie — тем же клиентом, которым ходит остальное приложение.
        authorizer: (channel: { name: string }) => ({
            authorize: (socketId: string, callback: ChannelAuthorizationCallback): void => {
                http.post<ChannelAuthorizationData>(
                    config.auth_endpoint,
                    { socket_id: socketId, channel_name: channel.name },
                    { baseURL: '' },
                )
                    .then((response) => callback(null, response.data))
                    .catch((failure: unknown) =>
                        callback(
                            failure instanceof Error ? failure : new Error(String(failure)),
                            null,
                        ),
                    );
            },
        }),
    });

    return connection;
}

interface Socket {
    bind(event: string, handler: () => void): void;
    unbind(event: string, handler: () => void): void;
}

/** Сокет протокола Pusher, спрятанный внутри Echo, — если до него вообще можно добраться. */
function socketOf(connection: Connection): Socket | null {
    const socket = (connection as unknown as { connector?: { pusher?: { connection?: Socket } } })
        .connector?.pusher?.connection;

    return typeof socket?.bind === 'function' ? socket : null;
}

/**
 * Вызывает обработчик, когда websocket не открылся или оборвался — например, сервер просто не запущен.
 * Тогда вызывающий код возвращается к опросу API, вместо того чтобы ждать обновлений, которые не придут.
 */
export function onConnectionFailure(connection: Connection, callback: () => void): () => void {
    const socket = socketOf(connection);

    if (socket === null) {
        callback();

        return () => {};
    }

    const events = ['unavailable', 'failed', 'error', 'disconnected'];
    events.forEach((event) => socket.bind(event, callback));

    return () => events.forEach((event) => socket.unbind(event, callback));
}

/** Закрывает соединение — например, когда пользователь вышел. */
export function disconnect(): void {
    connection?.disconnect();
    connection = null;
}
