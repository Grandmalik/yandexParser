import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaginationNav from './PaginationNav.vue';

/*
| Строка с номерами страниц ломается тихо: она обязана оставаться короткой на списке из сотен страниц и при этом
| держать первую и последнюю страницу в одном клике.
*/

function labels(currentPage: number, lastPage: number): string[] {
    return mount(PaginationNav, { props: { currentPage, lastPage } })
        .findAll('button')
        .map((button) => button.text());
}

describe('PaginationNav', () => {
    it('collapses the middle but keeps the first and the last page reachable', () => {
        expect(labels(50, 100)).toEqual(['Назад', '1', '49', '50', '51', '100', 'Вперёд']);
    });

    it('shows every page when they all fit', () => {
        expect(labels(1, 3)).toEqual(['Назад', '1', '2', '3', 'Вперёд']);
    });

    it('keeps the row the same width at both ends of a long list', () => {
        expect(labels(1, 100)).toEqual(['Назад', '1', '2', '3', '4', '100', 'Вперёд']);
        expect(labels(100, 100)).toEqual(['Назад', '1', '97', '98', '99', '100', 'Вперёд']);
    });

    it('reports the page the reader asked for instead of navigating itself', async () => {
        const wrapper = mount(PaginationNav, { props: { currentPage: 2, lastPage: 5 } });
        const buttons = wrapper.findAll('button');

        await buttons.at(0)?.trigger('click');
        await buttons.at(-1)?.trigger('click');

        // Оба клика дошли: компонент просит предыдущую и следующую страницу, но сам никуда не переходит.
        expect(wrapper.emitted('change')).toEqual([[1], [3]]);
    });

    it('draws nothing when everything fits on one page', () => {
        expect(
            mount(PaginationNav, { props: { currentPage: 1, lastPage: 1 } })
                .find('nav')
                .exists(),
        ).toBe(false);
    });

    it('blocks the buttons while a page is loading', () => {
        const wrapper = mount(PaginationNav, {
            props: { currentPage: 2, lastPage: 5, disabled: true },
        });

        expect(
            wrapper
                .findAll('button')
                .every((button) => button.attributes('disabled') !== undefined),
        ).toBe(true);
    });
});
