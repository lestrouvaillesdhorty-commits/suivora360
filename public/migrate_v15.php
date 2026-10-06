<?php

/**
 * Migration V15 — Suivora360
 * ---------------------------------------------------------------
 * Module Dossiers (report de la maquette complémentaire du 05-06/10,
 * cahier des charges section 9) : ajoute 4 colonnes, strictement
 * additives, pour la déclinaison "Prestation entreprise" de l'onglet
 * Exécution :
 *
 * - dossiers.type_prestation (VARCHAR, nullable) — sous-type de la
 *   prestation (phytosanitaire / maintenance / nettoyage / installation /
 *   autre), pertinent uniquement quand dossiers.type_dossier =
 *   'prestation_entreprise'. Voir Dossier::TYPES_PRESTATION.
 * - dossiers.visite_terrain_necessaire (TINYINT, nullable, défaut 1) —
 *   case à cocher décidée au cas par cas par l'équipe, jamais déduite
 *   automatiquement du type (cahier des charges : "sans rendre une visite
 *   terrain obligatoire pour toutes les entreprises").
 * - dossiers.prestation_mesure_1 / prestation_mesure_2 (VARCHAR, nullable)
 *   — les 2 champs de mesure dont le libellé s'adapte au type de
 *   prestation (voir Dossier::TYPES_PRESTATION_CHAMPS), colonnes
 *   génériques plutôt que dédiées par type (même esprit minimal que
 *   demandes.qualification_notes).
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
    ['dossiers', 'type_prestation', "ALTER TABLE dossiers ADD COLUMN type_prestation VARCHAR(30)"],
    ['dossiers', 'visite_terrain_necessaire', "ALTER TABLE dossiers ADD COLUMN visite_terrain_necessaire TINYINT(1) DEFAULT 1"],
    ['dossiers', 'prestation_mesure_1', "ALTER TABLE dossiers ADD COLUMN prestation_mesure_1 VARCHAR(255)"],
    ['dossiers', 'prestation_mesure_2', "ALTER TABLE dossiers ADD COLUMN prestation_mesure_2 VARCHAR(255)"],
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
<title>Migration V15 — Suivora360</title>
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
<h1>Migration V15 — Module Dossiers, Exécution Prestation (4 nouvelles colonnes)</h1>
<p style="font-size:14px;color:#555">
Ajoute <code>dossiers.type_prestation</code>, <code>dossiers.visite_terrain_necessaire</code>,
<code>dossiers.prestation_mesure_1</code> et <code>dossiers.prestation_mesure_2</code>. Ne supprime et ne modifie
<strong>aucune donnée existante</strong>, et peut être relancée sans risque.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v15.php</code> de votre hébergement.</div>
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
