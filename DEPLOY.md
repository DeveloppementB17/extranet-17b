# Déploiement OVH mutualisé (Symfony 7 / PHP 8.3)

Ce projet est prévu pour un **hébergement OVH mutualisé** où la racine web pointe souvent vers le dossier du projet (et non vers `public/`).  
On place donc un **`.htaccess` à la racine** pour rediriger le trafic vers `public/index.php` tout en servant les fichiers statiques depuis `public/`.

## Structure conseillée côté serveur OVH

Exemple (à adapter à ton offre OVH) :

```text
/home/<login>/
├─ www/                       # Racine du site (DocumentRoot OVH)
│  ├─ .htaccess               # (fourni par le projet) réécrit vers /public
│  ├─ public/                 # Front controller Symfony + assets publics
│  ├─ vendor/                 # Dépendances PHP (si déploiement sans Composer serveur)
│  ├─ var/                    # cache/log (doit être writable)
│  ├─ storage/                # uploads privés (doit être writable)
│  └─ ...                     # le reste du projet (src/, config/, templates/, etc.)
└─ logs/ (optionnel, OVH)
```

Points importants :
- **`storage/` ne doit pas être exposé** via une URL directe.
- Vérifier les droits d’écriture sur `var/` et `storage/` (au minimum `var/cache`, `var/log`, `storage/uploads`).

## Variables d’environnement (production)

Sur mutualisé, le plus simple est de créer un fichier **`.env.local` en production** (non versionné) et/ou d’utiliser des variables d’environnement OVH si disponibles.

Exemple minimal `.env.local` (production) :

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=change-me

DATABASE_URL="mysql://db_user:db_pass@db_host:3306/db_name?serverVersion=8.0&charset=utf8mb4"
MAILER_DSN="smtp://user:pass@smtp.provider.tld:587"

STORAGE_PATH="/home/<login>/www/storage/uploads"
```

## Après chaque déploiement (commandes)

### Cache et migrations

```bash
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

### Assets (Asset Mapper + Tailwind)

Selon ton workflow, exécuter avant l’upload (recommandé) ou sur le serveur si possible :

```bash
php bin/console tailwind:build --minify
php bin/console asset-map:compile
```

## Déploiement sans accès SSH (FTP uniquement)

Approche recommandée : **build en local**, upload via FTP.

1. En local, installer les dépendances de prod :

```bash
composer install --no-dev --optimize-autoloader
```

2. En local, builder les assets :

```bash
php bin/console tailwind:build --minify
php bin/console asset-map:compile
```

3. Uploader via FTP dans `www/` :
- tout le projet, incluant `vendor/` (puisque pas de Composer côté serveur)
- `public/` (incluant les assets compilés)

4. Vérifier/poser le `.env.local` de prod sur le serveur.

5. Lancer les opérations post-déploiement :
- si OVH fournit une console PHP/commande planifiée, exécuter `cache:clear` et `migrations:migrate`
- sinon, prévoir une stratégie de migration (à définir plus tard) car Symfony/Doctrine nécessitent idéalement un accès CLI

## Déploiement avec accès SSH

1. Upload (rsync/scp/git pull selon ton setup).

2. Installer/mettre à jour les dépendances sur le serveur :

```bash
composer install --no-dev --optimize-autoloader
```

3. Builder les assets (si nécessaire) :

```bash
php bin/console tailwind:build --minify
php bin/console asset-map:compile
```

4. Exécuter les commandes post-déploiement :

```bash
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

## Checklist rapide

- `.htaccess` présent **à la racine** (pas seulement dans `public/`)
- `APP_ENV=prod`, `APP_DEBUG=0`
- `APP_SECRET` défini
- `DATABASE_URL` correct + accès MySQL OK
- `var/` et `storage/` writable
- `storage/uploads` **hors web**
- migrations appliquées

