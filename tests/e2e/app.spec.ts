import { expect, test, type Page } from '@playwright/test';

/*
| Сценарий, который ревьюер прошёл бы руками: войти, найти организацию, открыть её, почитать отзывы и полистать
| страницы. Проверяется то, что интерфейс обещает, — русские тексты с бэкенда, 24-часовые даты, оценки звёздами:
| всё это ломалось так, как unit-тесты не видели.
*/

// Тот же пользователь, что создаёт DemoUserSeeder.
const user = { email: 'demo@example.com', password: 'password' };

async function signIn(page: Page): Promise<void> {
    await page.goto('/');
    await expect(page).toHaveURL(/\/login/);

    await page.fill('input[type=email]', user.email);
    await page.fill('input[type=password]', user.password);
    await page.click('button[type=submit]');

    await page.waitForSelector('main a[href*="/organizations/"]');
}

async function openOrganization(page: Page): Promise<void> {
    await signIn(page);
    await page.click('text=Вольт 11');
    await page.waitForSelector('main ul li');
}

test('a visitor without a session is sent to the login screen', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('button[type=submit]')).toHaveText('Войти');
});

test('the list shows the organization with both figures of the platform', async ({ page }) => {
    await signIn(page);

    const list = page.locator('main');
    await expect(list).toContainText('Вольт 11');
    // Оценки и отзывы — разные числа, и на экране должны быть оба.
    await expect(list).toContainText('416');
    await expect(list).toContainText('191');
});

test('the interface speaks Russian and shows 24-hour dates', async ({ page }) => {
    await openOrganization(page);

    const card = page.locator('main');
    await expect(card).toContainText('Отзывы');
    await expect(card).toContainText('Собрать заново');
    // Из-за устаревшей локали в интерфейс однажды протекли английские подписи прямо из переводов бэкенда.
    await expect(card).not.toContainText('Done');
    await expect(card).not.toContainText('Collecting data');

    const text = (await card.textContent()) ?? '';
    expect(text).toMatch(/\d{1,2}\s(янв|фев|мар|апр|ма|июн|июл|авг|сент|окт|нояб|дек)/i);
    expect(text).not.toMatch(/\b(AM|PM)\b/);
});

test('a rating is drawn as that many yellow stars, not as a number', async ({ page }) => {
    await openOrganization(page);

    const stars = page.locator('main span').filter({ hasText: '★' }).first();
    await expect(stars).toHaveText(/^★+$/);

    const colour = await stars.evaluate((element) => getComputedStyle(element).color);
    // Tailwind v4 отдаёт цвета в oklch; жёлтый оттенок лежит около 86.
    expect(colour).toMatch(/oklch\(0\.[78]\d+ 0\.1\d+ (8[0-9]|9[0-5])/);

    await expect(page.locator('main')).toContainText('Без оценки');
});

test('pages are numbered and switch without reloading', async ({ page }) => {
    await openOrganization(page);

    const pagination = page.locator('nav').last();
    await expect(pagination.locator('button')).toContainText(['Назад', '1', '2', '3', 'Вперёд']);

    const firstReview = await page.locator('main ul li').first().textContent();
    await pagination.locator('button', { hasText: '2' }).click();

    await expect(page).toHaveURL(/page=2/);
    await expect
        .poll(async () => page.locator('main ul li').first().textContent())
        .not.toBe(firstReview);

    // Номер страницы живёт в адресной строке, поэтому кнопка «назад» работает так, как читатель и ожидает.
    await page.goBack();
    await expect(page).not.toHaveURL(/page=2/);
});
