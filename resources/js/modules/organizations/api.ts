import { get, http, post } from '@/shared/api/http';
import type {
    MetricsSnapshotData,
    OrganizationData,
    PaginatedDataCollection,
} from '@/types/generated/api';

export type OrganizationsPage = PaginatedDataCollection<number, OrganizationData>;

/**
 * Одна страница подключённых организаций. Конверт ответа сохраняется целиком: размер страницы и число страниц —
 * ответ бэкенда, а не решение экрана.
 */
export async function organizations(page: number): Promise<OrganizationsPage> {
    const response = await http.get<OrganizationsPage>('/organizations', { params: { page } });

    return response.data;
}

/** Подключает карточку по ссылке; бэкенд проверяет ссылку и сразу запускает сбор. */
export function connectOrganization(url: string): Promise<OrganizationData> {
    return post<OrganizationData>('/organizations', { url });
}

export function organization(id: string): Promise<OrganizationData> {
    return get<OrganizationData>(`/organizations/${id}`);
}

/** Как менялись показатели площадки между сборами, свежие сверху. */
export function metricsHistory(id: string): Promise<MetricsSnapshotData[]> {
    return get<MetricsSnapshotData[]>(`/organizations/${id}/history`);
}
