import { ApiError } from '@/shared/api/error';
import { http, post } from '@/shared/api/http';
import type { SyncRunData } from '@/types/generated/api';

/** Последний сбор организации или null, если её ещё ни разу не собирали. */
export async function latestSyncRun(organizationId: string): Promise<SyncRunData | null> {
    try {
        const response = await http.get<{ data: SyncRunData }>(
            `/organizations/${organizationId}/sync-runs/latest`,
        );

        return response.data.data;
    } catch (failure) {
        if (ApiError.wrap(failure).status === 404) {
            return null;
        }

        throw failure;
    }
}

/** Просит собрать заново; пока сбор уже идёт, бэкенд откажет. */
export function startSyncRun(organizationId: string): Promise<SyncRunData> {
    return post<SyncRunData>(`/organizations/${organizationId}/sync-runs`);
}
