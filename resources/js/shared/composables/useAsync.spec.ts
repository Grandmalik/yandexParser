import { describe, expect, it } from 'vitest';
import { useAsync } from './useAsync';
import { ApiError } from '@/shared/api/error';

describe('useAsync', () => {
    it('exposes loading and then the result', async () => {
        const { data, error, isLoading, execute } = useAsync(async () => 'готово');

        const pending = execute();
        expect(isLoading.value).toBe(true);

        await pending;
        expect(isLoading.value).toBe(false);
        expect(data.value).toBe('готово');
        expect(error.value).toBeNull();
    });

    it('captures a failure as an ApiError instead of throwing', async () => {
        const { data, error, isLoading, execute } = useAsync(async () => {
            throw new ApiError(503, 'source.unavailable', 'Источник недоступен.');
        });

        await execute();

        expect(error.value?.code).toBe('source.unavailable');
        expect(error.value?.message).toBe('Источник недоступен.');
        expect(data.value).toBeNull();
        expect(isLoading.value).toBe(false);
    });

    it('clears the previous error on a new call', async () => {
        let shouldFail = true;
        const { error, execute } = useAsync(async () => {
            if (shouldFail) {
                throw new ApiError(500, 'internal', 'Ошибка');
            }

            return 'ок';
        });

        await execute();
        expect(error.value).not.toBeNull();

        shouldFail = false;
        await execute();
        expect(error.value).toBeNull();
    });

    it('ignores an answer that a newer call has overtaken', async () => {
        const answers: Record<string, number> = { slow: 30, fast: 0 };
        const { data, execute } = useAsync(
            (key: string) =>
                new Promise<string>((resolve) => setTimeout(() => resolve(key), answers[key])),
        );

        const slow = execute('slow');
        const fast = execute('fast');
        await Promise.all([slow, fast]);

        expect(data.value).toBe('fast');
    });
});
