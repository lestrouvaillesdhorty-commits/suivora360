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

    // Urgence — 4 niveaux (Demande ET Dossier, qui hérite de la valeur à la création)
    public const PRIORITES = [
        'basse' => 'Basse',
        'normale' => 'Normale',
        'haute' => 'Haute',
        'critique' => 'Critique',
    ];

    public const PRIORITE_BADGES = [
        'basse' => 'badge-gray',
        'normale' => 'badge-blue',
        'haute' => 'badge-orange',
        'critique' => 'badge-red',
    ];

    // Libellé + classe de badge par statut — source unique utilisée par
    // index.php et show.php (évite la dérive entre les deux vues ; [réécrit
    // 04/10], cohérence visuelle du module Demandes).
    public const STATUT_BADGES = [
        'a_qualifier' => ['À qualifier', 'badge-yellow'],
        'en_attente_info' => ["En attente d'infos", 'badge-yellow'],
        'qualifiee' => ['Qualifiée', 'badge-blue'],
        'rattachee' => ['Rattachée', 'badge-blue'],
        'rejetee' => ['Rejetée', 'badge-red'],
        'archivee' => ['Archivée', 'badge-gray'],
    ];

    // Activité : un simple champ à liste de valeurs sur la Demande (repris
    // par le Dossier via une jointure sur demande_id, voir Pilotage) — pas
    // un module séparé (décision confirmée avec Marie Laure, feuille de
    // route section 7).
    public const ACTIVITES = [
        'Sourcing et approvisionnement',
        'Transport et logistique',
        'Import',
        'Export',
        'Dédouanement et transit',
        'Négoce international',
        'Représentation commerciale',
        'Prestation de service',
        'Autre',
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
     * Historique des demandes rattachées à ce client, tous dossiers
     * confondus (même esprit que ConsultationFournisseur::forFournisseur
     * côté Fournisseur) — demande de Marie Laure, 03/10.
     */
    public static function forClient(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT de.*, d.id AS dossier_id, d.reference AS dossier_reference
             FROM demandes de
             LEFT JOIN dossiers d ON d.demande_id = de.id
             WHERE de.client_id = ?
             ORDER BY de.created_at DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    // Taille de page par défaut pour la liste des demandes (section 1 de la
    // refonte du 04/10 — "Tri explicite et pagination").
    public const PAR_PAGE = 20;

    /** Sur téléphone, 10 lignes par page (moins de défilement). */
    public static function parPage(): int
    {
        return preg_match('/Mobi|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT'] ?? '') ? 10 : self::PAR_PAGE;
    }

    public const TRIS = [
        'created_at' => 'Date de réception (défaut)',
        'echeance' => 'Échéance',
        'priorite' => 'Priorité',
        'reference' => 'Référence',
    ];

    /**
     * [ajouté 04/10, refonte du module Demandes] Construit la clause WHERE
     * commune à visibleFor()/countVisibleFor() — un seul point de vérité
     * pour la liste des filtres combinables (filiale, activité, statut,
     * responsable, priorité, canal, période, recherche élargie), partagé
     * entre le comptage (pour la pagination) et la liste elle-même.
     *
     * `statut` accepte, en plus des statuts réellement stockés, deux statuts
     * dérivés (jamais enregistrés en base, calculés à la volée comme pour
     * Dossier) :
     * - 'en_retard' : pas encore qualifiée et échéance dépassée.
     * - 'transformee' : qualifiée ET un dossier existe déjà pour cette
     *   demande (le statut littéral "transformee" n'a jamais été utilisé
     *   par le code — Dossier::createFromDemande() laisse la demande à
     *   "qualifiee" — donc le filtre se base sur l'existence réelle du
     *   dossier plutôt que sur une valeur qui ne serait jamais trouvée).
     *
     * `responsable_id` accepte aussi 'non_assigne' (IS NULL) et 'me'
     * (résolu côté appelant vers l'id de l'utilisateur courant).
     */
    private static function buildVisibleWhere(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (!empty($filters['filiale_id']) && in_array((int) $filters['filiale_id'], $filialeIds, true)) {
            $filialeIds = [(int) $filters['filiale_id']];
        }
        if (empty($filialeIds)) {
            return ['', [], []];
        }

        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "FROM demandes d
                INNER JOIN filiales f ON f.id = d.filiale_id
                LEFT JOIN clients c ON c.id = d.client_id
                WHERE d.filiale_id IN ($placeholders)";
        $params = $filialeIds;

        if (!empty($filters['activite'])) {
            $sql .= ' AND d.activite = ?';
            $params[] = $filters['activite'];
        }

        if (!empty($filters['statut']) && $filters['statut'] === 'en_retard') {
            $sql .= " AND d.statut IN ('a_qualifier', 'en_attente_info') AND d.echeance IS NOT NULL AND d.echeance < ?";
            $params[] = date('Y-m-d');
        } elseif (!empty($filters['statut']) && $filters['statut'] === 'transformee') {
            $sql .= " AND d.statut = 'qualifiee' AND EXISTS (SELECT 1 FROM dossiers dos WHERE dos.demande_id = d.id)";
        } elseif (!empty($filters['statut'])) {
            $sql .= ' AND d.statut = ?';
            $params[] = $filters['statut'];
        }

        if (!empty($filters['responsable_id']) && $filters['responsable_id'] === 'non_assigne') {
            $sql .= ' AND d.responsable_id IS NULL';
        } elseif (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = (int) $filters['responsable_id'];
        }

        if (!empty($filters['priorite'])) {
            $sql .= ' AND d.priorite = ?';
            $params[] = $filters['priorite'];
        }

        if (!empty($filters['canal'])) {
            $sql .= ' AND d.canal = ?';
            $params[] = $filters['canal'];
        }

        if (!empty($filters['recherche'])) {
            // Recherche élargie : référence, objet, contact (expéditeur ou
            // client déjà qualifié), entreprise.
            $sql .= ' AND (d.reference LIKE ? OR d.objet LIKE ? OR d.expediteur_nom LIKE ? OR d.expediteur_entreprise LIKE ? OR c.nom LIKE ?)';
            $like = '%' . $filters['recherche'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($filters['date_debut'])) {
            $sql .= ' AND d.recue_le >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= ' AND d.recue_le <= ?';
            $params[] = $filters['date_fin'];
        }

        return [$sql, $params, $filialeIds];
    }

    /**
     * Liste des demandes visibles pour l'utilisateur (filtrées par filiales
     * autorisées et filtres combinables). $pagination = ['page' => n] pour
     * appliquer une page de PAR_PAGE lignes ; null (défaut, utilisé par
     * l'export CSV) renvoie tout le résultat sans découpage.
     */
    public static function visibleFor(array $user, array $filters = [], ?array $pagination = null): array
    {
        [$where, $params] = self::buildVisibleWhere($user, $filters);
        if ($where === '') {
            return [];
        }

        $tri = array_key_exists($filters['tri'] ?? '', self::TRIS) ? $filters['tri'] : 'created_at';
        $sens = (($filters['sens'] ?? '') === 'asc') ? 'ASC' : 'DESC';
        $orderBy = match ($tri) {
            'echeance' => "d.echeance IS NULL, d.echeance $sens",
            'priorite' => "CASE d.priorite WHEN 'critique' THEN 4 WHEN 'haute' THEN 3 WHEN 'normale' THEN 2 WHEN 'basse' THEN 1 ELSE 0 END $sens",
            'reference' => "d.reference $sens",
            default => "d.created_at $sens",
        };

        $sql = "SELECT d.*, f.nom AS filiale_nom $where ORDER BY $orderBy";
        if ($pagination !== null) {
            $page = max(1, (int) ($pagination['page'] ?? 1));
            $sql .= ' LIMIT ' . self::parPage() . ' OFFSET ' . (($page - 1) * self::parPage());
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Nombre total de demandes correspondant aux filtres (hors pagination) —
     * sert à calculer le nombre de pages.
     */
    public static function countVisibleFor(array $user, array $filters = []): int
    {
        [$where, $params] = self::buildVisibleWhere($user, $filters);
        if ($where === '') {
            return 0;
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Compteurs des "vues rapides" de la liste (Toutes / À qualifier / Mes
     * demandes / En retard / Archivées) — toujours calculés sur l'ensemble
     * des demandes accessibles, indépendamment des autres filtres déjà
     * choisis (même logique qu'une boîte mail : les compteurs des vues ne
     * bougent pas quand on affine par ailleurs).
     */
    public static function compteursVuesRapides(array $user): array
    {
        return [
            'toutes' => self::countVisibleFor($user, []),
            'a_qualifier' => self::countVisibleFor($user, ['statut' => 'a_qualifier']),
            'mes_demandes' => self::countVisibleFor($user, ['responsable_id' => (string) $user['id']]),
            'en_retard' => self::countVisibleFor($user, ['statut' => 'en_retard']),
            'archivees' => self::countVisibleFor($user, ['statut' => 'archivee']),
        ];
    }

    /**
     * [ajouté 05/10, report de la maquette du 05/10 dans le vrai code] KPI
     * affichés en haut de la liste des demandes (Reçues ce mois / À
     * qualifier / En retard / Qualifiées ce mois). Même esprit que
     * compteursVuesRapides() : des repères stables, indépendants des
     * filtres secondaires (statut, responsable, canal...) déjà posés par
     * l'utilisateur — seul le filtre Filiale, qui change le périmètre
     * affiché par la liste elle-même, influence ces chiffres.
     */
    public static function kpisListe(array $user, ?int $filialeId = null): array
    {
        $filtreBase = $filialeId ? ['filiale_id' => $filialeId] : [];
        $debutMois = date('Y-m-01');
        $finMois = date('Y-m-t');

        $filialeIds = Filiale::visibleIdsFor($user);
        if ($filialeId && in_array($filialeId, $filialeIds, true)) {
            $filialeIds = [$filialeId];
        }
        $qualifieesCeMois = 0;
        if (!empty($filialeIds)) {
            $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM demandes
                 WHERE filiale_id IN ($placeholders)
                   AND statut IN ('qualifiee', 'rattachee')
                   AND qualified_at >= ? AND qualified_at <= ?"
            );
            $stmt->execute([...$filialeIds, $debutMois . ' 00:00:00', $finMois . ' 23:59:59']);
            $qualifieesCeMois = (int) $stmt->fetchColumn();
        }

        return [
            'recues_mois' => self::countVisibleFor($user, $filtreBase + ['date_debut' => $debutMois, 'date_fin' => $finMois]),
            'a_qualifier' => self::countVisibleFor($user, $filtreBase + ['statut' => 'a_qualifier']),
            'en_retard' => self::countVisibleFor($user, $filtreBase + ['statut' => 'en_retard']),
            'qualifiees_mois' => $qualifieesCeMois,
        ];
    }

    /**
     * [ajouté 05/10, report de la maquette] Dossier lié à une demande
     * Qualifiée (voie Nouveau dossier) ou Rattachée (voie Complément, soit
     * directement via linked_dossier_id, soit indirectement via
     * linked_request_id → le dossier de la demande d'origine) — utilisé
     * pour le lien "Voir le dossier →" sur la liste et la fiche. Retourne
     * null si la demande n'est pas dans un de ces deux statuts ou si aucun
     * dossier n'est finalement rattaché (ne devrait pas arriver en usage
     * normal, mais pas d'hypothèse risquée).
     */
    public static function dossierLieId(array $demande): ?int
    {
        if (($demande['statut'] ?? null) === 'qualifiee') {
            $dossier = Dossier::findByDemande((int) $demande['id']);
            return $dossier['id'] ?? null;
        }
        if (($demande['statut'] ?? null) === 'rattachee') {
            if (!empty($demande['linked_dossier_id'])) {
                return (int) $demande['linked_dossier_id'];
            }
            if (!empty($demande['linked_request_id'])) {
                $dossier = Dossier::findByDemande((int) $demande['linked_request_id']);
                return $dossier['id'] ?? null;
            }
        }
        return null;
    }

    /**
     * Valeurs de canal distinctes déjà utilisées (filiales visibles) — pour
     * peupler le filtre Canal sans figer une liste en dur qui désynchroniserait
     * de l'import CSV (qui accepte n'importe quel texte de canal).
     */
    public static function distinctCanaux(array $filialeIds): array
    {
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT canal FROM demandes WHERE filiale_id IN ($placeholders) AND canal IS NOT NULL AND canal != '' ORDER BY canal"
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $data = self::assainirIdentifiants((int) $data['filiale_id'], $data);
        $filiale = Filiale::find((int) $data['filiale_id']);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'demande');
        $reference = Compteur::formatReference('DEM', $numero);

        $stmt = $pdo->prepare(
            'INSERT INTO demandes
             (filiale_id, reference, objet, message, canal, expediteur_nom, expediteur_entreprise, expediteur_email, expediteur_telephone, recue_le, activite, responsable_id, priorite, echeance, date_souhaitee_client, notes_internes, client_id, statut, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            $data['date_souhaitee_client'] ?: null,
            $data['notes_internes'] ?? '',
            $data['client_id'] ?: null,
            'a_qualifier',
            date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Modification d'une demande (écran 2, voie "modification") — tous les
     * champs de saisie de la création restent modifiables tant que la
     * demande n'est pas déjà qualifiée/rattachée/rejetée (voir
     * DemandeController::update() pour la vérification d'état ; ce modèle
     * n'impose pas lui-même cette règle pour rester réutilisable).
     */
    /** Filiale d'une demande (0 si introuvable). */
    private static function filialeDe(int $demandeId): int
    {
        $stmt = Database::connection()->prepare('SELECT filiale_id FROM demandes WHERE id = ?');
        $stmt->execute([$demandeId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Isolation multi-entreprises : un client_id ou responsable_id reçu d'un
     * formulaire n'est conservé que s'il appartient à la même organisation que
     * la demande (garde-fou de base ; le contrôleur affine selon les filiales visibles) ;
     * sinon il est ignoré (null). Voir App\Core\Tenant.
     */
    private static function assainirIdentifiants(int $filialeId, array $data): array
    {
        if (array_key_exists('client_id', $data)) {
            $org = \App\Core\Tenant::organisationDeFiliale($filialeId);
            $data['client_id'] = $org === null ? null : \App\Core\Tenant::clientDOrganisation($data['client_id'], $org);
        }
        if (array_key_exists('responsable_id', $data)) {
            $data['responsable_id'] = \App\Core\Tenant::responsableDeFiliale($data['responsable_id'], $filialeId);
        }
        return $data;
    }

    public static function update(int $id, array $data): void
    {
        $data = self::assainirIdentifiants(self::filialeDe($id), $data);
        $stmt = Database::connection()->prepare(
            'UPDATE demandes SET
                objet = ?, message = ?, canal = ?, expediteur_nom = ?, expediteur_entreprise = ?,
                expediteur_email = ?, expediteur_telephone = ?, recue_le = ?, activite = ?,
                responsable_id = ?, priorite = ?, echeance = ?, date_souhaitee_client = ?,
                notes_internes = ?, client_id = ?
             WHERE id = ?'
        );
        $stmt->execute([
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
            $data['date_souhaitee_client'] ?: null,
            $data['notes_internes'] ?? '',
            $data['client_id'] ?: null,
            $id,
        ]);
    }

    public static function markQualifiee(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE demandes SET statut = 'qualifiee' WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * [ajouté 04/10] Archivage manuel — statut prévu depuis l'origine dans
     * les filtres de la liste mais jamais appliqué nulle part : on range une
     * demande dont le traitement est terminé (rejetée, ou qualifiée/rattachée
     * de longue date) sans jamais la supprimer. Réversible (voir
     * desarchiver()).
     */
    public static function archiver(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE demandes SET statut = 'archivee' WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Désarchivage : on ne connaît pas toujours le statut antérieur exact
     * une fois archivé (l'information n'est pas conservée séparément), donc
     * on retombe sur "qualifiee" si une qualification a déjà eu lieu,
     * sinon "a_qualifier" — jamais un statut inventé plus avancé que ce que
     * les données permettent d'affirmer.
     */
    public static function desarchiver(int $id): void
    {
        $demande = self::find($id);
        if (!$demande) {
            return;
        }
        $statutRestaure = $demande['qualification_type'] ? 'qualifiee' : 'a_qualifier';
        $stmt = Database::connection()->prepare('UPDATE demandes SET statut = ? WHERE id = ?');
        $stmt->execute([$statutRestaure, $id]);
    }

    /**
     * [ajouté 04/10] Raisons empêchant la suppression d'une demande (tableau
     * vide = suppression possible). Décision confirmée avec Marie Laure :
     * on bloque plutôt que de risquer d'orpheliner un dossier ou un
     * rattachement — aucune contrainte de clé étrangère n'existe en base
     * dans cette application, donc rien d'autre n'empêcherait techniquement
     * de créer une référence pointant vers une ligne supprimée.
     */
    public static function raisonsBlocantesSuppression(int $id): array
    {
        $pdo = Database::connection();
        $raisons = [];

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM dossiers WHERE demande_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $raisons[] = 'Un dossier a déjà été créé à partir de cette demande.';
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM demandes WHERE linked_request_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $raisons[] = "Une autre demande est rattachée à celle-ci (voie \"Complément\").";
        }

        return $raisons;
    }

    /**
     * Supprime la demande ainsi que ses articles, pièces jointes (lignes ET
     * fichiers physiques, voir DemandeController::supprimer()) — à n'appeler
     * qu'après avoir vérifié raisonsBlocantesSuppression().
     */
    public static function supprimer(int $id): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM demande_articles WHERE demande_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM demande_pieces_jointes WHERE demande_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM demandes WHERE id = ?')->execute([$id]);
    }

    /**
     * Voie 1 — Nouvelle demande : sélection (ou non) d'un client connu,
     * complément des informations de suivi. Passe au statut "qualifiee" si les
     * informations minimales sont réunies, sinon "en_attente_info".
     */
    public static function qualifyNew(int $id, array $data, int $userId): void
    {
        $data = self::assainirIdentifiants(self::filialeDe($id), $data);
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
        $data = self::assainirIdentifiants(self::filialeDe($id), $data);
        // [complété 05/10, report de la maquette] Ajout du client — manquait
        // jusqu'ici : le dossier créé depuis cette voie n'était rattaché à
        // aucun client (le lien client passe uniquement par
        // demandes.client_id, voir Dossier::createFromDemande()).
        $clientId = ($data['client_id'] ?? null) ?: null;

        $stmt = Database::connection()->prepare(
            "UPDATE demandes SET
                client_id = ?,
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
            $clientId,
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

    /**
     * Demandes actuellement "en cours" (ni rejetées ni déjà transformées en
     * dossier) — utilisé par le bloc "Suivi des opérations" du tableau de
     * bord pour donner un volume global, distinct du compteur "à qualifier".
     */
    /**
     * [ajouté 03/10] $filialeIds/$activite : filtres optionnels du switcher
     * Tableau de bord (voir DashboardController) — null = comportement
     * d'origine (toutes les filiales visibles de l'utilisateur, aucun filtre
     * d'activité). `activite` vit directement sur `demandes`, aucune
     * jointure n'est nécessaire ici (contrairement à Dossier/Offre/Cotation/
     * Commande qui la retrouvent via leur Demande d'origine).
     */
    public static function enCoursCount(array $user, ?array $filialeIds = null, ?string $activite = null): int
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT COUNT(*) FROM demandes
                WHERE statut IN ('a_qualifier','en_attente_info','qualifiee') AND filiale_id IN ($placeholders)";
        $params = $filialeIds;
        if ($activite) {
            $sql .= ' AND activite = ?';
            $params[] = $activite;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function counts(array $user, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return ['a_qualifier' => 0, 'en_retard' => 0];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $clauseActivite = $activite ? ' AND activite = ?' : '';

        $sql = "SELECT COUNT(*) FROM demandes WHERE statut = 'a_qualifier' AND filiale_id IN ($placeholders)$clauseActivite";
        $params = $activite ? array_merge($filialeIds, [$activite]) : $filialeIds;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $aQualifier = (int) $stmt->fetchColumn();

        $sql = "SELECT COUNT(*) FROM demandes
                WHERE statut IN ('a_qualifier', 'en_attente_info') AND echeance IS NOT NULL AND echeance < ?
                AND filiale_id IN ($placeholders)$clauseActivite";
        $params = array_merge([date('Y-m-d')], $filialeIds);
        if ($activite) {
            $params[] = $activite;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $enRetard = (int) $stmt->fetchColumn();

        return ['a_qualifier' => $aQualifier, 'en_retard' => $enRetard];
    }

    /**
     * Dernières demandes reçues (bloc "action rapide" du tableau de bord).
     */
    public static function recentesFor(array $user, int $limite = 5, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT d.*, f.nom AS filiale_nom FROM demandes d
                INNER JOIN filiales f ON f.id = d.filiale_id
                WHERE d.filiale_id IN ($placeholders)";
        $params = $filialeIds;
        if ($activite) {
            $sql .= ' AND d.activite = ?';
            $params[] = $activite;
        }
        $sql .= ' ORDER BY d.created_at DESC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * [ajouté 04/10, réorganisation du tableau de bord demandée par Marie
     * Laure : fusion "Alertes" + "Mes priorités" en une liste "Actions
     * prioritaires" unique] Demandes non qualifiées dont l'échéance est
     * dépassée — même clause WHERE que counts()['en_retard'], mais renvoie
     * les lignes au lieu d'un simple compte, pour alimenter cette liste
     * unifiée (action : "Qualifier la demande").
     */
    public static function enRetardFor(array $user, int $limite = 10, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT d.* FROM demandes d
                WHERE d.statut IN ('a_qualifier', 'en_attente_info') AND d.echeance IS NOT NULL AND d.echeance < ?
                AND d.filiale_id IN ($placeholders)";
        $params = array_merge([date('Y-m-d')], $filialeIds);
        if ($activite) {
            $sql .= ' AND d.activite = ?';
            $params[] = $activite;
        }
        $sql .= ' ORDER BY d.echeance ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Demandes non encore qualifiées dont l'échéance arrive dans les
     * prochains jours (pas encore en retard) — bloc "échéances à venir".
     */
    public static function echeancesAVenirFor(array $user, int $jours = 7, int $limite = 8, ?array $filialeIds = null, ?string $activite = null): array
    {
        $filialeIds = $filialeIds ?? Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT d.*, f.nom AS filiale_nom FROM demandes d
                INNER JOIN filiales f ON f.id = d.filiale_id
                WHERE d.filiale_id IN ($placeholders)
                AND d.statut IN ('a_qualifier', 'en_attente_info')
                AND d.echeance IS NOT NULL AND d.echeance >= ? AND d.echeance <= ?";
        $params = array_merge($filialeIds, [date('Y-m-d'), date('Y-m-d', strtotime('+' . $jours . ' days'))]);
        if ($activite) {
            $sql .= ' AND d.activite = ?';
            $params[] = $activite;
        }
        $sql .= ' ORDER BY d.echeance ASC LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
