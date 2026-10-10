<?php

/**
 * Migration V24 — Suivora360
 * ---------------------------------------------------------------
 * Bon de commande fournisseur (07/10) : tables bons_commande_fournisseur et
 * bon_commande_fournisseur_lignes. Strictement additive et idempotente : aucune donnée
 * existante n'est modifiée. Protégée par INSTALL_TOKEN (.env). Après usage, supprimez ce fichier.
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
        $engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        $tables = [
            'bons_commande_fournisseur' => 'CREATE TABLE bons_commande_fournisseur (
                id ' . Database::idColumnType() . ',
                filiale_id INT NOT NULL,
                fournisseur_id INT NOT NULL,
                dossier_id INT NULL,
                offre_id INT NULL,
                reference VARCHAR(30) NOT NULL,
                statut VARCHAR(15) NOT NULL DEFAULT \'brouillon\',
                date_emission DATE NOT NULL,
                date_livraison_souhaitee DATE NULL,
                devise VARCHAR(10) NOT NULL DEFAULT \'\',
                incoterm VARCHAR(10) NOT NULL DEFAULT \'\',
                lieu_livraison VARCHAR(255) NOT NULL DEFAULT \'\',
                conditions_paiement VARCHAR(255) NOT NULL DEFAULT \'\',
                reference_offre VARCHAR(60) NOT NULL DEFAULT \'\',
                notes TEXT NULL,
                notes_internes TEXT NULL,
                fournisseur_nom VARCHAR(255) NOT NULL DEFAULT \'\',
                fournisseur_adresse VARCHAR(500) NOT NULL DEFAULT \'\',
                destinataire_nom VARCHAR(200) NOT NULL DEFAULT \'\',
                destinataire_email VARCHAR(255) NOT NULL DEFAULT \'\',
                montant_total DECIMAL(14,2) NOT NULL DEFAULT 0,
                envoye_le DATETIME NULL,
                confirme_le DATETIME NULL,
                annule_le DATETIME NULL,
                annule_motif VARCHAR(255) NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )',
            'bon_commande_fournisseur_lignes' => 'CREATE TABLE bon_commande_fournisseur_lignes (
                id ' . Database::idColumnType() . ',
                bon_id INT NOT NULL,
                designation VARCHAR(255) NOT NULL,
                quantite DECIMAL(12,2) NULL,
                unite VARCHAR(20) NOT NULL DEFAULT \'\',
                prix_unitaire DECIMAL(14,2) NULL,
                montant DECIMAL(14,2) NULL,
                ordre INT NOT NULL DEFAULT 0
            )',
        ];
        foreach ($tables as $nom => $sql) {
            if (!tableExiste($pdo, $driver, $nom)) {
                $pdo->exec($sql . $engine);
                $log[] = "CRÉÉE — table $nom";
            } else {
                $log[] = "OK (déjà présente) — table $nom";
            }
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
<title>Migration V24 — Suivora360</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f5f7; margin: 0; padding: 40px 20px; }
.card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
h1 { font-size: 20px; margin-top: 0; }
button { margin-top: 18px; padding: 12px 20px; background: #2d18fa; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
.note { font-size: 13px; color: #666; margin-top: 20px; line-height: 1.5; }
li.err { color: #9b1c1c; }
</style>
</head>
<body>
<div class="card">
<h1>Migration V24 — Bon de commande fournisseur</h1>
<p style="font-size:14px;color:#555">Ajoute les tables du bon de commande fournisseur. Aucune donnée existante n'est modifiée. Peut être relancée sans risque.</p>
<?php if ($ran): ?>
<ul style="font-size:13px;line-height:1.6">
<?php foreach ($log as $line): ?><li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li><?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v24.php</code> de votre hébergement.</div>
<?php else: ?>
<?php foreach ($log as $line): ?><p class="err"><?= htmlspecialchars($line) ?></p><?php endforeach; ?>
<form method="post"><input type="hidden" name="action" value="migrer"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><button type="submit">Lancer la migration</button></form>
<?php endif; ?>
</div>
</body>
</html>
