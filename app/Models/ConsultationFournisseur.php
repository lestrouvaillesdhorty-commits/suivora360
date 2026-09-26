<?php

namespace App\Models;

use App\Core\Database;

class ConsultationFournisseur
{
    public const STATUTS = [
        'envoyee' => 'Envoyée',
        'relance' => 'Relancée',
        'reponse_recue' => 'Réponse reçue',
        'sans_reponse' => 'Sans réponse',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM consultations_fournisseur WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findWithDetails(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT cf.*, fo.nom AS fournisseur_nom, fo.email AS fournisseur_email, d.reference AS dossier_reference
             FROM consultations_fournisseur cf
             INNER JOIN fournisseurs fo ON fo.id = cf.fournisseur_id
             INNER JOIN dossiers d ON d.id = cf.dossier_id
             WHERE cf.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT cf.*, fo.nom AS fournisseur_nom,
                    (SELECT COUNT(*) FROM offres o WHERE o.consultation_id = cf.id) AS nb_offres
             FROM consultations_fournisseur cf
             INNER JOIN fournisseurs fo ON fo.id = cf.fournisseur_id
             WHERE cf.dossier_id = ?
             ORDER BY cf.created_at DESC'
        );
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    public static function create(int $dossierId, int $filialeId, array $data, int $userId): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find($filialeId);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'consultation');
        $reference = Compteur::formatReference('CONS', $numero);
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO consultations_fournisseur
             (dossier_id, filiale_id, fournisseur_id, reference, articles_demandes, statut, date_envoi, date_relance, notes, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $dossierId,
            $filialeId,
            $data['fournisseur_id'],
            $reference,
            trim($data['articles_demandes'] ?? ''),
            'envoyee',
            $data['date_envoi'] ?? date('Y-m-d'),
            null,
            trim($data['notes'] ?? ''),
            $userId,
            $now,
            $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function updateStatut(int $id, string $statut, ?string $dateRelance = null): void
    {
        if (!array_key_exists($statut, self::STATUTS)) {
            throw new \InvalidArgumentException('Statut de consultation invalide.');
        }
        $stmt = Database::connection()->prepare(
            'UPDATE consultations_fournisseur SET statut = ?, date_relance = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$statut, $dateRelance ?: null, date('Y-m-d H:i:s'), $id]);
    }

    public static function userCanAccess(array $user, array $consultation): bool
    {
        return Filiale::userCanAccess($user, (int) $consultation['filiale_id']);
    }
}
