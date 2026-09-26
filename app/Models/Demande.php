<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Demande
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM demandes WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Liste des demandes visibles pour l'utilisateur (filtrées par filiales autorisées).
     */
    public static function visibleFor(array $user, array $filters = []): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT d.*, f.nom AS filiale_nom FROM demandes d
                INNER JOIN filiales f ON f.id = d.filiale_id
                WHERE d.filiale_id IN ($placeholders)";
        $params = $filialeIds;

        if (!empty($filters['statut'])) {
            $sql .= ' AND d.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        if (!empty($filters['recherche'])) {
            $sql .= ' AND (d.reference LIKE ? OR d.objet LIKE ? OR d.expediteur_nom LIKE ?)';
            $like = '%' . $filters['recherche'] . '%';
            array_push($params, $like, $like, $like);
        }

        $sql .= ' ORDER BY d.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find((int) $data['filiale_id']);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'demande');
        $reference = Compteur::formatReference('DEM', $numero);

        $stmt = $pdo->prepare(
            'INSERT INTO demandes
             (filiale_id, reference, objet, message, canal, expediteur_nom, expediteur_entreprise, expediteur_email, expediteur_telephone, recue_le, activite, responsable_id, priorite, echeance, statut, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['filiale_id'],
            $reference,
            $data['objet'],
            $data['message'] ?? '',
            $data['canal'] ?? 'Formulaire',
            $data['expediteur_nom'] ?? '',
            $data['expediteur_entreprise'] ?? '',
            $data['expediteur_email'] ?? '',
            $data['expediteur_telephone'] ?? '',
            $data['recue_le'] ?? date('Y-m-d'),
            $data['activite'] ?? '',
            $data['responsable_id'] ?: null,
            $data['priorite'] ?? 'normale',
            $data['echeance'] ?: null,
            'a_qualifier',
            date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function markQualifiee(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE demandes SET statut = 'qualifiee' WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function counts(array $user): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return ['a_qualifier' => 0];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM demandes WHERE statut = 'a_qualifier' AND filiale_id IN ($placeholders)"
        );
        $stmt->execute($filialeIds);
        return ['a_qualifier' => (int) $stmt->fetchColumn()];
    }
}
