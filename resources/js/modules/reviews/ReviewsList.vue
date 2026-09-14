<script setup lang="ts">
import { watch } from 'vue';
import { reviewsPage } from '@/modules/reviews/api';
import { useAsync } from '@/shared/composables/useAsync';
import { usePageQuery } from '@/shared/composables/usePageQuery';
import { fill, formatDate } from '@/shared/i18n/format';
import { ru } from '@/shared/i18n/ru';
import PaginationNav from '@/shared/ui/PaginationNav.vue';

const props = defineProps<{ organizationId: string }>();

const { page, goToPage } = usePageQuery();

const { data, error, isLoading, execute } = useAsync((requested: number) =>
    reviewsPage(props.organizationId, requested),
);

// Размер страницы и число страниц — ответ бэкенда; список только переключается между ними.
watch(page, (requested) => void execute(requested), { immediate: true });

/** Оценка показывается таким же количеством звёзд; само число остаётся для скринридеров и подсказки. */
function stars(rating: number): string {
    return '★'.repeat(rating);
}

/** Вызывается страницей, когда сбор закончился и список мог измениться. */
function reload(): void {
    void execute(page.value);
}

defineExpose({ reload });
</script>

<template>
    <section>
        <div class="mb-3 flex items-baseline justify-between gap-3">
            <h2 class="font-semibold">{{ ru.reviews.title }}</h2>
            <span v-if="data" class="text-sm text-gray-600">
                {{ fill(ru.reviews.total, { total: data.meta.total }) }}
            </span>
        </div>

        <p v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700" role="alert">
            {{ error.message }}
        </p>

        <p v-else-if="isLoading && !data" class="text-sm text-gray-600">{{ ru.common.loading }}</p>

        <p
            v-else-if="data && data.data.length === 0"
            class="rounded-lg border border-gray-200 bg-white p-6 text-gray-600"
        >
            {{ ru.reviews.empty }}
        </p>

        <template v-else-if="data">
            <ul class="space-y-3" :class="{ 'opacity-60': isLoading }">
                <li
                    v-for="review in data.data"
                    :key="review.id"
                    class="rounded-lg border border-gray-200 bg-white p-4"
                >
                    <div class="mb-1 flex flex-wrap items-baseline gap-x-3 text-sm">
                        <span class="font-medium">{{
                            review.author_name ?? ru.reviews.anonymous
                        }}</span>
                        <span class="text-gray-500">{{ formatDate(review.published_at) }}</span>
                        <span
                            v-if="review.rating !== null"
                            class="tracking-wide text-yellow-500"
                            :title="fill(ru.reviews.ratingLabel, { rating: review.rating })"
                            :aria-label="fill(ru.reviews.ratingLabel, { rating: review.rating })"
                        >
                            {{ stars(review.rating) }}
                        </span>
                        <span v-else class="text-gray-500">{{ ru.reviews.noRating }}</span>
                    </div>

                    <p v-if="review.text" class="whitespace-pre-line text-gray-800">
                        {{ review.text }}
                    </p>

                    <p
                        v-if="review.business_reply"
                        class="mt-3 border-l-2 border-gray-200 pl-3 text-sm text-gray-700"
                    >
                        <span class="mb-1 block font-medium">{{ ru.reviews.reply }}</span>
                        {{ review.business_reply }}
                    </p>
                </li>
            </ul>

            <PaginationNav
                :current-page="data.meta.current_page"
                :last-page="data.meta.last_page"
                :disabled="isLoading"
                @change="goToPage"
            />
        </template>
    </section>
</template>
