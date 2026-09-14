import { effectScope } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { OrganizationData, SyncRunData } from '@/types/generated/api';

const latestSyncRun = vi.hoisted(() => vi.fn());
const listen = vi.hoisted(() => vi.fn());

// Вещание настроено — тот самый случай, когда опрос раньше выключался полностью.
vi.mock('@/modules/app-config/store', () => ({
    useAppConfigStore: () => ({
        broadcasting: {
            enabled: true,
            driver: 'reverb',
            key: 'local-key',
            host: '127.0.0.1',
            port: 8080,
            scheme: 'http',
            auth_endpoint: '/broadcasting/auth',
        },
        progressEvent: '.sync-run.updated',
        pollingIntervalMs: 2000,
    }),
}));

vi.mock('@/modules/sync/api', () => ({
    latestSyncRun,
    startSyncRun: vi.fn(),
}));

// Сервер принимает подписку, но не присылает ничего — например, Reverb просто не запущен.
vi.mock('@/shared/realtime/echo', () => ({
    realtime: () => ({
        private: () => ({ listen, stopListening: vi.fn() }),
        leave: vi.fn(),
    }),
    onConnectionFailure: () => () => {},
}));

const { useSyncProgress } = await import('./useSyncProgress');

function run(current: number, finished = false): SyncRunData {
    return {
        id: 'run-1',
        organization_id: 'org-1',
        status: { value: finished ? 'completed' : 'running', label: 'Сбор данных' },
        is_finished: finished,
        trigger: 'connected',
        progress: { current, total: 100, percent: current },
        attempts: 1,
        error: null,
        stats: null,
        requested_at: '2026-09-12T10:00:00+00:00',
        started_at: '2026-09-12T10:00:01+00:00',
        finished_at: finished ? '2026-09-12T10:00:20+00:00' : null,
    };
}

const organization: OrganizationData = {
    id: 'org-1',
    platform: { value: 'yandex', label: 'Яндекс Карты' },
    external_id: '1703836794',
    source_url: 'https://yandex.ru/maps/org/1703836794/',
    name: null,
    address: null,
    rating_summary: null,
    metrics_updated_at: null,
    reviews_stored: 0,
    channel: 'organizations.org-1',
};

describe('useSyncProgress', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        latestSyncRun.mockReset();
        listen.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('keeps asking the API while a configured websocket pushes nothing', async () => {
        latestSyncRun
            .mockResolvedValueOnce(run(0))
            .mockResolvedValueOnce(run(50))
            .mockResolvedValueOnce(run(100, true));

        const scope = effectScope();
        const progress = scope.run(() => useSyncProgress(organization));

        await vi.advanceTimersByTimeAsync(0);
        expect(progress?.run.value?.progress.current).toBe(0);

        await vi.advanceTimersByTimeAsync(2000);
        expect(progress?.run.value?.progress.current).toBe(50);

        await vi.advanceTimersByTimeAsync(2000);
        expect(progress?.run.value?.is_finished).toBe(true);

        // О завершённом прогоне больше не спрашиваем.
        await vi.advanceTimersByTimeAsync(10_000);
        expect(latestSyncRun).toHaveBeenCalledTimes(3);

        scope.stop();
    });

    it('subscribes to the organization channel it was told about', async () => {
        latestSyncRun.mockResolvedValue(run(100, true));

        const scope = effectScope();
        scope.run(() => useSyncProgress(organization));
        await vi.advanceTimersByTimeAsync(0);

        expect(listen).toHaveBeenCalledWith('.sync-run.updated', expect.any(Function));

        scope.stop();
    });
});
