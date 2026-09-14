import { defineStore } from 'pinia';
import { computed, shallowRef } from 'vue';
import { get } from '@/shared/api/http';
import type {
    ApplicationConfigData,
    BroadcastingConfigData,
    SyncConfigData,
} from '@/types/generated/api';

interface AppConfig {
    application: ApplicationConfigData;
    broadcasting: BroadcastingConfigData;
    sync: SyncConfigData;
}

/**
 * Параметры и словари, которые интерфейсу нельзя зашивать у себя: название приложения, подписи статусов,
 * частота опроса прогресса.
 */
export const useAppConfigStore = defineStore('app-config', () => {
    const config = shallowRef<AppConfig | null>(null);

    async function load(): Promise<void> {
        config.value = await get<AppConfig>('/app-config');
    }

    const applicationName = computed(() => config.value?.application.name ?? '');
    const broadcasting = computed(() => config.value?.broadcasting ?? null);
    const progressEvent = computed(() => config.value?.sync.progress_event ?? '');
    const pollingIntervalMs = computed(() => config.value?.sync.polling_interval_ms ?? 0);

    return { config, applicationName, broadcasting, progressEvent, pollingIntervalMs, load };
});
