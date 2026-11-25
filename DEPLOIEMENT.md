# Mettre FocusIA en ligne gratuitement (alwaysdata)

Hébergeur choisi : **alwaysdata**, offre **Free** : 0 €/mois, sans limite de durée, 1 Go de disque, 256 Mo de mémoire.
PHP, MariaDB et le HTTPS sur l'adresse `https://VOTRE-COMPTE.alwaysdata.net` sont fournis.
Le HTTPS est indispensable pour le mode hors ligne et l'installation sur téléphone.

> À vérifier lors de l'inscription, car l'offre peut évoluer : l'accès SSH sur l'offre Free et l'absence de carte
> bancaire demandée. Le nom de domaine personnalisé n'est pas disponible en Free : on garde l'adresse `.alwaysdata.net`.

Dans ce guide, remplacez `focusia` par le nom de **votre** compte alwaysdata.

## 1. Créer le compte et la base (5 minutes, dans le navigateur)

1. Sur <https://www.alwaysdata.com/fr/>, inscrivez-vous et choisissez l'offre **Free**. Nommez le compte `focusia` :
   l'adresse du site sera `https://focusia.alwaysdata.net`.
2. Menu **Bases de données → MariaDB → Ajouter une base** : nom `focusia_app`. Créez aussi un **utilisateur** avec un
   mot de passe fort, et donnez-lui tous les droits sur cette base. Notez l'hôte indiqué (`mysql-focusia.alwaysdata.net`).
3. Menu **Accès distant → SSH** : activez la connexion par mot de passe pour l'utilisateur SSH, et notez le mot de passe.
4. Menu **Environnement → PHP** : choisissez **PHP 8.3** ou plus récent.

## 2. Installer le code (terminal de votre PC)

```bash
ssh focusia@ssh-focusia.alwaysdata.net

# sur le serveur :
git clone https://github.com/abduchakour/focusia.git
cd focusia
composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env        # voir l'étape 3
php artisan key:generate
php artisan migrate --seed --force   # tables + données de démonstration
php artisan optimize
exit
```

## 3. Le fichier `.env` en ligne

Modifiez ces lignes, et laissez les autres comme dans `.env.example` :

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://focusia.alwaysdata.net

DB_HOST=mysql-focusia.alwaysdata.net
DB_DATABASE=focusia_app
DB_USERNAME=focusia_utilisateur
DB_PASSWORD=le-mot-de-passe-de-la-base

SESSION_SECURE_COOKIE=true
FOCUSIA_TEACHER_CODE=UN-CODE-SECRET-A-DONNER-AUX-ENSEIGNANTS
```

`APP_DEBUG=false` est **obligatoire** en ligne : sinon les erreurs afficheraient des informations sensibles.

## 4. Envoyer l'interface compilée (terminal de votre PC, dans le dossier du projet)

Le serveur gratuit est trop petit pour compiler l'interface : on la compile sur le PC et on l'envoie.

```bash
npm run build
scp -r public/build public/sw.js focusia@ssh-focusia.alwaysdata.net:focusia/public/
```

## 5. Brancher le site (dans le navigateur)

Menu **Web → Sites** : modifiez le site `focusia.alwaysdata.net` :

- **Type** : PHP ;
- **Racine de l'application** : `/focusia/public`. C'est important : seul le dossier `public` doit être visible ;
- onglet **SSL** : cochez **Forcer le HTTPS**.

Ouvrez `https://focusia.alwaysdata.net` : c'est en ligne ! Pour vous connecter, utilisez les comptes de démonstration,
mot de passe `Demo2026!`. **Changez-les ou supprimez-les avant d'inviter de vrais étudiants.**

## Mettre à jour après une modification

Sur le PC : `git push`, `npm run build`, puis la commande `scp` de l'étape 4. Ensuite, sur le serveur :

```bash
ssh focusia@ssh-focusia.alwaysdata.net
cd focusia && git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan optimize
```

## Bon à savoir

- **Sauvegardes** : alwaysdata garde 3 jours de sauvegardes. Téléchargez aussi un export de la base de temps en temps
  (menu Bases de données).
- **Fichiers déposés** (cours, devoirs) : ils comptent dans le 1 Go de disque. Pour la démo, c'est largement suffisant.
- **Photo locale de l'accueil** : le dossier `public/images/accueil/local` n'est jamais envoyé sur GitHub. En ligne,
  la page d'accueil affiche donc les photos libres de droits, ce qui est voulu.
