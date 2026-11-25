import { execSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';

/**
 * Prepares the e2e environment: `.env.e2e` (copy of `.env` on the focusia_e2e database),
 * an empty database, then the demo data. Laravel loads `.env.e2e` because APP_ENV=e2e.
 */
export default function globalSetup(): void {
    if (!existsSync('public/build/manifest.json') || !existsSync('public/sw.js')) {
        throw new Error('Assets manquants : lancez « npm run build » avant les tests e2e.');
    }

    const env = readFileSync('.env', 'utf8')
        .replace(/^DB_DATABASE=.*$/m, 'DB_DATABASE=focusia_e2e')
        .replace(/^APP_URL=.*$/m, 'APP_URL=http://127.0.0.1:8002')
        .replace(/^SESSION_SECURE_COOKIE=.*$/m, 'SESSION_SECURE_COOKIE=false');
    writeFileSync('.env.e2e', env);

    const value = (key: string, fallback = '') => env.match(new RegExp(`^${key}=(.*)$`, 'm'))?.[1].trim().replace(/^"|"$/g, '') ?? fallback;
    const dsn = `mysql:host=${value('DB_HOST', '127.0.0.1')};port=${value('DB_PORT', '3306')}`;
    execSync(
        `php -r "(new PDO('${dsn}', '${value('DB_USERNAME', 'root')}', '${value('DB_PASSWORD')}'))->exec('CREATE DATABASE IF NOT EXISTS focusia_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"`,
        { stdio: 'inherit' },
    );
    execSync('php artisan migrate:fresh --seed --force', { stdio: ['ignore', 'ignore', 'inherit'], env: { ...process.env, APP_ENV: 'e2e' } });
}
