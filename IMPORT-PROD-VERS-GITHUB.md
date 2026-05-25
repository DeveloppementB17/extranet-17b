# Import de l'etat production vers un nouveau depot GitHub

Commit serveur : `f1e7a20` (branche `prod/ovh-sync-2026-05-25`)

## Contenu du snapshot

- Refontes CSS/Twig (templates, `assets/styles/app.css`, `app.custom.scss`)
- Correctifs ModSecurity OVH (routes `/activate`, CSRF, `WEB_PROFILER_ENABLED`)
- Script `watch-full-css.sh`
- **Non inclus** : `.env.local`, `storage/`, `vendor/`, `node_modules/`

## Etape 1 — Creer le depot vide sur GitHub

Organisation : `DeveloppementB17`  
Nom suggere : `extranet-17b-prod-ovh`  
Visibilite : private  
Ne pas initialiser avec README.

## Etape 2 — Recuperer le bundle depuis le serveur OVH

```bash
scp agence-b17.dev:/var/www/vhosts/agence-b17.dev/extranet-17b-prod-ovh.bundle ~/Downloads/
```

(Adapter l'utilisateur SSH si besoin.)

## Etape 3 — Cloner le bundle en local

```bash
cd /Users/guillaumebex/WEB/17b-extranet
git clone ~/Downloads/extranet-17b-prod-ovh.bundle extranet-17b-prod-ovh
cd extranet-17b-prod-ovh
git branch -m main
```

## Etape 4 — Pousser vers le nouveau depot

```bash
git remote add origin git@github.com:DeveloppementB17/extranet-17b-prod-ovh.git
git push -u origin main
```

## Alternative — Mettre a jour le depot existant

```bash
cd /Users/guillaumebex/WEB/17b-extranet/extranet-17b
git fetch ~/Downloads/extranet-17b-prod-ovh.bundle prod/ovh-sync-2026-05-25:prod/ovh-sync-2026-05-25
git checkout prod/ovh-sync-2026-05-25
```

Puis merger dans `develop` apres revue.

## .env.local en local

```dotenv
WEB_PROFILER_ENABLED=1
```

(Sur OVH : `WEB_PROFILER_ENABLED=0`)
