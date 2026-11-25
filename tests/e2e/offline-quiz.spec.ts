import { expect, test, type Page } from '@playwright/test';

/**
 * Acceptance criterion of phase 4: a student starts a quiz online, loses the network,
 * keeps answering (even after reloading the page), sends the answers while offline,
 * and gets the grade automatically when the network comes back (rule 8).
 */

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', 'Demo2026!');
    await Promise.all([page.waitForURL('**/etudiant'), page.click('button[type=submit]')]);
}

async function waitForServiceWorker(page: Page) {
    await page.evaluate(async () => {
        await navigator.serviceWorker.ready;
    });
    // The first page load is not controlled yet: reload so the service worker handles navigations.
    await page.reload();
    await expect.poll(() => page.evaluate(() => navigator.serviceWorker.controller !== null)).toBe(true);
}

test('a quiz answered offline is sent and graded when the network comes back', async ({ page, context }) => {
    await login(page, 'etudiant01@focusia.test');
    await waitForServiceWorker(page);

    // Start the open quiz (online: the server records the start time).
    await page.getByRole('link', { name: 'Quiz', exact: true }).first().click();
    await page.waitForURL('**/etudiant/quiz');
    await page.getByRole('button', { name: 'Commencer' }).first().click();
    await page.getByRole('button', { name: "C'est parti !" }).click();
    await page.waitForURL('**/passer');
    const takeUrl = page.url();

    // The quiz page is kept in the cache for offline use.
    await expect.poll(() => page.evaluate((url) => caches.match(url).then((r) => r !== undefined), takeUrl)).toBe(true);

    const questions = page.getByRole('navigation', { name: 'Questions' }).getByRole('button');
    const total = await questions.count();
    expect(total).toBeGreaterThan(1);

    // Question 1 online.
    await page.getByRole('radio').first().click();
    await page.getByRole('button', { name: 'Suivant' }).click();

    // The network disappears.
    await context.setOffline(true);
    await expect(page.getByText('Hors connexion.')).toBeVisible();

    // Question 2 offline, then the phone reloads the page: the service worker serves it
    // and the answers come back from IndexedDB.
    await page.getByRole('radio').nth(1).click();
    await page.waitForTimeout(300);
    await page.reload();
    await expect(page.getByRole('button', { name: 'Question 2 (répondue)' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Question 1 (répondue)' })).toBeVisible();

    // Answer the remaining questions, still offline.
    for (let i = 2; i < total; i++) {
        await page.getByRole('button', { name: `Question ${i + 1}`, exact: true }).click();
        await page.getByRole('radio').nth(i % 4).click();
    }
    await page.getByRole('button', { name: 'Terminer' }).click();
    await expect(page.getByRole('heading', { name: 'Prêt à envoyer ?' })).toBeVisible();
    await page.getByRole('button', { name: 'Envoyer mes réponses' }).click();

    // Kept on the phone while offline.
    await expect(page.getByRole('heading', { name: 'Réponses enregistrées sur ce téléphone' })).toBeVisible();

    // The network comes back: the queue sends the answers and the grade appears.
    await context.setOffline(false);
    await page.waitForURL('**/resultat', { timeout: 30_000 });
    await expect(page.getByText('sur 20')).toBeVisible();
    await expect(page.getByText(/bonnes? réponses? sur \d+/)).toBeVisible();
    // Rule 7: no correction before corrections_at.
    await expect(page.getByText(/Corrections disponibles/)).toBeVisible();
});

test('a page never opened shows the French offline page', async ({ page, context }) => {
    await login(page, 'etudiant02@focusia.test');
    await waitForServiceWorker(page);

    await context.setOffline(true);
    await page.goto('/etudiant/groupes').catch(() => undefined);
    await expect(page.getByRole('heading', { name: 'Vous êtes hors connexion' })).toBeVisible();
});
