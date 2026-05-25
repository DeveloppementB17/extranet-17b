# Outil de recette (checklist)

Interface parallèle à l’extranet pour les chefs de projet : **https://…/recette**

## Utilisation

1. Ouvrir `/recette` (accès public, sans connexion extranet).
2. Créer une session : prénom + rôle de recette.
3. Cocher chaque point : **Non vérifié** (défaut), **En cours**, **Validé** + commentaire.
4. La sauvegarde est automatique (debounce) ; export JSON possible.
5. Suppression : bouton **Supprimer** sur la liste `/recette` ou en bas d’une session ouverte.

## Fichiers

| Fichier | Rôle |
|---------|------|
| `config/recette/checklist-definition.json` | Points à tester par section et rôle (versionné) |
| `var/recette/sessions/{uuid}.json` | Sessions enregistrées (non versionné, `var/` ignoré par git) |

## Modifier la checklist

Éditer `checklist-definition.json` : sections, `roles` au niveau section/item, libellés et détails.

Puis incrémenter `version` si besoin de tracer les changements côté sessions.

Les versions récentes incluent des micro-tests (✓ succès / ✗ échec attendu) : doublons email/slug, validations formulaires admin, création utilisateurs et entreprises.

## Activer / désactiver (sans retirer le code)

Variable d’environnement **`RECETTE_ENABLED`** :

| Valeur | Comportement |
|--------|----------------|
| `1` | `/recette` accessible |
| `0` | Toutes les URLs `/recette/*` renvoient **404** (module invisible) |

### Pendant la recette (prod)

Dans `.env.local` sur le serveur :

```dotenv
RECETTE_ENABLED=1
```

Puis :

```bash
php bin/console cache:clear --env=prod
```

### Après la recette (recommandé)

```dotenv
RECETTE_ENABLED=0
```

```bash
php bin/console cache:clear --env=prod
```

Les sessions JSON restent dans `var/recette/sessions/` (suppression manuelle optionnelle) :

```bash
rm -rf var/recette/sessions/*
```

Le code peut rester déployé : il ne sera plus joignable tant que `RECETTE_ENABLED=0`.

### Retrait complet (optionnel)

Si vous souhaitez supprimer le module du dépôt plus tard : retirer les dossiers `config/recette/`, `src/Recette/`, `src/Controller/Recette/`, `templates/recette/`, `assets/recette/`, `src/EventSubscriber/RecetteAccessSubscriber.php`, `config/packages/recette.yaml`, la règle `^/recette` dans `security.yaml`, et la variable dans `.env`.

## Sécurité

En production avec `RECETTE_ENABLED=1`, l’URL `/recette` est en `PUBLIC_ACCESS`. Préférer `RECETTE_ENABLED=0` dès la fin de recette, ou protection HTTP/IP en complément.
