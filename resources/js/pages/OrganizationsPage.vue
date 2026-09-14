<script setup lang="ts">
import { ref, watch } from 'vue';
import { connectOrganization, organizations } from '@/modules/organizations/api';
import SyncProgress from '@/modules/sync/SyncProgress.vue';
import { useAsync } from '@/shared/composables/useAsync';
import { usePageQuery } from '@/shared/composables/usePageQuery';
import { fill, formatDateTime } from '@/shared/i18n/format';
import { ru } from '@/shared/i18n/ru';
import PaginationNav from '@/shared/ui/PaginationNav.vue';
import type { OrganizationData } from '@/types/generated/api';

const url = ref('');
const watched = ref(new Set<string>());

const { page, goToPage } = usePageQuery();

// Размер страницы и число страниц — ответ бэкенда; список только переключается между ними.
const list = useAsync((requested: number) => organizations(requested));

watch(page, (requested) => void list.execute(requested), { immediate: true });

// Бэкенд проверяет ссылку и запускает сбор; карточка появляется сразу и наполняется по мере прихода данных.
const connect = useAsync(async () => {
    const organization = await connectOrganization(url.value);
    watched.value = new Set([...watched.value, organization.id]);
    url.value = '';

    // Только что подключённая — первая строка первой страницы, там её и будет видно.
    goToPage(1);
    await list.execute(1);
});

/**
 * Прогресс показывается для только что подключённых и для всех, у кого ещё нет показателей, — чтобы перезагрузка
 * страницы посреди сбора всё равно приводила на экран, который объясняет, что происходит.
 */
function isCollecting(organization: OrganizationData): boolean {
    return watched.value.has(organization.id) || organization.metrics_updated_at === null;
}
</script>

<template>
    <section>
        <div class="mb-4 flex items-baseline justify-between gap-3">
            <h1 class="text-xl font-semibold">{{ ru.organizations.title }}</h1>
            <span v-if="list.data.value" class="text-sm text-gray-600">
                {{ fill(ru.organizations.total, { total: list.data.value.meta.total }) }}
            </span>
        </div>

        <form
            class="mb-6 rounded-lg border border-gray-200 bg-white p-4"
            @submit.prevent="connect.execute()"
        >
            <h2 class="mb-3 font-semibold">{{ ru.organizations.connect.title }}</h2>

            <p
                v-if="connect.error.value && !connect.error.value.fields"
                class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700"
                role="alert"
            >
                {{ connect.error.value.message }}
            </p>

            <label class="block">
                <span class="mb-1 block text-sm text-gray-700">{{
                    ru.organizations.connect.url
                }}</span>
                <div class="flex gap-2">
                    <input
                        v-model="url"
                        type="url"
                        required
                        :placeholder="ru.organizations.connect.placeholder"
                        class="w-full rounded-md border border-gray-300 px-3 py-2"
                    />
                    <button
                        type="submit"
                        :disabled="connect.isLoading.value"
                        class="shrink-0 rounded-md bg-gray-900 px-4 py-2 text-white disabled:opacity-60"
                    >
                        {{
                            connect.isLoading.value
                                ? ru.organizations.connect.submitting
                                : ru.organizations.connect.submit
                        }}
                    </button>
                </div>
                <span
                    v-if="connect.error.value?.fieldError('url')"
                    class="mt-1 block text-sm text-red-700"
                >
                    {{ connect.error.value.fieldError('url') }}
                </span>
            </label>
        </form>

        <p
            v-if="list.error.value"
            class="rounded-md bg-red-50 p-3 text-sm text-red-700"
            role="alert"
        >
            {{ list.error.value.message }}
        </p>

        <p
            v-else-if="list.data.value && list.data.value.data.length === 0"
            class="rounded-lg border border-gray-200 bg-white p-6 text-gray-600"
        >
            {{ ru.organizations.empty }}
        </p>

        <template v-else-if="list.data.value">
            <ul class="space-y-3" :class="{ 'opacity-60': list.isLoading.value }">
                <li
                    v-for="organization in list.data.value.data"
                    :key="organization.id"
                    class="rounded-lg border border-gray-200 bg-white p-4"
                >
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <div>
                            <RouterLink
                                :to="{ name: 'organization', params: { id: organization.id } }"
                                class="font-medium underline"
                            >
                                {{ organization.name ?? organization.source_url }}
                            </RouterLink>
                            <p v-if="organization.address" class="text-sm text-gray-600">
                                {{ organization.address }}
                            </p>
                        </div>

                        <div class="text-right text-sm">
                            <p v-if="organization.rating_summary">
                                <span class="font-medium"
                                    >★ {{ organization.rating_summary.rating }}</span
                                >
                                <span class="text-gray-600">
                                    · {{ ru.organization.ratingsCount }}:
                                    {{ organization.rating_summary.ratings_count }} ·
                                    {{ ru.organization.reviewsCount }}:
                                    {{ organization.rating_summary.reviews_count }}
                                </span>
                            </p>
                            <p v-else class="text-gray-600">
                                {{ ru.organizations.card.noFigures }}
                            </p>

                            <p class="text-gray-600">
                                {{ ru.organizations.card.stored }}:
                                {{ organization.reviews_stored }}
                            </p>
                            <p v-if="organization.metrics_updated_at" class="text-gray-500">
                                {{ ru.organization.updatedAt }}
                                {{ formatDateTime(organization.metrics_updated_at) }}
                            </p>
                        </div>
                    </div>

                    <SyncProgress
                        v-if="isCollecting(organization)"
                        class="mt-3"
                        :organization="organization"
                        @finished="list.execute(page)"
                    />
                </li>
            </ul>

            <PaginationNav
                :current-page="list.data.value.meta.current_page"
                :last-page="list.data.value.meta.last_page"
                :disabled="list.isLoading.value"
                @change="goToPage"
            />
        </template>
    </section>
</template>
