# Contribuer à Dendrila Forms WordPress

Merci pour les retours, les correctifs et les cas de test 🌱

## Avant une proposition

- Ouvrir une issue pour décrire le comportement observé, la version de WordPress/PHP et les étapes de reproduction, sans publier de données personnelles ni de secrets.
- Pour une faille de sécurité, utiliser les **GitHub Security Advisories** privés si disponibles, plutôt qu'une issue publique.
- Proposer des changements ciblés sur une branche et ouvrir une pull request. Documenter les cas non pris en charge et éviter de promettre une compatibilité non testée.
- Exécuter les vérifications PHP : `php tests/smoke.php` et `find . -name '*.php' -print0 | xargs -0 -n1 php -l`.
- Préserver les droits WordPress, les jetons de requêtes et les échappements des sorties HTML.

## Points importants

Le ZIP doit être construit depuis GitHub Actions : le dépôt ne versionne pas le moteur commun, qui est embarqué au packaging depuis un SHA figé. Respecter la licence AGPL-3.0-or-later du code commun et du plugin. Ne jamais collecter de vraies réponses dans les reproductions.

**Statut actuel : version alpha.** Un test automatisé vert ne remplace pas un essai sur un WordPress pilote ni une revue de sécurité avant utilisation réelle.

Licence et redistribution : consulter `LICENSE`.
