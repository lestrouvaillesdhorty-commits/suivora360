# Suivora360 — document de structure pour l'audit

Version du 02/11/2026 — branche `dossiers-onglets-etapes-1-5` du dépôt GitHub `lestrouvaillesdhorty-commits/suivora360`.
But : donner à la personne qui audite une vue honnête de ce qui existe, de ce qui est protégé et de ce qui reste fragile.

## 1. Vue d'ensemble
- Application web **PHP sans framework** (architecture MVC maison), routage par `index.php?r=…` (`public/index.php`, ~190 routes), vues PHP.
- Base **MySQL/MariaDB** en production (SQLite pour les tests locaux). Accès par PDO, requêtes préparées.
- Dossiers : `app/Core` (Auth, Permissions, Tenant, Router, Storage, Env…), `app/Controllers` (24), `app/Models` (30), `app/Services` (PDF, messages, IA, e-mail, parcours), `views/`, `database/schema.sql`, `public/migrate_vNN.php` (migrations additives idempotentes), `storage/` (fichiers téléversés, hors racine web).
- Aucune dépendance externe (pas de Composer) : PDF généré par `PdfSimple`, e-mail par `mail()`, IA par appel HTTP direct à l'API Anthropic (facultatif).
- Hébergement actuel : IONOS mutualisé, HTTPS actif depuis le 02/11/2026. Cible : serveur dédié, un sous-domaine par entreprise (voir `INSTALLATION-SERVEUR.md`).

## 2. Modèle de données (38 tables)
- **Multi-entreprise** : `organisations` (entreprise cliente, `sous_domaine`, abonnement) → `filiales` → `utilisateurs` (+ `utilisateur_filiales` pour l'accès par filiale).
- **Demande / besoin** : `demandes`, `demande_articles`, `demande_pieces_jointes`.
- **Tiers** : `clients` (+ contacts, adresses, pièces, `client_portail_liens`), `fournisseurs` (+ contacts, adresses, pièces, qualifications, évaluations).
- **Dossier** (cœur du métier) : `dossiers` (étape : qualifié → sourcing → cotation → commande → livraison → clôturé ; statut actif / annulé / clôturé), `dossier_pieces_jointes`, `dossier_collaborateurs`, `dossier_budget_lignes`.
- **Achats** : `consultations_fournisseur`, `consultation_partages` (liens publics par jeton), `offres`, `offre_items`, `bons_commande_fournisseur` (+ lignes).
- **Vente** : `cotations`, `cotation_items`, `commandes`, `commande_steps`, `factures`.
- **Transverse** : `parametres`, `compteurs` (numérotation), `audit_logs`, `notifications`, `tentatives_connexion`.
- Chaque ligne métier porte `filiale_id` ; la filiale appartient à UNE organisation.

## 3. Isolation entre entreprises
- Règle : un utilisateur n'atteint que les données des filiales auxquelles il a accès, dans son organisation (`Filiale::userCanAccess`, `Permissions::seesAllFiliales`).
- Chaque page vérifie l'accès à l'objet demandé (`userCanAccess` du modèle concerné) avant affichage ou action.
- `app/Core/Tenant.php` : tout identifiant reçu en formulaire (client, fournisseur, offre, cotation, commande, utilisateur, responsable) est validé comme appartenant à la même filiale / au même dossier avant usage.
- Audit d'isolation fait le 07/10 (fuites corrigées). À refaire par le développeur sur l'ensemble des routes.
- **Sous-domaine** (nouveau) : chaque entreprise a son adresse de connexion ; une session n'est acceptée que sur l'adresse de l'entreprise (`Auth::refusSousDomaine`). Ce n'est pas la barrière de sécurité principale (c'est l'appartenance du compte), c'est une défense en profondeur et une séparation des sessions.
- Administrateur Suivora (`is_super_admin`) : voit la liste des entreprises et leurs compteurs, jamais leurs données ; pas de « connexion en tant que ».

## 4. Rôles et droits
Six rôles par entreprise (`app/Core/Permissions.php`, source de vérité) : Propriétaire, Admin d'organisation, Achats, Commercial, Finance, Lecture seule.
- Propriétaire et Admin d'organisation voient toutes les filiales de l'entreprise et gèrent utilisateurs et filiales.
- Valider une offre (Comparateur) : Propriétaire, Achats. Créer une cotation : Propriétaire, Admin, Commercial, Finance, Achats.
- Marge : visible par Propriétaire, Admin, Finance, Achats, Commercial ; modifiable par Propriétaire, Admin, Finance, Achats ; le Commercial peut la modifier seulement si la filiale l'autorise (paramètre).
- Données financières clients/fournisseurs : réservées à certains rôles (onglets Finances).
- Lecture seule : aucune action d'écriture (`Auth::requireWrite`).
- Suppression d'une consultation : libre tant qu'elle n'est pas envoyée, administrateur seulement ensuite, jamais s'il existe des offres.

## 5. Parcours métier
Demande (qualification, articles, pièces) → Dossier → Consultation fournisseurs (message texte FR/EN, lien public, PDF, WhatsApp / e-mail) → Offres (saisie, lecture IA facultative) → Comparateur (retenir / écarter) → Cotation client (marge) → Commande (suivi par étapes) → Factures → Clôture.
Parcours guidé (02/11) : bandeau « Prochaine action » (`ProchaineAction`) ; passage automatique d'étape sur consultation envoyée, offre retenue et cotation acceptée (`Dossier::avancerA`, jamais en arrière, tracé dans `audit_logs`).
Accès externes sans compte : lien fournisseur (`/partage-public/{jeton}`), espace client (`/espace-client/{jeton}`) — jetons aléatoires de 192 bits, limités dans le temps, révocables, donnant accès uniquement aux éléments choisis.

## 6. Mesures de sécurité en place
- Mots de passe : `password_hash`, minimum 8 caractères, mot de passe provisoire à changer à la première connexion.
- Limitation des tentatives : 5 échecs / 15 min par e-mail, 20 par IP ; nouvel identifiant de session à chaque connexion.
- Sessions : cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS ; compte désactivé ou entreprise suspendue = accès coupé immédiatement.
- CSRF : jeton vérifié sur les actions POST (voir limites ci-dessous).
- Injection SQL : requêtes préparées PDO ; échappement HTML systématique dans les vues (`View::e`, ~1 070 usages ; 4 usages `innerHTML` à relire).
- Fichiers : stockés hors racine web (`storage/`), nom aléatoire, liste d'extensions autorisées, 10 Mo maximum, téléchargement uniquement via un contrôleur qui vérifie les droits, `X-Content-Type-Options: nosniff`.
- Erreurs masquées en production (`APP_DEBUG=false`) ; `.env` interdit d'accès par `.htaccess`.
- Journal d'audit des actions sensibles (`audit_logs`) ; suspension / réactivation d'une entreprise par l'administrateur Suivora.
- HTTPS actif, redirection forcée `http` → `https`.

## 7. Points faibles connus — à auditer en priorité
1. **Pas de contraintes de clés étrangères** en base et peu d'index (9) : l'intégrité repose sur le code ; performances à vérifier avec du volume.
2. **Authentification route par route** (pas de refus par défaut) : 176 des ~190 routes appellent `requireLogin` / `requireSuperAdmin` ; 12 sont publiques volontairement (connexion, liens fournisseur et client). Une nouvelle route oubliée serait publique. Recommandation : refus par défaut dans le routeur avec liste blanche explicite.
3. **CSRF** : 112 routes POST. Une vérification automatique (relecture du code) trouve `logout` sans jeton (risque faible) ; les autres routes sans vérification visible déléguent à des fonctions internes, à confirmer une par une.
4. **Aucun test automatisé** (ni unitaire ni d'intégration). Les tests ont été faits à la main et par navigateur piloté.
5. **En-têtes de sécurité globaux absents** : pas de CSP, HSTS, X-Frame-Options, Referrer-Policy (seulement `nosniff` sur les téléchargements). À poser au niveau du serveur web.
6. **Téléversements validés par l'extension seulement** (pas de contrôle du contenu réel / type MIME, pas d'antivirus).
7. **Migrations et installation accessibles par le web** (`install.php`, `migrate_*.php`, protégés par un jeton `INSTALL_TOKEN`) : doivent être supprimés après usage ; sur un serveur dédié, préférer une exécution en ligne de commande.
8. **Pas de limitation de débit** sur les liens publics (jetons de 192 bits, donc non devinables, mais pas de blocage en cas d'abus) ni sur les appels IA.
9. **E-mail** : fonction `mail()` simple, sans SMTP authentifié ni suivi ; l'envoi depuis Suivora est désactivé si `MAIL_FROM_ADDRESS` est vide (l'utilisateur envoie depuis sa propre messagerie). Un SMTP par entreprise n'existe pas.
10. **IA (Anthropic)** : le contenu des documents lus par l'IA est envoyé à un service externe — point RGPD à documenter et à faire accepter ; clé API unique dans `.env`.
11. **Données** : adresse e-mail d'utilisateur unique à l'échelle globale (pas par entreprise) ; trous possibles dans la numérotation entre filiales d'une même entreprise ; référentiels (incoterms, modes de paiement, statuts) communs à toutes les entreprises, écrits dans le code.
12. **Sauvegardes, supervision, journaux d'erreurs** : rien n'est prévu dans l'application (à mettre en place côté serveur).
13. **Sessions** : stockage PHP par défaut ; sur plusieurs serveurs, prévoir un stockage partagé.
14. **Dette technique** : contrôleurs volumineux (jusqu'à près de 1 500 lignes), vues mêlant PHP, HTML et JavaScript, noms de colonnes en français, quelques fonctions dupliquées entre clients et fournisseurs.

## 8. Ce qui reste à construire (hors audit)
Rangement des modules Offres / Comparateur / Cotations dans le dossier, qualification guidée en un clic, lecture des PDF d'offres sans IA, SMTP par entreprise, facturation et abonnements automatisés (aujourd'hui suivi manuel), onboarding autonome.

## 9. Pour démarrer l'audit
- Lire `INSTALLATION-SERVEUR.md` (installation, DNS, certificat, `.env`) et `DEPLOIEMENT.md`.
- Cloner le dépôt, créer une base MySQL, lancer `install.php` puis les migrations jusqu'à v31.
- Comptes de test : créer une entreprise via « Administration Suivora » avec deux entreprises pour tester l'isolation.
