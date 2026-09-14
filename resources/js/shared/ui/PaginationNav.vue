<script setup lang="ts">
import { computed } from 'vue';
import { fill } from '@/shared/i18n/format';
import { ru } from '@/shared/i18n/ru';

/**
 * Нумерованная пагинация любого списка. Сколько всего страниц — ответ бэкенда, он приходит в props;
 * компонент только сообщает, какую страницу попросил читатель.
 */
const props = defineProps<{ currentPage: number; lastPage: number; disabled?: boolean }>();

const emit = defineEmits<{ change: [page: number] }>();

const GAP = '…';

/**
 * Номера страниц вокруг текущей: первая и последняя всегда доступны, середина схлопывается многоточием —
 * чтобы список из сотен страниц по-прежнему помещался в одну строку.
 */
const pageNumbers = computed<(number | typeof GAP)[]>(() => {
    const { currentPage: current, lastPage: last } = props;

    if (last <= 1) {
        return [];
    }

    const wanted = new Set([1, last, current, current - 1, current + 1]);

    // У обоих краёв держим ширину строки постоянной, чтобы она не прыгала в момент появления многоточия.
    if (current <= 3) {
        [2, 3, 4].forEach((number) => wanted.add(number));
    }

    if (current >= last - 2) {
        [last - 1, last - 2, last - 3].forEach((number) => wanted.add(number));
    }

    const numbers = [...wanted]
        .filter((number) => number >= 1 && number <= last)
        .sort((a, b) => a - b);

    return numbers.flatMap((number, index) => {
        const previous = numbers[index - 1];

        return previous !== undefined && number - previous > 1 ? [GAP, number] : [number];
    });
});
</script>

<template>
    <nav
        v-if="lastPage > 1"
        class="mt-4 flex flex-wrap items-center justify-center gap-1 text-sm"
        :aria-label="fill(ru.pagination.page, { current: currentPage, last: lastPage })"
    >
        <button
            type="button"
            :disabled="currentPage <= 1 || disabled"
            class="rounded-md border border-gray-300 px-2.5 py-1.5 disabled:opacity-50"
            @click="emit('change', currentPage - 1)"
        >
            {{ ru.pagination.previous }}
        </button>

        <template v-for="(number, index) in pageNumbers" :key="`${number}-${index}`">
            <span v-if="number === GAP" class="px-1 text-gray-500">{{ GAP }}</span>
            <button
                v-else
                type="button"
                :disabled="disabled"
                :aria-current="number === currentPage ? 'page' : undefined"
                :title="fill(ru.pagination.goToPage, { page: number })"
                class="min-w-9 rounded-md border px-2.5 py-1.5"
                :class="
                    number === currentPage
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : 'border-gray-300 hover:bg-gray-50'
                "
                @click="emit('change', Number(number))"
            >
                {{ number }}
            </button>
        </template>

        <button
            type="button"
            :disabled="currentPage >= lastPage || disabled"
            class="rounded-md border border-gray-300 px-2.5 py-1.5 disabled:opacity-50"
            @click="emit('change', currentPage + 1)"
        >
            {{ ru.pagination.next }}
        </button>
    </nav>
</template>
