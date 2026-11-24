<?php

/**
 * Installateur de mise à jour — UN SEUL fichier à envoyer sur le serveur (dans le dossier public).
 * Ouvrez : /installer_maj.php?token=VOTRE_INSTALL_TOKEN , choisissez le zip, cliquez. Le fichier se supprime ensuite.
 * Utile pour la toute première mise à jour (ensuite, la page « Mise à jour de l'application » de Suivora360 suffit).
 */

use App\Core\Env;

require __DIR__ . '/../app/autoload.php';
Env::load(__DIR__ . '/../.env');

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$attendu = (string) Env::get('INSTALL_TOKEN', '');
if ($attendu === '' || $attendu === 'change-moi-avant-mise-en-ligne') {
    http_response_code(403);
    die("Sécurité : INSTALL_TOKEN doit être défini dans .env.");
}
if (!hash_equals($attendu, $token)) {
    http_response_code(403);
    die("Jeton incorrect. Ajoutez ?token=VOTRE_TOKEN à l'adresse.");
}

$lignes = [];
$erreur = '';
$fini = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(300);
    $f = $_FILES['zip'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $erreur = 'Aucun fichier reçu (ou trop gros : limite du serveur ' . ini_get('upload_max_filesize') . ').';
    } elseif (!class_exists('ZipArchive')) {
        $erreur = "L'extension Zip n'est pas disponible sur ce serveur.";
    } else {
        $zip = new ZipArchive();
        $cible = __DIR__ . '/../app/Services/MiseAJour.php';
        $ok = $zip->open($f['tmp_name']) === true;
        $prefixe = '';
        if ($ok && $zip->locateName('app/Services/MiseAJour.php') === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (preg_match('#^([^/]+)/app/Services/MiseAJour\.php$#', (string) $zip->getNameIndex($i), $m)) { $prefixe = $m[1] . '/'; break; }
            }
        }
        $code = $ok ? $zip->getFromName($prefixe . 'app/Services/MiseAJour.php') : false;
        if ($ok) { $zip->close(); }
        if ($code === false) {
            $erreur = "Ce zip n'est pas une mise à jour Suivora360 récente (outil de mise à jour introuvable dedans).";
        } else {
            file_put_contents($cible, $code);
            if (function_exists('opcache_invalidate')) { @opcache_invalidate($cible, true); }
            require_once $cible;
            $_SERVER['DOCUMENT_ROOT'] = __DIR__;
            $res = \App\Services\MiseAJour::installer($f['tmp_name']);
            $lignes = $res['lignes'];
            $erreur = $res['erreur'] ?? '';
            $fini = !empty($res['ok']);
            if ($fini) { @unlink(__FILE__); }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>Mise à jour — Suivora360</title><meta name="viewport" content="width=device-width, initial-scale=1">
<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f5f7;margin:0;padding:30px 16px}.c{max-width:620px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.08)}button{margin-top:16px;padding:12px 20px;background:#0B7C8A;color:#fff;border:0;border-radius:6px;font-size:15px;cursor:pointer}.e{color:#9b1c1c}.o{color:#166534}</style></head><body><div class="c">
<h1 style="font-size:20px;margin-top:0">Mise à jour de Suivora360</h1>
<?php if ($erreur): ?><p class="e"><strong><?= htmlspecialchars($erreur) ?></strong></p><?php endif; ?>
<?php foreach ($lignes as $l): ?><p style="margin:4px 0;font-size:14px"><?= htmlspecialchars($l) ?></p><?php endforeach; ?>
<?php if ($fini): ?>
  <p class="o"><strong>Mise à jour terminée.</strong> Ce fichier s'est supprimé tout seul. <a href="/index.php">Ouvrir l'application →</a></p>
<?php else: ?>
  <p style="font-size:14px;color:#555">Choisissez le fichier <strong>.zip</strong> de la mise à jour : il est installé tel quel, sans rien décompresser.</p>
  <form method="post" enctype="multipart/form-data" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Mise à jour en cours…'">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <input type="file" name="zip" accept=".zip" required><br>
    <button type="submit">Mettre à jour</button>
  </form>
<?php endif; ?>
</div></body></html>
