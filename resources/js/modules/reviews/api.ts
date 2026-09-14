import { http } from '@/shared/api/http';
import type { PaginatedDataCollection, ReviewData } from '@/types/generated/api';

export type ReviewsPage = PaginatedDataCollection<number, ReviewData>;

/**
 * Одна страница отзывов. Конверт ответа сохраняется целиком: размер страницы и число страниц — ответ бэкенда,
 * а не решение экрана.
 */
export async function reviewsPage(organizationId: string, page: number): Promise<ReviewsPage> {
    const response = await http.get<ReviewsPage>(`/organizations/${organizationId}/reviews`, {
        params: { page },
    });

    return response.data;
}
