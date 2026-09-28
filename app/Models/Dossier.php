<?php

namespace App\Models;

use App\Core\Database;

class Dossier
{
    public const ETAPES = ['qualifie', 'sourcing', 'cotation', 'commande', 'livraison', 'cloture'];

    public const ETAPES_LABELS = [
        'qualifie' => 'Qualifié',
        'sourcing' => 'Sourcing',
        'cotation' => 'Cotation',
        'commande' => 'Commande',
        'livraison' => 'Livraison',
        'cloture' => 'Clôturé',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM dossiers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByDemande(int $demandeId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM dossiers WHERE demande_id = ?');
        $stmt->execute([$demandeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function visibleFor(array $user, array $filters = []): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT d.*, f.nom AS filiale_nom FROM dossiers d
                INNER JOIN filiales f ON f.id = d.filiale_id
                WHERE d.filiale_id IN ($placeholders)";
        $params = $filialeIds;

        if (!empty($filters['statut']) && $filters['statut'] === 'en_retard') {
            // "En retard" n'est pas une valeur stockée : un dossier actif dont
            // l'échéance est dépassée.
            $sql .= " AND d.statut = 'actif' AND d.echeance IS NOT NULL AND d.echeance < ?";
            $params[] = date('Y-m-d');
        } elseif (!empty($filters['statut'])) {
            $sql .= ' AND d.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['etape'])) {
            $sql .= ' AND d.etape = ?';
            $params[] = $filters['etape'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        if (!empty($filters['recherche'])) {
            $sql .= ' AND (d.reference LIKE ? OR d.objet LIKE ?)';
            $like = '%' . $filters['recherche'] . '%';
            array_push($params, $like, $like);
        }
        if (!empty($filters['date_debut'])) {
            $sql .= ' AND d.created_at >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= ' AND d.created_at <= ?';
            $params[] = $filters['date_fin'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY d.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function userCanAccess(array $user, array $dossier): bool
    {
        return Filiale::userCanAccess($user, (int) $dossier['filiale_id']);
    }

    /**
     * Qualifie une demande : crée le dossier lié (1 demande = 1 dossier) et
     * fait passer la demande au statut "qualifiée". Opération transactionnelle
     * pour éviter l'état incohérent vu dans le prototype précédent (demande
     * qualifiée sans dossier créé).
     */
    public static function createFromDemande(int $demandeId, array $data): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $demande = Demande::find($demandeId);
            if (!$demande) {
                throw new \RuntimeException('Demande introuvable.');
            }
            if ($demande['statut'] !== 'qualifiee') {
                throw new \RuntimeException("La demande doit d'abord être qualifiée (voie Nouvelle demande ou Reprise) avant de créer un dossier.");
            }

            $existing = self::findByDemande($demandeId);
            if ($existing) {
                $pdo->rollBack();
                return (int) $existing['id'];
            }

            $filiale = Filiale::find((int) $demande['filiale_id']);
            $numero = Compteur::next((int) $filiale['organisation_id'], 'dossier');
            $reference = Compteur::formatReference('DOS', $numero);

            $stmt = $pdo->prepare(
                'INSERT INTO dossiers
                 (demande_id, filiale_id, reference, objet, etape, statut, responsable_id, priorite, echeance, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                $demandeId,
                $demande['filiale_id'],
                $reference,
                $demande['objet'],
                'qualifie',
                'actif',
                $data['responsable_id'] ?: $demande['responsable_id'],
                $data['priorite'] ?: $demande['priorite'],
                $data['echeance'] ?: $demande['echeance'],
                '',
                $now,
                $now,
            ]);
            $dossierId = (int) $pdo->lastInsertId();

            $update = $pdo->prepare("UPDATE demandes SET statut = 'qualifiee' WHERE id = ?");
            $update->execute([$demandeId]);

            $pdo->commit();
            return $dossierId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateEtape(int $id, string $etape): void
    {
        if (!in_array($etape, self::ETAPES, true)) {
            throw new \InvalidArgumentException('Étape invalide.');
        }
        $statut = $etape === 'cloture' ? 'cloture' : 'actif';
        $stmt = Database::connection()->prepare('UPDATE dossiers SET etape = ?, statut = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$etape, $statut, date('Y-m-d H:i:s'), $id]);
    }

    public static function updateNotes(int $id, string $notes): void
    {
        $stmt = Database::connection()->prepare('UPDATE dossiers SET notes = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$notes, date('Y-m-d H:i:s'), $id]);
    }

    public static function counts(array $user): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return ['actifs' => 0, 'en_retard' => 0];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));

        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM dossiers WHERE statut = 'actif' AND filiale_id IN ($placeholders)"
        );
        $stmt->execute($filialeIds);
        $actifs = (int) $stmt->fetchColumn();

        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM dossiers
             WHERE statut = 'actif' AND echeance IS NOT NULL AND echeance < ?
             AND filiale_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([date('Y-m-d')], $filialeIds));
        $enRetard = (int) $stmt->fetchColumn();

        return ['actifs' => $actifs, 'en_retard' => $enRetard];
    }

    /**
     * Dossiers actifs dont l'échéance arrive dans les prochains jours (pas
     * encore en retard) — bloc "échéances à venir" du tableau de bord.
     */
    public static function echeancesAVenirFor(array $user, int $jours = 7, int $limite = 8): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT d.* FROM dossiers d
             WHERE d.filiale_id IN ($placeholders)
             AND d.statut = 'actif'
             AND d.echeance IS NOT NULL AND d.echeance >= ? AND d.echeance <= ?
             ORDER BY d.echeance ASC LIMIT " . (int) $limite
        );
        $stmt->execute(array_merge($filialeIds, [date('Y-m-d'), date('Y-m-d', strtotime('+' . $jours . ' days'))]));
        return $stmt->fetchAll();
    }
}
