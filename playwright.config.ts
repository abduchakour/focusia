import { defineConfig } from '@playwright/test';

/**
 * End-to-end tests (offline quiz flow) with the Edge browser already installed on Windows:
 * no browser download. They run on a separate database (focusia_e2e) and port (8002),
 * so the demo data on port 8001 is never touched.
 *   npm run build && npm run test:e2e
 */
export default defineConfig({
    testDir: 'tests/e2e',
    timeout: 90_000,
    workers: 1,
    reporter: [['list']],
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL: 'http://127.0.0.1:8002',
        channel: 'msedge',
        locale: 'fr-FR',
        timezoneId: 'Africa/Djibouti',
        serviceWorkers: 'allow',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8002',
        url: 'http://127.0.0.1:8002/up',
        env: { APP_ENV: 'e2e' },
        reuseExistingServer: false,
        timeout: 60_000,
    },
});
