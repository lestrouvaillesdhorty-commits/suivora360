<?php

/**
 * Migration V9 — Suivora360
 * ---------------------------------------------------------------
 * Formalise la suite du parcours métier : Comparateur détaillé (coût
 * rendu, conformité, conditions), avancement/relance de la Commande, et
 * le parcours "Demander une offre" (récapitulatif + lien fournisseur
 * sécurisé + WhatsApp). 100% additif : ne supprime et ne modifie jamais
 * de données existantes. Idempotente, peut être relancée sans risque.
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
        $offreColumns = [
            'pays_origine' => 'VARCHAR(100)',
            'lieu_depart' => 'VARCHAR(150)',
            'quantite_min' => 'VARCHAR(100)',
            'disponibilite' => 'VARCHAR(150)',
            'poids_kg' => 'DECIMAL(10,2)',
            'nombre_colis' => 'INT',
            'volume_m3' => 'DECIMAL(10,3)',
            'conformite_technique' => 'VARCHAR(20)',
            'conditions_paiement' => 'VARCHAR(30)',
            'garantie' => 'VARCHAR(150)',
            'transport_montant' => 'DECIMAL(12,2)',
            'assurance_montant' => 'DECIMAL(12,2)',
            'emballage_montant' => 'DECIMAL(12,2)',
            'douane_montant' => 'DECIMAL(12,2)',
            'dedouanement_montant' => 'DECIMAL(12,2)',
            'autres_frais_montant' => 'DECIMAL(12,2)',
            'motif_decision' => 'TEXT',
        ];
        foreach ($offreColumns as $col => $def) {
            addColumn($pdo, $driver, 'offres', $col, $def, $log);
        }

        $commandeColumns = [
            'prochaine_action' => 'VARCHAR(255)',
            'date_relance' => 'DATE',
        ];
        foreach ($commandeColumns as $col => $def) {
            addColumn($pdo, $driver, 'commandes', $col, $def, $log);
        }

        addColumn($pdo, $driver, 'parametres', 'diviseur_volumetrique_aerien', 'DECIMAL(10,2) NOT NULL DEFAULT 6000', $log);
        addColumn($pdo, $driver, 'parametres', 'diviseur_volumetrique_maritime', 'DECIMAL(10,2) NOT NULL DEFAULT 1000', $log);

        if (!tableExists($pdo, $driver, 'consultation_partages')) {
            $pdo->exec("CREATE TABLE consultation_partages (
                id $id,
                consultation_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                masquer_client TINYINT(1) NOT NULL DEFAULT 1,
                pieces_ids TEXT,
                echeance_reponse DATE,
                expire_le DATETIME NOT NULL,
                revoque_le DATETIME,
                marque_envoye_le DATETIME,
                nb_consultations INT NOT NULL DEFAULT 0,
                dernier_acces DATETIME,
                cree_par INT,
                created_at DATETIME NOT NULL
            )$engine");
            $log[] = 'CRÉÉE — table consultation_partages';
        } else {
            $log[] = 'OK (déjà présente) — table consultation_partages';
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
<title>Migration V9 — Suivora360</title>
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
<h1>Migration V9 — Comparateur détaillé, Commande, Demander une offre</h1>
<p style="font-size:14px;color:#555">
Ajoute des colonnes détaillées à la table <code>offres</code> (coût rendu, conformité, conditions...),
ajoute <code>prochaine_action</code> / <code>date_relance</code> à <code>commandes</code>, et crée la table
<code>consultation_partages</code> (liens sécurisés fournisseur). Ne supprime et ne modifie
<strong>aucune donnée existante</strong>, et peut être relancée sans risque si besoin.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php?r=dossiers">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v9.php</code> de votre hébergement.</div>
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
