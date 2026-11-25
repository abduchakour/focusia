# FocusIA

Plateforme web de quiz et de suivi pédagogique pour les universités et instituts d'enseignement supérieur de Djibouti.
Les enseignants créent des groupes, des quiz corrigés automatiquement (note sur 20), des supports, des devoirs et une
bibliothèque ; les résultats sont transformés en **suivi de la compréhension, notion par notion**. Les étudiants peuvent
répondre aux quiz même avec une connexion intermittente.

> État : **V1 terminée** (phases 0 à 9). Pour présenter le projet : **[DEMO.md](DEMO.md)**.

## Fonctionnalités

- **Enseignant** : groupes avec code d'accès, quiz (4 propositions, 1 bonne réponse, chaque question liée à une notion),
  notions, résultats détaillés (répartition des notes, erreurs fréquentes), suivi de la compréhension par notion,
  étudiants en difficulté, supports de cours, bibliothèque, devoirs avec notation sur 20 et commentaire.
- **Étudiant** : rejoindre un groupe avec un code, passer les quiz **même hors connexion** (application installable),
  note immédiate, corrections après la date prévue, supports, bibliothèque, devoirs (fichier et/ou texte).
- **Sécurité** : rôles et appartenance aux groupes vérifiés à chaque requête, Argon2id, limitation des tentatives,
  fichiers privés au type réel contrôlé, liens de téléchargement signés et personnels, en-têtes de sécurité.

## Technologies (100 % gratuites)

Laravel 12 (PHP 8.3) · Inertia + Vue 3 + TypeScript · Tailwind CSS · MariaDB (XAMPP) · PWA (vite-plugin-pwa, Dexie) ·
PHPUnit · Playwright (Edge déjà installé) · GitHub Actions · Cloudflare Tunnel gratuit pour la démo sur téléphone.

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

# 5. Lancer l'application
composer start   # léger : fichiers déjà compilés (démo, PC avec peu de mémoire)
composer dev     # développement : rechargement automatique avec Vite (plus gourmand)
```

Après toute modification de l'interface, `npm run build` met à jour les fichiers utilisés par `composer start`.

L'application est disponible sur **http://127.0.0.1:8001**.

## Comptes de démonstration

Mot de passe pour tous les comptes : **`Demo2026!`**

| Rôle | E-mail | Contenu |
| --- | --- | --- |
| Enseignante | `amina.hassan@focusia.test` | 2 groupes d'Algorithmique (L1 TD1, TD2), 4 notions, 4 quiz, 1 devoir |
| Enseignant | `omar.ali@focusia.test` | 1 groupe de Bases de données (L2), 2 notions, 1 quiz, 1 devoir |
| Étudiants | `etudiant01@focusia.test` … `etudiant30@focusia.test` | Inscrits dans les groupes, avec des résultats variés |

Pour créer un **compte enseignant** depuis la page d'inscription, il faut le code `FOCUSIA_TEACHER_CODE` du fichier `.env`
(`PROF-DJIB-2026` en démo). Les étudiants n'ont besoin d'aucun code.

Codes d'accès des groupes : `ALGO1A` (L1 TD1), `ALGO2B` (L1 TD2), `BDD2L2` (L2).
Remettre la démo à zéro : `composer demo`.

## Commandes utiles

| Commande | Rôle |
| --- | --- |
| `composer start` | Lance l'application compilée (port 8001), le plus léger pour une démo |
| `composer dev` | Lance le serveur (port 8001) et Vite |
| `composer telephone` | Ouvre l'application en HTTPS sur Internet (tunnel Cloudflare gratuit) pour les téléphones |
| `composer backup` | Sauvegarde la base et les fichiers dans `backups/` (les 10 dernières sont gardées) |
| `composer demo` | Recrée toutes les tables et les données de démonstration |
| `php artisan test` | Lance les tests (base `focusia_test`) |
| `vendor/bin/pint` | Met en forme le code PHP |
| `npm run lint` / `npm run format` | ESLint / Prettier |
| `npm run typecheck` | Vérification TypeScript |
| `npm run build` | Construit les fichiers front et le service worker (mode hors ligne) |
| `npm run test:e2e` | Test du mode hors ligne dans Edge (après `npm run build`, base séparée `focusia_e2e`, port 8002) |

## Configuration (`.env`)

| Variable | Rôle | Défaut |
| --- | --- | --- |
| `FOCUSIA_TEACHER_CODE` | Code à donner aux enseignants pour créer leur compte (vide = inscription enseignant fermée) | `PROF-DJIB-2026` en démo |
| `FOCUSIA_FILES_DISK` | Disque privé des fichiers (`local` ou `s3`) | `local` |
| `FOCUSIA_MAX_UPLOAD_KB` | Taille maximale d'un fichier (Ko) | `10240` (10 Mo) |
| `FOCUSIA_SIGNED_URL_MINUTES` | Durée de validité d'un lien de téléchargement | `10` |
| `FOCUSIA_DIFFICULTY_AVERAGE` | Moyenne en dessous de laquelle un étudiant est « en difficulté » | `10` |
| `FOCUSIA_DIFFICULTY_NOTION_RATE` | Taux de réussite minimal par notion | `0.5` |
| `FOCUSIA_QUIZ_GRACE_SECONDS` | Tolérance sur la durée d'un quiz passé hors ligne | `60` |
| `FOCUSIA_LOGIN_MAX_ATTEMPTS` | Tentatives de connexion par minute | `5` |

## Mise en ligne gratuite

Guide pas à pas : **[DEPLOIEMENT.md](DEPLOIEMENT.md)** (alwaysdata, offre Free).

## Mise en ligne sur un vrai serveur (plus tard)

- `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true` ;
- `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`,
  `php artisan optimize` ;
- servir le dossier `public/` en HTTPS (obligatoire pour le mode hors ligne) ;
- fichiers sur un stockage S3 compatible (Cloudflare R2, Backblaze B2) : `FOCUSIA_FILES_DISK=s3` + variables `AWS_*` ;
- sauvegarde quotidienne : tâche planifiée Windows qui lance `composer backup`.
