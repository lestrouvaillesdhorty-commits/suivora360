# Déployer Suivora360 sur ton hébergement IONOS

Ce guide suppose : hébergement Web IONOS avec PHP et MySQL, accès SFTP,
et un accès à phpMyAdmin (ou "Bases de données" dans My IONOS). Pas besoin de
SSH ni de Composer — tous les fichiers sont prêts à l'emploi.

## 1. Créer la base de données MySQL

Dans My IONOS → Hébergement → Bases de données → Créer une base de données.
Note bien : le nom de la base, l'utilisateur, le mot de passe, et l'hôte
(souvent quelque chose comme `dbXXXXXXXXX.hosting-data.io`).

## 2. Envoyer les fichiers via SFTP

Avec un client SFTP (FileZilla, Cyberduck, ou l'explorateur de fichiers de
My IONOS), envoie **tout le contenu de ce dossier** vers ton espace web,
de façon à ce que le dossier `public/` de Suivora360 corresponde à la racine
de ton domaine `suivora360.com` (souvent un dossier `htdocs` ou similaire
chez IONOS — vérifie dans My IONOS → Hébergement → FTP, SSH & fichiers Web
quel dossier correspond à la racine de suivora360.com).

Concrètement :
- Le contenu de `public/` (index.php, install.php, assets/) va dans la racine web
- Les dossiers `app/`, `views/`, `database/` vont **juste au-dessus** de cette racine
  (pas accessibles directement depuis le navigateur — c'est voulu, pour la sécurité)

Si ton hébergement ne permet pas de sortir du dossier web public, dis-le-moi :
il existe une solution alternative (tout mettre dans le même dossier avec
protection par .htaccess).

## 3. Configurer le fichier .env

Renomme `.env.example` en `.env` et remplis :

```
DB_CONNECTION=mysql
DB_HOST=<hôte fourni par IONOS>
DB_DATABASE=<nom de la base>
DB_USERNAME=<utilisateur>
DB_PASSWORD=<mot de passe>
INSTALL_TOKEN=<invente une longue chaîne aléatoire, garde-la secrète>
APP_KEY=<invente une autre longue chaîne aléatoire>
```

## 4. Lancer l'installation

Ouvre dans ton navigateur :
`https://suivora360.com/install.php?token=LE_TOKEN_QUE_TU_AS_MIS_DANS_.ENV`

Remplis le formulaire (nom de l'organisation, première filiale, ton compte
dirigeant). Les tables sont créées automatiquement.

## 5. Supprimer install.php

**Étape de sécurité importante** : une fois l'installation terminée, supprime
le fichier `install.php` de ton hébergement (via SFTP). Sinon n'importe qui
connaissant le token pourrait relancer l'installation.

## 6. Se connecter

`https://suivora360.com/login` avec l'email et le mot de passe du compte
dirigeant créé à l'étape 4.

---

## Pour ajouter tes filiales et utilisateurs

Une fois connectée en tant que dirigeante :
- **Filiales** (menu Administration) : ajoute Millenium et tes autres entités (5 maximum)
- **Utilisateurs & accès** : crée les comptes de tes collaborateurs, en cochant
  les filiales auxquelles chacun a accès

## En cas de problème

Si une page affiche une erreur générique, tu peux temporairement passer
`APP_DEBUG=true` dans `.env` pour voir le message d'erreur précis — à remettre
sur `false` une fois le problème réglé (pour ne pas exposer de détails
techniques publiquement).
