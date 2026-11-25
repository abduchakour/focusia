# FocusIA

Plateforme web de quiz et de suivi pédagogique pour les universités et instituts d'enseignement supérieur de Djibouti.
Les enseignants créent des groupes, des quiz corrigés automatiquement (note sur 20), des supports, des devoirs et une
bibliothèque ; les résultats sont transformés en **suivi de la compréhension, notion par notion**. Les étudiants peuvent
répondre aux quiz même avec une connexion intermittente.

> État : **V1 en cours** — phases 0 à 2 terminées (fondations, authentification, groupes) + base du design.

## Technologies (100 % gratuites)

Laravel 12 (PHP 8.3) · Inertia + Vue 3 + TypeScript · Tailwind CSS · MariaDB (XAMPP) · PHPUnit · GitHub Actions.

## Installation (Windows)

Prérequis :

- **PHP 8.3 ou plus** avec les extensions `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `intl`, `zip`, `gd`, `sodium`
  (le PHP 8.0 fourni avec XAMPP est trop ancien) ;
- **Composer** ;
- **Node.js 20 ou plus** ;
- **MariaDB / MySQL** (celui de XAMPP convient), démarré.

```bash
# 1. Dépendances
composer install
npm install

# 2. Configuration
cp .env.example .env
php artisan key:generate

# 3. Bases de données (application + tests)
mysql -u root -e "CREATE DATABASE focusia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                  CREATE DATABASE focusia_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Tables + données de démonstration
composer demo

# 5. Lancer l'application (serveur PHP + Vite)
composer dev
```

L'application est disponible sur **http://localhost:8001**.

## Comptes de démonstration

Mot de passe pour tous les comptes : **`Demo2026!`**

| Rôle | E-mail | Contenu |
| --- | --- | --- |
| Enseignante | `amina.hassan@focusia.test` | 2 groupes d'Algorithmique (L1 TD1, TD2), 4 notions, 4 quiz, 1 devoir |
| Enseignant | `omar.ali@focusia.test` | 1 groupe de Bases de données (L2), 2 notions, 1 quiz, 1 devoir |
| Étudiants | `etudiant01@focusia.test` … `etudiant30@focusia.test` | Inscrits dans les groupes, avec des résultats variés |

Codes d'accès des groupes : `ALGO1A` (L1 TD1), `ALGO2B` (L1 TD2), `BDD2L2` (L2).
Remettre la démo à zéro : `composer demo`.

## Commandes utiles

| Commande | Rôle |
| --- | --- |
| `composer dev` | Lance le serveur (port 8001) et Vite |
| `composer demo` | Recrée toutes les tables et les données de démonstration |
| `php artisan test` | Lance les tests (base `focusia_test`) |
| `vendor/bin/pint` | Met en forme le code PHP |
| `npm run lint` / `npm run format` | ESLint / Prettier |
| `npm run typecheck` | Vérification TypeScript |
| `npm run build` | Construit les fichiers front pour la production |

## Configuration (`.env`)

| Variable | Rôle | Défaut |
| --- | --- | --- |
| `FOCUSIA_FILES_DISK` | Disque privé des fichiers (`local` ou `s3`) | `local` |
| `FOCUSIA_MAX_UPLOAD_KB` | Taille maximale d'un fichier (Ko) | `10240` (10 Mo) |
| `FOCUSIA_SIGNED_URL_MINUTES` | Durée de validité d'un lien de téléchargement | `10` |
| `FOCUSIA_DIFFICULTY_AVERAGE` | Moyenne en dessous de laquelle un étudiant est « en difficulté » | `10` |
| `FOCUSIA_DIFFICULTY_NOTION_RATE` | Taux de réussite minimal par notion | `0.5` |
| `FOCUSIA_QUIZ_GRACE_SECONDS` | Tolérance sur la durée d'un quiz passé hors ligne | `60` |
| `FOCUSIA_LOGIN_MAX_ATTEMPTS` | Tentatives de connexion par minute | `5` |
