<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Demande
{
    // Voies de qualification (reprises de la v1 React/PocketBase)
    public const QUALIFICATION_TYPES = [
        'NEW' => 'Nouvelle demande',
        'ADDITION' => 'Complément à une demande existante',
        'EXTERNAL_TAKEOVER' => 'Reprise hors Suivora',
    ];

    // Incoterms 2020 (ICC) — liste standard, utilisée pour le souhait initial du client
    public const INCOTERMS = [
        'EXW' => 'EXW — Ex Works (départ usine)',
        'FCA' => 'FCA — Free Carrier',
        'FAS' => 'FAS — Free Alongside Ship',
        'FOB' => 'FOB — Free On Board',
        'CFR' => 'CFR — Cost and Freight',
        'CIF' => 'CIF — Cost, Insurance and Freight',
        'CPT' => 'CPT — Carriage Paid To',
        'CIP' => 'CIP — Carriage and Insurance Paid To',
        'DAP' => 'DAP — Delivered At Place',
        'DPU' => 'DPU — Delivered at Place Unloaded',
        'DDP' => 'DDP — Delivered Duty Paid',
    ];

    // Modes de paiement courants — souhait initial du client, à reconfirmer en cotation
    public const MODES_PAIEMENT = [
        'virement' => 'Virement bancaire',
        'lc' => 'Lettre de crédit (L/C)',
        'avance' => 'Paiement à la commande (avance)',
        'echelonne' => 'Paiement échelonné',
        'livraison' => 'Paiement à la livraison',
        'mobile_money' => 'Mobile Money',
        'cheque' => 'Chèque',
        'especes' => 'Espèces',
        'autre' => 'Autre',
    ];

    public const TAKEOVER_STAGES = [
        'qualification' => 'Demande en cours de qualification',
        'recherche_fournisseurs' => 'Recherche de fournisseurs',
        'consultation_preparee' => 'Consultation fournisseur préparée',
        'consultation_envoyee' => 'Consultation envoyée',
        'offres_recues' => 'Offres fournisseurs reçues',
        'comparaison' => 'Comparaison en cours',
        'cotation_preparee' => 'Cotation client préparée',
        'cotation_envoyee' => 'Cotation client envoyée',
        'commande_confirmee' => 'Commande client confirmée',
        'suivi_operationnel' => 'Suivi opérationnel en cours',
    ];

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
        if (!empty($filters['date_debut'])) {
            $sql .= ' AND d.recue_le >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= ' AND d.recue_le <= ?';
            $params[] = $filters['date_fin'];
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

    /**
     * Voie 1 — Nouvelle demande : sélection (ou non) d'un client connu,
     * complément des informations de suivi. Passe au statut "qualifiee" si les
     * informations minimales sont réunies, sinon "en_attente_info".
     */
    public static function qualifyNew(int $id, array $data, int $userId): void
    {
        $clientId = ($data['client_id'] ?? null) ?: null;
        $activite = trim($data['activite'] ?? '');
        $responsableId = ($data['responsable_id'] ?? null) ?: null;

        $status = ($clientId || $activite) ? 'qualifiee' : 'en_attente_info';

        $stmt = Database::connection()->prepare(
            "UPDATE demandes SET
                client_id = ?,
                activite = ?,
                responsable_id = ?,
                priorite = ?,
                echeance = ?,
                destination_pays = ?,
                lieu_livraison = ?,
                incoterm_souhaite = ?,
                mode_paiement_souhaite = ?,
                qualification_type = 'NEW',
                qualification_status = ?,
                qualification_notes = ?,
                qualified_at = ?,
                qualified_by = ?,
                statut = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $clientId,
            $activite,
            $responsableId,
            $data['priorite'] ?? 'normale',
            ($data['echeance'] ?? null) ?: null,
            trim($data['destination_pays'] ?? ''),
            trim($data['lieu_livraison'] ?? ''),
            trim($data['incoterm_souhaite'] ?? ''),
            trim($data['mode_paiement_souhaite'] ?? ''),
            $status,
            trim($data['notes'] ?? ''),
            date('Y-m-d H:i:s'),
            $userId,
            $status,
            $id,
        ]);
    }

    /**
     * Voie 2 — Complément à une demande/dossier existant : jamais de création
     * automatique de dossier, rattachement explicite après confirmation humaine.
     */
    public static function qualifyAddition(int $id, string $linkedType, int $linkedId, string $notes, int $userId): void
    {
        $linkedRequestId = $linkedType === 'demande' ? $linkedId : null;
        $linkedDossierId = $linkedType === 'dossier' ? $linkedId : null;

        $stmt = Database::connection()->prepare(
            "UPDATE demandes SET
                qualification_type = 'ADDITION',
                qualification_status = 'rattachee',
                qualification_notes = ?,
                linked_request_id = ?,
                linked_dossier_id = ?,
                qualified_at = ?,
                qualified_by = ?,
                statut = 'rattachee'
             WHERE id = ?"
        );
        $stmt->execute([$notes, $linkedRequestId, $linkedDossierId, date('Y-m-d H:i:s'), $userId, $id]);
    }

    /**
     * Voie 3 — Reprise d'un dossier déjà commencé hors Suivora : on enregistre
     * l'historique sans jamais inventer les étapes antérieures.
     */
    public static function qualifyTakeover(int $id, array $data, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE demandes SET
                responsable_id = ?,
                priorite = ?,
                echeance = ?,
                qualification_type = 'EXTERNAL_TAKEOVER',
                qualification_status = 'qualifiee',
                qualification_notes = ?,
                historical_takeover = 1,
                original_started_at = ?,
                registered_in_suivora_at = ?,
                external_source = ?,
                external_reference = ?,
                takeover_stage = ?,
                qualified_at = ?,
                qualified_by = ?,
                statut = 'qualifiee'
             WHERE id = ?"
        );
        $stmt->execute([
            ($data['responsable_id'] ?? null) ?: null,
            $data['priorite'] ?? 'normale',
            ($data['echeance'] ?? null) ?: null,
            trim($data['notes'] ?? ''),
            ($data['original_started_at'] ?? null) ?: null,
            ($data['registered_in_suivora_at'] ?? null) ?: date('Y-m-d'),
            trim($data['external_source'] ?? ''),
            trim($data['external_reference'] ?? ''),
            $data['takeover_stage'] ?? '',
            date('Y-m-d H:i:s'),
            $userId,
            $id,
        ]);
    }

    public static function reject(int $id, string $motif): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE demandes SET statut = 'rejetee', qualification_notes = ? WHERE id = ?"
        );
        $stmt->execute([trim($motif), $id]);
    }

    /**
     * Recherche de demandes/dossiers existants pour la voie "Complément",
     * limitée aux filiales visibles par l'utilisateur (isolation multi-org).
     */
    public static function searchForAddition(array $filialeIds, string $term, int $excludeId): array
    {
        if (empty($filialeIds) || trim($term) === '') {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $like = '%' . $term . '%';
        $results = [];

        $stmt = Database::connection()->prepare(
            "SELECT id, reference, objet AS libelle, statut, created_at FROM demandes
             WHERE filiale_id IN ($placeholders) AND id != ?
             AND (reference LIKE ? OR objet LIKE ? OR expediteur_nom LIKE ? OR expediteur_entreprise LIKE ?)
             ORDER BY created_at DESC LIMIT 15"
        );
        $stmt->execute([...$filialeIds, $excludeId, $like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $row['type'] = 'demande';
            $results[] = $row;
        }

        $stmt = Database::connection()->prepare(
            "SELECT id, reference, objet AS libelle, statut, created_at FROM dossiers
             WHERE filiale_id IN ($placeholders)
             AND (reference LIKE ? OR objet LIKE ?)
             ORDER BY created_at DESC LIMIT 15"
        );
        $stmt->execute([...$filialeIds, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $row['type'] = 'dossier';
            $results[] = $row;
        }

        return $results;
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
