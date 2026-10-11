# Sécurité, statut alpha et signalement

**Ne pas déployer pour une vraie enquête sensible.** Le noyau PHP et le plugin WordPress doivent être audités en environnement pilote avant toute collecte. Les formulaires actifs sont publics et ne disposent pas d'un système d'invitation nominative.

Le secret de reprise est généré aléatoirement. Seule son empreinte est stockée en base ; il est transmis en cookie HttpOnly avec SameSite=Lax et Secure lorsque WordPress détecte HTTPS. Sa possession permet la lecture et la suppression des réponses correspondantes. Si la page est servie via un cache partagé, ce mécanisme ne suffit pas : **désactiver impérativement ce cache pour les pages contenant le shortcode**. Le plugin émet des en-têtes anti-cache avant le rendu lorsque la page est détectable, mais un cache frontal agissant avant WordPress peut quand même servir un contenu privé ; l'exclusion explicite reste obligatoire.

Les formulaires sont configurables uniquement par des personnes disposant de `manage_options`. L'export privé exige la même capacité et un nonce ; les hashes des codes ne sont pas inclus dans le téléchargement. L'export se limite aux 500 réponses terminées les plus récentes et indique `more_available` pour éviter de confondre ce lot avec un export exhaustif. La suppression individuelle est accessible depuis le questionnaire tant que le code est disponible.

Une limitation légère utilise des transients WordPress indexés par une empreinte de l'adresse IP serveur. Elle n'offre pas une protection contre les attaques réparties et ne permet pas de se substituer à un WAF ou à un contrôle côté reverse proxy. WP-Cron purge les données inactives à 30 jours par défaut ; WP-Cron nécessite du trafic ou un déclencheur système, et les sauvegardes de la base doivent suivre une politique distincte.

Les pages de formulaires sont à exclure du cache HTML de WordPress, de l'hébergeur, d'un CDN, d'un reverse proxy et de toute optimisation qui met les réponses en cache. Pas de fuite intentionnelle des données par appel externe.

## Signalement

Utiliser les avis de sécurité privés GitHub (Security Advisories), lorsqu'ils sont disponibles. Ne jamais publier un exemple de réponse réel, un code de reprise ni des données personnelles dans une issue publique.

## Avant production

- Essai WordPress réel avec PHP 8.1+, divers thèmes et environnements d'hébergement.
- Tests fonctionnels mobile, clavier et lecteur d'écran.
- Test complet TLS, redirections, cookies, CDN et plugins de cache.
- Authenticité des invitations, droits de relecture, export/purge/audit.
- Informations de confidentialité, base légale, hébergement et sauvegardes.
- Protections anti-abus renforcées, vérification approfondie des droits et de l'échappement.
