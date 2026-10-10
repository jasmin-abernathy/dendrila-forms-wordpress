# Dendrila Forms pour WordPress 🌱

Extension libre qui adapte le moteur [Dendrila Forms](https://github.com/jasmin-abernathy/dendrila-forms) à WordPress.

**0.1.0-alpha : prototype de démonstration, pas prêt pour une collecte de données personnelles ou sensibles.** Les interviews privées, pièces jointes, autorisations éditoriales, statistiques publiques et éditeur visuel ne sont pas encore disponibles.

## Ce qui fonctionne dans le premier lot

- Administration réservée à `manage_options`.
- Création et configuration d'enquêtes et d'interviews à partir d'exemples JSON ; validation par le moteur commun.
- Activation explicite de chaque formulaire et shortcode `[dendrila_forms id="mon-enquete"]`.
- Parcours sans JavaScript obligatoire : une question à la fois, conditions, retour, progression, relecture.
- Sauvegarde en table WordPress dédiée ; reprise via un secret aléatoire dans un cookie HttpOnly et un code affiché ; seul le hachage du secret est en base.
- Envoi verrouillé, suppression par la personne détentrice du code et export JSON privé des réponses **terminées** par l'administration (500 maximum par export).
- Limitation indicative de tentatives de création et reprise, purge quotidienne via WP-Cron des enregistrements inactifs après 30 jours (valeur filtrable).
- Aucun traceur ni service tiers obligatoire, aucune publication automatique de réponses.

## Installation et essai

**N'installez pas l'archive GitHub Source code.** Le moteur n'est pas dupliqué dans le dépôt : il est intégré uniquement au ZIP construit. Le workflow **ZIP installable WordPress** est déclenché manuellement depuis GitHub Actions et produit `dendrila-forms-wordpress.zip`.

Pour construire localement, cloner également `dendrila-forms` au commit `335f92e01eada5cb9ee1dee71a974c841d310de4`, copier `src/Core.php` et les JSON de `forms/` sous `vendor/dendrila-forms/`, puis exécuter `bash scripts/package.sh`. Le ZIP contient une seule racine `dendrila-forms-wordpress/`.

Compatibilité visée : **WordPress 6.5+ et PHP 8.1+**. Installation réelle non encore validée.

1. Sur un WordPress de test, activer le plugin.
2. Dans **Dendrila Forms**, choisir d'abord « Exemple d’enquête » ou « Exemple d’interview ».
3. Adapter l'identifiant et le JSON ; cocher **Autoriser les réponses** et enregistrer.
4. Copier le shortcode affiché et le coller dans une **page WordPress publiée**.
5. Exclure cette page du cache pleine page et du CDN. Sans cela, le code de reprise et les états de réponse pourraient être exposés entre visiteurs.
6. Parcourir l'exemple fictif, quitter/reprendre, soumettre et tester la suppression. Vérifier en admin que l'export est inaccessible à un autre rôle.

L'export fournit uniquement les réponses soumises et n'exporte **jamais** les codes de reprise ni leur empreinte. Conserver le JSON exporté de manière privée ; il peut contenir des données saisies par les répondants.

## Réutilisation du moteur

Le code canonique de validation/conditions/réponses est dans `jasmin-abernathy/dendrila-forms`. Un commit précis est intégré au ZIP **à la construction**, jamais téléchargé à l'exécution. WordPress possède son propre stockage, ses nonces, ses permissions et ses tâches programmées. Voir [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Tests

`php tests/smoke.php` après installation de la dépendance figée. GitHub Actions vérifie PHP 8.1 et 8.3. Ces tests **ne remplacent pas** un essai sur une installation WordPress ni un audit de sécurité.

## Sécurité et confidentialité

Lire [SECURITY.md](SECURITY.md) avant tout déploiement. Les nonces WordPress d'une personne non connectée ne constituent pas une authentification individuelle ; le code aléatoire est le secret réel pour accéder au brouillon. L'export est réservé à l'administration. Les pages contenant ce shortcode ne doivent jamais être mises en cache de manière partagée.

Licence **AGPL-3.0-or-later** pour le plugin et le moteur embarqué. Aucun service distant requis.
