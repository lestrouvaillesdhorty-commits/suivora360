<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    /** Statut = état de la fiche (actif / inactif), distinct du type et de la relation commerciale. */
    public const STATUTS = [
        'actif' => 'Actif',
        'inactif' => 'Inactif',
    ];

    /** Relation commerciale (07/10) : un prospect n'a pas encore passé de commande. */
    public const RELATIONS = [
        'prospect' => 'Prospect',
        'client' => 'Client',
    ];

    public const RELATIONS_BADGES = [
        'prospect' => 'badge-blue',
        'client' => 'badge-green',
    ];

    public const DEVISES = ['XAF', 'XOF', 'EUR', 'USD', 'GBP', 'CNY'];

    public const PAR_PAGE = 20;

    /** Sur téléphone, 10 lignes par page (moins de défilement). */
    public static function parPage(): int
    {
        return preg_match('/Mobi|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT'] ?? '') ? 10 : self::PAR_PAGE;
    }

    public const STATUT_BADGES = [
        'actif' => 'badge-green',
        'inactif' => 'badge-gray',
    ];

    public const TYPES = [
        'particulier' => 'Particulier',
        'entreprise' => 'Entreprise',
        'distributeur' => 'Distributeur',
        'grossiste' => 'Grossiste',
        'revendeur' => 'Revendeur',
        'gms' => 'GMS / Enseigne',
        'administration' => 'Administration',
    ];

    public const CONDITIONS_PAIEMENT = [
        'comptant' => 'Comptant',
        'acompte_solde' => 'Acompte et solde avant expédition',
        '30j' => '30 jours',
        '45j' => '45 jours',
        '60j' => '60 jours',
        'autre' => 'Autre',
    ];

    public const MODES_TRANSPORT = [
        'maritime' => 'Maritime',
        'aerien' => 'Aérien',
        'routier' => 'Routier',
        'multimodal' => 'Multimodal',
        'autre' => 'Autre',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allForFiliale(int $filialeId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM clients WHERE filiale_id = ?';
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
            "SELECT c.*, f.nom AS filiale_nom FROM clients c
             INNER JOIN filiales f ON f.id = c.filiale_id
             WHERE c.filiale_id IN ($placeholders)
             ORDER BY c.nom"
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll();
    }

    public static function userCanAccess(array $user, array $client): bool
    {
        return Filiale::userCanAccess($user, (int) $client['filiale_id']);
    }

    /**
     * Recherche simple pour le sélecteur client du module Demandes (section
     * 2.B — "Recherche d'un client existant") : filtre côté serveur sur le
     * nom, limité à la filiale, pour rester utilisable même quand la liste
     * de clients est longue (pas de dépendance JS obligatoire : un simple
     * select reste toujours utilisable, cette recherche ne fait qu'aider).
     */
    public static function rechercherPourFiliale(int $filialeId, string $terme, int $limite = 30): array
    {
        $sql = 'SELECT * FROM clients WHERE filiale_id = ? AND is_active = 1';
        $params = [$filialeId];
        if (trim($terme) !== '') {
            $sql .= ' AND nom LIKE ?';
            $params[] = '%' . trim($terme) . '%';
        }
        $sql .= ' ORDER BY nom LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * [ajouté 04/10, création rapide de client depuis le module Demandes]
     * Contrôle des doublons avant création — même nom (insensible à la
     * casse/aux espaces) OU même e-mail non vide, au sein de la même
     * filiale. Ne bloque rien : signale seulement le client existant pour
     * que la personne choisisse (réutiliser ou confirmer malgré tout).
     */
    public static function rechercherDoublon(int $filialeId, string $nom, string $email): ?array
    {
        $nom = trim($nom);
        $email = trim($email);
        if ($nom === '' && $email === '') {
            return null;
        }
        $sql = 'SELECT * FROM clients WHERE filiale_id = ? AND (LOWER(nom) = LOWER(?)';
        $params = [$filialeId, $nom];
        if ($email !== '') {
            $sql .= ' OR (email != \'\' AND LOWER(email) = LOWER(?))';
            $params[] = $email;
        }
        $sql .= ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Les colonnes du module Clients de la version d'octobre (relation,
     * responsable, devise préférée, contact principal) n'existent qu'après
     * migrate_v21.php. Tant qu'elle n'a pas été lancée, l'application continue
     * de fonctionner avec les anciennes colonnes (aucune erreur).
     */
    public static function schemaPret(): bool
    {
        static $pret = null;
        if ($pret === null) {
            $pret = self::colonneExiste('clients', 'relation') && self::tableExiste('client_contacts')
                && self::tableExiste('client_adresses') && self::tableExiste('client_pieces_jointes');
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

    private static function tableExiste(string $table): bool
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

    private const COLONNES_BASE = [
        'nom', 'type', 'email', 'telephone', 'pays', 'ville', 'code_postal', 'adresse', 'adresse_livraison',
        'secteur', 'siret', 'tva', 'incoterm_habituel', 'mode_transport_habituel', 'conditions_paiement',
        'fonction_contact', 'notes',
    ];
    private const COLONNES_V21 = ['relation', 'responsable_id', 'devise_preferee', 'contact_prenom', 'contact_nom'];

    private static function valeursPourColonnes(array $data, array $colonnes): array
    {
        $out = [];
        foreach ($colonnes as $c) {
            $v = $data[$c] ?? '';
            if ($c === 'responsable_id') {
                $v = $v ? (int) $v : null;
            } elseif ($c === 'relation') {
                $v = array_key_exists($v, self::RELATIONS) ? $v : 'client';
            } elseif ($c === 'devise_preferee') {
                $v = $v !== '' ? $v : null;
            }
            $out[$c] = $v;
        }
        return $out;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find((int) $data['filiale_id']);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'client');
        $code = Compteur::formatReference('CLI', $numero);

        $actif = (($data['statut'] ?? 'actif') === 'inactif') ? 0 : 1;
        $colonnes = self::COLONNES_BASE;
        if (self::schemaPret()) {
            $colonnes = array_merge($colonnes, self::COLONNES_V21);
        }
        $valeurs = self::valeursPourColonnes($data, $colonnes);

        $cols = array_merge(['filiale_id', 'code', 'statut', 'is_active', 'created_at'], array_keys($valeurs));
        $params = array_merge([
            (int) $data['filiale_id'], $code, $actif ? 'actif' : 'inactif', $actif, date('Y-m-d H:i:s'),
        ], array_values($valeurs));
        $stmt = $pdo->prepare(
            'INSERT INTO clients (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')'
        );
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Met à jour la fiche. Le statut (actif/inactif) n'est modifié que si la
     * clé `statut` est fournie, afin qu'un appel partiel ne réactive jamais
     * par erreur un client désactivé.
     */
    public static function update(int $id, array $data): void
    {
        $colonnes = self::COLONNES_BASE;
        if (self::schemaPret()) {
            $colonnes = array_merge($colonnes, self::COLONNES_V21);
        }
        $valeurs = self::valeursPourColonnes($data, $colonnes);
        $sets = array_map(fn($c) => "$c = ?", array_keys($valeurs));
        $params = array_values($valeurs);
        if (isset($data['statut'])) {
            $actif = $data['statut'] === 'inactif' ? 0 : 1;
            $sets[] = 'statut = ?';
            $sets[] = 'is_active = ?';
            array_push($params, $actif ? 'actif' : 'inactif', $actif);
        }
        $params[] = $id;
        Database::connection()->prepare('UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    /**
     * Désactiver/Réactiver : conservé pour compatibilité (bouton rapide),
     * agit à la fois sur is_active (ancien mécanisme) et sur statut (nouveau,
     * plus riche — Prospect/Actif/Suspendu/Inactif).
     */
    public static function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE clients SET is_active = ?, statut = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $active ? 'actif' : 'inactif', $id]);
    }

    public static function nameOf(?int $id): string
    {
        if (!$id) {
            return '—';
        }
        $client = self::find($id);
        return $client ? $client['nom'] : '—';
    }

    /**
     * Chiffre d'affaires total : somme des cotations acceptées liées au
     * client, regroupée par devise (pas de conversion multi-devises pour
     * l'instant — Phase 4 — donc on n'additionne jamais des montants dans
     * des devises différentes sous un seul total, ce qui serait trompeur).
     * Retourne ['EUR' => 1200.0, 'FCFA' => 500000.0, ...].
     */
    public static function caTotal(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(devise, '—') AS devise, SUM(montant_total) AS total
             FROM cotations WHERE client_id = ? AND statut = 'acceptee'
             GROUP BY devise"
        );
        $stmt->execute([$clientId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['devise']] = (float) $row['total'];
        }
        return $result;
    }

    /**
     * Montant restant à payer : somme des factures émises (non payées,
     * non annulées) rattachées aux cotations de ce client, regroupée par
     * devise pour la même raison que caTotal().
     */
    public static function resteAPayer(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(fa.devise, '—') AS devise, SUM(fa.montant) AS total
             FROM factures fa
             INNER JOIN cotations co ON co.id = fa.cotation_id
             WHERE co.client_id = ? AND fa.statut = 'emise'
             GROUP BY fa.devise"
        );
        $stmt->execute([$clientId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['devise']] = (float) $row['total'];
        }
        return $result;
    }

    // =====================================================================
    // Liste, indicateurs, export (07/10)
    // =====================================================================

    /** Filtres autorisés de la liste (valeurs nettoyées, jamais directement injectées en SQL). */
    public static function filtresDepuis(array $src): array
    {
        $onglet = $src['onglet'] ?? 'tous';
        return [
            'q' => trim((string) ($src['q'] ?? '')),
            'pays' => trim((string) ($src['pays'] ?? '')),
            'filiale_id' => (int) ($src['filiale_id'] ?? 0),
            'type' => array_key_exists($src['type'] ?? '', self::TYPES) ? $src['type'] : '',
            'responsable_id' => (int) ($src['responsable_id'] ?? 0),
            'relation' => array_key_exists($src['relation'] ?? '', self::RELATIONS) ? $src['relation'] : '',
            'onglet' => in_array($onglet, ['tous', 'actifs', 'inactifs'], true) ? $onglet : 'tous',
        ];
    }

    /**
     * Construit la clause WHERE de la liste. $avecOnglet=false sert aux
     * indicateurs (Total / Actifs / Dossiers actifs), calculés sur le même
     * périmètre que la liste mais sans l'onglet, pour que « Total » reste
     * égal à Actifs + Inactifs.
     */
    private static function clause(array $user, array $f, bool $avecOnglet): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (!empty($f['filiale_id'])) {
            $filialeIds = in_array((int) $f['filiale_id'], $filialeIds, true) ? [(int) $f['filiale_id']] : [];
        }
        if (empty($filialeIds)) {
            return ['1 = 0', []];
        }
        $ph = implode(',', array_fill(0, count($filialeIds), '?'));
        $where = "c.filiale_id IN ($ph)";
        $params = $filialeIds;
        $pret = self::schemaPret();

        if ($f['q'] !== '') {
            $like = '%' . $f['q'] . '%';
            $champs = ['c.nom', 'c.code', 'c.email', 'c.telephone', 'c.fonction_contact'];
            if ($pret) {
                $champs[] = 'c.contact_prenom';
                $champs[] = 'c.contact_nom';
            }
            $parts = array_map(fn($c) => "$c LIKE ?", $champs);
            $params = array_merge($params, array_fill(0, count($champs), $like));
            if ($pret) {
                $parts[] = "EXISTS (SELECT 1 FROM client_contacts cc WHERE cc.client_id = c.id AND cc.actif = 1
                            AND (cc.prenom LIKE ? OR cc.nom LIKE ? OR cc.email LIKE ? OR cc.telephone LIKE ?))";
                array_push($params, $like, $like, $like, $like);
            }
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        if ($f['pays'] !== '') {
            $where .= ' AND c.pays = ?';
            $params[] = $f['pays'];
        }
        if ($f['type'] !== '') {
            $where .= ' AND c.type = ?';
            $params[] = $f['type'];
        }
        if ($pret && !empty($f['responsable_id'])) {
            $where .= ' AND c.responsable_id = ?';
            $params[] = (int) $f['responsable_id'];
        }
        if ($pret && $f['relation'] !== '') {
            $where .= ' AND c.relation = ?';
            $params[] = $f['relation'];
        }
        if ($avecOnglet && $f['onglet'] === 'actifs') {
            $where .= ' AND c.is_active = 1';
        } elseif ($avecOnglet && $f['onglet'] === 'inactifs') {
            $where .= ' AND c.is_active = 0';
        }
        return [$where, $params];
    }

    private const SOUS_REQUETE_DOSSIERS_ACTIFS =
        "(SELECT COUNT(*) FROM dossiers d INNER JOIN demandes de ON de.id = d.demande_id
          WHERE de.client_id = c.id AND d.statut = 'actif')";

    /** Une page de la liste + total pour la pagination. */
    public static function liste(array $user, array $f, int $page, ?int $parPage = null): array
    {
        $parPage = $parPage ?? self::parPage();
        [$where, $params] = self::clause($user, $f, true);
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients c WHERE $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $parPage));
        $page = min(max(1, $page), $pages);

        $resp = self::schemaPret() ? ', u.nom AS responsable_nom' : '';
        $jointure = self::schemaPret() ? 'LEFT JOIN utilisateurs u ON u.id = c.responsable_id' : '';
        $stmt = $pdo->prepare(
            "SELECT c.*, f.nom AS filiale_nom, " . self::SOUS_REQUETE_DOSSIERS_ACTIFS . " AS nb_dossiers_actifs $resp
             FROM clients c INNER JOIN filiales f ON f.id = c.filiale_id $jointure
             WHERE $where ORDER BY c.nom LIMIT " . (int) $parPage . ' OFFSET ' . (int) (($page - 1) * $parPage)
        );
        $stmt->execute($params);
        return ['lignes' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** Toutes les lignes du périmètre filtré (export), sans pagination. */
    public static function listeComplete(array $user, array $f): array
    {
        [$where, $params] = self::clause($user, $f, true);
        $resp = self::schemaPret() ? ', u.nom AS responsable_nom' : '';
        $jointure = self::schemaPret() ? 'LEFT JOIN utilisateurs u ON u.id = c.responsable_id' : '';
        $stmt = Database::connection()->prepare(
            "SELECT c.*, f.nom AS filiale_nom, " . self::SOUS_REQUETE_DOSSIERS_ACTIFS . " AS nb_dossiers_actifs $resp
             FROM clients c INNER JOIN filiales f ON f.id = c.filiale_id $jointure
             WHERE $where ORDER BY c.nom"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Indicateurs du périmètre filtré (hors onglet) : total, actifs, dossiers actifs. */
    public static function indicateurs(array $user, array $f): array
    {
        [$where, $params] = self::clause($user, $f, false);
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END), 0) AS actifs FROM clients c WHERE $where");
        $stmt->execute($params);
        $r = $stmt->fetch();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM dossiers d INNER JOIN demandes de ON de.id = d.demande_id
             WHERE d.statut = 'actif' AND de.client_id IN (SELECT c.id FROM clients c WHERE $where)"
        );
        $stmt->execute($params);
        return ['total' => (int) $r['total'], 'actifs' => (int) $r['actifs'], 'dossiers_actifs' => (int) $stmt->fetchColumn()];
    }

    /** Identifiants des clients du périmètre filtré (hors onglet) : sert à filtrer la liste des dossiers. */
    public static function idsDuPerimetre(array $user, array $f): array
    {
        [$where, $params] = self::clause($user, $f, false);
        $stmt = Database::connection()->prepare("SELECT c.id FROM clients c WHERE $where");
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Pays présents dans les clients visibles (liste déroulante du filtre). */
    public static function paysUtilises(array $user): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (empty($ids)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("SELECT DISTINCT pays FROM clients WHERE filiale_id IN ($ph) AND pays IS NOT NULL AND pays != '' ORDER BY pays");
        $stmt->execute($ids);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Valeurs proposées par défaut à la création : pays et devise les plus
     * utilisés par les clients de l'entreprise (aucune valeur imposée — rien
     * n'est proposé pour une entreprise sans client, et tout reste modifiable).
     */
    public static function defautsOrganisation(array $user): array
    {
        $ids = Filiale::visibleIdsFor($user);
        $defauts = ['pays' => '', 'devise' => ''];
        if (empty($ids)) {
            return $defauts;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT pays FROM clients WHERE filiale_id IN ($ph) AND pays != '' GROUP BY pays ORDER BY COUNT(*) DESC, pays LIMIT 1");
        $stmt->execute($ids);
        $defauts['pays'] = (string) ($stmt->fetchColumn() ?: '');
        if (self::schemaPret()) {
            $stmt = $pdo->prepare("SELECT devise_preferee FROM clients WHERE filiale_id IN ($ph) AND devise_preferee IS NOT NULL AND devise_preferee != '' GROUP BY devise_preferee ORDER BY COUNT(*) DESC LIMIT 1");
            $stmt->execute($ids);
            $defauts['devise'] = (string) ($stmt->fetchColumn() ?: '');
        }
        if ($defauts['devise'] === '') {
            $stmt = $pdo->prepare("SELECT devise FROM cotations WHERE filiale_id IN ($ph) AND devise IS NOT NULL AND devise != '' GROUP BY devise ORDER BY COUNT(*) DESC LIMIT 1");
            $stmt->execute($ids);
            $defauts['devise'] = (string) ($stmt->fetchColumn() ?: '');
        }
        return $defauts;
    }

    /**
     * Doublons potentiels (nom identique, même e-mail ou même téléphone) parmi
     * les filiales visibles de la personne. Ne bloque rien et ne fusionne
     * jamais : la personne décide.
     */
    public static function doublonsPotentiels(array $user, string $nom, string $email, string $telephone, int $exclureId = 0): array
    {
        $ids = Filiale::visibleIdsFor($user);
        $conds = [];
        $params = [];
        if (trim($nom) !== '') {
            $conds[] = 'LOWER(TRIM(c.nom)) = LOWER(TRIM(?))';
            $params[] = $nom;
        }
        if (trim($email) !== '') {
            $conds[] = "(c.email != '' AND LOWER(c.email) = LOWER(?))";
            $params[] = trim($email);
        }
        $chiffres = preg_replace('/\D/', '', $telephone);
        if (strlen($chiffres) >= 7) {
            $conds[] = "(c.telephone != '' AND REPLACE(REPLACE(REPLACE(c.telephone, ' ', ''), '+', ''), '.', '') LIKE ?)";
            $params[] = '%' . $chiffres;
        }
        if (empty($ids) || empty($conds)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT c.id, c.code, c.nom, c.email, c.telephone FROM clients c WHERE c.filiale_id IN ($ph) AND (" . implode(' OR ', $conds) . ')';
        $params = array_merge($ids, $params);
        if ($exclureId) {
            $sql .= ' AND c.id != ?';
            $params[] = $exclureId;
        }
        $stmt = Database::connection()->prepare($sql . ' ORDER BY c.nom LIMIT 5');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // =====================================================================
    // Contacts supplémentaires (le contact principal est sur la ligne clients)
    // =====================================================================

    public static function contacts(int $clientId, bool $actifsSeulement = false): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $sql = 'SELECT * FROM client_contacts WHERE client_id = ?' . ($actifsSeulement ? ' AND actif = 1' : '') . ' ORDER BY actif DESC, nom, prenom';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function contact(int $clientId, int $contactId): ?array
    {
        if (!self::schemaPret()) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM client_contacts WHERE id = ? AND client_id = ?');
        $stmt->execute([$contactId, $clientId]);
        return $stmt->fetch() ?: null;
    }

    public static function enregistrerContact(int $clientId, ?int $contactId, array $d): int
    {
        $pdo = Database::connection();
        $vals = [trim($d['prenom'] ?? ''), trim($d['nom'] ?? ''), trim($d['fonction'] ?? ''), trim($d['email'] ?? ''), trim($d['telephone'] ?? '')];
        if ($contactId) {
            $pdo->prepare('UPDATE client_contacts SET prenom = ?, nom = ?, fonction = ?, email = ?, telephone = ? WHERE id = ? AND client_id = ?')
                ->execute(array_merge($vals, [$contactId, $clientId]));
            return $contactId;
        }
        $pdo->prepare('INSERT INTO client_contacts (prenom, nom, fonction, email, telephone, client_id, actif, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)')
            ->execute(array_merge($vals, [$clientId, date('Y-m-d H:i:s')]));
        return (int) $pdo->lastInsertId();
    }

    public static function basculerContact(int $clientId, int $contactId, bool $actif): void
    {
        Database::connection()->prepare('UPDATE client_contacts SET actif = ? WHERE id = ? AND client_id = ?')
            ->execute([$actif ? 1 : 0, $contactId, $clientId]);
    }

    /**
     * Promotion d'un contact au rang de contact principal : on ÉCHANGE ses
     * coordonnées avec celles du contact principal (portées par la fiche
     * client, lues par les demandes, cotations et documents). L'ancien
     * principal devient un contact supplémentaire — rien n'est perdu.
     */
    public static function definirContactPrincipal(int $clientId, int $contactId): void
    {
        $client = self::find($clientId);
        $contact = self::contact($clientId, $contactId);
        if (!$client || !$contact || !self::schemaPret()) {
            return;
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE clients SET contact_prenom = ?, contact_nom = ?, fonction_contact = ?, email = ?, telephone = ? WHERE id = ?')
                ->execute([$contact['prenom'], $contact['nom'], $contact['fonction'], $contact['email'], $contact['telephone'], $clientId]);
            $pdo->prepare('UPDATE client_contacts SET prenom = ?, nom = ?, fonction = ?, email = ?, telephone = ?, actif = 1 WHERE id = ?')
                ->execute([
                    (string) ($client['contact_prenom'] ?? ''), (string) ($client['contact_nom'] ?? ''), (string) ($client['fonction_contact'] ?? ''),
                    (string) ($client['email'] ?? ''), (string) ($client['telephone'] ?? ''), $contactId,
                ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =====================================================================
    // Adresses supplémentaires (facturation / livraison)
    // =====================================================================

    public const TYPES_ADRESSE = ['facturation' => 'Facturation', 'livraison' => 'Livraison'];

    public static function adresses(int $clientId): array
    {
        if (!self::schemaPret()) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM client_adresses WHERE client_id = ? ORDER BY actif DESC, type, par_defaut DESC, libelle');
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function adresse(int $clientId, int $adresseId): ?array
    {
        if (!self::schemaPret()) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM client_adresses WHERE id = ? AND client_id = ?');
        $stmt->execute([$adresseId, $clientId]);
        return $stmt->fetch() ?: null;
    }

    public static function enregistrerAdresse(int $clientId, ?int $adresseId, array $d): int
    {
        $pdo = Database::connection();
        $type = array_key_exists($d['type'] ?? '', self::TYPES_ADRESSE) ? $d['type'] : 'livraison';
        $vals = [$type, trim($d['libelle'] ?? ''), trim($d['adresse'] ?? ''), trim($d['code_postal'] ?? ''), trim($d['ville'] ?? ''), trim($d['pays'] ?? '')];
        if ($adresseId) {
            $pdo->prepare('UPDATE client_adresses SET type = ?, libelle = ?, adresse = ?, code_postal = ?, ville = ?, pays = ? WHERE id = ? AND client_id = ?')
                ->execute(array_merge($vals, [$adresseId, $clientId]));
            return $adresseId;
        }
        $pdo->prepare('INSERT INTO client_adresses (type, libelle, adresse, code_postal, ville, pays, client_id, par_defaut, actif, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?)')
            ->execute(array_merge($vals, [$clientId, date('Y-m-d H:i:s')]));
        return (int) $pdo->lastInsertId();
    }

    public static function basculerAdresse(int $clientId, int $adresseId, bool $actif): void
    {
        Database::connection()->prepare('UPDATE client_adresses SET actif = ?, par_defaut = CASE WHEN ? = 0 THEN 0 ELSE par_defaut END WHERE id = ? AND client_id = ?')
            ->execute([$actif ? 1 : 0, $actif ? 1 : 0, $adresseId, $clientId]);
    }

    /**
     * Adresse par défaut. Livraison : une seule adresse « par défaut » parmi les
     * sites de livraison actifs. Facturation : l'adresse de la fiche (colonne
     * adresse/ville/pays) est l'adresse de facturation par défaut ; en choisir
     * une autre ÉCHANGE les deux (l'ancienne devient une adresse supplémentaire).
     */
    public static function definirAdresseParDefaut(int $clientId, int $adresseId): void
    {
        $a = self::adresse($clientId, $adresseId);
        $client = self::find($clientId);
        if (!$a || !$client || (int) $a['actif'] !== 1) {
            return;
        }
        $pdo = Database::connection();
        if ($a['type'] === 'livraison') {
            $pdo->prepare("UPDATE client_adresses SET par_defaut = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE client_id = ? AND type = 'livraison'")
                ->execute([$adresseId, $clientId]);
            return;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE clients SET adresse = ?, code_postal = ?, ville = ?, pays = ? WHERE id = ?')
                ->execute([$a['adresse'], $a['code_postal'], $a['ville'], $a['pays'], $clientId]);
            $pdo->prepare('UPDATE client_adresses SET adresse = ?, code_postal = ?, ville = ?, pays = ?, libelle = \'Ancienne adresse principale\' WHERE id = ?')
                ->execute([(string) $client['adresse'], (string) $client['code_postal'], (string) $client['ville'], (string) $client['pays'], $adresseId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =====================================================================
    // Opérations liées et finances (07/10)
    // =====================================================================

    /** Demandes du client avec leur dossier éventuel (références cliquables, étape, statut, échéance, responsable). */
    public static function operations(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT de.id AS demande_id, de.reference AS demande_reference, de.objet, de.activite, de.statut AS demande_statut,
                    de.echeance AS demande_echeance, de.created_at,
                    d.id AS dossier_id, d.reference AS dossier_reference, d.etape, d.statut AS dossier_statut, d.echeance AS dossier_echeance,
                    u.nom AS responsable_nom
             FROM demandes de
             LEFT JOIN dossiers d ON d.demande_id = de.id
             LEFT JOIN utilisateurs u ON u.id = COALESCE(d.responsable_id, de.responsable_id)
             WHERE de.client_id = ?
             ORDER BY de.created_at DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    /** Début de période pour les indicateurs financiers (null = toutes périodes). */
    public static function debutPeriode(string $periode): ?string
    {
        return match ($periode) {
            'annee' => date('Y') . '-01-01',
            '12m' => date('Y-m-d', strtotime('-12 months')),
            default => null,
        };
    }

    /**
     * Indicateurs financiers d'un client, TOUJOURS séparés par devise (jamais
     * additionnés entre devises : aucune conversion n'est enregistrée dans
     * l'application).
     *  - cotations acceptées : dernière version acceptée de chaque dossier
     *    (évite le double compte entre versions) ;
     *  - facturé : factures non annulées ;
     *  - encaissé : factures marquées « payée » ;
     *  - reste à encaisser : factures « émise » (non payées, non annulées).
     * Les avoirs et règlements partiels ne sont pas gérés par l'application :
     * ils ne sont donc pas déduits (signalé à l'écran).
     * Retourne null si une table financière est indisponible → l'écran affiche
     * « Non disponible », jamais zéro.
     */
    public static function finances(int $clientId, ?string $depuis): ?array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                "SELECT co.id, co.reference, co.dossier_id, co.version, co.montant_total, co.devise, co.statut, co.created_at, d.reference AS dossier_reference
                 FROM cotations co INNER JOIN dossiers d ON d.id = co.dossier_id
                 WHERE co.client_id = ? ORDER BY co.dossier_id, co.version DESC"
            );
            $stmt->execute([$clientId]);
            $cotations = $stmt->fetchAll();

            $stmt = $pdo->prepare(
                "SELECT fa.id, fa.reference, fa.dossier_id, fa.montant, fa.devise, fa.statut, fa.date_emission, fa.date_echeance, d.reference AS dossier_reference
                 FROM factures fa
                 INNER JOIN dossiers d ON d.id = fa.dossier_id
                 INNER JOIN demandes de ON de.id = d.demande_id
                 LEFT JOIN cotations co ON co.id = fa.cotation_id
                 WHERE (co.client_id = ? OR (co.id IS NULL AND de.client_id = ?))
                 ORDER BY fa.date_emission DESC, fa.id DESC"
            );
            $stmt->execute([$clientId, $clientId]);
            $factures = $stmt->fetchAll();

            $stmt = $pdo->prepare(
                "SELECT cm.id, cm.reference, cm.statut, cm.etape, cm.dossier_id, d.reference AS dossier_reference
                 FROM commandes cm
                 INNER JOIN dossiers d ON d.id = cm.dossier_id
                 INNER JOIN demandes de ON de.id = d.demande_id
                 WHERE de.client_id = ? ORDER BY cm.id DESC"
            );
            $stmt->execute([$clientId]);
            $commandes = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return null;
        }

        // Dernière version acceptée par dossier, dans la période.
        $acceptees = [];
        foreach ($cotations as $c) {
            if ($c['statut'] !== 'acceptee' || isset($acceptees[$c['dossier_id']])) {
                continue; // tri par version décroissante : la première rencontrée est la plus récente
            }
            if ($depuis !== null && substr((string) $c['created_at'], 0, 10) < $depuis) {
                continue;
            }
            $acceptees[$c['dossier_id']] = $c;
        }
        $totaux = [];
        $ajout = function (string $cle, ?string $devise, float $montant) use (&$totaux) {
            $devise = $devise !== null && $devise !== '' ? $devise : '—';
            $totaux[$devise][$cle] = ($totaux[$devise][$cle] ?? 0) + $montant;
        };
        foreach ($acceptees as $c) {
            $ajout('acceptees', $c['devise'], (float) $c['montant_total']);
        }
        $nbAnnulees = 0;
        $facturesPeriode = [];
        foreach ($factures as $fa) {
            if ($depuis !== null && ($fa['date_emission'] ?? '') !== '' && $fa['date_emission'] < $depuis) {
                continue;
            }
            $facturesPeriode[] = $fa;
            if ($fa['statut'] === 'annulee') {
                $nbAnnulees++;
                continue;
            }
            $m = (float) $fa['montant'];
            $ajout('facture', $fa['devise'], $m);
            if ($fa['statut'] === 'payee') {
                $ajout('encaisse', $fa['devise'], $m);
            } else {
                $ajout('reste', $fa['devise'], $m);
            }
        }
        ksort($totaux);
        return [
            'totaux' => $totaux,
            'cotations' => array_values(array_filter($cotations, fn($c) => $depuis === null || substr((string) $c['created_at'], 0, 10) >= $depuis)),
            'acceptees_ids' => array_map(fn($c) => (int) $c['id'], $acceptees),
            'factures' => $facturesPeriode,
            'commandes' => $commandes,
            'nb_annulees' => $nbAnnulees,
        ];
    }
}
