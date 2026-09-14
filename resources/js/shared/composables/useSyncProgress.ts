import { onScopeDispose, ref } from 'vue';
import { useAppConfigStore } from '@/modules/app-config/store';
import { latestSyncRun, startSyncRun } from '@/modules/sync/api';
import { onConnectionFailure, realtime } from '@/shared/realtime/echo';
import type { OrganizationData, SyncRunData } from '@/types/generated/api';

/**
 * Состояние текущего сбора. Если websocket настроен, сервер присылает его пушем; если нет — экран спрашивает
 * по таймеру. В обоих случаях экран читает только `run`, поэтому транспорт остаётся спрятанным здесь.
 */
export function useSyncProgress(organization: OrganizationData) {
    const appConfig = useAppConfigStore();
    const run = ref<SyncRunData | null>(null);

    let nextPoll: ReturnType<typeof setTimeout> | null = null;
    let stopListening: (() => void) | null = null;
    let stopWatchingConnection: (() => void) | null = null;
    // Доказательство, что websocket действительно доставляет, а не просто настроен.
    let receivingPushes = false;

    function cancelPoll(): void {
        if (nextPoll !== null) {
            clearTimeout(nextPoll);
            nextPoll = null;
        }
    }

    /**
     * О незавершённых прогонах спрашиваем, пока первый пуш не докажет, что websocket работает. Настроенный,
     * но так и не подключившийся сервер не должен оставить экран замороженным, а соединение, которое молча
     * висит в состоянии «подключаюсь», об ошибке не сообщает — откатываться будет не на что.
     */
    function schedulePoll(): void {
        cancelPoll();

        if (receivingPushes || run.value === null || run.value.is_finished) {
            return;
        }

        nextPoll = setTimeout(() => void refresh(), Math.max(1000, appConfig.pollingIntervalMs));
    }

    function apply(state: SyncRunData | null): void {
        run.value = state;
        schedulePoll();
    }

    async function refresh(): Promise<void> {
        apply(await latestSyncRun(organization.id));
    }

    /** Просит собрать заново; пока сбор уже идёт, бэкенд откажет. */
    async function collectAgain(): Promise<void> {
        apply(await startSyncRun(organization.id));
    }

    function listen(): void {
        const broadcasting = appConfig.broadcasting;
        const connection = broadcasting === null ? null : realtime(broadcasting);

        if (connection === null) {
            return;
        }

        const event = appConfig.progressEvent;
        const channel = connection.private(organization.channel);
        channel.listen(event, (pushed: { sync_run: SyncRunData }) => {
            receivingPushes = true;
            cancelPoll();
            run.value = pushed.sync_run;
        });

        stopListening = (): void => {
            channel.stopListening(event);
            connection.leave(organization.channel);
        };

        // Оборвалось соединение — пуши больше ничего не доказывают: возвращаемся к опросу API.
        stopWatchingConnection = onConnectionFailure(connection, () => {
            receivingPushes = false;
            stopPush();
            void refresh();
        });
    }

    function stopPush(): void {
        stopListening?.();
        stopListening = null;
        stopWatchingConnection?.();
        stopWatchingConnection = null;
    }

    listen();
    void refresh();

    onScopeDispose(() => {
        cancelPoll();
        stopPush();
    });

    return { run, refresh, collectAgain };
}
