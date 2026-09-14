<script setup lang="ts">
import { onMounted, ref, useTemplateRef } from 'vue';
import { metricsHistory, organization as fetchOrganization } from '@/modules/organizations/api';
import ReviewsList from '@/modules/reviews/ReviewsList.vue';
import SyncProgress from '@/modules/sync/SyncProgress.vue';
import { useAsync } from '@/shared/composables/useAsync';
import { formatDate, formatDateTime } from '@/shared/i18n/format';
import { ru } from '@/shared/i18n/ru';
import type { MetricsSnapshotData, OrganizationData } from '@/types/generated/api';

const props = defineProps<{ id: string }>();

const card = ref<OrganizationData | null>(null);
const history = ref<MetricsSnapshotData[]>([]);
const reviews = useTemplateRef<InstanceType<typeof ReviewsList>>('reviews');

const load = useAsync(async () => {
    [card.value, history.value] = await Promise.all([
        fetchOrganization(props.id),
        metricsHistory(props.id),
    ]);
});

/** Завершившийся сбор меняет сразу всё: и показатели, и историю, и список отзывов. */
async function reloadAfterSync(): Promise<void> {
    await load.execute();
    reviews.value?.reload();
}

onMounted(() => void load.execute());
</script>

<template>
    <section>
        <RouterLink :to="{ name: 'organizations' }" class="mb-4 inline-block text-sm underline">
            {{ ru.organization.back }}
        </RouterLink>

        <p
            v-if="load.error.value"
            class="rounded-md bg-red-50 p-3 text-sm text-red-700"
            role="alert"
        >
            {{ load.error.value.message }}
        </p>

        <p v-else-if="card === null" class="text-sm text-gray-600">{{ ru.common.loading }}</p>

        <template v-else>
            <header class="mb-6">
                <h1 class="text-xl font-semibold">{{ card.name ?? card.source_url }}</h1>
                <p v-if="card.address" class="text-gray-600">{{ card.address }}</p>
                <a
                    :href="card.source_url"
                    target="_blank"
                    rel="noopener"
                    class="text-sm text-gray-600 underline"
                >
                    {{ ru.organization.sourceLink }}
                </a>
            </header>

            <dl class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-sm text-gray-600">{{ ru.organization.rating }}</dt>
                    <dd class="text-lg font-semibold">{{ card.rating_summary?.rating ?? '—' }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-sm text-gray-600">{{ ru.organization.ratingsCount }}</dt>
                    <dd class="text-lg font-semibold">
                        {{ card.rating_summary?.ratings_count ?? '—' }}
                    </dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-sm text-gray-600">{{ ru.organization.reviewsCount }}</dt>
                    <dd class="text-lg font-semibold">
                        {{ card.rating_summary?.reviews_count ?? '—' }}
                    </dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-sm text-gray-600">{{ ru.organization.stored }}</dt>
                    <dd class="text-lg font-semibold">{{ card.reviews_stored }}</dd>
                </div>
            </dl>

            <div class="mb-6">
                <SyncProgress :organization="card" @finished="reloadAfterSync" />
            </div>

            <section class="mb-6">
                <h2 class="mb-3 font-semibold">{{ ru.history.title }}</h2>

                <p
                    v-if="history.length === 0"
                    class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600"
                >
                    {{ ru.history.empty }}
                </p>

                <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ ru.history.capturedAt }}</th>
                                <th class="px-4 py-2 font-medium">{{ ru.history.rating }}</th>
                                <th class="px-4 py-2 font-medium">{{ ru.history.ratingsCount }}</th>
                                <th class="px-4 py-2 font-medium">{{ ru.history.reviewsCount }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="snapshot in history"
                                :key="snapshot.captured_at"
                                class="border-b border-gray-100 last:border-0"
                            >
                                <td class="px-4 py-2">{{ formatDate(snapshot.captured_at) }}</td>
                                <td class="px-4 py-2">
                                    {{ snapshot.rating }}
                                    <span
                                        v-if="snapshot.rating_change"
                                        :class="
                                            snapshot.rating_change > 0
                                                ? 'text-green-700'
                                                : 'text-red-700'
                                        "
                                    >
                                        {{ snapshot.rating_change > 0 ? '+' : ''
                                        }}{{ snapshot.rating_change }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    {{ snapshot.ratings_count }}
                                    <span
                                        v-if="snapshot.ratings_count_change"
                                        class="text-gray-500"
                                    >
                                        {{ snapshot.ratings_count_change > 0 ? '+' : ''
                                        }}{{ snapshot.ratings_count_change }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    {{ snapshot.reviews_count }}
                                    <span
                                        v-if="snapshot.reviews_count_change"
                                        class="text-gray-500"
                                    >
                                        {{ snapshot.reviews_count_change > 0 ? '+' : ''
                                        }}{{ snapshot.reviews_count_change }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <ReviewsList ref="reviews" :organization-id="card.id" />

            <p v-if="card.metrics_updated_at" class="mt-6 text-sm text-gray-500">
                {{ ru.organization.updatedAt }} {{ formatDateTime(card.metrics_updated_at) }}
            </p>
        </template>
    </section>
</template>
