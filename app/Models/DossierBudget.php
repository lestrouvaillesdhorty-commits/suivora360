<?php

namespace App\Models;

use App\Core\Database;

/**
 * [ajouté 06/10, étape 5 du découpage Dossiers — migrate_v20.php] Budget
 * prévisionnel vs réalisé d'un dossier (carte de l'onglet Exécution, maquette
 * ExecutionPrestation.dc.html). Six catégories fixes, une ligne par
 * (dossier, catégorie) créée à la première saisie. Le "réalisé" est nul tant
 * qu'il n'est pas renseigné (affiché « — » comme sur la maquette).
 */
class DossierBudget
{
    public const CATEGORIES = [
        'main_oeuvre' => "Main-d'œuvre",
        'consommables' => 'Consommables',
        'materiel_disponible' => 'Matériel disponible',
        'materiel_achat_location' => 'Matériel à acheter / louer',
        'sous_traitance' => 'Sous-traitance',
        'deplacement_transport' => 'Déplacement et transport',
    ];

    public const DEVISES = ['FCFA', 'EUR'];

    /** Lignes indexées par catégorie ; [] si la table n'existe pas encore (migration non lancée). */
    public static function forDossier(int $dossierId): array
    {
        try {
            $stmt = Database::connection()->prepare('SELECT * FROM dossier_budget_lignes WHERE dossier_id = ?');
            $stmt->execute([$dossierId]);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['categorie']] = $row;
        }
        return $out;
    }

    public static function totaux(array $lignes): array
    {
        $prev = 0.0;
        $real = 0.0;
        $aRealise = false;
        foreach ($lignes as $l) {
            $prev += (float) $l['montant_previsionnel'];
            if ($l['montant_realise'] !== null) {
                $real += (float) $l['montant_realise'];
                $aRealise = true;
            }
        }
        return ['previsionnel' => $prev, 'realise' => $aRealise ? $real : null];
    }

    /**
     * Enregistre les 6 catégories d'un coup (upsert).
     * $data : [categorie => ['previsionnel' => float, 'realise' => ?float, 'detail' => ?string]]
     */
    public static function enregistrer(int $dossierId, int $filialeId, string $devise, array $data, ?int $userId): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            foreach ($data as $cat => $d) {
                $sel = $pdo->prepare('SELECT id FROM dossier_budget_lignes WHERE dossier_id = ? AND categorie = ?');
                $sel->execute([$dossierId, $cat]);
                $id = $sel->fetchColumn();
                if ($id) {
                    $pdo->prepare('UPDATE dossier_budget_lignes SET montant_previsionnel = ?, montant_realise = ?, devise = ?, detail = ?, updated_by = ?, updated_at = ? WHERE id = ?')
                        ->execute([$d['previsionnel'], $d['realise'], $devise, $d['detail'], $userId, $now, $id]);
                } else {
                    $pdo->prepare('INSERT INTO dossier_budget_lignes (dossier_id, filiale_id, categorie, montant_previsionnel, montant_realise, devise, detail, updated_by, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
                        ->execute([$dossierId, $filialeId, $cat, $d['previsionnel'], $d['realise'], $devise, $d['detail'], $userId, $now]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
