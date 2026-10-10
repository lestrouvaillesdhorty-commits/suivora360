<?php

/**
 * Migration V23 — Suivora360
 * ---------------------------------------------------------------
 * Module Fournisseurs (07/10) : nouveaux champs de la fiche (types de partenaire,
 * spécialités, conditions commerciales, responsable, qualification, lien client),
 * tables fournisseur_contacts, fournisseur_adresses, fournisseur_qualifications
 * (historique), fournisseur_evaluations, et date d'expiration des documents.
 * Strictement additive et idempotente. Seule reprise de données : l'ancien statut
 * (Approuvé / Sous surveillance / Suspendu / À qualifier / Inactif) est converti
 * en niveau de qualification, avec une ligne d'historique qui conserve l'ancien libellé.
 * Protégée par INSTALL_TOKEN (.env). Après usage, supprimez ce fichier.
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
        $premiereFois = !colonneExiste($pdo, $driver, 'fournisseurs', 'qualification');

        foreach ([
            'nom_commercial' => 'VARCHAR(255) NULL',
            'types_partenaire' => 'VARCHAR(255) NULL',
            'specialites' => 'VARCHAR(500) NULL',
            'activites' => 'VARCHAR(255) NULL',
            'code_postal' => 'VARCHAR(20) NULL',
            'siret' => 'VARCHAR(60) NULL',
            'tva' => 'VARCHAR(60) NULL',
            'contact_prenom' => 'VARCHAR(100) NULL',
            'contact_nom' => 'VARCHAR(100) NULL',
            'devises_proposees' => 'VARCHAR(100) NULL',
            'conditions_paiement' => 'VARCHAR(30) NULL',
            'delai_indicatif' => 'VARCHAR(100) NULL',
            'conditions_livraison' => 'VARCHAR(255) NULL',
            'responsable_id' => 'INT NULL',
            'origine_contact' => 'VARCHAR(100) NULL',
            'qualification' => "VARCHAR(15) NOT NULL DEFAULT 'a_qualifier'",
            'reexamen_le' => 'DATE NULL',
            'client_id' => 'INT NULL',
        ] as $col => $def) {
            if (!colonneExiste($pdo, $driver, 'fournisseurs', $col)) {
                $pdo->exec("ALTER TABLE fournisseurs ADD COLUMN $col $def");
                $log[] = "CRÉÉE — colonne fournisseurs.$col";
            } else {
                $log[] = "OK (déjà présente) — colonne fournisseurs.$col";
            }
        }

        $tables = [
            'fournisseur_contacts' => 'CREATE TABLE fournisseur_contacts (
                id ' . Database::idColumnType() . ',
                fournisseur_id INT NOT NULL,
                prenom VARCHAR(100) NOT NULL DEFAULT \'\',
                nom VARCHAR(100) NOT NULL DEFAULT \'\',
                fonction VARCHAR(100) NOT NULL DEFAULT \'\',
                email VARCHAR(255) NOT NULL DEFAULT \'\',
                telephone VARCHAR(50) NOT NULL DEFAULT \'\',
                actif TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            )',
            'fournisseur_adresses' => 'CREATE TABLE fournisseur_adresses (
                id ' . Database::idColumnType() . ',
                fournisseur_id INT NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT \'autre\',
                libelle VARCHAR(100) NOT NULL DEFAULT \'\',
                adresse VARCHAR(255) NOT NULL DEFAULT \'\',
                code_postal VARCHAR(20) NOT NULL DEFAULT \'\',
                ville VARCHAR(100) NOT NULL DEFAULT \'\',
                pays VARCHAR(100) NOT NULL DEFAULT \'\',
                par_defaut TINYINT(1) NOT NULL DEFAULT 0,
                actif TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            )',
            'fournisseur_qualifications' => 'CREATE TABLE fournisseur_qualifications (
                id ' . Database::idColumnType() . ',
                fournisseur_id INT NOT NULL,
                decision VARCHAR(15) NOT NULL,
                criteres TEXT NULL,
                commentaire TEXT NULL,
                justificatifs VARCHAR(255) NULL,
                evaluateur_id INT NULL,
                date_decision DATE NOT NULL,
                date_reexamen DATE NULL,
                created_at DATETIME NOT NULL
            )',
            'fournisseur_evaluations' => 'CREATE TABLE fournisseur_evaluations (
                id ' . Database::idColumnType() . ',
                fournisseur_id INT NOT NULL,
                dossier_id INT NULL,
                critere VARCHAR(60) NOT NULL,
                resultat VARCHAR(15) NOT NULL,
                commentaire TEXT NULL,
                piece_id INT NULL,
                evaluateur_id INT NULL,
                date_evaluation DATE NOT NULL,
                created_at DATETIME NOT NULL
            )',
        ];
        foreach ($tables as $nom => $sql) {
            if (!tableExiste($pdo, $driver, $nom)) {
                $pdo->exec($sql . $engine);
                $pdo->exec("CREATE INDEX idx_{$nom}_fou ON $nom (fournisseur_id)");
                $log[] = "CRÉÉE — table $nom";
            } else {
                $log[] = "OK (déjà présente) — table $nom";
            }
        }

        if (!colonneExiste($pdo, $driver, 'fournisseur_pieces_jointes', 'expire_le')) {
            $pdo->exec('ALTER TABLE fournisseur_pieces_jointes ADD COLUMN expire_le DATE NULL');
            $log[] = 'CRÉÉE — colonne fournisseur_pieces_jointes.expire_le (échéance facultative des documents)';
        } else {
            $log[] = 'OK (déjà présente) — colonne fournisseur_pieces_jointes.expire_le';
        }

        // Reprise de l'ancien statut -> niveau de qualification (une seule fois).
        if ($premiereFois) {
            $map = ['approuve' => 'valide', 'sous_surveillance' => 'en_cours', 'suspendu' => 'non_retenu'];
            $libelles = ['approuve' => 'Approuvé', 'sous_surveillance' => 'Sous surveillance', 'suspendu' => 'Suspendu'];
            $now = date('Y-m-d H:i:s');
            $nb = 0;
            foreach ($pdo->query("SELECT id, statut FROM fournisseurs")->fetchAll(\PDO::FETCH_ASSOC) as $f) {
                if (!isset($map[$f['statut']])) {
                    continue;
                }
                $pdo->prepare('UPDATE fournisseurs SET qualification = ? WHERE id = ?')->execute([$map[$f['statut']], $f['id']]);
                $pdo->prepare('INSERT INTO fournisseur_qualifications (fournisseur_id, decision, commentaire, date_decision, created_at) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$f['id'], $map[$f['statut']], 'Reprise de l\'ancien statut « ' . $libelles[$f['statut']] . ' » (migration V23).', date('Y-m-d'), $now]);
                $nb++;
            }
            $pdo->exec("UPDATE fournisseurs SET specialites = categories_produits WHERE (specialites IS NULL OR specialites = '') AND categories_produits IS NOT NULL AND categories_produits != ''");
            $pdo->exec("UPDATE fournisseurs SET statut = CASE WHEN is_active = 1 THEN 'actif' ELSE 'inactif' END");
            $log[] = "REPRISE — $nb fournisseur(s) : ancien statut converti en niveau de qualification (libellé conservé dans l'historique) ; statut = actif / inactif ; catégories de produits copiées dans les spécialités";
        } else {
            $log[] = 'OK — reprise des anciens statuts déjà effectuée (non rejouée)';
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
<title>Migration V23 — Suivora360</title>
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
<h1>Migration V23 — Module Fournisseurs</h1>
<p style="font-size:14px;color:#555">Ajoute les nouveaux champs et tables du module Fournisseurs. Aucun fournisseur, offre, consultation ni document n'est supprimé ; l'ancien statut est converti en niveau de qualification (conservé dans l'historique). Peut être relancée sans risque.</p>
<?php if ($ran): ?>
<ul style="font-size:13px;line-height:1.6">
<?php foreach ($log as $line): ?><li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li><?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v23.php</code> de votre hébergement.</div>
<?php else: ?>
<?php foreach ($log as $line): ?><p class="err"><?= htmlspecialchars($line) ?></p><?php endforeach; ?>
<form method="post"><input type="hidden" name="action" value="migrer"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><button type="submit">Lancer la migration</button></form>
<?php endif; ?>
</div>
</body>
</html>
