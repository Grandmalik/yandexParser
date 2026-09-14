import { computed, type ComputedRef } from 'vue';
import { useRoute, useRouter } from 'vue-router';

/**
 * Текущая страница списка, живущая в адресной строке: и кнопка «назад» в браузере, и присланная кем-то ссылка
 * приводят на ту же самую страницу. Остальные параметры запроса сохраняются, поэтому экран может держать
 * рядом свои собственные.
 */
export function usePageQuery(): {
    page: ComputedRef<number>;
    goToPage: (page: number) => void;
} {
    const route = useRoute();
    const router = useRouter();

    const page = computed(() => Math.max(1, Number(route.query.page ?? 1) || 1));

    function goToPage(requested: number): void {
        void router.push({ query: { ...route.query, page: requested } });
    }

    return { page, goToPage };
}
