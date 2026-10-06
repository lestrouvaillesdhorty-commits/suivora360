<?php

/**
 * Migration V11 — Suivora360
 * ---------------------------------------------------------------
 * Ajoute le champ "type de dossier" (section 9 de la feuille de route,
 * verrouillé 29/09, codé 02/10) : colonne `dossiers.type_dossier`
 * (rétro-remplie pour les dossiers déjà existants à partir de l'Activité
 * de leur demande d'origine), et les colonnes additionnelles sur `offres`
 * et `commandes` utilisées selon le type (Transport/Logistique,
 * Prestation entreprise). 100% additif : ne supprime et ne modifie jamais
 * de données existantes. Idempotente, peut être relancée sans risque.
 *
 * Protégée par le même jeton que l'installation (INSTALL_TOKEN dans .env).
 * Après usage, supprimez ce fichier de l'hébergement.
 */

use App\Core\Database;
use App\Core\Env;
use App\Models\Dossier;

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

function columnExists(\PDO $pdo, string $driver, string $table, string $column): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            if ($col['name'] === $column) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool) $stmt->fetch();
}

$log = [];

function addColumn(\PDO $pdo, string $driver, string $table, string $column, string $definition, array &$log): void
{
    if (columnExists($pdo, $driver, $table, $column)) {
        $log[] = "OK (déjà présente) — $table.$column";
        return;
    }
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
    $log[] = "AJOUTÉE — $table.$column";
}

$ran = false;

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'migrer') {
    try {
        addColumn($pdo, $driver, 'dossiers', 'type_dossier', "VARCHAR(30) NOT NULL DEFAULT 'autre'", $log);

        addColumn($pdo, $driver, 'offres', 'mode_transport', 'VARCHAR(100)', $log);
        addColumn($pdo, $driver, 'offres', 'perimetre_mission', 'TEXT', $log);

        addColumn($pdo, $driver, 'commandes', 'tracking_numero', 'VARCHAR(100)', $log);
        addColumn($pdo, $driver, 'commandes', 'date_transit_debut', 'DATE', $log);
        addColumn($pdo, $driver, 'commandes', 'date_transit_fin', 'DATE', $log);
        addColumn($pdo, $driver, 'commandes', 'livrables', 'TEXT', $log);

        // Rétro-remplissage : déduit le type de dossier de l'Activité de la
        // demande d'origine pour chaque dossier déjà existant (jamais de
        // valeur "au hasard" — 'autre' par défaut si l'activité est vide ou
        // non reconnue par le mapping).
        $stmt = $pdo->query(
            "SELECT d.id, de.activite
             FROM dossiers d
             INNER JOIN demandes de ON de.id = d.demande_id
             WHERE d.type_dossier = 'autre' OR d.type_dossier IS NULL OR d.type_dossier = ''"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE dossiers SET type_dossier = ? WHERE id = ?');
        $maj = 0;
        foreach ($rows as $row) {
            $type = Dossier::deduireType($row['activite'] ?? null);
            if ($type !== 'autre') {
                $update->execute([$type, $row['id']]);
                $maj++;
            }
        }
        $log[] = "RÉTRO-REMPLISSAGE — $maj dossier(s) existant(s) mis à jour depuis l'activité de leur demande (le reste conserve 'autre')";

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
<title>Migration V11 — Suivora360</title>
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
<h1>Migration V11 — Type de dossier</h1>
<p style="font-size:14px;color:#555">
Ajoute <code>dossiers.type_dossier</code> (rétro-rempli depuis l'Activité de la demande d'origine pour
les dossiers déjà existants), ainsi que <code>offres.mode_transport</code>, <code>offres.perimetre_mission</code>,
<code>commandes.tracking_numero</code>, <code>commandes.date_transit_debut</code>,
<code>commandes.date_transit_fin</code> et <code>commandes.livrables</code>. Ne supprime et ne modifie
<strong>aucune donnée existante</strong>, et peut être relancée sans risque si besoin.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php?r=dossiers">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v11.php</code> de votre hébergement.</div>
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
