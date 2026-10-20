# Suivora360 — installation sur un serveur dédié, avec un sous-domaine par entreprise

Document destiné à la personne qui héberge l'application (changement d'hébergeur, audit).

## 1. Principe
- Une seule application, une seule base de données. Chaque entreprise cliente (organisation) a son sous-domaine :
  `ltdh.suivora360.com`, `beta.suivora360.com`…
- Le sous-domaine est l'**adresse de connexion** de l'entreprise : un compte ne peut se connecter que depuis l'adresse de son entreprise.
  L'administrateur Suivora (is_super_admin) se connecte sur l'adresse générale (`suivora360.com`, `www` ou `app`).
- Le sous-domaine n'est PAS ce qui sépare les données : l'isolation repose sur le compte → organisation → filiales (contrôles dans le code,
  voir `app/Core/Tenant.php`). Le sous-domaine apporte l'identité de marque, des sessions séparées par entreprise et une défense en profondeur
  (une session d'une entreprise est refusée sur l'adresse d'une autre).
- Les liens envoyés aux fournisseurs et aux clients (récapitulatif de consultation, espace client) restent sur l'adresse générale (`APP_URL`),
  volontairement : ils ne doivent pas révéler le nom de l'entreprise (option « masquer le client »).

## 2. Prérequis serveur
- PHP 8.1 ou plus (8.2 recommandé) avec : pdo_mysql, mbstring, json, fileinfo, openssl, curl (IA et lecture de sites), gd (facultatif).
- MySQL 8 ou MariaDB 10.6+ (utf8mb4). SQLite possible uniquement pour des tests.
- Serveur web : Nginx ou Apache. Racine du site = dossier `public/`. Le dossier `storage/` et le fichier `.env` doivent rester HORS de la racine web
  (ou inaccessibles) ; `storage/` doit être en écriture pour l'utilisateur du serveur web (documents téléversés).
- Le routage utilise `index.php?r=…` : aucune réécriture d'URL n'est nécessaire.
- Sortie réseau HTTPS vers api.anthropic.com si la fonction IA est utilisée (facultative).

## 3. DNS et certificat (le point clé des sous-domaines)
1. Enregistrement DNS joker : `*.suivora360.com  →  IP du serveur` (en plus de `suivora360.com` et `www`).
2. Certificat **joker** `*.suivora360.com` + `suivora360.com`. Avec Let's Encrypt : validation DNS-01 (certbot avec le plugin du fournisseur DNS,
   ou acme.sh). Un certificat par sous-domaine fonctionne aussi mais oblige à en émettre un à chaque nouvelle entreprise.
3. Un seul « virtual host » qui répond à tous les noms.

Nginx (exemple) :
```
server {
    listen 443 ssl http2;
    server_name suivora360.com *.suivora360.com;
    root /var/www/suivora360/public;
    index index.php;
    ssl_certificate     /etc/letsencrypt/live/suivora360.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/suivora360.com/privkey.pem;
    client_max_body_size 25m;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.2-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name suivora360.com *.suivora360.com; return 301 https://$host$request_uri; }
```
Apache : `ServerAlias *.suivora360.com` dans le même VirtualHost, `DocumentRoot …/public`, `AllowOverride All` (le `.htaccess` fourni protège `.env`).

## 4. Configuration (`.env`, jamais dans Git)
- `APP_URL=https://suivora360.com` (adresse générale)
- `BASE_DOMAIN=suivora360.com` (domaine sur lequel se placent les sous-domaines)
- `APP_DEBUG=false`, `DB_*` (MySQL), `INSTALL_TOKEN` (long, aléatoire), `APP_KEY` (long, aléatoire)
- `ANTHROPIC_API_KEY` (facultatif), `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` (facultatif ; envoi via mail() — pour un envoi fiable : SMTP du serveur avec SPF/DKIM/DMARC).
- Cookies de session : `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS ; sans attribut Domain → une session distincte par sous-domaine.

## 5. Base de données
- Nouvelle installation : `https://suivora360.com/install.php?token=…` puis les migrations `public/migrate_vNN.php?token=…` dans l'ordre
  (idempotentes, additives), puis **supprimer** `install.php` et tous les `migrate_*.php` de la racine web.
- Installation reprise depuis IONOS : exporter la base (mysqldump) et copier `storage/uploads/`, puis lancer les migrations manquantes (jusqu'à v31).
- Migration v31 : colonne `organisations.sous_domaine` (unique).

## 6. Mise en service d'une nouvelle entreprise (onboarding)
1. Se connecter sur l'adresse générale avec le compte administrateur Suivora → menu « Administration Suivora ».
2. « Créer une entreprise cliente » : nom, première filiale, Propriétaire, **sous-domaine**, mot de passe provisoire.
3. Avec un certificat joker et un DNS joker, aucune action serveur : l'adresse fonctionne immédiatement.
4. Transmettre au Propriétaire son adresse (`https://sous-domaine.suivora360.com`), son e-mail et son mot de passe provisoire (changé à la 1re connexion).
- Entreprises existantes sans sous-domaine : elles continuent de se connecter sur l'adresse générale tant que l'administrateur ne leur en attribue pas
  un (liste des entreprises → « Définir »). Dès qu'un sous-domaine est défini, la connexion depuis l'adresse générale est refusée avec un message
  indiquant la nouvelle adresse.
- Sous-domaines réservés : www, app, admin, api, mail, smtp, ftp, test, demo, suivora, support, aide, portail, static, cdn.

## 7. Sauvegardes et sécurité (à mettre en place côté serveur)
- Sauvegarde quotidienne de la base (mysqldump) et de `storage/uploads/`, copie hors serveur, test de restauration.
- Pare-feu (80/443 + SSH par clé), mises à jour système et PHP, accès base limité à localhost.
- HSTS et en-têtes de sécurité au niveau du serveur web ; limitation des tentatives de connexion déjà intégrée (5 échecs / 15 min par e-mail, 20 par IP).
