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

    public static function visibleFor(array $user): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT d.*, f.nom AS filiale_nom FROM dossiers d
             INNER JOIN filiales f ON f.id = d.filiale_id
             WHERE d.filiale_id IN ($placeholders)
             ORDER BY d.created_at DESC"
        );
        $stmt->execute($filialeIds);
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
            return ['actifs' => 0];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM dossiers WHERE statut = 'actif' AND filiale_id IN ($placeholders)"
        );
        $stmt->execute($filialeIds);
        return ['actifs' => (int) $stmt->fetchColumn()];
    }
}
