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

    /**
     * Type de dossier (section 9 de la feuille de route, verrouillé 29/09) :
     * discriminant qui active/masque des champs additionnels sur Offre/Commande
     * et adapte le vocabulaire affiché (voir libelleFournisseur()) — ne change
     * jamais la forme du pipeline (Consultation → Offres → Comparateur →
     * Cotation → Commande), identique pour les 4 types.
     */
    public const TYPES = ['achat_sourcing', 'transport_logistique', 'prestation_entreprise', 'autre'];

    public const TYPES_LABELS = [
        'achat_sourcing' => 'Achat / Sourcing',
        'transport_logistique' => 'Transport / Logistique',
        'prestation_entreprise' => 'Prestation entreprise',
        'autre' => 'Autre',
    ];

    /**
     * Déduction automatique du type de dossier à partir de l'Activité de la
     * demande d'origine (mapping verrouillé 29/09) — reste modifiable
     * manuellement après coup si le cas déborde du mapping.
     */
    public const ACTIVITE_TO_TYPE = [
        'Sourcing et approvisionnement' => 'achat_sourcing',
        'Import' => 'achat_sourcing',
        'Export' => 'achat_sourcing',
        'Négoce international' => 'achat_sourcing',
        'Transport et logistique' => 'transport_logistique',
        'Dédouanement et transit' => 'transport_logistique',
        'Représentation commerciale' => 'prestation_entreprise',
        'Autre' => 'autre',
    ];

    public static function deduireType(?string $activite): string
    {
        return self::ACTIVITE_TO_TYPE[$activite ?? ''] ?? 'autre';
    }

    /**
     * Vocabulaire adaptatif : une seule fonction centralisée pour le mot
     * utilisé à la place de "Fournisseur" selon le type de dossier (même
     * esprit que ETAPES_LABELS) — chaque vue l'appelle au lieu d'écrire le
     * mot en dur.
     */
    public static function libelleFournisseur(?string $type): string
    {
        return match ($type) {
            'transport_logistique' => 'Transitaire',
            'prestation_entreprise' => 'Prestataire',
            default => 'Fournisseur',
        };
    }

    /**
     * [ajouté 06/10, report de la maquette Dossiers] Onglets de la fiche
     * Dossier — voir DossierController::show().
     */
    public const ONGLETS = ['synthese', 'besoin', 'achats', 'cotations', 'execution', 'documents', 'equipe'];

    /**
     * [ajouté 06/10, report de la maquette Dossiers, cahier des charges
     * section 9] Sous-type d'un dossier "Prestation entreprise" — n'existe
     * pas pour les 3 autres types de dossier. Discriminant purement
     * d'affichage (comme TYPES ci-dessus) : adapte seulement les 2 champs
     * de mesure de l'onglet Exécution (voir TYPES_PRESTATION_CHAMPS), sans
     * jamais changer le parcours (évaluation → chiffrage → cotation →
     * accord client → intervention → clôturé, identique pour tous les
     * types de prestation). La case "Visite terrain nécessaire" (colonne
     * visite_terrain_necessaire) est volontairement indépendante de ce
     * type : c'est une case à cocher manuelle, jamais déduite ou
     * pré-cochée différemment selon le type_prestation — point validé
     * explicitement par Marie Laure le 06/10 ("pas une règle automatique
     * figée par type dans le code"). Proposé à Marie Laure et validé le
     * 06/10 avant codage (voir modules-verrouilles.md section 2).
     */
    public const TYPES_PRESTATION = ['phytosanitaire', 'maintenance', 'nettoyage', 'installation', 'autre'];

    public const TYPES_PRESTATION_LABELS = [
        'phytosanitaire' => 'Phytosanitaire',
        'maintenance' => 'Maintenance',
        'nettoyage' => 'Nettoyage',
        'installation' => 'Installation',
        'autre' => 'Autre',
    ];

    /**
     * Libellés des 2 champs de mesure adaptatifs selon le type de
     * prestation (le second est absent pour "autre", qui n'a qu'un champ
     * libre "Précisions") — validés par Marie Laure le 06/10. Stockage : 2
     * colonnes génériques (prestation_mesure_1/2) plutôt que des colonnes
     * dédiées par type, dans le même esprit minimal que
     * demandes.qualification_notes (voir section 1 de modules-verrouilles.md)
     * — point signalé comme "à affiner plus tard" par Marie Laure, pas figé
     * définitivement.
     */
    public const TYPES_PRESTATION_CHAMPS = [
        'phytosanitaire' => ['Surface à traiter', 'Type de nuisible / traitement'],
        'maintenance' => ["Équipement / installation concernée", "Fréquence d'entretien prévue"],
        'nettoyage' => ['Surface / zone à nettoyer', 'Nature de la salissure'],
        'installation' => ['Équipement à installer', 'Spécifications techniques'],
        'autre' => ['Précisions', null],
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

            $typeDossier = $data['type_dossier'] ?? '';
            if (!in_array($typeDossier, self::TYPES, true)) {
                $typeDossier = self::deduireType($demande['activite'] ?? null);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO dossiers
                 (demande_id, filiale_id, reference, objet, etape, statut, type_dossier, responsable_id, priorite, echeance, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                $demandeId,
                $demande['filiale_id'],
                $reference,
                $demande['objet'],
                'qualifie',
                'actif',
                $typeDossier,
                (\App\Core\Tenant::responsableDeFiliale($data['responsable_id'] ?? null, (int) $demande['filiale_id']) ?: $demande['responsable_id']),
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

    /**
     * Pourcentage d'avancement dans le pipeline du dossier (qualifié → ...
     * → clôturé), basé sur la position de l'étape courante dans ETAPES.
     * qualifié = 0 %, clôturé = 100 %, les étapes intermédiaires réparties
     * également entre les deux (demande de Marie Laure, 03/10 : voir en un
     * coup d'œil à quel niveau se situe chaque dossier).
     */
    public static function progression(string $etape): int
    {
        $index = array_search($etape, self::ETAPES, true);
        if ($index === false) {
            return 0;
        }
        $total = count(self::ETAPES) - 1;
        return $total > 0 ? (int) round($index / $total * 100) : 0;
    }

    /**
     * Liste complète des étapes avec leur état par rapport à l'étape
     * courante ('fait' / 'en_cours' / 'a_venir') — pour afficher un
     * mini-stepper listant toutes les étapes plutôt que seulement l'étape
     * en cours.
     */
    public static function etapesAvecStatut(string $etapeActuelle): array
    {
        $indexActuel = array_search($etapeActuelle, self::ETAPES, true);
        $result = [];
        foreach (self::ETAPES as $i => $etape) {
            if ($indexActuel === false) {
                $etat = 'a_venir';
            } elseif ($i < $indexActuel) {
                $etat = 'fait';
            } elseif ($i === $indexActuel) {
                $etat = 'en_cours';
            } else {
                $etat = 'a_venir';
            }
            $result[] = ['code' => $etape, 'label' => self::ETAPES_LABELS[$etape], 'etat' => $etat];
        }
        return $result;
    }

    public static function updateType(int $id, string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Type de dossier invalide.');
        }
        $stmt = Database::connection()->prepare('UPDATE dossiers SET type_dossier = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$type, date('Y-m-d H:i:s'), $id]);
    }

    public static function updateNotes(int $id, string $notes): void
    {
        $stmt = Database::connection()->prepare('UPDATE dossiers SET notes = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$notes, date('Y-m-d H:i:s'), $id]);
    }

    /**
     * [ajouté 06/10, report de la maquette Dossiers] Onglet Exécution,
     * déclinaison Prestation entreprise — type de prestation, case "visite
     * terrain nécessaire" (jamais déduite automatiquement du type, voir
     * TYPES_PRESTATION_CHAMPS ci-dessus) et les 2 champs de mesure
     * adaptatifs. Volontairement permissif sur $typePrestation (pas
     * d'exception si vide/inconnu) : ce bloc ne s'affiche que sur un
     * dossier "Prestation entreprise", et un type non choisi reste un état
     * valide ("À préciser") plutôt qu'une erreur bloquante.
     */
    /**
     * [étendu 06/10, migrate_v16.php] $evaluation porte les champs de la
     * carte "Évaluation du besoin" de la maquette (site, technicien, délai
     * estimé, constat, contraintes d'intervention) — report fidèle demandé
     * par Marie Laure le 06/10, distinct des "contraintes" de qualification
     * de la Demande affichées sur l'onglet Besoin (voir migrate_v16.php).
     * Clés optionnelles, toutes nullables : 'site', 'technicien',
     * 'delai_estime', 'constat', 'contraintes'.
     */
    public static function updatePrestation(int $id, ?string $typePrestation, bool $visiteTerrainNecessaire, ?string $mesure1, ?string $mesure2, array $evaluation = []): void
    {
        if ($typePrestation !== null && !in_array($typePrestation, self::TYPES_PRESTATION, true)) {
            throw new \InvalidArgumentException('Type de prestation invalide.');
        }
        $stmt = Database::connection()->prepare(
            'UPDATE dossiers SET type_prestation = ?, visite_terrain_necessaire = ?, prestation_mesure_1 = ?, prestation_mesure_2 = ?,
             prestation_site = ?, prestation_technicien = ?, prestation_delai_estime = ?, prestation_constat = ?, prestation_contraintes = ?,
             updated_at = ? WHERE id = ?'
        );
        $stmt->execute([
            $typePrestation,
            $visiteTerrainNecessaire ? 1 : 0,
            $mesure1,
            $mesure2,
            $evaluation['site'] ?? null,
            $evaluation['technicien'] ?? null,
            $evaluation['delai_estime'] ?? null,
            $evaluation['constat'] ?? null,
            $evaluation['contraintes'] ?? null,
            date('Y-m-d H:i:s'),
            $id,
        ]);
    }

    /**
     * [ajouté 03/10] $filialeIds/$activite : filtres optionnels du switcher
     * Tableau de bord (voir DashboardController) — null = comportement
     * d'origine. L'Activité n'existe que sur la Demande d'origine, d'où la
     * jointure sur `demandes` dès que `$activite` est fourni.
     */
    public static function counts(array $user, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return ['actifs' => 0, 'en_retard' => 0];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $join = $activite ? ' INNER JOIN demandes dm ON dm.id = d.demande_id' : '';
        $clauseActivite = $activite ? ' AND dm.activite = ?' : '';

        $sql = "SELECT COUNT(*) FROM dossiers d$join WHERE d.statut = 'actif' AND d.filiale_id IN ($placeholders)$clauseActivite";
        $params = $activite ? array_merge($filialeIds, [$activite]) : $filialeIds;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $actifs = (int) $stmt->fetchColumn();

        $sql = "SELECT COUNT(*) FROM dossiers d$join
                WHERE d.statut = 'actif' AND d.echeance IS NOT NULL AND d.echeance < ?
                AND d.filiale_id IN ($placeholders)$clauseActivite";
        $params = array_merge([date('Y-m-d')], $filialeIds);
        if ($activite) {
            $params[] = $activite;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $enRetard = (int) $stmt->fetchColumn();

        return ['actifs' => $actifs, 'en_retard' => $enRetard];
    }

    /**
     * Dossiers actifs dont l'échéance arrive dans les prochains jours (pas
     * encore en retard) — bloc "échéances à venir" du tableau de bord.
     */
    public static function echeancesAVenirFor(array $user, int $jours = 7, int $limite = 8, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $join = $activite ? ' INNER JOIN demandes dm ON dm.id = d.demande_id' : '';
        $sql = "SELECT d.* FROM dossiers d$join
                WHERE d.filiale_id IN ($placeholders)
                AND d.statut = 'actif'
                AND d.echeance IS NOT NULL AND d.echeance >= ? AND d.echeance <= ?";
        $params = array_merge($filialeIds, [date('Y-m-d'), date('Y-m-d', strtotime('+' . $jours . ' days'))]);
        if ($activite) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $activite;
        }
        $sql .= ' ORDER BY d.echeance ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
