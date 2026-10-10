# Conventions Dendrila Forms WordPress

- Lire `jasmin-abernathy/repo-factory/AGENTS.md` avant modification.
- La logique de questions, de conditions et de validation appartient à `jasmin-abernathy/dendrila-forms`, pas au plugin WordPress. Ne jamais maintenir une seconde copie divergente de Core.php dans ce dépôt.
- L'artefact WordPress doit contenir le cœur libre figé à un SHA exact, avec ses obligations de licence AGPL respectées.
- Refuser par défaut les accès aux réponses et limiter la configuration aux comptes avec `manage_options`.
- N'ajouter aucun code de reprise, e-mail privé, témoignage ni identifiant personnel réel au dépôt.
- Ne pas publier de statistiques de réponses avant validation de la confidentialité et du risque de réidentification.
- Un commit n'est pas un déploiement : tester le WordPress pilote et la visibilité publique séparément.
- Maintenir l'UX accessible sans JavaScript, avec progression et retour, et étiqueter clairement le prototype alpha.
- Réunir les modifications cohérentes en un commit et éviter les workflows inutiles.
