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
        // [ajouté 06/10, migrate_v18] Une cotation passe à "remplacee" quand
        // une nouvelle version est créée sur le même dossier — l'ancienne
        // reste en base (historique, non modifiable) mais n'est plus la
        // cotation courante.
        'remplacee' => 'Remplacée',
    ];

    /** Motifs de perte d'un devis refusé (liste déroulante + commentaire libre). */
    public const MOTIFS_PERTE = [
        'prix' => 'Prix trop élevé',
        'delai' => 'Délai trop long',
        'concurrent' => 'Concurrent choisi',
        'qualite' => 'Produit ou qualité non adapté',
        'besoin' => 'Besoin changé ou annulé',
        'abandon' => 'Projet abandonné ou reporté',
        'sans_reponse' => 'Plus de réponse du client',
        'autre' => 'Autre (préciser)',
    ];

    public static function colonnesTransport(): bool
    {
        return \App\Core\Schema::colonne('cotations', 'transport_mode');
    }

    public static function colonnesPerte(): bool
    {
        return \App\Core\Schema::colonne('cotations', 'motif_perte');
    }

    public static function enregistrerPerte(int $id, ?string $motif, string $detail = ''): void
    {
        if (!self::colonnesPerte()) {
            return;
        }
        $st = Database::connection()->prepare('SELECT filiale_id FROM cotations WHERE id = ?');
        $st->execute([$id]);
        $motif = MotifPerte::valide((int) $st->fetchColumn(), (string) $motif) ? $motif : null;
        Database::connection()->prepare('UPDATE cotations SET motif_perte = ?, motif_perte_detail = ? WHERE id = ?')
            ->execute([$motif, $motif ? mb_substr(trim($detail), 0, 300) : null, $id]);
    }

    /**
     * Statuts que l'utilisateur peut choisir à la main (liste déroulante de
     * la fiche cotation) — "remplacee" est posé uniquement par la création
     * d'une nouvelle version, jamais à la main.
     */
    public static function statutsManuels(): array
    {
        $statuts = self::STATUTS;
        unset($statuts['remplacee']);
        return $statuts;
    }

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

    /**
     * Cotation courante d'un dossier : la dernière version non remplacée.
     */
    public static function latestForDossier(int $dossierId): ?array
    {
        foreach (self::versionsForDossier($dossierId) as $row) {
            if ($row['statut'] !== 'remplacee') {
                return $row;
            }
        }
        return null;
    }

    /**
     * [ajouté 06/10, étape 3] Toutes les versions de cotation d'un dossier,
     * de la plus récente à la plus ancienne (onglet "Cotations client").
     * Chaque ligne porte `nb_lignes` (articles détaillés).
     */
    public static function versionsForDossier(int $dossierId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT co.*, c.nom AS client_nom,
                    (SELECT COUNT(*) FROM cotation_items ci WHERE ci.cotation_id = co.id) AS nb_lignes
             FROM cotations co
             INNER JOIN clients c ON c.id = co.client_id
             WHERE co.dossier_id = ? ORDER BY co.version DESC, co.id DESC'
        );
        $stmt->execute([$dossierId]);
        return $stmt->fetchAll();
    }

    /**
     * [ajouté 06/10, étape 3] Qui a marqué la cotation "envoyée", et quand
     * — lu dans le journal d'audit (action changement_statut_cotation,
     * détail "envoyee") plutôt que dans de nouvelles colonnes. Dernier
     * envoi en cas de plusieurs ; null si jamais marquée envoyée.
     */
    public static function envoiInfo(int $cotationId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.created_at, u.nom AS utilisateur_nom FROM audit_logs a
             LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE a.entite_type = 'cotation' AND a.entite_id = ?
               AND a.action = 'changement_statut_cotation' AND a.details = 'envoyee'
             ORDER BY a.created_at DESC, a.id DESC LIMIT 1"
        );
        $stmt->execute([$cotationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $dossierId, int $filialeId, array $data, array $items, ?array $precedente = null): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find($filialeId);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'cotation');
        $reference = Compteur::formatReference('COT', $numero);
        $now = date('Y-m-d H:i:s');

        $montantAchat = ($data['montant_achat'] ?? '') !== '' ? (float) $data['montant_achat'] : null;
        $margePourcentage = ($data['marge_pourcentage'] ?? '') !== '' ? (float) $data['marge_pourcentage'] : null;
        $montantTotal = (float) ($data['montant_total'] ?? 0);
        // HT / TVA / TTC : montant_total reste le TTC facturé. Sans HT saisi (anciens formulaires) on garde l'ancien calcul.
        $tvaTaux = ($data['tva_taux'] ?? '') !== '' ? (float) $data['tva_taux'] : null;
        $montantHt = ($data['montant_ht'] ?? '') !== '' ? (float) $data['montant_ht'] : null;
        if ($montantHt === null && $tvaTaux !== null && $montantTotal > 0) {
            $montantHt = round($montantTotal / (1 + $tvaTaux / 100), 2);
        }
        $montantTva = $montantHt !== null ? round($montantTotal - $montantHt, 2) : null;
        $tauxChange = ($data['taux_change'] ?? '') !== '' && (float) $data['taux_change'] > 0 ? (float) $data['taux_change'] : null;
        // Remises : le HT saisi est le NET. On retrouve le brut : net = (brut - remises lignes) x (1 - remise globale %).
        $remiseLignes = 0.0;
        foreach ($items as $it) {
            $r = (float) str_replace(',', '.', (string) ($it['remise_pct'] ?? 0));
            $q = (float) str_replace(',', '.', (string) ($it['quantite'] ?? 0));
            $pu = (float) str_replace(',', '.', (string) ($it['prix_unitaire'] ?? 0));
            if (trim((string) ($it['designation'] ?? '')) !== '' && $r > 0) {
                $remiseLignes += $q * $pu * min(100.0, $r) / 100;
            }
        }
        $remiseLignes = round($remiseLignes, 2);
        $remiseGlobalePct = ($data['remise_globale_pct'] ?? '') !== '' ? max(0.0, min(100.0, (float) str_replace(',', '.', (string) $data['remise_globale_pct']))) : 0.0;
        $montantBrut = $remiseGlobaleMontant = null;
        if ($montantHt !== null && ($remiseLignes > 0 || $remiseGlobalePct > 0)) {
            $apresLignes = $remiseGlobalePct < 100 ? $montantHt / (1 - $remiseGlobalePct / 100) : $montantHt;
            $remiseGlobaleMontant = round($apresLignes - $montantHt, 2);
            $montantBrut = round($apresLignes + $remiseLignes, 2);
        }
        // Transport « inclus dans le prix » : le coût imputé de la réservation d'expédition réduit la marge (coût rendu).
        $transportMode = in_array($data['transport_mode'] ?? '', ['inclus', 'a_part'], true) ? $data['transport_mode'] : null;
        $transportCout = $transportMode === 'inclus' && ($data['transport_cout'] ?? '') !== '' ? (float) $data['transport_cout'] : null;
        $margeMontant = ($montantAchat !== null) ? round(($montantHt ?? $montantTotal) - $montantAchat - ($transportCout ?? 0), 2) : null;

        // [ajouté 06/10, étape 3] Nouvelle version : même référence que la
        // version remplacée, numéro de version incrémenté, ancienne ligne
        // passée à "remplacee" (même mécanisme que Offre::create).
        $version = $precedente ? ((int) $precedente['version'] + 1) : 1;
        if ($precedente) {
            $reference = $precedente['reference'];
        }

        $pdo->beginTransaction();
        try {
            $cotationId = \App\Core\Schema::insert('cotations', [
                'dossier_id' => $dossierId,
                'filiale_id' => $filialeId,
                'offre_id' => ($data['offre_id'] ?? '') ?: null,
                'client_id' => $data['client_id'] ?? null,
                'reference' => $reference,
                'montant_achat' => $montantAchat,
                'marge_pourcentage' => $margePourcentage,
                'marge_montant' => $margeMontant,
                'montant_total' => $montantTotal,
                'devise' => trim($data['devise'] ?? ''),
                'mode_paiement_negocie' => trim($data['mode_paiement_negocie'] ?? ''),
                'incoterm_client' => trim($data['incoterm_client'] ?? ''),
                'validite_devis' => ($data['validite_devis'] ?? '') ?: null,
                'statut' => 'brouillon',
                'notes' => trim($data['notes'] ?? ''),
                'version' => $version,
                'cotation_precedente_id' => $precedente ? (int) $precedente['id'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'taux_change' => $tauxChange,
                'montant_ht' => $montantHt,
                'tva_taux' => $tvaTaux,
                'montant_tva' => $montantTva,
                'devise_achat' => ($data['devise_achat'] ?? '') !== '' ? trim($data['devise_achat']) : null,
                'montant_brut' => $montantBrut,
                'remise_lignes_montant' => $montantBrut !== null ? $remiseLignes : null,
                'remise_globale_pct' => $remiseGlobalePct > 0 ? $remiseGlobalePct : null,
                'remise_globale_montant' => $remiseGlobaleMontant,
                'remise_motif' => $remiseGlobalePct > 0 ? mb_substr(trim((string) ($data['remise_motif'] ?? '')), 0, 255) : null,
                'adresse_livraison' => trim((string) ($data['adresse_livraison'] ?? '')) ?: null,
                'transport_mode' => $transportMode,
                'transport_reservation_id' => $transportMode && (int) ($data['transport_reservation_id'] ?? 0) > 0 ? (int) $data['transport_reservation_id'] : null,
                'transport_prix' => $transportMode === 'a_part' && ($data['transport_prix'] ?? '') !== '' ? (float) $data['transport_prix'] : null,
                'transport_cout' => $transportCout,
                'transport_devise' => $transportMode ? (trim((string) ($data['transport_devise'] ?? '')) ?: null) : null,
            ]);

            if ($precedente) {
                $pdo->prepare("UPDATE cotations SET statut = 'remplacee', updated_at = ? WHERE id = ?")
                    ->execute([$now, (int) $precedente['id']]);
            }

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
        // "remplacee" n'est jamais posé à la main (voir create() / statutsManuels()).
        if (!array_key_exists($statut, self::statutsManuels())) {
            throw new \InvalidArgumentException('Statut de cotation invalide.');
        }
        $stmt = Database::connection()->prepare('UPDATE cotations SET statut = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$statut, date('Y-m-d H:i:s'), $id]);
        if (self::colonnesPerte()) {
            // Date de perte à l'entrée en « refusée » ; effacée (ainsi que le motif) si le devis est rouvert.
            if ($statut === 'refusee') {
                Database::connection()->prepare('UPDATE cotations SET perdu_le = COALESCE(perdu_le, ?) WHERE id = ?')->execute([date('Y-m-d H:i:s'), $id]);
            } else {
                Database::connection()->prepare('UPDATE cotations SET perdu_le = NULL, motif_perte = NULL, motif_perte_detail = NULL WHERE id = ?')->execute([$id]);
            }
        }
    }

    public static function userCanAccess(array $user, array $cotation): bool
    {
        return Filiale::userCanAccess($user, (int) $cotation['filiale_id']);
    }

    /**
     * Cotations envoyées au client, en attente de réponse — bloc
     * "commercial" du tableau de bord ("cotations à relancer").
     */
    /**
     * [ajouté 03/10] $filialeIds/$activite : filtres optionnels du switcher
     * Tableau de bord (voir DashboardController) — null = comportement
     * d'origine. Jointure sur `demandes` (via le Dossier) dès que
     * `$activite` est fourni.
     */
    public static function aRelancerCount(array $user, ?array $filialeIds = null, ?string $activite = null): int
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $params = $filialeIds;
        if ($activite) {
            $sql = "SELECT COUNT(*) FROM cotations co
                    INNER JOIN dossiers d ON d.id = co.dossier_id
                    INNER JOIN demandes dm ON dm.id = d.demande_id
                    WHERE co.statut = 'envoyee' AND co.filiale_id IN ($placeholders) AND dm.activite = ?" . Demande::sousFiltresSql('dm') . \App\Models\ActiviteAcces::sqlDossier('co.dossier_id');
            $params[] = $activite;
        } else {
            $sql = "SELECT COUNT(*) FROM cotations WHERE statut = 'envoyee' AND filiale_id IN ($placeholders)" . \App\Models\ActiviteAcces::sqlDossier('dossier_id');
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function aRelancerFor(array $user, int $limite = 5, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT co.*, d.reference AS dossier_reference, d.objet AS dossier_objet, d.responsable_id, cl.nom AS client_nom
                FROM cotations co
                INNER JOIN dossiers d ON d.id = co.dossier_id
                INNER JOIN clients cl ON cl.id = co.client_id";
        $params = $filialeIds;
        if ($activite) {
            $sql .= ' INNER JOIN demandes dm ON dm.id = d.demande_id';
        }
        $sql .= " WHERE co.statut = 'envoyee' AND co.filiale_id IN ($placeholders)" . \App\Models\ActiviteAcces::sqlDossier('co.dossier_id');
        if ($activite) {
            $sql .= ' AND dm.activite = ?' . Demande::sousFiltresSql('dm');
            $params[] = $activite;
        }
        $sql .= ' ORDER BY co.created_at ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Cotations envoyées et toujours sans réponse depuis plus de
     * $seuilJours jours — sous-ensemble "à risque" de aRelancerFor(), pour
     * le bloc Alertes du tableau de bord (spec : signaler ce qui traîne,
     * pas seulement compter).
     */
    public static function enAttenteDepuis(array $user, int $seuilJours, int $limite = 10, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $seuilDate = date('Y-m-d H:i:s', strtotime("-$seuilJours days"));
        $sql = "SELECT co.*, d.id AS dossier_id, d.reference AS dossier_reference, d.objet AS dossier_objet, cl.nom AS client_nom
                FROM cotations co
                INNER JOIN dossiers d ON d.id = co.dossier_id
                INNER JOIN clients cl ON cl.id = co.client_id";
        if ($activite) {
            $sql .= ' INNER JOIN demandes dm ON dm.id = d.demande_id';
        }
        $sql .= " WHERE co.statut = 'envoyee' AND co.filiale_id IN ($placeholders) AND co.created_at <= ?" . \App\Models\ActiviteAcces::sqlDossier('co.dossier_id');
        $params = array_merge($filialeIds, [$seuilDate]);
        if ($activite) {
            $sql .= ' AND dm.activite = ?' . Demande::sousFiltresSql('dm');
            $params[] = $activite;
        }
        $sql .= ' ORDER BY co.created_at ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
