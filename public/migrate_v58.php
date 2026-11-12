<?php

/**
 * Migration V58 — Suivora360
 * ---------------------------------------------------------------
 * Expéditions (groupage) : tables expeditions et expedition_reservations.
 * Strictement additive et idempotente. Protégée par INSTALL_TOKEN (.env). Après usage, supprimez ce fichier.
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
$log = [];
$ran = false;

function colonneExiste(\PDO $pdo, string $driver, string $table, string $colonne): bool
{
    if ($driver === 'sqlite') {
        foreach ($pdo->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            if ($col['name'] === $colonne) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    $stmt->execute([$table, $colonne]);
    return (bool) $stmt->fetch();
}

function tableExiste(\PDO $pdo, string $driver, string $table): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    }
    $stmt->execute([$table]);
    return (bool) $stmt->fetch();
}

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'migrer') {
    try {
        $id = Database::idColumnType();
        $engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        if (!tableExiste($pdo, $driver, 'expeditions')) {
            $pdo->exec("CREATE TABLE expeditions (
                id $id,
                filiale_id INT NOT NULL,
                reference VARCHAR(30) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'groupage',
                mode VARCHAR(20) NOT NULL DEFAULT 'maritime',
                origine VARCHAR(120) NOT NULL DEFAULT '',
                destination VARCHAR(120) NOT NULL DEFAULT '',
                partenaire VARCHAR(150) NOT NULL DEFAULT '',
                fournisseur_id INT NULL,
                date_depart VARCHAR(10) NULL,
                date_arrivee VARCHAR(10) NULL,
                cloture_reservations VARCHAR(10) NULL,
                capacite_volume DECIMAL(12,3) NULL,
                capacite_poids DECIMAL(12,3) NULL,
                cout_achat DECIMAL(14,2) NOT NULL DEFAULT 0,
                devise VARCHAR(10) NOT NULL DEFAULT 'EUR',
                numero_suivi VARCHAR(80) NOT NULL DEFAULT '',
                statut VARCHAR(12) NOT NULL DEFAULT 'ouverte',
                notes TEXT NULL,
                created_by INT NULL,
                created_at VARCHAR(19) NOT NULL
            )$engine");
            $log[] = 'AJOUTÉE — table expeditions';
        } else {
            $log[] = 'OK (déjà présente) — table expeditions';
        }
        if (!tableExiste($pdo, $driver, 'expedition_reservations')) {
            $pdo->exec("CREATE TABLE expedition_reservations (
                id $id,
                expedition_id INT NOT NULL,
                filiale_id INT NOT NULL,
                dossier_id INT NULL,
                client_id INT NULL,
                client_nom VARCHAR(150) NOT NULL DEFAULT '',
                type_marchandise VARCHAR(20) NOT NULL DEFAULT 'non_alimentaire',
                description VARCHAR(255) NOT NULL DEFAULT '',
                volume DECIMAL(12,3) NULL,
                poids DECIMAL(12,3) NULL,
                colis INT NULL,
                palettes INT NULL,
                tarification VARCHAR(10) NOT NULL DEFAULT 'forfait',
                tarif_unitaire DECIMAL(14,2) NOT NULL DEFAULT 0,
                prix_vente DECIMAL(14,2) NOT NULL DEFAULT 0,
                created_by INT NULL,
                created_at VARCHAR(19) NOT NULL
            )$engine");
            $log[] = 'AJOUTÉE — table expedition_reservations';
        } else {
            $log[] = 'OK (déjà présente) — table expedition_reservations';
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
<title>Migration V58 — Suivora360</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f5f7; margin: 0; padding: 40px 20px; }
.card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
h1 { font-size: 20px; margin-top: 0; }
button { margin-top: 18px; padding: 12px 20px; background: #0B7C8A; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
.note { font-size: 13px; color: #666; margin-top: 20px; line-height: 1.5; }
li.err { color: #9b1c1c; }
</style>
</head>
<body>
<div class="card">
<h1>Migration V58 — Expéditions</h1>
<p style="font-size:14px;color:#555">Ajoute les expéditions (groupage, conteneur complet) et leurs réservations clients. Aucune donnée existante n'est touchée. Peut être relancée sans risque.</p>
<?php if ($ran): ?>
<ul style="font-size:13px;line-height:1.6">
<?php foreach ($log as $line): ?><li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li><?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v58.php</code> de votre hébergement.</div>
<?php else: ?>
<?php foreach ($log as $line): ?><p class="err"><?= htmlspecialchars($line) ?></p><?php endforeach; ?>
<form method="post"><input type="hidden" name="action" value="migrer"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><button type="submit">Lancer la migration</button></form>
<?php endif; ?>
</div>
</body>
</html>
