<?php

/**
 * Migration V2 — Suivora360
 * ---------------------------------------------------------------
 * Ajoute, sur une base déjà installée, les tables et colonnes
 * nécessaires à la qualification en 3 voies + Clients/Fournisseurs.
 * 100% additif : ne supprime et ne modifie jamais de données
 * existantes. Peut être relancé sans risque (idempotent).
 *
 * Protégé par le même jeton que l'installation (INSTALL_TOKEN dans .env).
 * Après usage, supprimez ce fichier de l'hébergement comme install.php.
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
$id = Database::idColumnType();
$engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

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

function tableExists(\PDO $pdo, string $driver, string $table): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
    } else {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    }
    $stmt->execute([$table]);
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
        // ── Nouvelles colonnes sur demandes (qualification) ──────────────
        $demandesColumns = [
            'client_id' => 'INT',
            'destination_pays' => 'VARCHAR(100)',
            'qualification_type' => 'VARCHAR(30)',
            'qualification_status' => 'VARCHAR(30)',
            'qualification_notes' => 'TEXT',
            'qualified_at' => 'DATETIME',
            'qualified_by' => 'INT',
            'linked_request_id' => 'INT',
            'linked_dossier_id' => 'INT',
            'original_started_at' => 'DATE',
            'registered_in_suivora_at' => 'DATE',
            'external_reference' => 'VARCHAR(100)',
            'external_source' => 'VARCHAR(100)',
            'takeover_stage' => 'VARCHAR(50)',
            'historical_takeover' => "TINYINT(1) NOT NULL DEFAULT 0",
        ];
        foreach ($demandesColumns as $col => $def) {
            addColumn($pdo, $driver, 'demandes', $col, $def, $log);
        }

        // ── Nouvelles tables ──────────────────────────────────────────────
        if (!tableExists($pdo, $driver, 'clients')) {
            $pdo->exec("CREATE TABLE clients (
                id $id,
                filiale_id INT NOT NULL,
                nom VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                telephone VARCHAR(50),
                pays VARCHAR(100),
                ville VARCHAR(100),
                adresse VARCHAR(255),
                secteur VARCHAR(100),
                notes TEXT,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            )$engine");
            $log[] = 'CRÉÉE — table clients';
        } else {
            $log[] = 'OK (déjà présente) — table clients';
        }

        if (!tableExists($pdo, $driver, 'fournisseurs')) {
            $pdo->exec("CREATE TABLE fournisseurs (
                id $id,
                filiale_id INT NOT NULL,
                nom VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                telephone VARCHAR(50),
                pays VARCHAR(100),
                ville VARCHAR(100),
                adresse VARCHAR(255),
                devise VARCHAR(10),
                secteur VARCHAR(100),
                site_web VARCHAR(255),
                notes TEXT,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            )$engine");
            $log[] = 'CRÉÉE — table fournisseurs';
        } else {
            $log[] = 'OK (déjà présente) — table fournisseurs';
        }

        if (!tableExists($pdo, $driver, 'audit_logs')) {
            $pdo->exec("CREATE TABLE audit_logs (
                id $id,
                filiale_id INT NOT NULL,
                utilisateur_id INT,
                action VARCHAR(50) NOT NULL,
                entite_type VARCHAR(50) NOT NULL,
                entite_id INT,
                details TEXT,
                created_at DATETIME NOT NULL
            )$engine");
            $log[] = 'CRÉÉE — table audit_logs';
        } else {
            $log[] = 'OK (déjà présente) — table audit_logs';
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
<title>Migration V2 — Suivora360</title>
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
<h1>Migration V2 — qualification + clients/fournisseurs</h1>
<p style="font-size:14px;color:#555">
Cette migration ajoute des colonnes et des tables à votre base existante.
Elle ne supprime et ne modifie <strong>aucune donnée existante</strong>, et peut être relancée
sans risque si besoin.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php?r=demandes">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v2.php</code> de votre hébergement.</div>
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
