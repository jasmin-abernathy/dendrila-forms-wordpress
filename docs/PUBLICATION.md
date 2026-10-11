# Liste de contrôle — ouverture publique et publication

## GitHub public (code source)

- [x] Licence incluse et documentation de sécurité disponible.
- [x] README et `readme.txt` mentionnent explicitement le statut **alpha** et les principales fonctionnalités réellement implémentées.
- [x] CI PHP présente ; le paquet WordPress est généré manuellement et ne remplace pas les sources.
- [ ] Faire une revue finale du dépôt **et de son historique**, notamment secrets, fichiers privés, exports, identifiants personnels, licences de dépendances. La seule présence de `.gitignore` ne garantit rien.
- [ ] Passer volontairement la visibilité GitHub à *public* depuis les paramètres du dépôt.

## WordPress en production / WordPress.org — étapes distinctes

- [ ] Installer l'archive **construite** sur un WordPress pilote isolé ; tester activation, désactivation, PHP, WordPress, rôles et erreurs PHP.
- [ ] Vérifier chaque fonction annoncée sur des pages réelles, au clavier, sur mobile et avec un lecteur d'écran.
- [ ] Tester les interactions avec l'hébergement, le cache, les constructeurs de pages et leurs versions ciblées.
- [ ] Auditer les permissions, les nonces, le stockage et les protections contre les abus.
- [ ] Exécuter WordPress Plugin Check et les contrôles éditoriaux du répertoire.
- [ ] Revoir `readme.txt` (section principale `Description`) pour rendre visibles les fonctionnalités effectivement disponibles avant chaque nouvelle version.
- [ ] Préparer la procédure de support, de correction et de déploiement de sécurité.

**Un dépôt GitHub public ne signifie ni plugin référencé sur WordPress.org, ni version stable, ni compatibilité garantie.**
