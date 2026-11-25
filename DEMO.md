# Guide de démonstration FocusIA (jury)

Durée conseillée : **12 minutes**. Tout est gratuit ; l'application tourne sur l'ordinateur portable.

## La veille

1. Fermer les applications inutiles (le PC a 4 Go de mémoire) : garder un terminal et un navigateur.
2. Démarrer **MariaDB** dans XAMPP.
3. Dans le dossier du projet :

   ```bash
   composer backup     # sauvegarde de sécurité
   composer demo       # données de démonstration neuves (dates recalculées par rapport à aujourd'hui)
   npm run build       # interface à jour
   ```

4. Si la démo sur téléphone est prévue, installer une seule fois **cloudflared** (gratuit, sans compte) :
   `winget install --id Cloudflare.cloudflared`

## Le jour J (5 minutes avant)

1. Terminal 1 : `composer start` → l'application est sur **http://127.0.0.1:8001**.
2. Terminal 2 (démo téléphone) : `composer telephone` → recopier l'adresse `https://….trycloudflare.com`
   sur le téléphone, dans Chrome. Menu ⋮ → **Ajouter à l'écran d'accueil** : FocusIA s'installe comme une application.
3. Sur l'ordinateur : une fenêtre connectée en **enseignante**, une fenêtre privée en **étudiant**.

Comptes (mot de passe **`Demo2026!`**) : enseignante `amina.hassan@focusia.test`, étudiants `etudiant01@focusia.test`
à `etudiant30@focusia.test` (prendre `etudiant05` ou suivant pour un quiz jamais passé).

## Scénario

| Min | Écran | Ce qu'on montre | Phrase clé |
| --- | --- | --- | --- |
| 0 | Accueil public | Le problème : connexions instables, suivi difficile | « Pensé pour les téléphones d'entrée de gamme et les coupures de réseau. » |
| 1 | Enseignante → Accueil | Étudiants en difficulté, notions à retravailler (Boucles en orange), derniers quiz | « L'enseignant voit en 10 secondes où le groupe bloque. » |
| 2 | Groupes → TD1 → **Suivi** | Barres par notion, courbe des moyennes, étudiants à accompagner, **carte de compréhension** | « Chaque question est liée à une notion : la note devient un diagnostic. » |
| 4 | Quiz → **Nouveau quiz** | Groupes en un toucher, raccourcis de date, toucher la lettre de la bonne réponse, notion créée sans quitter l'éditeur | « Un quiz de 5 questions en moins de 5 minutes. » |
| 5 | Quiz 1 → **Résultats** | Répartition des notes, question par question, **« Erreur fréquente »** | « On sait quoi réexpliquer en classe. » |
| 6 | **Téléphone** étudiant → Quiz 4 | Commencer, répondre, puis **mode avion** ✈️ : continuer, recharger la page (les réponses restent), envoyer → « Réponses enregistrées sur ce téléphone » | « Sans réseau, rien n'est perdu. » |
| 8 | Téléphone : couper le mode avion | Envoi automatique → **note sur 20** animée ; corrections verrouillées jusqu'à la date prévue | « Le serveur corrige : les bonnes réponses ne sont jamais sur le téléphone avant l'heure. » |
| 9 | Supports / Devoirs | Déposer un PDF ; un devoir avec les états À rendre / Rendu / En retard / Noté ; noter 12,5 avec un commentaire | « Fichiers vérifiés sur leur vrai contenu, liens de téléchargement de 10 minutes. » |
| 11 | Conclusion | Sécurité, tests automatiques, coût zéro | voir ci-dessous |

### Arguments techniques (questions du jury)

- **Hors ligne** : application installable (PWA), réponses gardées dans le téléphone (IndexedDB), file d'envoi
  automatique. Une réponse donnée hors ligne est acceptée si elle a été donnée avant la date limite, même envoyée plus
  tard ; l'heure du téléphone est contrôlée (heure de départ fixée par le serveur, durée vérifiée).
- **Sécurité** : chaque requête vérifie le rôle **et** l'appartenance au groupe ; mots de passe Argon2id ; protection
  contre les tentatives de connexion répétées ; fichiers privés, type réel contrôlé, liens signés personnels.
- **Qualité** : 177 tests automatiques (les 20 règles métier), plus un test de bout en bout qui coupe vraiment le
  réseau dans le navigateur. Accessibilité, bonnes pratiques et SEO à 100 dans Lighthouse.
- **Coût** : uniquement des logiciels libres et gratuits (Laravel, Vue, MariaDB, Cloudflare Tunnel gratuit).

## Plan B

- **Pas d'Internet dans la salle** : tout se montre sur l'ordinateur. Le mode hors ligne se montre dans Edge ou Chrome :
  F12 → onglet **Réseau** → **Hors connexion**.
- **Écran blanc ou lenteur** : fermer d'autres applications, Ctrl+C dans le terminal puis `composer start`, et Ctrl+F5.
- **Données abîmées pendant les répétitions** : `composer demo` remet tout à neuf en quelques secondes.
