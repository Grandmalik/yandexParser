import { useAppConfigStore } from '@/modules/app-config/store';

/** Подставляет значения в места вида `{name}` статического текста. */
export function fill(template: string, values: Record<string, string | number>): string {
    return template.replace(/\{(\w+)\}/g, (match, key: string) =>
        key in values ? String(values[key]) : match,
    );
}

/** Даты показываются в локали, в которой работает бэкенд, — чтобы всё приложение читалось одинаково. */
export function formatDate(iso: string): string {
    const locale = useAppConfigStore().config?.application.locale ?? 'ru';

    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(iso));
}

/** Время всегда в 24-часовом формате, что бы ни решила локаль сама по себе. */
export function formatDateTime(iso: string): string {
    const locale = useAppConfigStore().config?.application.locale ?? 'ru';

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        hour12: false,
    }).format(new Date(iso));
}
