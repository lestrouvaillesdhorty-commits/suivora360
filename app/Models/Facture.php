<?php

namespace App\Models;

use App\Core\Database;

class Facture
{
    public const TYPES = [
        'acompte' => 'Acompte',
        'solde' => 'Solde',
        'unique' => 'Facture unique',
    ];

    public const STATUTS = [
        'emise' => 'Émise',
        'payee' => 'Payée',
        'annulee' => 'Annulée',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM factures WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM factures WHERE dossier_id = ? ORDER BY created_at DESC');
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    public static function create(int $dossierId, int $filialeId, array $data): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find($filialeId);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'facture');
        $reference = Compteur::formatReference('FAC', $numero);
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO factures
             (dossier_id, commande_id, cotation_id, filiale_id, reference, type, montant, devise, date_emission, date_echeance, statut, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $dossierId,
            ($data['commande_id'] ?? null) ?: null,
            ($data['cotation_id'] ?? null) ?: null,
            $filialeId,
            $reference,
            $data['type'] ?? 'unique',
            (float) ($data['montant'] ?? 0),
            trim($data['devise'] ?? ''),
            ($data['date_emission'] ?? null) ?: date('Y-m-d'),
            ($data['date_echeance'] ?? null) ?: null,
            'emise',
            trim($data['notes'] ?? ''),
            $now,
            $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function updateStatut(int $id, string $statut): void
    {
        if (!array_key_exists($statut, self::STATUTS)) {
            throw new \InvalidArgumentException('Statut de facture invalide.');
        }
        $stmt = Database::connection()->prepare('UPDATE factures SET statut = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$statut, date('Y-m-d H:i:s'), $id]);
    }

    public static function userCanAccess(array $user, array $facture): bool
    {
        return Filiale::userCanAccess($user, (int) $facture['filiale_id']);
    }
}
