# FocusIA

Plateforme web de quiz et de suivi pÃ©dagogique pour les universitÃ©s et instituts d'enseignement supÃ©rieur de Djibouti.
Les enseignants crÃ©ent des groupes, des quiz corrigÃ©s automatiquement (note sur 20), des supports, des devoirs et une
bibliothÃ¨que ; les rÃ©sultats sont transformÃ©s en **suivi de la comprÃ©hension, notion par notion**. Les Ã©tudiants peuvent
rÃ©pondre aux quiz mÃªme avec une connexion intermittente.

> Ã‰tat : **V1 en cours** â€” phases 0 (fondations) et 1 (authentification et rÃ´les) terminÃ©es.

## Technologies (100 % gratuites)

Laravel 12 (PHP 8.3) Â· Inertia + Vue 3 + TypeScript Â· Tailwind CSS Â· MariaDB (XAMPP) Â· PHPUnit Â· GitHub Actions.

## Installation (Windows)

PrÃ©requis :

- **PHP 8.3 ou plus** avec les extensions `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `intl`, `zip`, `gd`, `sodium`
  (le PHP 8.0 fourni avec XAMPP est trop ancien) ;
- **Composer** ;
- **Node.js 20 ou plus** ;
- **MariaDB / MySQL** (celui de XAMPP convient), dÃ©marrÃ©.

```bash
# 1. DÃ©pendances
composer install
npm install

# 2. Configuration
cp .env.example .env
php artisan key:generate

# 3. Bases de donnÃ©es (application + tests)
mysql -u root -e "CREATE DATABASE focusia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                  CREATE DATABASE focusia_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Tables + donnÃ©es de dÃ©monstration
composer demo

# 5. Lancer l'application (serveur PHP + Vite)
composer dev
```

L'application est disponible sur **http://localhost:8001**.

## Comptes de dÃ©monstration

Mot de passe pour tous les comptes : **`Demo2026!`**

| RÃ´le | E-mail | Contenu |
| --- | --- | --- |
| Enseignante | `amina.hassan@focusia.test` | 2 groupes d'Algorithmique (L1 TD1, TD2), 4 notions, 4 quiz, 1 devoir |
| Enseignant | `omar.ali@focusia.test` | 1 groupe de Bases de donnÃ©es (L2), 2 notions, 1 quiz, 1 devoir |
| Ã‰tudiants | `etudiant01@focusia.test` â€¦ `etudiant30@focusia.test` | Inscrits dans les groupes, avec des rÃ©sultats variÃ©s |

Codes d'accÃ¨s des groupes : `ALGO1A` (L1 TD1), `ALGO2B` (L1 TD2), `BDD2L2` (L2).
Remettre la dÃ©mo Ã  zÃ©ro : `composer demo`.

## Commandes utiles

| Commande | RÃ´le |
| --- | --- |
| `composer dev` | Lance le serveur (port 8001) et Vite |
| `composer demo` | RecrÃ©e toutes les tables et les donnÃ©es de dÃ©monstration |
| `php artisan test` | Lance les tests (base `focusia_test`) |
| `vendor/bin/pint` | Met en forme le code PHP |
| `npm run lint` / `npm run format` | ESLint / Prettier |
| `npm run typecheck` | VÃ©rification TypeScript |
| `npm run build` | Construit les fichiers front pour la production |

## Configuration (`.env`)

| Variable | RÃ´le | DÃ©faut |
| --- | --- | --- |
| `FOCUSIA_FILES_DISK` | Disque privÃ© des fichiers (`local` ou `s3`) | `local` |
| `FOCUSIA_MAX_UPLOAD_KB` | Taille maximale d'un fichier (Ko) | `10240` (10 Mo) |
| `FOCUSIA_SIGNED_URL_MINUTES` | DurÃ©e de validitÃ© d'un lien de tÃ©lÃ©chargement | `10` |
| `FOCUSIA_DIFFICULTY_AVERAGE` | Moyenne en dessous de laquelle un Ã©tudiant est Â« en difficultÃ© Â» | `10` |
| `FOCUSIA_DIFFICULTY_NOTION_RATE` | Taux de rÃ©ussite minimal par notion | `0.5` |
| `FOCUSIA_QUIZ_GRACE_SECONDS` | TolÃ©rance sur la durÃ©e d'un quiz passÃ© hors ligne | `60` |
| `FOCUSIA_LOGIN_MAX_ATTEMPTS` | Tentatives de connexion par minute | `5` |
