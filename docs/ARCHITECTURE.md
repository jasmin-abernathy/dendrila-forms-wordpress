# Architecture

## Deux dépôts, une seule implémentation métier

- **Dendrila Forms** (dépôt canonique) : `src/Core.php`, et l'API `df_validate_form()`, `df_steps()`, `df_prune()`, `df_answer()`. Code AGPL.
- **Dendrila Forms WordPress** : interface d'administration, modèle JSON enregistré dans les options, table dédiée aux réponses, shortcode, cookies, nonces, cron et export privé.
- À la construction, GitHub Actions intègre les fichiers `Core.php` et les deux exemples JSON depuis le commit précis `335f92e01eada5cb9ee1dee71a974c841d310de4`. Il n'y a aucun chargement dynamique de code distant ni copie métier suivie dans le dépôt WordPress.

## Données

`dfw_forms` : option WordPress non autoloadée, max 30 définitions validées (48 Ko maximum chacune), état d'activation.

`wp_dendrila_form_entries` (préfixe WP variable) : `id` aléatoire, slug, SHA-256 du secret de reprise, réponses JSON, curseur, statut, compteur de révision, dates UTC. Aucun identifiant client ou adresse IP n'est stocké dans les réponses. Les transients de limitation de tentative sont indexés via HMAC de l'adresse IP serveur et ont une expiration de 10 minutes.

Le compteur de révision permet de rejeter les mises à jour concurrentes. Le formulaire JSON ne peut plus être modifié dès qu'il possède au moins une réponse (brouillon inclus), pour éviter qu'une question change pendant une réponse ; on doit alors dupliquer l'identifiant du modèle.

## Sécurité

- Admin : `manage_options` + nonce pour édition, suppression et export.
- Répondant : nonce public et secret privé 256 bits. Les nonces des visiteurs déconnectés ne sont pas une authentification personnelle.
- Aucun endpoint de consultation publique des réponses.
- Les réponses sont supprimables par leur titulaire, purgées via WP-Cron après inactivité, ou exportées uniquement via l'administration.
- Le plugin ne donne aucune permission WordPress supplémentaire à quiconque.

## Limites du lot

Éditeur de modèles JSON seulement ; pas encore d'éditeur visuel ni de bloc Gutenberg dédié, de rapports statistiques, d'interviews sur invitation, de pièces jointes, de validation rédactionnelle ou de traduction EN. Les sauvegardes et la configuration des caches sont à gérer côté hébergement.

Tests CI = syntaxe et contrat moteur, sans exécution d'un WordPress réel.
