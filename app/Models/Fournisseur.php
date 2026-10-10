<?php

namespace App\Models;

use App\Core\Database;

/**
 * Module Fournisseurs (refonte 07/10).
 *
 * Trois notions distinctes (elles étaient mélangées dans l'ancien « statut ») :
 *  - le TYPE de partenaire (fabricant, distributeur, prestataire, transporteur,
 *    transitaire, autre) — un partenaire peut en cumuler plusieurs ;
 *  - le STATUT actif / inactif (colonne is_active) ;
 *  - le NIVEAU DE QUALIFICATION (à qualifier, en cours, validé, non retenu), avec
 *    historique complet (critères, évaluateur, date, décision, commentaire).
 *
 * Tant que migrate_v23.php n'a pas été lancée, le module continue de fonctionner
 * avec les anciennes colonnes (schemaPret() = false).
 */
class Fournisseur
{
    public const PAR_PAGE = 20;

    /** Sur téléphone, 10 lignes par page (moins de défilement). */
    public static function parPage(): int
    {
        return preg_match('/Mobi|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT'] ?? '') ? 10 : self::PAR_PAGE;
    }

    public const TYPES = [
        'fabricant' => 'Fabricant',
        'distributeur' => 'Distributeur',
        'prestataire' => 'Prestataire',
        'transporteur' => 'Transporteur',
        'transitaire' => 'Transitaire',
        'autre' => 'Autre',
    ];

    public const TYPES_BADGES = [
        'fabricant' => 'badge-blue',
        'distributeur' => 'badge-blue',
        'prestataire' => 'badge-orange',
        'transporteur' => 'badge-orange',
        'transitaire' => 'badge-orange',
        'autre' => 'badge-gray',
    ];

    public const QUALIFICATIONS = [
        'a_qualifier' => 'À qualifier',
        'en_cours' => 'En cours',
        'valide' => 'Validé',
        'non_retenu' => 'Non retenu',
    ];

    public const QUALIFICATION_BADGES = [
        'a_qualifier' => 'badge-gray',
        'en_cours' => 'badge-yellow',
        'valide' => 'badge-green',
        'non_retenu' => 'badge-red',
    ];

    /** Ancien statut -> niveau de qualification (fiches non encore migrées). */
    private const STATUT_HERITE = [
        'approuve' => 'valide',
        'sous_surveillance' => 'en_cours',
        'suspendu' => 'non_retenu',
    ];

    public const STATUTS = ['actif' => 'Actif', 'inactif' => 'Inactif'];

    public const TYPES_ADRESSE = [
        'siege' => 'Siège',
        'entrepot' => 'Entrepôt',
        'usine' => 'Usine / site de production',
        'facturation' => 'Facturation',
        'autre' => 'Autre',
    ];

    public const ORIGINES = [
        'recommandation' => 'Recommandation',
        'salon' => 'Salon / événement',
        'prospection' => 'Prospection',
        'site_web' => 'Site internet',
        'ancien_partenaire' => 'Ancien partenaire',
        'autre' => 'Autre',
    ];

    public const DEVISES = ['XAF', 'XOF', 'EUR', 'USD', 'GBP', 'CNY'];

    /** Seuil (jours) de l'alerte « document à renouveler ». */
    public const JOURS_ALERTE_DOCUMENT = 30;

    // Appréciations chiffrées facultatives (0 à 5) : critères visibles, moyenne = note globale.
    public const CRITERES_NOTE = [
        'note_prix' => 'Prix',
        'note_qualite' => 'Qualité',
        'note_delai' => 'Délai',
        'note_reactivite' => 'Réactivité',
        'note_conformite' => 'Conformité documentaire',
        'note_engagements' => 'Respect des engagements',
    ];

    /**
     * Critères d'examen d'une qualification. Chaque critère s'applique à tous les
     * partenaires (types = null) ou seulement à certains types : une assurance ou
     * une certification n'est jamais exigée de tous.
     */
    public const CRITERES_QUALIFICATION = [
        'identite' => ['Identité et existence légale vérifiées', null],
        'references' => ['Références ou historique d’activité', null],
        'conditions' => ['Conditions commerciales compatibles', null],
        'certification' => ['Certification / norme qualité', ['fabricant']],
        'capacite' => ['Capacité de production ou d’approvisionnement', ['fabricant', 'distributeur']],
        'echantillons' => ['Échantillons ou fiches techniques conformes', ['fabricant', 'distributeur']],
        'competences' => ['Compétences et moyens pour la prestation', ['prestataire']],
        'assurance_pro' => ['Assurance responsabilité professionnelle', ['prestataire', 'transporteur', 'transitaire']],
        'licence_transport' => ['Licence / autorisation de transport', ['transporteur']],
        'couverture' => ['Couverture géographique et délais de transit', ['transporteur', 'transitaire']],
        'agrement_douane' => ['Agrément douane / transit', ['transitaire']],
    ];

    public const RESULTATS_CRITERE = [
        'ok' => 'Satisfaisant',
        'a_verifier' => 'À vérifier',
        'ko' => 'Non satisfaisant',
        'na' => 'Sans objet',
    ];

    /** Critères d'une évaluation manuelle (opération réelle), selon le type. */
    public const CRITERES_EVALUATION = [
        'delai' => ['Respect du délai', null],
        'qualite' => ['Qualité / conformité', null],
        'communication' => ['Communication et réactivité', null],
        'documents' => ['Documents fournis', null],
        'conformite_produit' => ['Conformité du produit à la commande', ['fabricant', 'distributeur']],
        'emballage' => ['Emballage et conditionnement', ['fabricant', 'distributeur']],
        'qualite_prestation' => ['Qualité de la prestation', ['prestataire']],
        'respect_perimetre' => ['Respect du périmètre convenu', ['prestataire']],
        'ponctualite' => ['Ponctualité du transport', ['transporteur', 'transitaire']],
        'etat_marchandise' => ['État de la marchandise à l’arrivée', ['transporteur']],
        'suivi' => ['Suivi et information pendant le transit', ['transporteur', 'transitaire']],
    ];

    public const RESULTATS_EVALUATION = [
        'conforme' => 'Conforme',
        'reserve' => 'Avec réserve',
        'non_conforme' => 'Non conforme',
    ];

    public const RESULTATS_EVALUATION_BADGES = [
        'conforme' => 'badge-green',
        'reserve' => 'badge-orange',
        'non_conforme' => 'badge-red',
    ];

    // ------------------------------------------------------------------
    // Schéma (fonctionne avant et après la migration V23)
    // ------------------------------------------------------------------

    public static function schemaPret(): bool
    {
        static $pret = null;
        if ($pret === null) {
            $pret = self::colonneExiste('fournisseurs', 'qualification') && self::tableExiste('fournisseur_contacts')
                && self::tableExiste('fournisseur_adresses') && self::tableExiste('fournisseur_qualifications')
                && self::tableExiste('fournisseur_evaluations') && self::colonneExiste('fournisseur_pieces_jointes', 'expire_le');
        }
        return $pret;
    }

    private static function colonneExiste(string $table, string $colonne): bool
    {
        $pdo = Database::connection();
        try {
            if (Database::driver() === 'sqlite') {
                foreach ($pdo->query("PRAGMA table_info($table)")->fetchAll() as $col) {
                    if ($col['name'] === $colonne) {
                        return true;
                    }
                }
                return false;
            }
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
            $stmt->execute([$table, $colonne]);
            return (bool) $stmt->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function tableExiste(string $table): bool
    {
        $pdo = Database::connection();
        try {
            if (Database::driver() === 'sqlite') {
                $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
            } else {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            }
            $stmt->execute([$table]);
            return (bool) $stmt->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ------------------------------------------------------------------
    // Lecture de base (API conservée)
    // ------------------------------------------------------------------

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseurs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allForFiliale(int $filialeId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM fournisseurs WHERE filiale_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY nom';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$filialeId]);
        return $stmt->fetchAll();
    }

    public static function visibleFor(array $user): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT fo.*, fi.nom AS filiale_nom FROM fournisseurs fo
             INNER JOIN filiales fi ON fi.id = fo.filiale_id
             WHERE fo.filiale_id IN ($placeholders)
             ORDER BY fo.nom"
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll();
    }

    public static function userCanAccess(array $user, array $fournisseur): bool
    {
        return Filiale::userCanAccess($user, (int) $fournisseur['filiale_id']);
    }

    public static function nameOf(?int $id): string
    {
        if (!$id) {
            return '—';
        }
        $fournisseur = self::find($id);
        return $fournisseur ? $fournisseur['nom'] : '—';
    }

    // ------------------------------------------------------------------
    // Types, qualification, notes
    // ------------------------------------------------------------------

    /** Codes de types d'une fiche (stockés « ,fabricant,transporteur, »). */
    public static function typesDe(array $f): array
    {
        $codes = array_filter(array_map('trim', explode(',', (string) ($f['types_partenaire'] ?? ''))));
        return array_values(array_filter($codes, fn($c) => isset(self::TYPES[$c])));
    }

    public static function typesStockes(array $codes): string
    {
        $codes = array_values(array_unique(array_filter($codes, fn($c) => isset(self::TYPES[$c]))));
        return $codes ? ',' . implode(',', $codes) . ',' : '';
    }

    public static function qualificationDe(array $f): string
    {
        if (isset($f['qualification']) && isset(self::QUALIFICATIONS[$f['qualification']])) {
            return $f['qualification'];
        }
        return self::STATUT_HERITE[$f['statut'] ?? ''] ?? 'a_qualifier';
    }

    public static function specialitesDe(array $f): array
    {
        $src = trim((string) ($f['specialites'] ?? '')) !== '' ? $f['specialites'] : ($f['categories_produits'] ?? '');
        return array_values(array_filter(array_map('trim', explode(',', (string) $src)), fn($s) => $s !== ''));
    }

    /** Critères applicables à au moins un des types (tous les critères « génériques » si aucun type). */
    public static function criteresPour(array $definitions, array $types): array
    {
        $out = [];
        foreach ($definitions as $code => [$libelle, $limites]) {
            if ($limites === null || array_intersect($limites, $types)) {
                $out[$code] = $libelle;
            }
        }
        return $out;
    }

    private static function normalizeNote($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int) $value;
        return $n >= 0 && $n <= 5 ? $n : null;
    }

    /**
     * Note globale = moyenne des critères renseignés. Null si aucun critère
     * n'a été évalué (on n'invente jamais de note).
     */
    public static function noteGlobale(array $fournisseur): ?float
    {
        $valeurs = [];
        foreach (array_keys(self::CRITERES_NOTE) as $champ) {
            if (isset($fournisseur[$champ]) && $fournisseur[$champ] !== null && $fournisseur[$champ] !== '') {
                $valeurs[] = (float) $fournisseur[$champ];
            }
        }
        if (empty($valeurs)) {
            return null;
        }
        return round(array_sum($valeurs) / count($valeurs), 1);
    }

    // ------------------------------------------------------------------
    // Création / modification
    // ------------------------------------------------------------------

    private const COLONNES_BASE = [
        'nom', 'email', 'telephone', 'pays', 'ville', 'adresse', 'devise', 'secteur', 'site_web',
        'categories_produits', 'marques', 'pays_desservis', 'incoterms_pratiques', 'quantite_min',
        'fonction_contact', 'notes',
    ];
    private const COLONNES_NOTES = ['note_prix', 'note_qualite', 'note_delai', 'note_reactivite', 'note_conformite', 'note_engagements'];
    private const COLONNES_V23 = [
        'nom_commercial', 'types_partenaire', 'specialites', 'activites', 'code_postal', 'siret', 'tva',
        'contact_prenom', 'contact_nom', 'devises_proposees', 'conditions_paiement', 'delai_indicatif',
        'conditions_livraison', 'responsable_id', 'origine_contact', 'client_id',
    ];

    private static function valeurs(array $data, array $colonnes): array
    {
        $out = [];
        foreach ($colonnes as $c) {
            $v = $data[$c] ?? '';
            if (in_array($c, self::COLONNES_NOTES, true)) {
                $v = self::normalizeNote($data[$c] ?? null);
            } elseif ($c === 'responsable_id' || $c === 'client_id') {
                $v = $v ? (int) $v : null;
            }
            $out[$c] = $v;
        }
        return $out;
    }

    private static function colonnesActives(): array
    {
        $cols = array_merge(self::COLONNES_BASE, self::COLONNES_NOTES);
        return self::schemaPret() ? array_merge($cols, self::COLONNES_V23) : $cols;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find((int) $data['filiale_id']);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'fournisseur');
        $code = Compteur::formatReference('FOU', $numero);

        $actif = (($data['statut'] ?? 'actif') === 'inactif') ? 0 : 1;
        $valeurs = self::valeurs($data, self::colonnesActives());
        $pret = self::schemaPret();
        $qualification = array_key_exists($data['qualification'] ?? '', self::QUALIFICATIONS) ? $data['qualification'] : 'a_qualifier';

        $cols = array_merge(['filiale_id', 'code', 'statut', 'is_active', 'created_at'], array_keys($valeurs));
        $params = array_merge([
            (int) $data['filiale_id'], $code,
            $pret ? ($actif ? 'actif' : 'inactif') : ($actif ? 'a_qualifier' : 'inactif'),
            $actif, date('Y-m-d H:i:s'),
        ], array_values($valeurs));
        if ($pret) {
            $cols[] = 'qualification';
            $params[] = $qualification;
        }
        $pdo->prepare('INSERT INTO fournisseurs (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute($params);
        return (int) $pdo->lastInsertId();
    }

    /** Le statut et la qualification ne changent que si leurs clés sont fournies (jamais par un appel partiel). */
    public static function update(int $id, array $data): void
    {
        $valeurs = self::valeurs($data, self::colonnesActives());
        $sets = array_map(fn($c) => "$c = ?", array_keys($valeurs));
        $params = array_values($valeurs);
        if (isset($data['statut'])) {
            $actif = $data['statut'] === 'inactif' ? 0 : 1;
            $sets[] = 'is_active = ?';
            $params[] = $actif;
            if (self::schemaPret()) {
                $sets[] = 'statut = ?';
                $params[] = $actif ? 'actif' : 'inactif';
            }
        }
        $params[] = $id;
        Database::connection()->prepare('UPDATE fournisseurs SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    public static function setActive(int $id, bool $active): void
    {
        if (self::schemaPret()) {
            // La qualification n'est jamais modifiée par une (dés)activation.
            Database::connection()->prepare('UPDATE fournisseurs SET is_active = ?, statut = ? WHERE id = ?')
                ->execute([$active ? 1 : 0, $active ? 'actif' : 'inactif', $id]);
            return;
        }
        Database::connection()->prepare('UPDATE fournisseurs SET is_active = ?, statut = ? WHERE id = ?')
            ->execute([$active ? 1 : 0, $active ? 'approuve' : 'inactif', $id]);
    }

    // ------------------------------------------------------------------
    // Liste, filtres, indicateurs
    // ------------------------------------------------------------------

    public static function filtresDepuis(array $src): array
    {
        $onglet = $src['onglet'] ?? 'tous';
        $tris = ['nom', 'type', 'pays', 'qualification', 'statut'];
        return [
            'q' => trim((string) ($src['q'] ?? '')),
            'pays' => trim((string) ($src['pays'] ?? '')),
            'specialite' => trim((string) ($src['specialite'] ?? '')),
            'filiale_id' => (int) ($src['filiale_id'] ?? 0),
            'type' => array_key_exists($src['type'] ?? '', self::TYPES) ? $src['type'] : '',
            'responsable_id' => (int) ($src['responsable_id'] ?? 0),
            'qualification' => array_key_exists($src['qualification'] ?? '', self::QUALIFICATIONS) ? $src['qualification'] : '',
            'onglet' => in_array($onglet, ['tous', 'actifs', 'inactifs'], true) ? $onglet : 'tous',
            'alerte' => in_array($src['alerte'] ?? '', ['consultations', 'documents'], true) ? $src['alerte'] : '',
            'tri' => in_array($src['tri'] ?? '', $tris, true) ? $src['tri'] : 'nom',
            'dir' => ($src['dir'] ?? '') === 'desc' ? 'desc' : 'asc',
        ];
    }

    private static function seuilAlerte(): string
    {
        return date('Y-m-d', strtotime('+' . self::JOURS_ALERTE_DOCUMENT . ' days'));
    }

    /** WHERE de la liste ; $avecOnglet / $avecAlerte = false pour les indicateurs (même périmètre, sans onglet ni alerte). */
    private static function clause(array $user, array $f, bool $avecOnglet, bool $avecAlerte): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (!empty($f['filiale_id'])) {
            $filialeIds = in_array((int) $f['filiale_id'], $filialeIds, true) ? [(int) $f['filiale_id']] : [];
        }
        if (empty($filialeIds)) {
            return ['1 = 0', []];
        }
        $ph = implode(',', array_fill(0, count($filialeIds), '?'));
        $where = "fo.filiale_id IN ($ph)";
        $params = $filialeIds;
        $pret = self::schemaPret();

        if ($f['q'] !== '') {
            $like = '%' . $f['q'] . '%';
            $champs = ['fo.nom', 'fo.code', 'fo.categories_produits', 'fo.email', 'fo.telephone', 'fo.fonction_contact', 'fo.secteur'];
            if ($pret) {
                array_push($champs, 'fo.nom_commercial', 'fo.specialites', 'fo.contact_prenom', 'fo.contact_nom');
            }
            $parts = array_map(fn($c) => "$c LIKE ?", $champs);
            $params = array_merge($params, array_fill(0, count($champs), $like));
            if ($pret) {
                $parts[] = "EXISTS (SELECT 1 FROM fournisseur_contacts fc WHERE fc.fournisseur_id = fo.id AND fc.actif = 1
                            AND (fc.prenom LIKE ? OR fc.nom LIKE ? OR fc.email LIKE ? OR fc.telephone LIKE ?))";
                array_push($params, $like, $like, $like, $like);
            }
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        if ($f['pays'] !== '') {
            $where .= ' AND fo.pays = ?';
            $params[] = $f['pays'];
        }
        if ($pret && $f['type'] !== '') {
            $where .= ' AND fo.types_partenaire LIKE ?';
            $params[] = '%,' . $f['type'] . ',%';
        }
        if ($f['specialite'] !== '') {
            $where .= $pret ? ' AND (fo.specialites LIKE ? OR fo.categories_produits LIKE ?)' : ' AND fo.categories_produits LIKE ?';
            $params[] = '%' . $f['specialite'] . '%';
            if ($pret) {
                $params[] = '%' . $f['specialite'] . '%';
            }
        }
        if ($pret && !empty($f['responsable_id'])) {
            $where .= ' AND fo.responsable_id = ?';
            $params[] = (int) $f['responsable_id'];
        }
        if ($f['qualification'] !== '') {
            if ($pret) {
                $where .= ' AND fo.qualification = ?';
                $params[] = $f['qualification'];
            } else {
                $herites = array_keys(array_filter(self::STATUT_HERITE, fn($q) => $q === $f['qualification']));
                if ($f['qualification'] === 'a_qualifier') {
                    $herites = array_keys(self::STATUT_HERITE);
                    $where .= ' AND fo.statut NOT IN (' . implode(',', array_fill(0, count($herites), '?')) . ')';
                    $params = array_merge($params, $herites);
                } elseif ($herites) {
                    $where .= ' AND fo.statut IN (' . implode(',', array_fill(0, count($herites), '?')) . ')';
                    $params = array_merge($params, $herites);
                } else {
                    $where .= ' AND 1 = 0';
                }
            }
        }
        if ($avecOnglet && $f['onglet'] === 'actifs') {
            $where .= ' AND fo.is_active = 1';
        } elseif ($avecOnglet && $f['onglet'] === 'inactifs') {
            $where .= ' AND fo.is_active = 0';
        }
        if ($avecAlerte && $f['alerte'] === 'consultations') {
            $where .= " AND EXISTS (SELECT 1 FROM consultations_fournisseur cf WHERE cf.fournisseur_id = fo.id AND cf.statut IN ('envoyee', 'relance'))";
        } elseif ($avecAlerte && $f['alerte'] === 'documents' && $pret) {
            $where .= ' AND EXISTS (SELECT 1 FROM fournisseur_pieces_jointes pj WHERE pj.fournisseur_id = fo.id AND pj.expire_le IS NOT NULL AND pj.expire_le <= ?)';
            $params[] = self::seuilAlerte();
        }
        return [$where, $params];
    }

    private static function ordre(array $f): string
    {
        $pret = self::schemaPret();
        $dir = $f['dir'] === 'desc' ? 'DESC' : 'ASC';
        $col = match ($f['tri']) {
            'type' => $pret ? 'fo.types_partenaire' : 'fo.nom',
            'pays' => 'fo.pays',
            'qualification' => $pret ? 'fo.qualification' : 'fo.statut',
            'statut' => 'fo.is_active',
            default => 'fo.nom',
        };
        return "$col $dir, fo.nom ASC";
    }

    private const SOUS_REQUETES =
        "(SELECT COUNT(*) FROM consultations_fournisseur cf WHERE cf.fournisseur_id = fo.id AND cf.statut IN ('envoyee', 'relance')) AS nb_consultations_en_cours";

    private static function selectListe(): array
    {
        $pret = self::schemaPret();
        return [
            'SELECT fo.*, fi.nom AS filiale_nom, ' . self::SOUS_REQUETES . ($pret ? ', u.nom AS responsable_nom' : ''),
            'FROM fournisseurs fo INNER JOIN filiales fi ON fi.id = fo.filiale_id' . ($pret ? ' LEFT JOIN utilisateurs u ON u.id = fo.responsable_id' : ''),
        ];
    }

    public static function liste(array $user, array $f, int $page, ?int $parPage = null): array
    {
        $parPage = $parPage ?? self::parPage();
        [$where, $params] = self::clause($user, $f, true, true);
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fournisseurs fo WHERE $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $parPage));
        $page = min(max(1, $page), $pages);
        [$select, $from] = self::selectListe();
        $stmt = $pdo->prepare("$select $from WHERE $where ORDER BY " . self::ordre($f) . ' LIMIT ' . (int) $parPage . ' OFFSET ' . (int) (($page - 1) * $parPage));
        $stmt->execute($params);
        return ['lignes' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public static function listeComplete(array $user, array $f): array
    {
        [$where, $params] = self::clause($user, $f, true, true);
        [$select, $from] = self::selectListe();
        $stmt = Database::connection()->prepare("$select $from WHERE $where ORDER BY " . self::ordre($f));
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Indicateurs de la liste, calculés sur le même périmètre que les filtres
     * (hors onglet et hors alerte) pour rester cohérents avec les résultats.
     *  - actifs : fournisseurs actifs ;
     *  - a_qualifier : fournisseurs actifs au niveau « à qualifier » ;
     *  - consultations : consultations envoyées ou relancées, sans réponse ni clôture ;
     *  - documents : documents dont l'échéance est dépassée ou à moins de 30 jours
     *    (uniquement ceux qui ont une échéance), fournisseurs actifs.
     */
    public static function indicateurs(array $user, array $f): array
    {
        [$where, $params] = self::clause($user, $f, false, false);
        $pdo = Database::connection();
        $pret = self::schemaPret();
        $qualif = $pret ? "fo.qualification = 'a_qualifier'" : "fo.statut NOT IN ('approuve', 'sous_surveillance', 'suspendu')";
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN fo.is_active = 1 THEN 1 ELSE 0 END), 0) AS actifs,
                    COALESCE(SUM(CASE WHEN fo.is_active = 1 AND $qualif THEN 1 ELSE 0 END), 0) AS a_qualifier
             FROM fournisseurs fo WHERE $where"
        );
        $stmt->execute($params);
        $r = $stmt->fetch();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM consultations_fournisseur cf
             WHERE cf.statut IN ('envoyee', 'relance') AND cf.fournisseur_id IN (SELECT fo.id FROM fournisseurs fo WHERE $where)"
        );
        $stmt->execute($params);
        $consultations = (int) $stmt->fetchColumn();

        $documents = null;
        if ($pret) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM fournisseur_pieces_jointes pj
                 WHERE pj.expire_le IS NOT NULL AND pj.expire_le <= ?
                   AND pj.fournisseur_id IN (SELECT fo.id FROM fournisseurs fo WHERE $where AND fo.is_active = 1)"
            );
            $stmt->execute(array_merge([self::seuilAlerte()], $params));
            $documents = (int) $stmt->fetchColumn();
        }
        return [
            'total' => (int) $r['total'], 'actifs' => (int) $r['actifs'], 'a_qualifier' => (int) $r['a_qualifier'],
            'consultations' => $consultations, 'documents' => $documents,
        ];
    }

    public static function paysUtilises(array $user): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (empty($ids)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("SELECT DISTINCT pays FROM fournisseurs WHERE filiale_id IN ($ph) AND pays IS NOT NULL AND pays != '' ORDER BY pays");
        $stmt->execute($ids);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Spécialités distinctes des fournisseurs visibles (liste déroulante du filtre). */
    public static function specialitesUtilisees(array $user): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (empty($ids)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $col = self::schemaPret() ? "COALESCE(NULLIF(specialites, ''), categories_produits)" : 'categories_produits';
        $stmt = Database::connection()->prepare("SELECT $col FROM fournisseurs WHERE filiale_id IN ($ph)");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $csv) {
            foreach (array_map('trim', explode(',', (string) $csv)) as $s) {
                if ($s !== '') {
                    $out[mb_strtolower($s)] = $s;
                }
            }
        }
        ksort($out);
        return array_values($out);
    }

    /** Valeurs proposées à la création : pays et devise les plus utilisés (jamais imposés). */
    public static function defautsOrganisation(array $user): array
    {
        $ids = Filiale::visibleIdsFor($user);
        $defauts = ['pays' => '', 'devise' => ''];
        if (empty($ids)) {
            return $defauts;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT pays FROM fournisseurs WHERE filiale_id IN ($ph) AND pays != '' GROUP BY pays ORDER BY COUNT(*) DESC, pays LIMIT 1");
        $stmt->execute($ids);
        $defauts['pays'] = (string) ($stmt->fetchColumn() ?: '');
        $stmt = $pdo->prepare("SELECT devise FROM fournisseurs WHERE filiale_id IN ($ph) AND devise != '' GROUP BY devise ORDER BY COUNT(*) DESC LIMIT 1");
        $stmt->execute($ids);
        $defauts['devise'] = (string) ($stmt->fetchColumn() ?: '');
        return $defauts;
    }

    /** Doublons probables (nom, e-mail, téléphone, site web) : jamais de fusion automatique. */
    public static function doublonsPotentiels(array $user, string $nom, string $email, string $telephone, string $siteWeb = '', int $exclureId = 0): array
    {
        $ids = Filiale::visibleIdsFor($user);
        $conds = [];
        $params = [];
        if (trim($nom) !== '') {
            $conds[] = 'LOWER(TRIM(fo.nom)) = LOWER(TRIM(?))';
            $params[] = $nom;
        }
        if (trim($email) !== '') {
            $conds[] = "(fo.email != '' AND LOWER(fo.email) = LOWER(?))";
            $params[] = trim($email);
        }
        $chiffres = preg_replace('/\D/', '', $telephone);
        if (strlen($chiffres) >= 7) {
            $conds[] = "(fo.telephone != '' AND REPLACE(REPLACE(REPLACE(fo.telephone, ' ', ''), '+', ''), '.', '') LIKE ?)";
            $params[] = '%' . $chiffres;
        }
        $domaine = strtolower(preg_replace('#^(https?://)?(www\.)?#i', '', trim($siteWeb)));
        $domaine = rtrim(explode('/', $domaine)[0], '/');
        if (strlen($domaine) >= 4) {
            $conds[] = "(fo.site_web != '' AND LOWER(fo.site_web) LIKE ?)";
            $params[] = '%' . $domaine . '%';
        }
        if (empty($ids) || empty($conds)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT fo.id, fo.code, fo.nom, fo.email, fo.telephone FROM fournisseurs fo WHERE fo.filiale_id IN ($ph) AND (" . implode(' OR ', $conds) . ')';
        $params = array_merge($ids, $params);
        if ($exclureId) {
            $sql .= ' AND fo.id != ?';
            $params[] = $exclureId;
        }
        $stmt = Database::connection()->prepare($sql . ' ORDER BY fo.nom LIMIT 5');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Clients du même organisme, pour le lien explicite client <-> fournisseur (aucune fusion). */
    public static function clientsLiables(array $user): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (empty($ids)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("SELECT id, nom, code FROM clients WHERE filiale_id IN ($ph) ORDER BY nom");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Contacts supplémentaires (le contact principal est sur la fiche)
    // ------------------------------------------------------------------

    public static function contacts(int $fid): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseur_contacts WHERE fournisseur_id = ? ORDER BY actif DESC, nom, prenom');
        $stmt->execute([$fid]);
        return $stmt->fetchAll();
    }

    public static function contact(int $fid, int $cid): ?array
    {
        if (!self::schemaPret()) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseur_contacts WHERE id = ? AND fournisseur_id = ?');
        $stmt->execute([$cid, $fid]);
        return $stmt->fetch() ?: null;
    }

    public static function enregistrerContact(int $fid, ?int $cid, array $d): int
    {
        $pdo = Database::connection();
        $vals = [trim($d['prenom'] ?? ''), trim($d['nom'] ?? ''), trim($d['fonction'] ?? ''), trim($d['email'] ?? ''), trim($d['telephone'] ?? '')];
        if ($cid) {
            $pdo->prepare('UPDATE fournisseur_contacts SET prenom = ?, nom = ?, fonction = ?, email = ?, telephone = ? WHERE id = ? AND fournisseur_id = ?')
                ->execute(array_merge($vals, [$cid, $fid]));
            return $cid;
        }
        $pdo->prepare('INSERT INTO fournisseur_contacts (prenom, nom, fonction, email, telephone, fournisseur_id, actif, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)')
            ->execute(array_merge($vals, [$fid, date('Y-m-d H:i:s')]));
        return (int) $pdo->lastInsertId();
    }

    public static function basculerContact(int $fid, int $cid, bool $actif): void
    {
        Database::connection()->prepare('UPDATE fournisseur_contacts SET actif = ? WHERE id = ? AND fournisseur_id = ?')
            ->execute([$actif ? 1 : 0, $cid, $fid]);
    }

    /** Promotion : ÉCHANGE avec le contact principal (porté par la fiche) — rien n'est perdu. */
    public static function definirContactPrincipal(int $fid, int $cid): void
    {
        $f = self::find($fid);
        $c = self::contact($fid, $cid);
        if (!$f || !$c || !self::schemaPret()) {
            return;
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE fournisseurs SET contact_prenom = ?, contact_nom = ?, fonction_contact = ?, email = ?, telephone = ? WHERE id = ?')
                ->execute([$c['prenom'], $c['nom'], $c['fonction'], $c['email'], $c['telephone'], $fid]);
            $pdo->prepare('UPDATE fournisseur_contacts SET prenom = ?, nom = ?, fonction = ?, email = ?, telephone = ?, actif = 1 WHERE id = ?')
                ->execute([(string) ($f['contact_prenom'] ?? ''), (string) ($f['contact_nom'] ?? ''), (string) ($f['fonction_contact'] ?? ''), (string) ($f['email'] ?? ''), (string) ($f['telephone'] ?? ''), $cid]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Adresses supplémentaires
    // ------------------------------------------------------------------

    public static function adresses(int $fid): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseur_adresses WHERE fournisseur_id = ? ORDER BY actif DESC, type, libelle');
        $stmt->execute([$fid]);
        return $stmt->fetchAll();
    }

    public static function adresse(int $fid, int $aid): ?array
    {
        if (!self::schemaPret()) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseur_adresses WHERE id = ? AND fournisseur_id = ?');
        $stmt->execute([$aid, $fid]);
        return $stmt->fetch() ?: null;
    }

    public static function enregistrerAdresse(int $fid, ?int $aid, array $d): int
    {
        $pdo = Database::connection();
        $type = array_key_exists($d['type'] ?? '', self::TYPES_ADRESSE) ? $d['type'] : 'autre';
        $vals = [$type, trim($d['libelle'] ?? ''), trim($d['adresse'] ?? ''), trim($d['code_postal'] ?? ''), trim($d['ville'] ?? ''), trim($d['pays'] ?? '')];
        if ($aid) {
            $pdo->prepare('UPDATE fournisseur_adresses SET type = ?, libelle = ?, adresse = ?, code_postal = ?, ville = ?, pays = ? WHERE id = ? AND fournisseur_id = ?')
                ->execute(array_merge($vals, [$aid, $fid]));
            return $aid;
        }
        $pdo->prepare('INSERT INTO fournisseur_adresses (type, libelle, adresse, code_postal, ville, pays, fournisseur_id, par_defaut, actif, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?)')
            ->execute(array_merge($vals, [$fid, date('Y-m-d H:i:s')]));
        return (int) $pdo->lastInsertId();
    }

    public static function basculerAdresse(int $fid, int $aid, bool $actif): void
    {
        Database::connection()->prepare('UPDATE fournisseur_adresses SET actif = ? WHERE id = ? AND fournisseur_id = ?')
            ->execute([$actif ? 1 : 0, $aid, $fid]);
    }

    /** Adresse principale (celle de la fiche) : en choisir une autre ÉCHANGE les deux, l'ancienne est conservée. */
    public static function definirAdressePrincipale(int $fid, int $aid): void
    {
        $a = self::adresse($fid, $aid);
        $f = self::find($fid);
        if (!$a || !$f || (int) $a['actif'] !== 1) {
            return;
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE fournisseurs SET adresse = ?, code_postal = ?, ville = ?, pays = ? WHERE id = ?')
                ->execute([$a['adresse'], $a['code_postal'], $a['ville'], $a['pays'], $fid]);
            $pdo->prepare("UPDATE fournisseur_adresses SET adresse = ?, code_postal = ?, ville = ?, pays = ?, libelle = 'Ancienne adresse principale' WHERE id = ?")
                ->execute([(string) $f['adresse'], (string) ($f['code_postal'] ?? ''), (string) $f['ville'], (string) $f['pays'], $aid]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Qualification (historique conservé)
    // ------------------------------------------------------------------

    public static function qualifications(int $fid): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            'SELECT q.*, u.nom AS evaluateur_nom FROM fournisseur_qualifications q
             LEFT JOIN utilisateurs u ON u.id = q.evaluateur_id
             WHERE q.fournisseur_id = ? ORDER BY q.date_decision DESC, q.id DESC'
        );
        $stmt->execute([$fid]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['criteres_liste'] = json_decode((string) ($r['criteres'] ?? ''), true) ?: [];
        }
        unset($r);
        return $rows;
    }

    /**
     * Enregistre une décision de qualification (jamais écrasée : chaque décision est une ligne
     * d'historique) et met à jour le niveau courant de la fiche. Aucun effet sur le statut
     * actif/inactif : un fournisseur non retenu reste actif et conserve toutes ses relations.
     */
    public static function enregistrerQualification(int $fid, string $decision, array $criteres, string $commentaire, array $piecesIds, ?int $evaluateurId, ?string $dateReexamen): void
    {
        if (!self::schemaPret() || !isset(self::QUALIFICATIONS[$decision])) {
            return;
        }
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO fournisseur_qualifications (fournisseur_id, decision, criteres, commentaire, justificatifs, evaluateur_id, date_decision, date_reexamen, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $fid, $decision, json_encode($criteres, JSON_UNESCAPED_UNICODE), $commentaire,
            implode(',', array_map('intval', $piecesIds)), $evaluateurId, date('Y-m-d'), $dateReexamen ?: null, $now,
        ]);
        $pdo->prepare('UPDATE fournisseurs SET qualification = ?, reexamen_le = ? WHERE id = ?')->execute([$decision, $dateReexamen ?: null, $fid]);
    }

    // ------------------------------------------------------------------
    // Évaluations manuelles documentées
    // ------------------------------------------------------------------

    public static function evaluations(int $fid): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            'SELECT e.*, u.nom AS evaluateur_nom, d.reference AS dossier_reference, pj.nom_original AS piece_nom
             FROM fournisseur_evaluations e
             LEFT JOIN utilisateurs u ON u.id = e.evaluateur_id
             LEFT JOIN dossiers d ON d.id = e.dossier_id
             LEFT JOIN fournisseur_pieces_jointes pj ON pj.id = e.piece_id
             WHERE e.fournisseur_id = ? ORDER BY e.date_evaluation DESC, e.id DESC'
        );
        $stmt->execute([$fid]);
        return $stmt->fetchAll();
    }

    public static function ajouterEvaluation(int $fid, array $d, ?int $evaluateurId): void
    {
        Database::connection()->prepare(
            'INSERT INTO fournisseur_evaluations (fournisseur_id, dossier_id, critere, resultat, commentaire, piece_id, evaluateur_id, date_evaluation, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $fid, $d['dossier_id'] ?: null, $d['critere'], $d['resultat'], $d['commentaire'], $d['piece_id'] ?: null,
            $evaluateurId, $d['date_evaluation'], date('Y-m-d H:i:s'),
        ]);
    }

    // ------------------------------------------------------------------
    // Consultations, offres, commandes liées
    // ------------------------------------------------------------------

    /** Consultations adressées au fournisseur (une ligne par consultation). */
    public static function consultations(int $fid): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT cf.*, d.reference AS dossier_reference, d.objet AS dossier_objet, u.nom AS responsable_nom,
                    (SELECT cp.echeance_reponse FROM consultation_partages cp WHERE cp.consultation_id = cf.id ORDER BY cp.created_at DESC LIMIT 1) AS echeance_reponse,
                    (SELECT COUNT(*) FROM offres o WHERE o.consultation_id = cf.id AND o.statut != 'remplacee') AS nb_offres
             FROM consultations_fournisseur cf
             INNER JOIN dossiers d ON d.id = cf.dossier_id
             LEFT JOIN utilisateurs u ON u.id = cf.created_by
             WHERE cf.fournisseur_id = ? ORDER BY cf.created_at DESC"
        );
        $stmt->execute([$fid]);
        return $stmt->fetchAll();
    }

    /**
     * Offres reçues de ce fournisseur, toutes versions : les versions remplacées sont
     * marquées (historique) et ne comptent jamais comme une offre indépendante.
     */
    public static function offres(int $fid): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.id, o.reference, o.version, o.statut, o.montant_total, o.devise, o.validite_offre, o.delai_livraison,
                    o.created_at, o.motif_decision, cf.reference AS consultation_reference, cf.id AS consultation_id,
                    d.id AS dossier_id, d.reference AS dossier_reference
             FROM offres o
             INNER JOIN consultations_fournisseur cf ON cf.id = o.consultation_id
             INNER JOIN dossiers d ON d.id = o.dossier_id
             WHERE o.fournisseur_id = ? ORDER BY o.reference DESC, o.version DESC'
        );
        $stmt->execute([$fid]);
        return $stmt->fetchAll();
    }

    /**
     * « Commandes » liées : dossiers dont l'offre RETENUE vient de ce fournisseur, avec la
     * commande suivie sur le dossier (étape, livraison prévue / réelle). L'application ne
     * gère pas encore de bon de commande fournisseur séparé : c'est le suivi du dossier.
     */
    public static function commandes(int $fid): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT o.id AS offre_id, o.reference AS offre_reference, o.montant_total, o.devise, o.delai_livraison,
                    d.id AS dossier_id, d.reference AS dossier_reference, d.objet,
                    cm.id AS commande_id, cm.reference AS commande_reference, cm.statut AS commande_statut, cm.etape, cm.created_at AS commande_date,
                    cs.date_prevue AS livraison_prevue, cs.date_reelle AS livraison_reelle, cs.statut AS livraison_statut
             FROM offres o
             INNER JOIN dossiers d ON d.id = o.dossier_id
             LEFT JOIN commandes cm ON cm.dossier_id = o.dossier_id
             LEFT JOIN commande_steps cs ON cs.commande_id = cm.id AND cs.libelle = 'livraison'
             WHERE o.fournisseur_id = ? AND o.statut = 'retenue'
             ORDER BY COALESCE(cm.created_at, o.created_at) DESC"
        );
        $stmt->execute([$fid]);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Indicateurs de performance (explicables)
    // ------------------------------------------------------------------

    public static function debutPeriode(string $periode): ?string
    {
        return match ($periode) {
            'annee' => date('Y') . '-01-01',
            '12m' => date('Y-m-d', strtotime('-12 months')),
            default => null,
        };
    }

    private static function mediane(array $v): ?float
    {
        if (!$v) {
            return null;
        }
        sort($v);
        $n = count($v);
        $m = intdiv($n, 2);
        return $n % 2 ? (float) $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
    }

    /**
     * Indicateurs calculés sur les opérations réellement enregistrées. Chaque indicateur
     * indique sa règle, le nombre d'opérations évaluées et celles écartées ; s'il ne peut
     * pas être calculé, `valeur` vaut null (affiché « Données insuffisantes » ou
     * « Non disponible », jamais 0).
     */
    public static function performance(int $fid, ?string $depuis): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT cf.id, cf.statut, cf.date_envoi, cf.created_at FROM consultations_fournisseur cf WHERE cf.fournisseur_id = ?');
        $stmt->execute([$fid]);
        $consultations = array_values(array_filter($stmt->fetchAll(), fn($c) => $depuis === null || substr((string) $c['created_at'], 0, 10) >= $depuis));

        // Taux de réponse : consultations CLOSES (réponse reçue ou classée sans réponse).
        $closes = array_filter($consultations, fn($c) => in_array($c['statut'], ['reponse_recue', 'sans_reponse'], true));
        $repondues = array_filter($closes, fn($c) => $c['statut'] === 'reponse_recue');
        $enCours = count($consultations) - count($closes);
        $tauxReponse = [
            'valeur' => count($closes) > 0 ? round(count($repondues) / count($closes) * 100, 0) : null,
            'n' => count($closes),
            'detail' => count($repondues) . ' réponse(s) / ' . count($closes) . ' consultation(s) close(s)' . ($enCours > 0 ? ' — ' . $enCours . ' encore ouverte(s), non comptée(s)' : ''),
        ];

        // Délai médian : date d'envoi -> première offre reçue, en jours.
        $delais = [];
        $ecartes = 0;
        foreach ($repondues as $c) {
            $stmt = $pdo->prepare('SELECT MIN(created_at) FROM offres WHERE consultation_id = ?');
            $stmt->execute([$c['id']]);
            $premiere = $stmt->fetchColumn();
            if (empty($c['date_envoi']) || !$premiere) {
                $ecartes++;
                continue;
            }
            $jours = (strtotime(substr((string) $premiere, 0, 10)) - strtotime((string) $c['date_envoi'])) / 86400;
            if ($jours < 0) {
                $ecartes++;
                continue;
            }
            $delais[] = $jours;
        }
        $mediane = self::mediane($delais);
        $delaiMedian = [
            'valeur' => $mediane,
            'n' => count($delais),
            'detail' => count($delais) . ' réponse(s) mesurée(s)' . ($ecartes > 0 ? ' — ' . $ecartes . ' écartée(s) (date d’envoi absente ou incohérente)' : ''),
        ];

        // Livraison dans le délai : étape « livraison » terminée avec date prévue ET date réelle.
        $surTemps = 0;
        $mesurees = 0;
        $exclues = 0;
        foreach (self::commandes($fid) as $c) {
            if (empty($c['commande_id']) || $c['commande_statut'] === 'annulee') {
                continue;
            }
            if ($depuis !== null && substr((string) $c['commande_date'], 0, 10) < $depuis) {
                continue;
            }
            if (($c['livraison_statut'] ?? '') !== 'termine') {
                continue; // pas encore livrée : ni réussite ni échec
            }
            if (empty($c['livraison_prevue']) || empty($c['livraison_reelle'])) {
                $exclues++;
                continue;
            }
            $mesurees++;
            if ($c['livraison_reelle'] <= $c['livraison_prevue']) {
                $surTemps++;
            }
        }
        $livraison = [
            'valeur' => $mesurees > 0 ? round($surTemps / $mesurees * 100, 0) : null,
            'n' => $mesurees,
            'detail' => $surTemps . ' livraison(s) dans le délai / ' . $mesurees . ' mesurée(s)'
                . ($exclues > 0 ? ' — ' . $exclues . ' sans date exploitable, écartée(s)' : '') . ' (commandes annulées et non livrées exclues)',
        ];

        // Offres (versions remplacées exclues).
        $stmt = $pdo->prepare("SELECT statut FROM offres WHERE fournisseur_id = ? AND statut != 'remplacee'" . ($depuis !== null ? ' AND created_at >= ?' : ''));
        $stmt->execute($depuis !== null ? [$fid, $depuis . ' 00:00:00'] : [$fid]);
        $offres = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return [
            'taux_reponse' => $tauxReponse,
            'delai_median' => $delaiMedian,
            'livraison_delai' => $livraison,
            'offres' => ['courantes' => count($offres), 'retenues' => count(array_filter($offres, fn($s) => $s === 'retenue'))],
        ];
    }

    /** Documents (avec échéance) expirés ou à renouveler dans les 30 jours. */
    public static function documentsAlertes(int $fid): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT id, categorie, nom_original, expire_le FROM fournisseur_pieces_jointes WHERE fournisseur_id = ? AND expire_le IS NOT NULL AND expire_le <= ? ORDER BY expire_le');
        $stmt->execute([$fid, self::seuilAlerte()]);
        return $stmt->fetchAll();
    }
}
