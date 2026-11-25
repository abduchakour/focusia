# CLAUDE.md — FocusIA

## Product

FocusIA is a web platform for quizzes and learning follow-up in **higher education in Djibouti**.
Teachers create groups, quizzes (one correct answer out of 4, each question linked to a *notion*),
course materials, homework and a library. Results are turned into a **per-notion understanding view**.
Students take quizzes **even with an intermittent connection** (PWA + IndexedDB), mostly on low-end Android phones.

Current scope: **V1 / MVP** — the original brief is `prompt-claude-code-FocusIA.md` (outside the repo, given in chat).
Out of scope for V1: AI, institution ADMIN role, Excel import, monthly reports, notifications, app stores,
multi-answer questions, languages other than French. The `institutions` table already exists for V2.

**Hard constraints from the owner**
- **Zero cost**: free/open-source tools only, no paid service, no Docker. Demo runs on the owner's laptop.
- The owner presents the project to a **jury** soon: prioritise what is visible in a demo.
- Work **phase by phase**; at the end of each phase: tests green, commit, stop and summarise, wait for approval.
- Never guess an ambiguous business rule: ask first.

## Stack (replaces the NestJS/Next.js/Prisma stack of the original brief — decided 2026-11-25)

| Layer | Choice |
| --- | --- |
| Backend | **Laravel 12** (PHP 8.3, `C:\php`) — one app, no separate API |
| Frontend | **Inertia 2 + Vue 3 + TypeScript + Tailwind** (Laravel Vue starter kit, shadcn-vue components in `resources/js/components/ui`) |
| Database | **MariaDB 10.4** from XAMPP (`focusia`, tests on `focusia_test`) |
| Auth | Laravel sessions (DB driver, encrypted, httpOnly, sameSite=lax), **Argon2id** (`HASH_DRIVER`), CSRF built in, login throttling |
| Files | Private Laravel disk (`FOCUSIA_FILES_DISK=local` → `storage/app/private`), temporary signed URLs (10 min). `s3` (R2/B2) possible later without code changes |
| Offline | (phase 4) `vite-plugin-pwa` service worker + **Dexie.js** (IndexedDB) |
| Charts | (phase 5) Chart.js via vue-chartjs |
| Tests | PHPUnit (`php artisan test`) on MariaDB; (phase 4) Playwright for offline flows |
| CI | GitHub Actions `.github/workflows/ci.yml` (Pint, ESLint, Prettier, vue-tsc, build, tests on MariaDB service) |
| Demo on phones | free Cloudflare quick tunnel (`cloudflared tunnel --url http://localhost:8001`) for HTTPS |

## Commands

```bash
composer dev            # php artisan serve (port 8001) + vite
composer demo           # migrate:fresh --seed (demo data)
php artisan test        # all tests (needs MariaDB running, DB focusia_test)
vendor/bin/pint         # PHP formatting
npm run lint            # ESLint --fix      | npm run lint:check (CI)
npm run format          # Prettier         | npm run format:check (CI)
npm run typecheck       # vue-tsc
npm run build           # production assets
```

Port **8000 is used by another local project** of the owner: FocusIA uses **8001** (`SERVER_PORT` in `.env`).
Never stop processes that are not ours.

## Conventions

- **Code, identifiers and comments in English; every UI text in French** (clear success/error messages).
- Business settings live in `config/focusia.php` (overridable in `.env`); never hard-code thresholds.
- Secrets only in `.env` (never committed); keep `.env.example` in sync.
- Enums in `app/Enums` (string-backed, `label()` returns French). Pure business formulas in `app/Support` (no DB access) with unit tests.
- Authorization **server-side on every request**: role (teacher/student) **and** group membership / ownership (Policies). Never rely on the UI.
- `Choice::$hidden` hides `is_correct`: only reveal correct answers explicitly, and only after `corrections_at`.
- Prefer DB constraints for invariants (unique pairs, CHECKs) **and** app validation for friendly messages.
- File uploads: validate real MIME type (`mimetypes:`) + extension + size (`focusia.max_upload_kb`); store on the private disk; download through signed temporary URLs only.
- Dates: app timezone `Africa/Djibouti` (UTC+3, no DST).
- PHP style: Pint (Laravel preset). Front: Prettier + ESLint, `<script setup lang="ts">`.
- Commit at the end of each phase, message `feat(phase-N): ...`, with the Co-Authored-By trailer.

## Routing and access control

- `/dashboard` redirects to the role's space. Teacher routes: prefix `/enseignant`, names `teacher.*`, middleware `role:teacher`.
  Student routes: prefix `/etudiant`, names `student.*`, middleware `role:student`. Controllers in `App\Http\Controllers\Teacher|Student`.
- `EnsureRole` runs **before** route-model binding (priority list): wrong role ⇒ always 403, never a 404 leak.
- `tests/Feature/RoleAccessTest.php` walks **every** route tagged `role:*` — new routes are covered automatically; keep them inside those groups.
- Group membership / ownership checks (rule 9, 12) go in Policies, on top of the role middleware.
- Flash messages: `->with('success'|'error', 'Message en français')`, displayed by `FlashMessage.vue`.
- Errors 403/404/429 (and 500/503 when `APP_DEBUG=false`) render `pages/Error.vue` in French; 419 redirects back with a message.
- Shared Inertia props expose only explicit user fields (`HandleInertiaRequests`), never the model.
- Login throttling: `focusia.login_max_attempts` per e-mail+IP (LoginRequest) + `throttle:auth` 20/min per IP on login, register, forgot-password.
- Password rule: min 8, letters + numbers (`AppServiceProvider`). French translations come from `laravel-lang/common` (`lang/fr`).

## Design system (set up in phase 2 — every new screen must use it)

- Palette (inspired by the Djibouti flag) in `resources/css/app.css`: `primary` sky blue, `success` green, `warning` amber,
  `destructive` red; light + dark themes. Radius `0.75rem`, cards `rounded-2xl border bg-card`. System fonts only (no web font).
- Page skeleton: `AppLayout` → `PageContainer` (width, padding, entrance) → `PageHeader` (title, description, back link, `#actions`).
- Phones: bottom bar `MobileNav` (items from `composables/useNavigation.ts`, shared with the sidebar); touch targets ≥ 44 px (`h-11`).
- Reusable: `EmptyState`, `StatCard` (animated count-up), `ConfirmDialog` (every destructive action), `CopyButton`,
  `NativeSelect` (native select = best on phones), `FlashMessage`.
- Motion is **CSS only** (no animation library): `.animate-enter`, `.animate-stagger` + `style="--i: n"`, `.animate-pop`,
  `.press`, `.lift`; buttons scale on press. `prefers-reduced-motion` disables everything globally.
- Ziggy `route()` params: always named objects, e.g. `route('teacher.groups.show', { group: id })` (typecheck requires it).

## Data model (see `database/migrations`)

`institutions` · `users` (first_name, last_name, role teacher|student, institution_id) · `groups` (access_code 6 chars unique) ·
`enrollments` (unique group+student, `removed_at` keeps history) · `notions` (unique teacher+subject+name) ·
`quizzes` (due_at, corrections_at, published_at null = draft) · `questions` (notion_id, position) ·
`choices` (position 0..3 CHECK, unique per question, generated column `correct_for_question` unique ⇒ max 1 correct) ·
`group_quiz` · `attempts` (unique quiz+student; started_at server, answered_at device, synced_at server; score /20) ·
`attempt_answers` · `materials` + `group_material` · `assignments` (material_id null on delete) + `assignment_group` ·
`submissions` (unique assignment+student; CHECK file or body; CHECK grade 0..20) · `resources` + `group_resource`.

## The 20 business rules (each must be covered by tests)

Groups and quizzes
1. A teacher may have several groups; a student may belong to several groups.
2. A quiz may be assigned to several groups.
3. A student takes a given quiz **only once** (DB unique).
4. Each question has **exactly 4 choices and one correct answer**.
5. Each question is worth 1 point and is linked to a notion.
6. A quiz has a due date; the final grade is always **out of 20**.
7. The student gets the grade at the end; correct answers stay **hidden until corrections_at** (the server never sends them before).
8. Offline answers are accepted **if given before the due date**, even if synced later.

Materials, homework, library
9. A teacher only edits/deletes their own content.
10. A material has a file, a link, or both (at least one).
11. A material/homework/resource may target several groups, never twice the same (unique pair).
12. A student sees content only if published in one of their groups, and **only once** if published in several.
13. Homework has a due date; the linked material is optional.
14. One submission per student per homework, replaceable until the due date; file, text, or both.
15. After the due date, submission refused unless the teacher allows late work; then flagged late.
16. Homework grade entered by the teacher, 0 to 20, decimals allowed, optional comment.
17. Quiz grades and homework grades stay **separate**; no combined average.
18. A student removed from a group loses access to its content; results and submissions are **kept**.
19. Deleting a material linked to homework does not delete the homework; only the link disappears.
20. Accepted files: PDF, doc, docx, xls, xlsx, ppt, pptx, odt, jpg, png, zip; **10 MB max** (configurable); check the real type.

Formulas: quiz grade = correct / questions × 20, rounded to 2 decimals (6/10 → 12; 18/25 → 14.4).
Notion success rate = correct answers on the notion / answers given on it.
Student in difficulty: quiz average < 10 **or** rate < 50 % on at least one notion (configurable).

Homework states (derived, never stored — `App\Support\SubmissionState`): `A_RENDRE` → `RENDU` → `NOTE`;
`EN_RETARD` if submitted after the due date with late allowed; `NON_RENDU` when the due date passed with no submission and no late allowance.

## Decisions taken (2026-11-25)

- **Offline answer acceptance (rule 8)**: device clocks can be changed, so an offline attempt is accepted only if
  (a) the quiz was downloaded before `due_at` (`started_at` = server time), (b) `started_at ≤ answered_at ≤ due_at`,
  (c) with a duration: `answered_at − started_at ≤ duration + FOCUSIA_QUIZ_GRACE_SECONDS`.
- **Removed student** cannot re-join with the code; only the teacher can reinstate them.
- **Quiz with attempts**: questions/choices locked; dates and groups still editable; duplicate to change content.
- One default institution (id 1) created by migration; users and groups attach to it automatically.
