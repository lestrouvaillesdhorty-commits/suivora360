<?php

namespace App\Models;

use App\Core\Database;

/**
 * Expéditions : un conteneur ou un envoi acheté à un partenaire transitaire,
 * sur lequel plusieurs dossiers clients réservent une place (groupage) ou un
 * seul le prend en entier (conteneur complet). Le coût d'achat est réparti
 * entre les réservations ; la place libre reste un coût visible.
 */
class Expedition
{
    public const TYPES = ['groupage' => 'Groupage', 'conteneur_complet' => 'Conteneur complet'];
    public const MODES = ['maritime' => 'Maritime', 'aerien' => 'Aérien', 'routier' => 'Routier'];
    public const STATUTS = ['ouverte' => 'Ouverte', 'cloturee' => 'Réservations closes', 'partie' => 'Partie', 'arrivee' => 'Arrivée', 'dedouanee' => 'Dédouanée', 'livree' => 'Livrée aux clients'];
    public const MARCHANDISES = ['alimentaire' => 'Alimentaire', 'non_alimentaire' => 'Non alimentaire'];
    public const TARIFICATIONS = ['forfait' => 'Forfait', 'm3' => 'Au m³', 'kg' => 'Au kilo', 'colis' => 'Par colis', 'palette' => 'Par palette'];

    public const MODES_ACHAT = ['capacite' => "Capacité achetée d'avance", 'tarif' => 'À la demande, au tarif du transitaire'];
    public const TARIFS_ACHAT = ['m3' => 'au m³', 'kg' => 'au kilo', 'colis' => 'par colis', 'palette' => 'par palette'];

    public const CAS_SPECIAUX = ['hors_format' => 'Hors format', 'alcool' => 'Alcool', 'dangereux' => 'Produit dangereux', 'autre' => 'Autre cas particulier'];

    public static function nbAValider(int $expeditionId): int
    {
        if (!self::v60()) {
            return 0;
        }
        $st = Database::connection()->prepare("SELECT COUNT(*) FROM expedition_reservations WHERE expedition_id = ? AND validation_client = 'a_valider'");
        $st->execute([$expeditionId]);
        return (int) $st->fetchColumn();
    }

    /** Corrige les quantités d'une réservation (poids confirmé par le transitaire, etc.) et recalcule son prix vendu ; le coût se recalcule à l'affichage. */
    public static function modifierReservation(array $r, array $d): void
    {
        $suppV = self::v60() ? (float) ($r['supplement_vente'] ?? 0) : 0.0;
        $prix = round(self::prix((string) $r['tarification'], (float) $r['tarif_unitaire'], $d['volume'], $d['poids'], $d['colis'], $d['palettes']) + $suppV, 2);
        $pdo = Database::connection();
        $pdo->prepare('UPDATE expedition_reservations SET volume = ?, poids = ?, colis = ?, palettes = ?, prix_vente = ? WHERE id = ?')
            ->execute([$d['volume'], $d['poids'], $d['colis'], $d['palettes'], $prix, (int) $r['id']]);
        if (self::v60()) {
            $pdo->prepare('UPDATE expedition_reservations SET poids_confirme = ? WHERE id = ?')->execute([!empty($d['poids_confirme']) ? 1 : 0, (int) $r['id']]);
        }
        if (!empty($d['type_produit_id']) && (int) $d['type_produit_id'] !== (int) ($r['type_produit_id'] ?? 0)) {
            self::appliquerType((int) $r['id'], (int) $d['type_produit_id']);
        }
    }

    public static function validerReservation(int $id): void
    {
        if (self::v60()) {
            Database::connection()->prepare("UPDATE expedition_reservations SET validation_client = 'ok', valide_le = ? WHERE id = ?")->execute([date('Y-m-d'), $id]);
        }
    }

    public static function v60(): bool
    {
        return \App\Core\Schema::colonne('expeditions', 'mode_achat') && \App\Core\Schema::colonne('expedition_reservations', 'supplement_cout');
    }

    public static function modeAchat(array $exp): string
    {
        return ($exp['mode_achat'] ?? 'capacite') === 'tarif' ? 'tarif' : 'capacite';
    }

    /** Libellé du statut ; « Ouverte » devient « En regroupement » pour une expédition à la demande. */
    public static function libelleStatut(array $exp, ?string $statut = null): string
    {
        $statut ??= $exp['statut'];
        if ($statut === 'ouverte' && self::modeAchat($exp) === 'tarif') {
            return 'En regroupement';
        }
        return self::STATUTS[$statut] ?? $statut;
    }

    /** Coût d'achat d'une réservation d'une expédition à la demande : tarif du transitaire x quantité, plus supplément. */
    public static function coutTarif(array $exp, array $res): float
    {
        $taux = (float) ($exp['tarif_achat'] ?? 0);
        $unite = $exp['tarif_achat_type'] ?? '';
        if (($res['type_marchandise'] ?? '') === 'alimentaire' && ($exp['tarif_achat_alim'] ?? '') !== '' && $exp['tarif_achat_alim'] !== null) {
            $taux = (float) $exp['tarif_achat_alim'];
            $unite = ($exp['tarif_achat_alim_type'] ?? '') ?: $unite;
        }
        $base = match ($unite) {
            'm3' => (float) ($res['volume'] ?? 0) * $taux,
            'kg' => (float) ($res['poids'] ?? 0) * $taux,
            'colis' => (float) ($res['colis'] ?? 0) * $taux,
            'palette' => (float) ($res['palettes'] ?? 0) * $taux,
            default => 0.0,
        };
        return round($base + (float) ($res['supplement_cout'] ?? 0), 2);
    }

    /** Enregistre les champs du mode « à la demande » (ignoré avant migrate_v60). */
    public static function majTarif(int $id, array $d): void
    {
        if (!self::v60()) {
            return;
        }
        $type = isset(self::TARIFS_ACHAT[$d['tarif_achat_type'] ?? '']) ? $d['tarif_achat_type'] : null;
        $typeAlim = isset(self::TARIFS_ACHAT[$d['tarif_achat_alim_type'] ?? '']) ? $d['tarif_achat_alim_type'] : null;
        Database::connection()->prepare('UPDATE expeditions SET mode_achat = ?, tarif_achat_type = ?, tarif_achat = ?, tarif_achat_alim = ?, tarif_achat_alim_type = ? WHERE id = ?')
            ->execute([($d['mode_achat'] ?? '') === 'tarif' ? 'tarif' : 'capacite', $type, ($d['tarif_achat'] ?? '') !== '' ? $d['tarif_achat'] : null, ($d['tarif_achat_alim'] ?? '') !== '' ? $d['tarif_achat_alim'] : null, $typeAlim, $id]);
    }

    /** Rattache l'expédition à un transitaire de la liste des fournisseurs (0 = nom saisi à la main). */
    public static function definirTransitaire(int $id, int $fournisseurId): void
    {
        Database::connection()->prepare('UPDATE expeditions SET fournisseur_id = ? WHERE id = ?')->execute([$fournisseurId ?: null, $id]);
    }

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM expeditions LIMIT 1');
            Database::connection()->query('SELECT 1 FROM expedition_reservations LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function find(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM expeditions WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function visibleFor(array $user, string $statut = ''): array
    {
        $ids = Filiale::visibleIdsFor($user);
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT e.*, f.nom AS filiale_nom FROM expeditions e INNER JOIN filiales f ON f.id = e.filiale_id WHERE e.filiale_id IN ($in)";
        $p = $ids;
        if ($statut === 'en_cours') {
            $sql .= " AND e.statut IN ('ouverte','cloturee','partie','arrivee','dedouanee')";
        } elseif ($statut !== '' && isset(self::STATUTS[$statut])) {
            $sql .= ' AND e.statut = ?';
            $p[] = $statut;
        }
        $sql .= ' ORDER BY e.id DESC';
        $st = Database::connection()->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['bilan'] = self::bilan($r);
        }
        return $rows;
    }

    /** Expéditions ouvertes aux réservations d'une filiale (pour la liste de choix d'un dossier). */
    public static function ouvertes(int $filialeId): array
    {
        $st = Database::connection()->prepare("SELECT * FROM expeditions WHERE filiale_id = ? AND statut = 'ouverte' ORDER BY date_depart, id DESC");
        $st->execute([$filialeId]);
        return $st->fetchAll();
    }

    public static function create(array $d): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find((int) $d['filiale_id']);
        $ref = Compteur::formatReference('EXP', Compteur::next((int) $filiale['organisation_id'], 'expedition'));
        $pdo->prepare('INSERT INTO expeditions (filiale_id, reference, type, mode, origine, destination, partenaire, date_depart, date_arrivee, cloture_reservations, capacite_volume, capacite_poids, cout_achat, devise, numero_suivi, statut, notes, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                (int) $d['filiale_id'], $ref, $d['type'], $d['mode'], $d['origine'], $d['destination'], $d['partenaire'],
                $d['date_depart'] ?: null, $d['date_arrivee'] ?: null, $d['cloture_reservations'] ?: null,
                $d['capacite_volume'] !== '' ? $d['capacite_volume'] : null, $d['capacite_poids'] !== '' ? $d['capacite_poids'] : null,
                (float) $d['cout_achat'], $d['devise'], $d['numero_suivi'], 'ouverte', $d['notes'] ?? '', $d['created_by'] ?? null, date('Y-m-d H:i:s'),
            ]);
        return (int) $pdo->lastInsertId();
    }

    public static function modifier(int $id, array $d): void
    {
        Database::connection()->prepare('UPDATE expeditions SET type = ?, mode = ?, origine = ?, destination = ?, partenaire = ?, date_depart = ?, date_arrivee = ?, cloture_reservations = ?, capacite_volume = ?, capacite_poids = ?, cout_achat = ?, devise = ?, numero_suivi = ?, notes = ? WHERE id = ?')
            ->execute([
                $d['type'], $d['mode'], $d['origine'], $d['destination'], $d['partenaire'],
                $d['date_depart'] ?: null, $d['date_arrivee'] ?: null, $d['cloture_reservations'] ?: null,
                $d['capacite_volume'] !== '' ? $d['capacite_volume'] : null, $d['capacite_poids'] !== '' ? $d['capacite_poids'] : null,
                (float) $d['cout_achat'], $d['devise'], $d['numero_suivi'], $d['notes'] ?? '', $id,
            ]);
    }

    public static function changerStatut(int $id, string $statut): void
    {
        if (isset(self::STATUTS[$statut])) {
            Database::connection()->prepare('UPDATE expeditions SET statut = ? WHERE id = ?')->execute([$statut, $id]);
        }
    }

    /**
     * Fait avancer les commandes des dossiers liés (étapes Expédition / Dédouanement / Livraison) selon le statut de l'expédition.
     * N'avance que : une étape déjà plus loin n'est jamais ramenée en arrière. Retourne le nombre de dossiers mis à jour.
     */
    public static function propager(array $exp, string $statut): int
    {
        $cible = [
            'ouverte' => ['expedition' => 'en_cours'],
            'cloturee' => ['expedition' => 'en_cours'],
            'partie' => ['expedition' => 'termine', 'livraison' => 'en_cours'],
            'arrivee' => ['expedition' => 'termine', 'douane' => 'en_cours', 'livraison' => 'en_cours'],
            'dedouanee' => ['expedition' => 'termine', 'douane' => 'termine', 'livraison' => 'en_cours'],
            'livree' => ['expedition' => 'termine', 'douane' => 'termine', 'livraison' => 'termine'],
        ][$statut] ?? [];
        if (!$cible || !class_exists(Commande::class)) {
            return 0;
        }
        $rang = ['a_faire' => 0, 'en_cours' => 1, 'termine' => 2];
        $date = $statut === 'partie' ? ($exp['date_depart'] ?: date('Y-m-d')) : date('Y-m-d');
        $nb = 0;
        $vus = [];
        foreach (self::reservations((int) $exp['id']) as $r) {
            $did = (int) ($r['dossier_id'] ?? 0);
            if (!$did || isset($vus[$did])) {
                continue;
            }
            $vus[$did] = true;
            $cmd = Commande::findByDossier($did);
            if (!$cmd) {
                continue;
            }
            $change = false;
            foreach (Commande::steps((int) $cmd['id']) as $step) {
                $nouveau = $cible[$step['libelle']] ?? null;
                if ($nouveau === null || ($rang[$nouveau] ?? 0) <= ($rang[$step['statut']] ?? 0)) {
                    continue;
                }
                $note = (string) ($step['notes'] ?? '');
                $tag = 'Via expédition ' . $exp['reference'];
                Commande::updateStep((int) $step['id'], (int) $cmd['id'], $nouveau, $nouveau === 'termine' ? ($step['date_reelle'] ?: $date) : ($step['date_reelle'] ?: null), strpos($note, $tag) === false ? trim($note . ' ' . $tag) : $note);
                $change = true;
            }
            if (empty($cmd['mode_transport']) && empty($cmd['port_depart'])) {
                Commande::updateTrajet((int) $cmd['id'], $exp['mode'], $exp['origine'], $exp['destination'], $exp['date_arrivee'] ?: null);
            }
            $nb += $change ? 1 : 0;
        }
        return $nb;
    }

    /** Indicateurs du tableau de bord Groupage et transport (jamais d'addition entre devises : la place libre est ventilée par devise). */
    public static function resume(array $filialeIds): array
    {
        $r = ['ouvertes' => 0, 'transit' => 0, 'remplissage' => null, 'libre' => [], 'surveiller' => []];
        if (!$filialeIds || !self::disponible()) {
            return $r;
        }
        $in = implode(',', array_fill(0, count($filialeIds), '?'));
        $st = Database::connection()->prepare("SELECT * FROM expeditions WHERE filiale_id IN ($in) AND statut IN ('ouverte','cloturee','partie') ORDER BY cloture_reservations IS NULL, cloture_reservations, id DESC");
        $st->execute(array_values($filialeIds));
        $somme = 0;
        $nbRemp = 0;
        $aujourdhui = strtotime(date('Y-m-d'));
        foreach ($st->fetchAll() as $e) {
            if ($e['statut'] === 'partie') {
                $r['transit']++;
                continue;
            }
            $b = self::bilan($e);
            if ($e['statut'] === 'ouverte') {
                $r['ouvertes']++;
                $r['libre'][$e['devise']] = ($r['libre'][$e['devise']] ?? 0) + $b['libre'];
            }
            if ($b['remplissage'] !== null) {
                $somme += $b['remplissage'];
                $nbRemp++;
            }
            if ($e['statut'] === 'ouverte' && count($r['surveiller']) < 5) {
                $jours = $e['cloture_reservations'] ? (int) round((strtotime($e['cloture_reservations']) - $aujourdhui) / 86400) : null;
                $r['surveiller'][] = ['id' => (int) $e['id'], 'reference' => $e['reference'], 'trajet' => $e['origine'] . ' → ' . $e['destination'], 'remplissage' => $b['remplissage'], 'jours' => $jours];
            }
        }
        $r['remplissage'] = $nbRemp ? (int) round($somme / $nbRemp) : null;
        return $r;
    }

    /** Fiche Pilotage « Groupage et transport » sur une période (départ ou, à défaut, création). Montants ventilés par devise, jamais additionnés entre devises. */
    public static function fiche(array $user, ?string $debut, ?string $fin, ?int $filialeId = null): array
    {
        $r = ['nb' => 0, 'remplissage' => null, 'm3' => 0.0, 'kg' => 0.0, 'marge' => [], 'marge_moy' => [], 'libre' => [], 'retard' => 0];
        if (!self::disponible()) {
            return $r;
        }
        $ids = Filiale::visibleIdsFor($user);
        if ($filialeId) {
            $ids = array_values(array_intersect($ids, [$filialeId]));
        }
        if (!$ids) {
            return $r;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM expeditions WHERE filiale_id IN ($in)";
        $p = $ids;
        $dateExpr = 'COALESCE(date_depart, SUBSTR(created_at, 1, 10))';
        if ($debut) { $sql .= " AND $dateExpr >= ?"; $p[] = $debut; }
        if ($fin) { $sql .= " AND $dateExpr <= ?"; $p[] = $fin; }
        $st = Database::connection()->prepare($sql);
        $st->execute($p);
        $somme = 0;
        $nbRemp = 0;
        $parDevise = [];
        $aujourdhui = date('Y-m-d');
        foreach ($st->fetchAll() as $e) {
            $b = self::bilan($e);
            $r['nb']++;
            $r['m3'] += $b['volume'];
            $r['kg'] += $b['poids'];
            if ($b['remplissage'] !== null) { $somme += $b['remplissage']; $nbRemp++; }
            $d = $e['devise'];
            $parDevise[$d] = ($parDevise[$d] ?? 0) + 1;
            $r['marge'][$d] = ($r['marge'][$d] ?? 0) + $b['resultat'];
            $r['libre'][$d] = ($r['libre'][$d] ?? 0) + $b['libre'];
            if ($e['statut'] === 'partie' && $e['date_arrivee'] && $e['date_arrivee'] < $aujourdhui) { $r['retard']++; }
        }
        foreach ($parDevise as $d => $n) { $r['marge_moy'][$d] = $r['marge'][$d] / $n; }
        $r['remplissage'] = $nbRemp ? (int) round($somme / $nbRemp) : null;
        return $r;
    }

    public static function reservations(int $expeditionId): array
    {
        $join = TypeProduit::disponible() ? ', tp.libelle AS type_libelle' : '';
        $left = TypeProduit::disponible() ? 'LEFT JOIN expedition_types_produit tp ON tp.id = r.type_produit_id' : '';
        $st = Database::connection()->prepare(
            'SELECT r.*, d.reference AS dossier_reference, c.nom AS client_nom_lie' . $join . ' FROM expedition_reservations r
             LEFT JOIN dossiers d ON d.id = r.dossier_id LEFT JOIN clients c ON c.id = r.client_id ' . $left . '
             WHERE r.expedition_id = ? ORDER BY r.id'
        );
        $st->execute([$expeditionId]);
        return $st->fetchAll();
    }

    public static function reservationsDuDossier(int $dossierId): array
    {
        $st = Database::connection()->prepare(
            'SELECT r.*, e.reference AS expedition_reference, e.origine, e.destination, e.statut AS expedition_statut, e.devise, e.date_depart, e.date_arrivee
             FROM expedition_reservations r INNER JOIN expeditions e ON e.id = r.expedition_id WHERE r.dossier_id = ? ORDER BY r.id DESC'
        );
        $st->execute([$dossierId]);
        return $st->fetchAll();
    }

    public static function findReservation(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM expedition_reservations WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Prix de vente d'après la tarification choisie. */
    public static function prix(string $tarification, float $tarif, ?float $volume, ?float $poids, ?int $colis, ?int $palettes): float
    {
        return round(match ($tarification) {
            'm3' => ($volume ?? 0) * $tarif,
            'kg' => ($poids ?? 0) * $tarif,
            'colis' => ($colis ?? 0) * $tarif,
            'palette' => ($palettes ?? 0) * $tarif,
            default => $tarif,
        }, 2);
    }

    public static function ajouterReservation(array $exp, array $d): int
    {
        $pdo = Database::connection();
        $suppV = self::v60() ? max(0.0, (float) ($d['supplement_vente'] ?? 0)) : 0.0;
        $prix = round(self::prix($d['tarification'], (float) $d['tarif_unitaire'], $d['volume'], $d['poids'], $d['colis'], $d['palettes']) + $suppV, 2);
        $pdo->prepare('INSERT INTO expedition_reservations (expedition_id, filiale_id, dossier_id, client_id, client_nom, type_marchandise, description, volume, poids, colis, palettes, tarification, tarif_unitaire, prix_vente, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                (int) $exp['id'], (int) $exp['filiale_id'], $d['dossier_id'] ?: null, $d['client_id'] ?: null, $d['client_nom'],
                $d['type_marchandise'], $d['description'], $d['volume'], $d['poids'], $d['colis'], $d['palettes'],
                $d['tarification'], (float) $d['tarif_unitaire'], $prix, $d['created_by'] ?? null, date('Y-m-d H:i:s'),
            ]);
        $rid = (int) $pdo->lastInsertId();
        if (self::v60()) {
            $cas = isset(self::CAS_SPECIAUX[$d['cas_special'] ?? '']) ? $d['cas_special'] : null;
            $pdo->prepare('UPDATE expedition_reservations SET supplement_cout = ?, supplement_vente = ?, supplement_motif = ?, cas_special = ?, validation_client = ? WHERE id = ?')
                ->execute([max(0.0, (float) ($d['supplement_cout'] ?? 0)), $suppV, mb_substr((string) ($d['supplement_motif'] ?? ''), 0, 100), $cas, $cas ? 'a_valider' : 'ok', $rid]);
        }
        self::appliquerType($rid, (int) ($d['type_produit_id'] ?? 0), true);
        return $rid;
    }

    /** Applique un type de produit à une réservation : famille (tarif), validation client. */
    public static function appliquerType(int $rid, int $typeId, bool $nouvelle = false): void
    {
        $t = $typeId > 0 ? TypeProduit::find($typeId) : null;
        if (!$t) {
            return;
        }
        $pdo = Database::connection();
        $pdo->prepare('UPDATE expedition_reservations SET type_produit_id = ?, type_marchandise = ? WHERE id = ?')->execute([$typeId, $t['famille'], $rid]);
        if (self::v60()) {
            $cas = (int) $t['validation_client'] === 1 ? (isset(self::CAS_SPECIAUX[$t['code']]) ? $t['code'] : 'autre') : null;
            if ($nouvelle) {
                $pdo->prepare('UPDATE expedition_reservations SET cas_special = ?, validation_client = ? WHERE id = ?')->execute([$cas, $cas ? 'a_valider' : 'ok', $rid]);
            } elseif ($cas) {
                $pdo->prepare("UPDATE expedition_reservations SET cas_special = ?, validation_client = CASE WHEN cas_special IS NULL OR cas_special = '' THEN 'a_valider' ELSE validation_client END WHERE id = ?")->execute([$cas, $rid]);
            } else {
                $pdo->prepare("UPDATE expedition_reservations SET cas_special = NULL, validation_client = 'ok' WHERE id = ?")->execute([$rid]);
            }
        }
    }

    public static function supprimerReservation(int $id): void
    {
        Database::connection()->prepare('DELETE FROM expedition_reservations WHERE id = ?')->execute([$id]);
    }

    /** Part du coût d'achat imputée à une réservation (volume, ou poids en aérien ; tout pour un conteneur complet). */
    public static function coutImpute(array $exp, array $res, float $totalVolume, float $totalPoids): float
    {
        $cout = (float) $exp['cout_achat'];
        if ($exp['type'] === 'conteneur_complet') {
            return $cout;
        }
        $capV = (float) ($exp['capacite_volume'] ?? 0);
        $capP = (float) ($exp['capacite_poids'] ?? 0);
        $v = (float) ($res['volume'] ?? 0);
        $p = (float) ($res['poids'] ?? 0);
        $parV = $capV > 0 ? $v / $capV : null;
        $parP = $capP > 0 ? $p / $capP : null;
        $part = $exp['mode'] === 'aerien' ? ($parP ?? $parV) : ($parV ?? $parP);
        return round($cout * ($part ?? 0), 2);
    }

    /** Remplissage, ventes, coûts imputés, place libre et résultat d'une expédition. */
    public static function bilan(array $exp, ?array $reservations = null): array
    {
        $reservations ??= self::reservations((int) $exp['id']);
        $volume = $poids = $vente = $imputes = 0.0;
        foreach ($reservations as $r) {
            $volume += (float) ($r['volume'] ?? 0);
            $poids += (float) ($r['poids'] ?? 0);
            $vente += (float) $r['prix_vente'];
        }
        if (self::modeAchat($exp) === 'tarif') {
            $lignes = [];
            foreach ($reservations as $r) {
                $c = self::coutTarif($exp, $r);
                $imputes += $c;
                $lignes[(int) $r['id']] = ['cout' => $c, 'marge' => round((float) $r['prix_vente'] - $c, 2)];
            }
            return [
                'volume' => $volume, 'poids' => $poids, 'vente' => round($vente, 2), 'imputes' => round($imputes, 2), 'cout' => round($imputes, 2),
                'libre' => 0.0, 'resultat' => round($vente - $imputes, 2), 'remplissage' => null, 'surcharge' => false,
                'lignes' => $lignes, 'nb' => count($reservations),
            ];
        }
        $lignes = [];
        foreach ($reservations as $r) {
            $c = self::coutImpute($exp, $r, $volume, $poids);
            $imputes += $c;
            $lignes[(int) $r['id']] = ['cout' => $c, 'marge' => round((float) $r['prix_vente'] - $c, 2)];
        }
        $capV = (float) ($exp['capacite_volume'] ?? 0);
        $capP = (float) ($exp['capacite_poids'] ?? 0);
        $rempV = $capV > 0 ? min(100, round($volume / $capV * 100)) : null;
        $rempP = $capP > 0 ? min(100, round($poids / $capP * 100)) : null;
        $remplissage = $exp['type'] === 'conteneur_complet' ? ($reservations ? 100 : 0) : ($exp['mode'] === 'aerien' ? ($rempP ?? $rempV) : ($rempV ?? $rempP));
        $cout = (float) $exp['cout_achat'];
        $libre = $exp['type'] === 'conteneur_complet' ? 0.0 : max(0.0, round($cout - $imputes, 2));
        return [
            'volume' => $volume, 'poids' => $poids, 'vente' => round($vente, 2), 'imputes' => round($imputes, 2), 'cout' => $cout,
            'libre' => $libre, 'resultat' => round($vente - $cout, 2), 'remplissage' => $remplissage,
            'surcharge' => ($capV > 0 && $volume > $capV) || ($capP > 0 && $poids > $capP),
            'lignes' => $lignes, 'nb' => count($reservations),
        ];
    }
}
