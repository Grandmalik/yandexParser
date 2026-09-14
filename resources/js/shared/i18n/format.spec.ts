import { describe, expect, it } from 'vitest';
import { fill } from './format';

describe('fill', () => {
    it('puts values into the placeholders of a static text', () => {
        expect(fill('Собрано {current} из {total}', { current: 50, total: 191 })).toBe(
            'Собрано 50 из 191',
        );
    });

    it('leaves a placeholder alone when there is no value for it', () => {
        expect(fill('Страница {current} из {last}', { current: 2 })).toBe('Страница 2 из {last}');
    });
});
