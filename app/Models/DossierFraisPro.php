<?php

namespace App\Models;

use App\Core\Database;

/**
 * Frais professionnels d'un dossier (déplacements, hébergement, temps passé facturé en interne…), imputés à un
 * collaborateur (par défaut le responsable du dossier). Ils alimentent « Coûts affectés » et « Contribution nette »
 * du Pilotage. Tolère l'absence de la table (migration v48 pas encore lancée).
 */
class DossierFraisPro
{
    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM dossier_frais_pro LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function forDossier(int $dossierId): array
    {
        if (!self::disponible()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM dossier_frais_pro WHERE dossier_id = ? ORDER BY date_frais DESC, id DESC');
        $st->execute([$dossierId]);
        return $st->fetchAll();
    }

    public static function ajouter(int $dossierId, int $filialeId, ?int $userId, string $libelle, float $montant, string $devise, string $date): void
    {
        $libelle = mb_substr(trim($libelle) !== '' ? trim($libelle) : 'Frais', 0, 120);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
        Database::connection()->prepare('INSERT INTO dossier_frais_pro (dossier_id, filiale_id, utilisateur_id, libelle, montant, devise, date_frais, created_at) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$dossierId, $filialeId, $userId ?: null, $libelle, max(0, $montant), $devise !== '' ? $devise : 'EUR', $date, date('Y-m-d H:i:s')]);
    }

    public static function supprimer(int $dossierId, int $id): void
    {
        Database::connection()->prepare('DELETE FROM dossier_frais_pro WHERE id = ? AND dossier_id = ?')->execute([$id, $dossierId]);
    }

    /**
     * Frais de la période par collaborateur (imputé, sinon responsable du dossier), convertis dans la devise de reporting.
     * Retour : [utilisateur_id => ['total' => float, 'non_convertis' => array, 'nb' => int]].
     */
    public static function parCollaborateur(array $user, array $filters): array
    {
        if (!self::disponible()) {
            return [];
        }
        $filialeIds = Filiale::visibleIdsFor($user);
        if (!$filialeIds) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT f.montant, f.devise, f.filiale_id, COALESCE(f.utilisateur_id, d.responsable_id) AS uid
                FROM dossier_frais_pro f
                INNER JOIN dossiers d ON d.id = f.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                WHERE f.filiale_id IN ($ph) AND f.date_frais >= ? AND f.date_frais <= ?";
        $params = array_merge($filialeIds, [$filters['date_debut'], $filters['date_fin']]);
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['client_id'])) {
            $sql .= ' AND dm.client_id = ?';
            $params[] = $filters['client_id'];
        }
        $st = Database::connection()->prepare($sql);
        $st->execute($params);
        $groupes = [];
        foreach ($st->fetchAll() as $r) {
            $uid = (int) ($r['uid'] ?? 0);
            if ($uid > 0) {
                $groupes[$uid][] = ['montant' => $r['montant'], 'devise' => $r['devise'], 'filiale_id' => $r['filiale_id']];
            }
        }
        $out = [];
        foreach ($groupes as $uid => $lignes) {
            $agg = Pilotage::agregerMontants($lignes, $filters['devise'], $filialeIds);
            $out[$uid] = ['total' => $agg['total'], 'non_convertis' => $agg['non_convertis'], 'nb' => count($lignes)];
        }
        return $out;
    }
}
