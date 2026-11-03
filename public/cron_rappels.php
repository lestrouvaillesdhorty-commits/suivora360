<?php

/**
 * Rappels automatiques (cloche + e-mail) pour les dossiers dont l'échéance est dépassée ou proche,
 * sans attendre que l'utilisateur ouvre l'application. À appeler une fois par jour (tâche planifiée / cron IONOS) :
 *   - en ligne de commande : php /chemin/vers/suivora360/public/cron_rappels.php
 *   - ou par adresse : https://VOTRE-SITE/cron_rappels.php?token=VOTRE_CODE  (CRON_TOKEN du .env, sinon INSTALL_TOKEN)
 * Les e-mails ne partent que si MAIL_FROM_ADDRESS est renseigné dans le .env.
 */

use App\Core\Env;
use App\Services\Rappels;

require __DIR__ . '/../app/autoload.php';
Env::load(__DIR__ . '/../.env');

if (PHP_SAPI !== 'cli') {
    $attendu = Env::get('CRON_TOKEN', '') ?: Env::get('INSTALL_TOKEN', '');
    if ($attendu === '' || $attendu === 'change-moi-avant-mise-en-ligne' || !hash_equals($attendu, (string) ($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('Accès refusé.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}
echo 'OK — ' . Rappels::pourTous() . " utilisateur(s) traité(s)\n";
