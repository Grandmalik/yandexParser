import { ref, shallowRef } from 'vue';
import { ApiError } from '@/shared/api/error';

/**
 * Выполняет запрос к API и отдаёт три состояния, которые обязан показывать любой экран: загрузка, ошибка,
 * результат. Медленный ответ, который обогнал более новый вызов, отбрасывается.
 */
export function useAsync<TResult, TArguments extends unknown[] = []>(
    operation: (...args: TArguments) => Promise<TResult>,
) {
    const data = shallowRef<TResult | null>(null);
    const error = shallowRef<ApiError | null>(null);
    const isLoading = ref(false);
    let lastCall = 0;

    async function execute(...args: TArguments): Promise<TResult | null> {
        const call = ++lastCall;
        isLoading.value = true;
        error.value = null;

        try {
            const result = await operation(...args);

            if (call === lastCall) {
                data.value = result;
            }

            return result;
        } catch (failure) {
            if (call === lastCall) {
                error.value = ApiError.wrap(failure);
            }

            return null;
        } finally {
            if (call === lastCall) {
                isLoading.value = false;
            }
        }
    }

    return { data, error, isLoading, execute };
}
