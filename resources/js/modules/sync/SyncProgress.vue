<script setup lang="ts">
import { computed, watch } from 'vue';
import { useAsync } from '@/shared/composables/useAsync';
import { useSyncProgress } from '@/shared/composables/useSyncProgress';
import { fill, formatDateTime } from '@/shared/i18n/format';
import { ru } from '@/shared/i18n/ru';
import type { OrganizationData } from '@/types/generated/api';

const props = defineProps<{ organization: OrganizationData }>();
const emit = defineEmits<{ finished: [] }>();

const { run, collectAgain } = useSyncProgress(props.organization);
const again = useAsync(collectAgain);

// Площадка отдаёт не все отзывы: собрано всё доступное, но это только последние из них.
const latestOnly = computed(() => {
    const total = run.value?.progress.total ?? null;
    const reviewsOnPlatform = props.organization.rating_summary?.reviews_count ?? 0;

    return run.value?.is_finished === true && total !== null && reviewsOnPlatform > total;
});

// В момент завершения сбора показатели и отзывы на экране устаревают — сообщаем об этом родителю.
watch(
    () => run.value?.is_finished,
    (finished, wasFinished) => {
        if (finished === true && wasFinished === false) {
            emit('finished');
        }
    },
);
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="font-semibold">{{ ru.sync.title }}</h2>

            <button
                type="button"
                :disabled="again.isLoading.value || (run !== null && !run.is_finished)"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-60"
                @click="again.execute()"
            >
                {{
                    run !== null && !run.is_finished
                        ? ru.organization.collecting
                        : ru.organization.collectAgain
                }}
            </button>
        </div>

        <p
            v-if="again.error.value"
            class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700"
            role="alert"
        >
            {{ again.error.value.message }}
        </p>

        <p v-if="run === null" class="text-sm text-gray-600">{{ ru.sync.never }}</p>

        <template v-else>
            <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <span class="font-medium">{{ run.status.label }}</span>
                <span class="text-gray-600">
                    {{
                        latestOnly
                            ? fill(ru.sync.latestOnly, { current: run.progress.current })
                            : run.progress.total === null
                              ? fill(ru.sync.progressUnknown, { current: run.progress.current })
                              : fill(ru.sync.progress, {
                                    current: run.progress.current,
                                    total: run.progress.total,
                                })
                    }}
                </span>
                <span v-if="run.attempts > 1" class="text-gray-500">
                    {{ fill(ru.sync.attempt, { number: run.attempts }) }}
                </span>
                <span v-if="run.finished_at" class="text-gray-500">
                    {{ ru.sync.finishedAt }}: {{ formatDateTime(run.finished_at) }}
                </span>
            </div>

            <div
                v-if="!run.is_finished"
                class="h-2 w-full overflow-hidden rounded-full bg-gray-100"
            >
                <div
                    class="h-full bg-gray-900 transition-all"
                    :style="{ width: `${run.progress.percent ?? 0}%` }"
                />
            </div>

            <!-- Бэкенд уже превратил сбой в сообщение; экран только находит ему место. -->
            <p
                v-if="run.error"
                class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800"
                role="alert"
            >
                {{ run.error.message }}
            </p>

            <p v-if="run.stats" class="mt-3 text-sm text-gray-600">
                {{ run.stats.created }} {{ ru.sync.stats.created }} · {{ run.stats.updated }}
                {{ ru.sync.stats.updated }} · {{ run.stats.unchanged }}
                {{ ru.sync.stats.unchanged }} · {{ run.stats.removed }} {{ ru.sync.stats.removed }}
            </p>
        </template>
    </section>
</template>
