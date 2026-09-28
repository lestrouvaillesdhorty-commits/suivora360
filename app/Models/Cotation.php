<?php

namespace App\Models;

use App\Core\Database;

class Cotation
{
    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'envoyee' => 'Envoyée',
        'acceptee' => 'Acceptée',
        'refusee' => 'Refusée',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cotations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findWithDetails(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT co.*, c.nom AS client_nom, d.reference AS dossier_reference
             FROM cotations co
             INNER JOIN clients c ON c.id = co.client_id
             INNER JOIN dossiers d ON d.id = co.dossier_id
             WHERE co.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT co.*, c.nom AS client_nom FROM cotations co
             INNER JOIN clients c ON c.id = co.client_id
             WHERE co.dossier_id = ? ORDER BY co.created_at DESC'
        );
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    public static function latestForDossier(int $dossierId): ?array
    {
        $rows = self::forDossier($dossierId);
        return $rows[0] ?? null;
    }

    public static function create(int $dossierId, int $filialeId, array $data, array $items): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find($filialeId);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'cotation');
        $reference = Compteur::formatReference('COT', $numero);
        $now = date('Y-m-d H:i:s');

        $montantAchat = $data['montant_achat'] !== '' && $data['montant_achat'] !== null ? (float) $data['montant_achat'] : null;
        $margePourcentage = $data['marge_pourcentage'] !== '' && $data['marge_pourcentage'] !== null ? (float) $data['marge_pourcentage'] : null;
        $montantTotal = (float) ($data['montant_total'] ?? 0);
        $margeMontant = ($montantAchat !== null) ? round($montantTotal - $montantAchat, 2) : null;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO cotations
                 (dossier_id, filiale_id, offre_id, client_id, reference, montant_achat, marge_pourcentage, marge_montant, montant_total, devise, mode_paiement_negocie, incoterm_client, validite_devis, statut, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $dossierId,
                $filialeId,
                $data['offre_id'] ?: null,
                $data['client_id'],
                $reference,
                $montantAchat,
                $margePourcentage,
                $margeMontant,
                $montantTotal,
                trim($data['devise'] ?? ''),
                trim($data['mode_paiement_negocie'] ?? ''),
                trim($data['incoterm_client'] ?? ''),
                $data['validite_devis'] ?: null,
                'brouillon',
                trim($data['notes'] ?? ''),
                $now,
                $now,
            ]);
            $cotationId = (int) $pdo->lastInsertId();

            foreach ($items as $item) {
                if (trim($item['designation'] ?? '') === '') {
                    continue;
                }
                CotationItem::create($cotationId, $item);
            }

            $pdo->commit();
            return $cotationId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateStatut(int $id, string $statut): void
    {
        if (!array_key_exists($statut, self::STATUTS)) {
            throw new \InvalidArgumentException('Statut de cotation invalide.');
        }
        $stmt = Database::connection()->prepare('UPDATE cotations SET statut = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$statut, date('Y-m-d H:i:s'), $id]);
    }

    public static function userCanAccess(array $user, array $cotation): bool
    {
        return Filiale::userCanAccess($user, (int) $cotation['filiale_id']);
    }

    /**
     * Cotations envoyées au client, en attente de réponse — bloc
     * "commercial" du tableau de bord ("cotations à relancer").
     */
    public static function aRelancerCount(array $user): int
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM cotations WHERE statut = 'envoyee' AND filiale_id IN ($placeholders)"
        );
        $stmt->execute($filialeIds);
        return (int) $stmt->fetchColumn();
    }

    public static function aRelancerFor(array $user, int $limite = 5): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT co.*, d.reference AS dossier_reference, cl.nom AS client_nom
             FROM cotations co
             INNER JOIN dossiers d ON d.id = co.dossier_id
             INNER JOIN clients cl ON cl.id = co.client_id
             WHERE co.statut = 'envoyee' AND co.filiale_id IN ($placeholders)
             ORDER BY co.created_at ASC LIMIT " . (int) $limite
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll();
    }

    /**
     * Cotations envoyées et toujours sans réponse depuis plus de
     * $seuilJours jours — sous-ensemble "à risque" de aRelancerFor(), pour
     * le bloc Alertes du tableau de bord (spec : signaler ce qui traîne,
     * pas seulement compter).
     */
    public static function enAttenteDepuis(array $user, int $seuilJours, int $limite = 10): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $seuilDate = date('Y-m-d H:i:s', strtotime("-$seuilJours days"));
        $stmt = Database::connection()->prepare(
            "SELECT co.*, d.id AS dossier_id, d.reference AS dossier_reference, cl.nom AS client_nom
             FROM cotations co
             INNER JOIN dossiers d ON d.id = co.dossier_id
             INNER JOIN clients cl ON cl.id = co.client_id
             WHERE co.statut = 'envoyee' AND co.filiale_id IN ($placeholders) AND co.created_at <= ?
             ORDER BY co.created_at ASC LIMIT " . (int) $limite
        );
        $stmt->execute(array_merge($filialeIds, [$seuilDate]));
        return $stmt->fetchAll();
    }
}
