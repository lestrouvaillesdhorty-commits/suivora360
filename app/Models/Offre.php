<?php

namespace App\Models;

use App\Core\Database;

class Offre
{
    public const STATUTS = [
        'recue' => 'Reçue',
        'retenue' => 'Retenue',
        'rejetee' => 'Rejetée',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM offres WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forConsultation(int $consultationId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, fo.nom AS fournisseur_nom FROM offres o
             INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
             WHERE o.consultation_id = ? ORDER BY o.created_at DESC'
        );
        $stmt->execute([$consultationId]);
        return $stmt->fetchAll();
    }

    /**
     * Toutes les offres reçues pour un dossier, tous fournisseurs confondus
     * — utilisé par le comparateur.
     */
    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, fo.nom AS fournisseur_nom, cf.reference AS consultation_reference
             FROM offres o
             INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
             INNER JOIN consultations_fournisseur cf ON cf.id = o.consultation_id
             WHERE o.dossier_id = ?
             ORDER BY o.montant_total ASC'
        );
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    public static function retenueForDossier(int $dossierId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT o.*, fo.nom AS fournisseur_nom FROM offres o
             INNER JOIN fournisseurs fo ON fo.id = o.fournisseur_id
             WHERE o.dossier_id = ? AND o.statut = 'retenue' LIMIT 1"
        );
        $stmt->execute([$dossierId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $consultation, array $data, array $items): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find((int) $consultation['filiale_id']);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'offre');
        $reference = Compteur::formatReference('OFR', $numero);
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO offres
                 (consultation_id, dossier_id, filiale_id, fournisseur_id, reference, montant_total, devise, incoterm_negocie, delai_livraison, validite_offre, statut, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $consultation['id'],
                $consultation['dossier_id'],
                $consultation['filiale_id'],
                $consultation['fournisseur_id'],
                $reference,
                (float) ($data['montant_total'] ?? 0),
                trim($data['devise'] ?? ''),
                trim($data['incoterm_negocie'] ?? ''),
                trim($data['delai_livraison'] ?? ''),
                $data['validite_offre'] ?: null,
                'recue',
                trim($data['notes'] ?? ''),
                $now,
                $now,
            ]);
            $offreId = (int) $pdo->lastInsertId();

            foreach ($items as $item) {
                if (trim($item['designation'] ?? '') === '') {
                    continue;
                }
                OffreItem::create($offreId, $item);
            }

            // Une réponse a été reçue : la consultation associée est marquée en conséquence.
            $updateConsultation = $pdo->prepare(
                "UPDATE consultations_fournisseur SET statut = 'reponse_recue', updated_at = ? WHERE id = ?"
            );
            $updateConsultation->execute([$now, $consultation['id']]);

            $pdo->commit();
            return $offreId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Marque une offre comme retenue pour le dossier et rejette les autres
     * offres du même dossier (une seule offre retenue à la fois).
     */
    public static function retenir(int $dossierId, int $offreId): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            // Toutes les autres offres du dossier (reçues ou précédemment retenues)
            // passent à "rejetée" : une seule offre retenue à la fois par dossier.
            $reset = $pdo->prepare(
                "UPDATE offres SET statut = 'rejetee', updated_at = ? WHERE dossier_id = ? AND id != ? AND statut IN ('recue', 'retenue')"
            );
            $reset->execute([$now, $dossierId, $offreId]);

            $retain = $pdo->prepare(
                "UPDATE offres SET statut = 'retenue', updated_at = ? WHERE id = ? AND dossier_id = ?"
            );
            $retain->execute([$now, $offreId, $dossierId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function userCanAccess(array $user, array $offre): bool
    {
        return Filiale::userCanAccess($user, (int) $offre['filiale_id']);
    }
}
