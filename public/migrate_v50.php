<?php

/**
 * Migration V50 — Suivora360
 * ---------------------------------------------------------------
 * Documents : remises (lignes et globale) sur les devis et bons de commande, identité complète de l'entreprise (pays, forme juridique, capital, régime TVA, couleur, mise en page, mentions), suivi de facture (n° officiel + pièce jointe) ; idempotente. Protégée par INSTALL_TOKEN (.env). Après usage, supprimez ce fichier.
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
        $ajouts = [
            'entreprise_infos' => [['forme_juridique', 'VARCHAR(100) NULL'], ['capital', 'VARCHAR(60) NULL'], ['pays', 'VARCHAR(10) NULL'], ['regime_tva', 'VARCHAR(190) NULL'], ['couleur', 'VARCHAR(7) NULL'], ['mise_en_page', 'VARCHAR(20) NULL'], ['mentions_pied', 'TEXT NULL']],
            'cotations' => [['montant_brut', 'DECIMAL(14,2) NULL'], ['remise_lignes_montant', 'DECIMAL(14,2) NULL'], ['remise_globale_pct', 'DECIMAL(6,2) NULL'], ['remise_globale_montant', 'DECIMAL(14,2) NULL'], ['remise_motif', 'VARCHAR(255) NULL'], ['adresse_livraison', 'TEXT NULL']],
            'cotation_items' => [['remise_pct', 'DECIMAL(6,2) NULL']],
            'bon_commande_fournisseur_lignes' => [['remise_pct', 'DECIMAL(6,2) NULL']],
            'factures' => [['numero_officiel', 'VARCHAR(60) NULL'], ['fichier', 'VARCHAR(190) NULL'], ['fichier_nom', 'VARCHAR(255) NULL']],
        ];
        foreach ($ajouts as $table => $cols) {
            if (!tableExiste($pdo, $driver, $table)) {
                $log[] = "IGNORÉE — table $table absente";
                continue;
            }
            foreach ($cols as [$col, $def]) {
                if (!colonneExiste($pdo, $driver, $table, $col)) {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
                    $log[] = "AJOUTÉE — colonne $table.$col";
                } else {
                    $log[] = "OK (déjà présente) — colonne $table.$col";
                }
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
<title>Migration V50 — Suivora360</title>
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
<h1>Migration V50 — Objectifs par période</h1>
<p style="font-size:14px;color:#555">Ajoute les colonnes nécessaires aux remises, à l'identité de l'entreprise et au suivi de facture. Aucune donnée existante n'est modifiée. Peut être relancée sans risque.</p>
<?php if ($ran): ?>
<ul style="font-size:13px;line-height:1.6">
<?php foreach ($log as $line): ?><li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li><?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v50.php</code> de votre hébergement.</div>
<?php else: ?>
<?php foreach ($log as $line): ?><p class="err"><?= htmlspecialchars($line) ?></p><?php endforeach; ?>
<form method="post"><input type="hidden" name="action" value="migrer"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><button type="submit">Lancer la migration</button></form>
<?php endif; ?>
</div>
</body>
</html>
