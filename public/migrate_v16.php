<?php

/**
 * Migration V16 — Suivora360
 * ---------------------------------------------------------------
 * Report dans le vrai code de la carte "Évaluation du besoin — visite
 * terrain" de la maquette Dossiers (ExecutionPrestation.dc.html) et de la
 * carte "Besoin structuré" (Besoin.dc.html), demandé explicitement par
 * Marie Laure le 06/10 pour que le vrai code suive fidèlement le visuel
 * validé. Ajoute 6 colonnes, strictement additives :
 *
 * - dossiers.prestation_site (VARCHAR, nullable) — lieu d'intervention.
 * - dossiers.prestation_technicien (VARCHAR, nullable) — technicien assigné.
 * - dossiers.prestation_delai_estime (VARCHAR, nullable) — texte libre
 *   (ex. "3 jours d'intervention"), pas une durée structurée.
 * - dossiers.prestation_constat (TEXT, nullable) — constat de visite.
 * - dossiers.prestation_contraintes (VARCHAR, nullable) — contraintes
 *   d'intervention propres au site (distinct des "contraintes" de
 *   qualification de la Demande, affichées sur l'onglet Besoin via
 *   demandes.qualification_notes — deux champs différents malgré le nom
 *   proche, l'un décrit le besoin client, l'autre l'intervention).
 * - demande_articles.caracteristiques (VARCHAR, nullable) — colonne
 *   "Caractéristiques" du tableau Articles de la maquette Besoin.dc.html,
 *   absente jusqu'ici (seules désignation/quantité/unité/conditionnement/
 *   référence/marque existaient).
 *
 * Ne supprime et ne modifie aucune donnée existante. Idempotente (vérifie
 * l'existence de chaque colonne avant de l'ajouter), peut être relancée
 * sans risque.
 *
 * Protégée par le même jeton que l'installation (INSTALL_TOKEN dans .env).
 * Après usage, supprimez ce fichier de l'hébergement.
 */

use App\Core\Database;
use App\Core\Env;

require __DIR__ . '/../app/autoload.php';
Env::load(__DIR__ . '/../.env');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$expected = Env::get('INSTALL_TOKEN', '');

if ($expected === '' || $expected === 'change-moi-avant-mise-en-ligne') {
    http_response_code(403);
    die("Sécurité : INSTALL_TOKEN doit être défini dans .env avant de lancer la migration.");
}
if (!hash_equals($expected, (string) $token)) {
    http_response_code(403);
    die("Jeton incorrect. Vérifiez l'URL utilisée.");
}

$pdo = Database::connection();
$driver = Database::driver();

function colonneExiste(\PDO $pdo, string $driver, string $table, string $colonne): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            if ($col['name'] === $colonne) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $colonne]);
    return (bool) $stmt->fetch();
}

$colonnesAAjouter = [
    ['dossiers', 'prestation_site', "ALTER TABLE dossiers ADD COLUMN prestation_site VARCHAR(255)"],
    ['dossiers', 'prestation_technicien', "ALTER TABLE dossiers ADD COLUMN prestation_technicien VARCHAR(255)"],
    ['dossiers', 'prestation_delai_estime', "ALTER TABLE dossiers ADD COLUMN prestation_delai_estime VARCHAR(100)"],
    ['dossiers', 'prestation_constat', "ALTER TABLE dossiers ADD COLUMN prestation_constat TEXT"],
    ['dossiers', 'prestation_contraintes', "ALTER TABLE dossiers ADD COLUMN prestation_contraintes VARCHAR(255)"],
    ['demande_articles', 'caracteristiques', "ALTER TABLE demande_articles ADD COLUMN caracteristiques VARCHAR(255)"],
];

$log = [];
$ran = false;

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'migrer') {
    try {
        foreach ($colonnesAAjouter as [$table, $colonne, $sql]) {
            if (colonneExiste($pdo, $driver, $table, $colonne)) {
                $log[] = "OK (déjà présente) — $table.$colonne";
                continue;
            }
            $pdo->exec($sql);
            $log[] = "AJOUTÉE — $table.$colonne";
        }
        $ran = true;
    } catch (\Throwable $e) {
        $log[] = 'ERREUR : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Migration V16 — Suivora360</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f5f7; margin: 0; padding: 40px 20px; }
.card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
h1 { font-size: 20px; margin-top: 0; }
button { margin-top: 16px; padding: 12px 20px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
.note { font-size: 13px; color: #666; margin-top: 20px; line-height: 1.5; }
ul { font-size: 13px; line-height: 1.6; padding-left: 20px; }
li.err { color: #9b1c1c; }
</style>
</head>
<body>
<div class="card">
<h1>Migration V16 — Onglets Besoin et Exécution-Prestation, conformité à la maquette (6 colonnes)</h1>
<p style="font-size:14px;color:#555">
Ajoute <code>dossiers.prestation_site</code>, <code>prestation_technicien</code>, <code>prestation_delai_estime</code>,
<code>prestation_constat</code>, <code>prestation_contraintes</code> et <code>demande_articles.caracteristiques</code>.
Ne supprime et ne modifie <strong>aucune donnée existante</strong>, et peut être relancée sans risque.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v16.php</code> de votre hébergement.</div>
<?php else: ?>
<form method="post">
<input type="hidden" name="action" value="migrer">
<input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
<button type="submit">Lancer la migration</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
