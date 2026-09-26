<?php

/**
 * Migration V7 — Suivora360
 * ---------------------------------------------------------------
 * Enrichit les fiches Client et Fournisseur (code auto, statut détaillé,
 * type, conditions de paiement, notation fournisseur, documents
 * catégorisés...). 100% additif : ne supprime et ne modifie jamais de
 * données existantes. Idempotente, peut être relancée sans risque.
 *
 * Protégée par le même jeton que l'installation (INSTALL_TOKEN dans .env).
 * Après usage, supprimez ce fichier de l'hébergement.
 */

use App\Core\Database;
use App\Core\Env;
use App\Models\Compteur;

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
        $clientColumns = [
            'code' => 'VARCHAR(20)',
            'statut' => "VARCHAR(20) NOT NULL DEFAULT 'actif'",
            'type' => 'VARCHAR(30)',
            'code_postal' => 'VARCHAR(20)',
            'adresse_livraison' => 'VARCHAR(255)',
            'siret' => 'VARCHAR(50)',
            'tva' => 'VARCHAR(50)',
            'incoterm_habituel' => 'VARCHAR(10)',
            'mode_transport_habituel' => 'VARCHAR(30)',
            'conditions_paiement' => 'VARCHAR(30)',
            'fonction_contact' => 'VARCHAR(100)',
        ];
        foreach ($clientColumns as $col => $def) {
            addColumn($pdo, $driver, 'clients', $col, $def, $log);
        }

        $fournisseurColumns = [
            'code' => 'VARCHAR(20)',
            'statut' => "VARCHAR(20) NOT NULL DEFAULT 'a_qualifier'",
            'categories_produits' => 'VARCHAR(255)',
            'marques' => 'VARCHAR(255)',
            'pays_desservis' => 'VARCHAR(255)',
            'incoterms_pratiques' => 'VARCHAR(255)',
            'quantite_min' => 'VARCHAR(100)',
            'fonction_contact' => 'VARCHAR(100)',
            'note_prix' => 'TINYINT',
            'note_qualite' => 'TINYINT',
            'note_delai' => 'TINYINT',
            'note_reactivite' => 'TINYINT',
            'note_conformite' => 'TINYINT',
            'note_engagements' => 'TINYINT',
        ];
        foreach ($fournisseurColumns as $col => $def) {
            addColumn($pdo, $driver, 'fournisseurs', $col, $def, $log);
        }

        if (!tableExists($pdo, $driver, 'fournisseur_pieces_jointes')) {
            $pdo->exec("CREATE TABLE fournisseur_pieces_jointes (
                id $id,
                fournisseur_id INT NOT NULL,
                categorie VARCHAR(30) NOT NULL DEFAULT 'autre',
                nom_original VARCHAR(255) NOT NULL,
                nom_fichier VARCHAR(255) NOT NULL,
                taille INT NOT NULL DEFAULT 0,
                type_mime VARCHAR(100),
                uploaded_by INT,
                created_at DATETIME NOT NULL
            )$engine");
            $log[] = 'CRÉÉE — table fournisseur_pieces_jointes';
        } else {
            $log[] = 'OK (déjà présente) — table fournisseur_pieces_jointes';
        }

        // ── Backfill : code auto pour les clients/fournisseurs déjà existants ──
        $stmt = $pdo->query("SELECT id, filiale_id FROM clients WHERE code IS NULL OR code = ''");
        $organisationParFiliale = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $filialeId = (int) $row['filiale_id'];
            if (!isset($organisationParFiliale[$filialeId])) {
                $f = $pdo->prepare('SELECT organisation_id FROM filiales WHERE id = ?');
                $f->execute([$filialeId]);
                $organisationParFiliale[$filialeId] = (int) $f->fetchColumn();
            }
            $numero = Compteur::next($organisationParFiliale[$filialeId], 'client');
            $code = Compteur::formatReference('CLI', $numero);
            $u = $pdo->prepare('UPDATE clients SET code = ?, statut = COALESCE(NULLIF(statut, \'\'), CASE WHEN is_active = 1 THEN \'actif\' ELSE \'inactif\' END) WHERE id = ?');
            $u->execute([$code, $row['id']]);
            $log[] = "Code $code attribué au client #{$row['id']}";
        }

        $stmt = $pdo->query("SELECT id, filiale_id FROM fournisseurs WHERE code IS NULL OR code = ''");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $filialeId = (int) $row['filiale_id'];
            if (!isset($organisationParFiliale[$filialeId])) {
                $f = $pdo->prepare('SELECT organisation_id FROM filiales WHERE id = ?');
                $f->execute([$filialeId]);
                $organisationParFiliale[$filialeId] = (int) $f->fetchColumn();
            }
            $numero = Compteur::next($organisationParFiliale[$filialeId], 'fournisseur');
            $code = Compteur::formatReference('FOU', $numero);
            $u = $pdo->prepare('UPDATE fournisseurs SET code = ?, statut = COALESCE(NULLIF(statut, \'\'), CASE WHEN is_active = 1 THEN \'approuve\' ELSE \'inactif\' END) WHERE id = ?');
            $u->execute([$code, $row['id']]);
            $log[] = "Code $code attribué au fournisseur #{$row['id']}";
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
<title>Migration V7 — Suivora360</title>
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
<h1>Migration V7 — fiches Client et Fournisseur enrichies</h1>
<p style="font-size:14px;color:#555">
Ajoute des colonnes aux tables <code>clients</code> et <code>fournisseurs</code>, crée la table
<code>fournisseur_pieces_jointes</code>, et attribue un code automatique (CLI-/FOU-) aux fiches
existantes qui n'en ont pas encore. Ne supprime et ne modifie <strong>aucune donnée existante</strong>,
et peut être relancée sans risque si besoin.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php?r=clients">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v7.php</code> de votre hébergement.</div>
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
