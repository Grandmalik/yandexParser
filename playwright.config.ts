import { defineConfig, devices } from '@playwright/test';
import { fileURLToPath, URL } from 'node:url';

const port = 8001;
const baseURL = `http://127.0.0.1:${port}`;
const database = fileURLToPath(new URL('./tests/e2e/e2e.sqlite', import.meta.url));

/*
| End-to-end checks run against the built SPA and a real backend, but never against a real platform: the database
| is seeded beforehand, so the scenario is deterministic and safe to run in CI.
*/
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    timeout: 30_000,
    use: {
        baseURL,
        locale: 'ru-RU',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

    // A throwaway SQLite file, its own port and its own stateful domain: the developer's environment is untouched.
    // Seeding is part of the server command on purpose — Playwright starts the web server before globalSetup, so a
    // seeder placed there would leave the application booting without a database and answering 500.
    webServer: {
        command: `php tests/e2e/seed.php && php artisan serve --port=${port}`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 60_000,
        env: {
            APP_ENV: 'local',
            APP_URL: baseURL,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            SANCTUM_STATEFUL_DOMAINS: `127.0.0.1:${port},localhost:${port}`,
            CACHE_STORE: 'array',
            QUEUE_CONNECTION: 'sync',
            BROADCAST_CONNECTION: 'log',
        },
    },
});
