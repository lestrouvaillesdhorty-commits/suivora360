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

    public static function reservations(int $expeditionId): array
    {
        $st = Database::connection()->prepare(
            'SELECT r.*, d.reference AS dossier_reference, c.nom AS client_nom_lie FROM expedition_reservations r
             LEFT JOIN dossiers d ON d.id = r.dossier_id LEFT JOIN clients c ON c.id = r.client_id
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
        $prix = self::prix($d['tarification'], (float) $d['tarif_unitaire'], $d['volume'], $d['poids'], $d['colis'], $d['palettes']);
        $pdo->prepare('INSERT INTO expedition_reservations (expedition_id, filiale_id, dossier_id, client_id, client_nom, type_marchandise, description, volume, poids, colis, palettes, tarification, tarif_unitaire, prix_vente, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                (int) $exp['id'], (int) $exp['filiale_id'], $d['dossier_id'] ?: null, $d['client_id'] ?: null, $d['client_nom'],
                $d['type_marchandise'], $d['description'], $d['volume'], $d['poids'], $d['colis'], $d['palettes'],
                $d['tarification'], (float) $d['tarif_unitaire'], $prix, $d['created_by'] ?? null, date('Y-m-d H:i:s'),
            ]);
        return (int) $pdo->lastInsertId();
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
            'volume' => $volume, 'poids' => $poids, 'vente' => round($vente, 2), 'imputes' => round($imputes, 2),
            'libre' => $libre, 'resultat' => round($vente - $cout, 2), 'remplissage' => $remplissage,
            'surcharge' => ($capV > 0 && $volume > $capV) || ($capP > 0 && $poids > $capP),
            'lignes' => $lignes, 'nb' => count($reservations),
        ];
    }
}
