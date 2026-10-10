=== Dendrila Forms – Enquêtes & Interviews ===
Contributors: juliane16
Tags: survey, questionnaire, interview, privacy, accessibility
Requires at least: 6.5
Requires PHP: 8.1
License: AGPLv3 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

Prototype alpha de questionnaires et d'interviews guidés pour WordPress.

== Description ==

Dendrila Forms accompagne les enquêtes et entretiens éditoriaux sur WordPress.

Ce prototype alpha permet de créer des modèles JSON de questionnaires à partir d'exemples, de les valider, de les activer explicitement et de les afficher via shortcode. La personne répondante progresse question par question, peut revenir en arrière, conserver un code de reprise, relire puis terminer. Elle peut supprimer ses réponses. L'administration peut exporter un lot limité de réponses terminées en JSON. Les réponses ne sont jamais publiées automatiquement. Pas de traçage tiers.

**Avertissement** : version expérimentale uniquement, sans invitations authentifiées, statistiques publiques, éditeur visuel ni tests sur WordPress en conditions réelles. Ne pas recueillir de données personnelles ou sensibles. Exclure les pages de réponse du cache public.

== Installation ==

Installer le ZIP généré par le workflow manuel GitHub, et non l'archive du dépôt source. Ouvrir Dendrila Forms depuis l'administration puis créer un exemple. Ajouter le shortcode fourni à une page publiée sans cache partagé.

== Frequently Asked Questions ==

= Les réponses sont-elles publiques ? =

Non. Seule l'administration peut exporter les réponses soumises. Le code de reprise permet de retrouver ou supprimer un questionnaire précis.

= Peut-on interviewer des personnes identifiées ? =

Pas encore. Le système d'invitation et les autorisations éditoriales sont prévus pour une version future.

= Le formulaire est-il compatible avec les caches ? =

Pas avec les caches pleine page partagés. Excluez les pages contenant le shortcode.

== Changelog ==

= 0.1.0-alpha =
* Administration des modèles JSON et shortcode.
* Navigation, reprise, sauvegarde et suppression privées.
* Export administrateur, limite de tentatives et purge WP-Cron.
