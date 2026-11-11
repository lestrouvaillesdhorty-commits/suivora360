<?php

namespace App\Models;

use App\Core\Database;

/**
 * Objectifs mensuels de marge par collaborateur et réglages de commission
 * (taux + base de calcul). Tolère l'absence des tables (migration v40 pas
 * encore lancée) : disponible() permet à l'écran d'afficher un message.
 */
class ObjectifCollaborateur
{
    public const BASES = [
        'responsable' => 'Resp. dossier',
        'offres' => 'Auteur offre',
    ];

    /** Indicateurs d'objectif : clé => [libellé, type] (money = montant, count = nombre). */
    public const INDICATEURS = [
        'marge' => ['Marge', 'money'],
        'ventes' => ["Chiffre d'affaires HT", 'money'],
        'commandes' => ['Nombre de commandes', 'count'],
    ];

    public static function colonneFonction(): bool
    {
        try {
            Database::connection()->query('SELECT fonction_id FROM reglages_collaborateurs LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function colonneIndicateur(): bool
    {
        try {
            Database::connection()->query('SELECT indicateur FROM reglages_collaborateurs LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM objectifs_collaborateurs LIMIT 1');
            Database::connection()->query('SELECT 1 FROM reglages_collaborateurs LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Clé de période valide : AAAA-MM, AAAA-T1..T4, AAAA ou Ldate_date (dates libres). Sinon le mois en cours. */
    public static function cleValide($cle): string
    {
        $cle = is_string($cle) ? trim($cle) : '';
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $cle) || preg_match('/^\d{4}-T[1-4]$/', $cle) || preg_match('/^\d{4}$/', $cle)) {
            return $cle;
        }
        if (preg_match('/^L(\d{4}-\d{2}-\d{2})_(\d{4}-\d{2}-\d{2})$/', $cle, $m) && strtotime($m[1]) && strtotime($m[2]) && $m[1] <= $m[2] && $m[1] === date('Y-m-d', strtotime($m[1])) && $m[2] === date('Y-m-d', strtotime($m[2]))) {
            return $cle;
        }
        return date('Y-m');
    }

    /** Ancien nom conservé : accepte désormais toutes les périodes. */
    public static function moisValide($mois): string
    {
        return self::cleValide($mois);
    }

    /** Les dates libres demandent une colonne « mois » assez large (migration v49). */
    public static function libreDisponible(): bool
    {
        try {
            if (Database::driver() === 'sqlite') {
                return true;
            }
            $stmt = Database::connection()->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'objectifs_collaborateurs' AND column_name = 'mois'");
            return (int) $stmt->fetchColumn() >= 21;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Clé de la période en cours pour un type : mois, trimestre, annee. */
    public static function cleCourante(string $type): string
    {
        if ($type === 'trimestre') {
            return date('Y') . '-T' . (int) ceil((int) date('n') / 3);
        }
        if ($type === 'annee') {
            return date('Y');
        }
        return date('Y-m');
    }

    /**
     * Détail d'une période : type, dates, libellés, et clés de la période précédente,
     * suivante et de la même période un an plus tôt.
     */
    public static function periode($cle): array
    {
        $cle = self::cleValide($cle);
        $mois = ['01' => 'janv.', '02' => 'févr.', '03' => 'mars', '04' => 'avr.', '05' => 'mai', '06' => 'juin', '07' => 'juil.', '08' => 'août', '09' => 'sept.', '10' => 'oct.', '11' => 'nov.', '12' => 'déc.'];
        $moisLong = ['01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril', '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août', '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre'];
        if (preg_match('/^(\d{4})-T([1-4])$/', $cle, $m)) {
            $y = (int) $m[1];
            $q = (int) $m[2];
            $m1 = sprintf('%02d', $q * 3 - 2);
            $m3 = sprintf('%02d', $q * 3);
            $debut = "$y-$m1-01";
            return [
                'type' => 'trimestre', 'cle' => $cle, 'debut' => $debut, 'fin' => date('Y-m-t', strtotime("$y-$m3-01")),
                'label' => "T$q $y (" . $mois[$m1] . ' – ' . $mois[$m3] . ')', 'court' => 'T' . $q . ' ' . substr((string) $y, 2),
                'prec' => $q === 1 ? ($y - 1) . '-T4' : $y . '-T' . ($q - 1),
                'suiv' => $q === 4 ? ($y + 1) . '-T1' : $y . '-T' . ($q + 1),
                'an' => ($y - 1) . '-T' . $q,
            ];
        }
        if (preg_match('/^\d{4}$/', $cle)) {
            $y = (int) $cle;
            return ['type' => 'annee', 'cle' => $cle, 'debut' => "$y-01-01", 'fin' => "$y-12-31", 'label' => 'Année ' . $y, 'court' => (string) $y,
                'prec' => (string) ($y - 1), 'suiv' => (string) ($y + 1), 'an' => (string) ($y - 1)];
        }
        if (preg_match('/^L(\d{4}-\d{2}-\d{2})_(\d{4}-\d{2}-\d{2})$/', $cle, $m)) {
            $d1 = new \DateTimeImmutable($m[1]);
            $d2 = new \DateTimeImmutable($m[2]);
            $n = (int) $d1->diff($d2)->days + 1;
            $fmt = fn(\DateTimeImmutable $d) => $d->format('Y-m-d');
            return [
                'type' => 'libre', 'cle' => $cle, 'debut' => $m[1], 'fin' => $m[2],
                'label' => 'Du ' . $d1->format('d/m/Y') . ' au ' . $d2->format('d/m/Y'), 'court' => $d1->format('d/m') . '–' . $d2->format('d/m'),
                'prec' => 'L' . $fmt($d1->modify('-' . $n . ' days')) . '_' . $fmt($d1->modify('-1 day')),
                'suiv' => 'L' . $fmt($d2->modify('+1 day')) . '_' . $fmt($d2->modify('+' . $n . ' days')),
                'an' => 'L' . $fmt($d1->modify('-1 year')) . '_' . $fmt($d2->modify('-1 year')),
            ];
        }
        $y = (int) substr($cle, 0, 4);
        $mm = substr($cle, 5, 2);
        $debut = $cle . '-01';
        return [
            'type' => 'mois', 'cle' => $cle, 'debut' => $debut, 'fin' => date('Y-m-t', strtotime($debut)),
            'label' => $moisLong[$mm] . ' ' . $y, 'court' => $mois[$mm] . ' ' . substr((string) $y, 2),
            'prec' => date('Y-m', strtotime($debut . ' -1 month')), 'suiv' => date('Y-m', strtotime($debut . ' +1 month')),
            'an' => ($y - 1) . '-' . $mm,
        ];
    }

    /** @return array<int,array{taux_commission:float,base:string,indicateur:string}> */
    public static function reglages(int $organisationId): array
    {
        $col = (self::colonneIndicateur() ? ', indicateur' : '') . (self::colonneFonction() ? ', fonction_id' : '');
        $stmt = Database::connection()->prepare('SELECT utilisateur_id, taux_commission, base' . $col . ' FROM reglages_collaborateurs WHERE organisation_id = ?');
        $stmt->execute([$organisationId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['utilisateur_id']] = ['taux_commission' => (float) $r['taux_commission'], 'base' => $r['base'], 'indicateur' => $r['indicateur'] ?? 'marge', 'fonction_id' => isset($r['fonction_id']) && $r['fonction_id'] ? (int) $r['fonction_id'] : null];
        }
        return $out;
    }

    /** @return array<int,array{objectif_marge:float,devise:string}> */
    public static function objectifs(int $organisationId, string $mois): array
    {
        $stmt = Database::connection()->prepare('SELECT utilisateur_id, objectif_marge, devise FROM objectifs_collaborateurs WHERE organisation_id = ? AND mois = ?');
        $stmt->execute([$organisationId, $mois]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['utilisateur_id']] = ['objectif_marge' => (float) $r['objectif_marge'], 'devise' => $r['devise']];
        }
        return $out;
    }

    public static function enregistrer(int $organisationId, int $utilisateurId, string $mois, float $objectif, string $devise, float $taux, string $base, string $indicateur = 'marge', ?int $fonctionId = null): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $base = isset(self::BASES[$base]) ? $base : 'responsable';
        $indicateur = isset(self::INDICATEURS[$indicateur]) ? $indicateur : 'marge';
        $avecInd = self::colonneIndicateur();
        $avecFct = self::colonneFonction();
        $taux = max(0.0, min(100.0, $taux));
        $objectif = max(0.0, $objectif);

        $stmt = $pdo->prepare('SELECT id FROM objectifs_collaborateurs WHERE utilisateur_id = ? AND mois = ?');
        $stmt->execute([$utilisateurId, $mois]);
        if ($stmt->fetchColumn()) {
            $pdo->prepare('UPDATE objectifs_collaborateurs SET objectif_marge = ?, devise = ?, updated_at = ? WHERE utilisateur_id = ? AND mois = ?')
                ->execute([$objectif, $devise, $now, $utilisateurId, $mois]);
        } else {
            $pdo->prepare('INSERT INTO objectifs_collaborateurs (organisation_id, utilisateur_id, mois, objectif_marge, devise, updated_at) VALUES (?,?,?,?,?,?)')
                ->execute([$organisationId, $utilisateurId, $mois, $objectif, $devise, $now]);
        }

        $stmt = $pdo->prepare('SELECT id FROM reglages_collaborateurs WHERE utilisateur_id = ?');
        $stmt->execute([$utilisateurId]);
        if ($stmt->fetchColumn()) {
            $pdo->prepare('UPDATE reglages_collaborateurs SET taux_commission = ?, base = ?, updated_at = ?' . ($avecInd ? ', indicateur = ?' : '') . ($avecFct ? ', fonction_id = ?' : '') . ' WHERE utilisateur_id = ?')
                ->execute(array_merge([$taux, $base, $now], $avecInd ? [$indicateur] : [], $avecFct ? [$fonctionId] : [], [$utilisateurId]));
        } else {
            $pdo->prepare('INSERT INTO reglages_collaborateurs (organisation_id, utilisateur_id, taux_commission, base, updated_at' . ($avecInd ? ', indicateur' : '') . ($avecFct ? ', fonction_id' : '') . ') VALUES (?,?,?,?,?' . ($avecInd ? ',?' : '') . ($avecFct ? ',?' : '') . ')')
                ->execute(array_merge([$organisationId, $utilisateurId, $taux, $base, $now], $avecInd ? [$indicateur] : [], $avecFct ? [$fonctionId] : []));
        }
    }

    /** Copie les objectifs de la période précédente vers $cle pour les collaborateurs sans objectif sur cette période. */
    public static function copierMoisPrecedent(int $organisationId, string $cle): int
    {
        $prec = self::periode($cle)['prec'];
        $existants = self::objectifs($organisationId, $cle);
        $reglages = self::reglages($organisationId);
        $n = 0;
        foreach (self::objectifs($organisationId, $prec) as $uid => $o) {
            if (isset($existants[$uid])) {
                continue;
            }
            $r = $reglages[$uid] ?? ['taux_commission' => 0.0, 'base' => 'responsable', 'indicateur' => 'marge'];
            self::enregistrer($organisationId, $uid, $cle, $o['objectif_marge'], $o['devise'], $r['taux_commission'], $r['base'], $r['indicateur'], $r['fonction_id'] ?? null);
            $n++;
        }
        return $n;
    }
}
