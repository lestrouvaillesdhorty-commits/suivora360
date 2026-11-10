<?php

/**
 * Migration V51 — Suivora360
 * ---------------------------------------------------------------
 * Motifs de perte modifiables par filiale (table motifs_perte, remplie avec les 8 motifs d'origine)
 * + motif de clôture d'un dossier sans commande (dossiers.motif_cloture, motif_cloture_detail, motif_cloture_le).
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
        if (!tableExiste($pdo, $driver, 'motifs_perte')) {
            $pdo->exec("CREATE TABLE motifs_perte (
                id $id,
                filiale_id INT NOT NULL,
                code VARCHAR(30) NOT NULL,
                libelle VARCHAR(120) NOT NULL,
                ordre INT NOT NULL DEFAULT 0,
                actif TINYINT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            )$engine");
            $log[] = 'AJOUTÉE — table motifs_perte';
        } else {
            $log[] = 'OK (déjà présente) — table motifs_perte';
        }
        foreach ([['motif_cloture', 'VARCHAR(30) NULL'], ['motif_cloture_detail', 'VARCHAR(300) NULL'], ['motif_cloture_le', 'DATETIME NULL']] as [$col, $def]) {
            if (!colonneExiste($pdo, $driver, 'dossiers', $col)) {
                $pdo->exec("ALTER TABLE dossiers ADD COLUMN $col $def");
                $log[] = "AJOUTÉE — colonne dossiers.$col";
            } else {
                $log[] = "OK (déjà présente) — colonne dossiers.$col";
            }
        }
        // Motifs d'origine pour chaque filiale qui n'en a pas encore.
        $origine = ['prix' => 'Prix trop élevé', 'delai' => 'Délai trop long', 'concurrent' => 'Concurrent choisi', 'qualite' => 'Produit ou qualité non adapté',
            'besoin' => 'Besoin changé ou annulé', 'abandon' => 'Projet abandonné ou reporté', 'sans_reponse' => 'Plus de réponse du client', 'autre' => 'Autre (préciser)'];
        $deja = $pdo->query('SELECT DISTINCT filiale_id FROM motifs_perte')->fetchAll(\PDO::FETCH_COLUMN);
        $ins = $pdo->prepare('INSERT INTO motifs_perte (filiale_id, code, libelle, ordre, actif, created_at) VALUES (?,?,?,?,1,?)');
        $n = 0;
        foreach ($pdo->query('SELECT id FROM filiales')->fetchAll(\PDO::FETCH_COLUMN) as $fid) {
            if (in_array($fid, $deja)) {
                continue;
            }
            $i = 0;
            foreach ($origine as $code => $lib) {
                $ins->execute([$fid, $code, $lib, ++$i, date('Y-m-d H:i:s')]);
            }
            $n++;
        }
        $log[] = "Motifs d'origine copiés pour $n filiale(s).";
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
<title>Migration V51 — Suivora360</title>
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
<h1>Migration V51 — Motifs de perte modifiables</h1>
<p style="font-size:14px;color:#555">Crée la liste des motifs de perte modifiable par filiale et le motif de clôture des dossiers sans commande. Aucune donnée existante n'est supprimée. Peut être relancée sans risque.</p>
<?php if ($ran): ?>
<ul style="font-size:13px;line-height:1.6">
<?php foreach ($log as $line): ?><li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li><?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v51.php</code> de votre hébergement.</div>
<?php else: ?>
<?php foreach ($log as $line): ?><p class="err"><?= htmlspecialchars($line) ?></p><?php endforeach; ?>
<form method="post"><input type="hidden" name="action" value="migrer"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><button type="submit">Lancer la migration</button></form>
<?php endif; ?>
</div>
</body>
</html>
