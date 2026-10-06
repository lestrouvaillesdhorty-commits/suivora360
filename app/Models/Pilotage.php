<?php

namespace App\Models;

use App\Core\Database;

/**
 * Module Pilotage v2 — trois onglets (Vue générale / Activités / Collaborateurs).
 * =============================================================================
 * Documentation de fiabilité exigée par la spec (section 6) : chaque indicateur
 * ci-dessous précise sa source, sa formule, le champ de date utilisé pour le
 * filtrage, les statuts inclus/exclus, et le traitement des données absentes.
 *
 * -----------------------------------------------------------------------------
 * POPULATIONS DE RÉFÉRENCE (toutes les cartes/graphiques/tableaux d'un même
 * onglet interrogent la MÊME population, pour garantir la cohérence exigée par
 * la spec — "les graphiques, tableaux et cartes doivent correspondre aux mêmes
 * données").
 * -----------------------------------------------------------------------------
 *
 * A) « Commandes confirmées » (utilisée pour Montant HT / Marge / tableaux
 *    d'activité, indépendamment de la base prévisionnel/réalisé) :
 *    - Source : table `commandes`, jointe à sa `cotations` d'origine (1
 *      commande = 1 cotation, cf. commandes.cotation_id), jointe à
 *      `dossiers` puis `demandes` (pour l'activité).
 *    - Champ de date de filtrage : commandes.created_at (date de confirmation
 *      de la commande — pas la date de la cotation ni de la demande).
 *    - Une commande confirmée NE signifie PAS qu'elle est payée (cf. règle
 *      spec §6) : aucune hypothèse d'encaissement n'est faite ici.
 *    - Montant HT = cotations.montant_total ; Marge = cotations.marge_montant
 *      (peut être NULL si aucun montant_achat n'a été renseigné sur la
 *      cotation — affiché "Non renseigné", jamais remplacé par 0).
 *    - Dédoublonnage : 1 commande = 1 cotation (contrainte dossier_id UNIQUE
 *      sur `commandes`), donc aucun risque de compter deux fois une même
 *      cotation ici.
 *
 * B) Base « Prévisionnel » (marge/valeur non encore confirmées en commande) :
 *    - Source : `cotations` statut = 'acceptee'.
 *    - Champ de date : cotations.created_at (aucun champ "acceptee_le" dédié
 *      n'existe aujourd'hui dans le schéma — limite documentée, déjà signalée
 *      dans le code v1).
 *    - Représente l'engagement commercial dès acceptation client, avant même
 *      la création de la commande de suivi.
 *
 * C) Base « Réalisé » (marge/valeur confirmées ET facturées) :
 *    - Source : `cotations` acceptées dont le dossier a au moins une facture
 *      émise (statut IN 'emise','payee' — 'annulee' exclue) sur la période.
 *    - Champ de date : factures.date_emission.
 *    - Chaque cotation n'est comptée qu'UNE SEULE FOIS même si plusieurs
 *      factures y sont rattachées (ex : acompte + solde) — évite le double
 *      compte explicitement proscrit par la spec.
 *    - Limite documentée : si une cotation n'est que partiellement facturée
 *      (ex : seul l'acompte est émis), sa marge totale est néanmoins comptée
 *      en entier — affiner nécessiterait un lien facture→montant de cotation
 *      qui n'existe pas dans le schéma actuel.
 *    - « Encaissements » (sous-ensemble de C, ventes réellement payées) :
 *      SUM(factures.montant) WHERE statut='payee' — indicateur distinct,
 *      affiché en complément, jamais confondu avec "ventes réalisées".
 *
 * D) « Valeur des dossiers actifs » (photographie à date, PAS un flux sur la
 *    période) :
 *    - Source : dernière cotation acceptée de chaque dossier dont
 *      dossiers.statut = 'actif'.
 *    - Ne dépend PAS de la période sélectionnée (c'est un état courant, pas
 *      un cumul sur un intervalle) — seuls activité/responsable s'appliquent.
 *      Documenté explicitement dans l'infobulle de la carte.
 *
 * -----------------------------------------------------------------------------
 * DEVISE DE REPORTING
 * -----------------------------------------------------------------------------
 * Aucune table de taux de change multi-devises n'existe dans le schéma actuel.
 * Seul `parametres.taux_eur_fcfa` (un taux fixe par filiale, avec sa date de
 * mise à jour `taux_date_maj`) est disponible. En conséquence :
 *   - Un montant déjà dans la devise de reporting demandée n'est pas converti.
 *   - Une conversion EUR <-> FCFA est appliquée via ce taux (le taux et sa
 *     date sont toujours affichés à côté du total converti).
 *   - Tout montant dans une autre devise (USD, etc.) NE PEUT PAS être converti
 *     : il est exclu du total consolidé et signalé séparément ("N non
 *     converti(s), voir détail") plutôt que d'être additionné à tort ou
 *     silencieusement ignoré.
 *
 * -----------------------------------------------------------------------------
 * ACTIVITÉ vs TYPE DE DOSSIER
 * -----------------------------------------------------------------------------
 * L'activité (Demande::ACTIVITES, 8 valeurs réelles configurées dans
 * l'organisation) classe les opérations pour l'analyse. Le dossier n'a pas sa
 * propre colonne "activite" : elle est toujours retrouvée via la jointure
 * dm.id = d.demande_id. Le "type de dossier"/parcours opérationnel
 * (Dossier::ETAPES) est un axe distinct qui n'influence jamais le classement
 * par activité. Aucune activité n'est codée en dur : la liste vient de
 * Demande::ACTIVITES + toute valeur réellement présente en base (au cas où
 * d'anciennes données porteraient une activité non standard), jamais des
 * exemples fictifs des maquettes.
 */
class Pilotage
{
    public const PERIODES = [
        'mois' => 'Ce mois',
        'trimestre' => 'Ce trimestre',
        'annee' => 'Cette année',
        'personnalise' => 'Période personnalisée',
    ];

    public const BASES = [
        'previsionnel' => 'Prévisionnel',
        'realise' => 'Réalisé',
    ];

    /** Rôles pour lesquels l'onglet Collaborateurs propose un jeu d'indicateurs dédié. */
    public const ROLES_COLLABORATEUR = [
        'commercial' => 'Commercial',
        'achats' => 'Achats',
    ];

    // -------------------------------------------------------------------
    // Formatage (partagé par toutes les vues du module)
    // -------------------------------------------------------------------

    public static function fmt(?float $montant, string $devise = ''): string
    {
        if ($montant === null) {
            return '—';
        }
        $s = number_format($montant, 0, ',', ' ') . ' ';
        return $s . ($devise === 'FCFA' ? 'FCFA' : ($devise !== '' ? $devise : '€'));
    }

    public static function fmtPct(?float $pct): string
    {
        return $pct === null ? '—' : number_format($pct, 1, ',', ' ') . ' %';
    }

    // -------------------------------------------------------------------
    // Tendances — comparaison vs période précédente de même longueur
    // -------------------------------------------------------------------

    /**
     * Calcule les bornes d'une période immédiatement précédente, de même
     * durée (en jours) que la période sélectionnée, pour permettre une
     * comparaison "vs période précédente" homogène quelle que soit la
     * période choisie (mois/trimestre/année/personnalisée).
     */
    public static function filtresPeriodePrecedente(array $filters): array
    {
        $debut = $filters['date_debut'];
        $fin = $filters['date_fin'];
        $jours = (int) round((strtotime($fin) - strtotime($debut)) / 86400) + 1;
        $jours = max(1, $jours);
        $finPrecedente = date('Y-m-d', strtotime($debut . ' -1 day'));
        $debutPrecedente = date('Y-m-d', strtotime($finPrecedente . ' -' . ($jours - 1) . ' days'));

        $f = $filters;
        $f['date_debut'] = $debutPrecedente;
        $f['date_fin'] = $finPrecedente;
        return $f;
    }

    /**
     * Compare une valeur actuelle à sa valeur sur la période précédente.
     * Ne fabrique jamais un pourcentage à partir de rien : si la valeur
     * précédente est nulle/inconnue, retourne direction=null (rien
     * n'est affiché plutôt qu'un delta inventé), sauf le cas "nouveau"
     * (précédent = 0, actuel > 0) où un pourcentage n'a pas de sens
     * mathématique mais où signaler une nouveauté reste honnête.
     */
    public static function tendance(?float $actuel, ?float $precedent): array
    {
        if ($actuel === null || $precedent === null) {
            return ['delta_pct' => null, 'direction' => null, 'nouveau' => false];
        }
        if (abs($precedent) < 0.005) {
            if (abs($actuel) < 0.005) {
                return ['delta_pct' => null, 'direction' => 'stable', 'nouveau' => false];
            }
            return ['delta_pct' => null, 'direction' => 'up', 'nouveau' => true];
        }
        $delta = round((($actuel - $precedent) / abs($precedent)) * 100, 1);
        $direction = $delta > 0.05 ? 'up' : ($delta < -0.05 ? 'down' : 'stable');
        return ['delta_pct' => $delta, 'direction' => $direction, 'nouveau' => false];
    }

    /** Rendu HTML du badge de tendance (icône + %), ou chaîne vide si rien de fiable à afficher. */
    public static function badgeTendance(?array $t): string
    {
        if (!$t || $t['direction'] === null) {
            return '';
        }
        if ($t['nouveau']) {
            return '<span class="trend-badge trend-up">' . \App\Core\Icon::svg('trending-up', 'icon', 12) . ' Nouveau</span>';
        }
        if ($t['direction'] === 'stable' && $t['delta_pct'] === null) {
            return '<span class="trend-badge trend-flat">' . \App\Core\Icon::svg('minus', 'icon', 12) . ' Stable</span>';
        }
        $cls = $t['direction'] === 'up' ? 'trend-up' : ($t['direction'] === 'down' ? 'trend-down' : 'trend-flat');
        $icon = $t['direction'] === 'up' ? 'trending-up' : ($t['direction'] === 'down' ? 'trending-down' : 'minus');
        $signe = $t['delta_pct'] > 0 ? '+' : '';
        return '<span class="trend-badge ' . $cls . '">' . \App\Core\Icon::svg($icon, 'icon', 12) . ' ' . $signe . number_format($t['delta_pct'], 1, ',', ' ') . ' %</span>';
    }

    /** Palette stable (même couleur pour une même série d'un rendu à l'autre). */
    public const PALETTE = ['#5036F5', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#ec4899', '#14b8a6'];

    public static function couleurPour(int $index): string
    {
        return self::PALETTE[$index % count(self::PALETTE)];
    }

    /**
     * Génère un graphique en courbes (SVG pur, sans dépendance) pour une ou
     * plusieurs séries mensuelles. $series = [nomSerie => [valeur1, valeur2, ...]].
     */
    public static function svgLineChart(array $series, array $labelsMois, int $largeur = 760, int $hauteur = 220): string
    {
        if (empty($series) || empty($labelsMois)) {
            return '';
        }
        $toutesValeurs = [];
        foreach ($series as $vals) {
            foreach ($vals as $v) {
                $toutesValeurs[] = $v;
            }
        }
        $max = max($toutesValeurs ?: [0]);
        $max = $max <= 0 ? 1 : $max * 1.15;
        $padL = 55; $padB = 26; $padT = 10; $padR = 15;
        $plotW = $largeur - $padL - $padR;
        $plotH = $hauteur - $padT - $padB;
        $n = count($labelsMois);
        $stepX = $n > 1 ? $plotW / ($n - 1) : 0;

        $svg = '<svg class="linechart-svg" viewBox="0 0 ' . $largeur . ' ' . $hauteur . '" xmlns="http://www.w3.org/2000/svg">';
        // Grille horizontale (4 lignes) + labels Y
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $plotH - ($plotH * $i / 4);
            $val = $max * $i / 4;
            $svg .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($largeur - $padR) . '" y2="' . round($y, 1) . '" stroke="#eef0f4" stroke-width="1"/>';
            $svg .= '<text x="' . ($padL - 8) . '" y="' . round($y + 3, 1) . '" font-size="9" fill="#999" text-anchor="end">' . self::compact($val) . '</text>';
        }
        // Labels X
        foreach ($labelsMois as $i => $lbl) {
            $x = $padL + $stepX * $i;
            $svg .= '<text x="' . round($x, 1) . '" y="' . ($hauteur - 6) . '" font-size="9" fill="#999" text-anchor="middle">' . htmlspecialchars($lbl) . '</text>';
        }
        $idx = 0;
        foreach ($series as $vals) {
            $couleur = self::couleurPour($idx);
            $points = [];
            foreach (array_values($vals) as $i => $v) {
                $x = $padL + $stepX * $i;
                $y = $padT + $plotH - ($plotH * min($v, $max) / $max);
                $points[] = round($x, 1) . ',' . round($y, 1);
            }
            $svg .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $couleur . '" stroke-width="2.5"/>';
            foreach (explode(' ', implode(' ', $points)) as $pt) {
                [$x, $y] = explode(',', $pt);
                $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="3" fill="' . $couleur . '"/>';
            }
            $idx++;
        }
        $svg .= '</svg>';
        return $svg;
    }

    private static function compact(float $v): string
    {
        if ($v >= 1000) {
            return round($v / 1000, 1) . 'k';
        }
        return (string) round($v);
    }

    /**
     * Génère un graphique en barres groupées (SVG pur, sans dépendance),
     * pour comparer plusieurs séries (ex : Ventes HT / Marge) par catégorie
     * (ex : une activité) — même approche que svgLineChart(), pour rester
     * cohérent visuellement sans introduire de bibliothèque externe.
     * $series = [nomSerie => [valeur_categorie1, valeur_categorie2, ...]],
     * valeurs alignées sur $labels dans le même ordre. $couleurs (optionnel)
     * = [nomSerie => couleur hex] ; sinon couleurPour(index) est utilisée.
     */
    public static function svgBarChart(array $labels, array $series, array $couleurs = [], int $largeur = 760, int $hauteur = 260): string
    {
        if (empty($labels) || empty($series)) {
            return '';
        }
        $toutesValeurs = [];
        foreach ($series as $vals) {
            foreach ($vals as $v) {
                $toutesValeurs[] = $v;
            }
        }
        $max = max($toutesValeurs ?: [0]);
        $max = $max <= 0 ? 1 : $max * 1.15;
        $padL = 55; $padB = 40; $padT = 14; $padR = 15;
        $plotW = $largeur - $padL - $padR;
        $plotH = $hauteur - $padT - $padB;
        $n = count($labels);
        $nSeries = count($series);
        $groupW = $n > 0 ? $plotW / $n : 0;
        $gapGroup = $groupW * 0.28;
        $barsW = $groupW - $gapGroup;
        $barW = $nSeries > 0 ? $barsW / $nSeries : 0;

        $svg = '<svg class="barchart-svg" viewBox="0 0 ' . $largeur . ' ' . $hauteur . '" xmlns="http://www.w3.org/2000/svg">';
        // Grille horizontale (4 lignes) + labels Y
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $plotH - ($plotH * $i / 4);
            $val = $max * $i / 4;
            $svg .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($largeur - $padR) . '" y2="' . round($y, 1) . '" stroke="#eef0f4" stroke-width="1"/>';
            $svg .= '<text x="' . ($padL - 8) . '" y="' . round($y + 3, 1) . '" font-size="9" fill="#999" text-anchor="end">' . self::compact($val) . '</text>';
        }
        // Barres groupées + labels X (repliés sur 2 lignes si le libellé est long)
        foreach ($labels as $gi => $label) {
            $groupX = $padL + $groupW * $gi + $gapGroup / 2;
            $si = 0;
            foreach ($series as $nomSerie => $vals) {
                $v = array_values($vals)[$gi] ?? 0;
                $couleur = $couleurs[$nomSerie] ?? self::couleurPour($si);
                $h = $plotH * min($v, $max) / $max;
                $x = $groupX + $barW * $si;
                $y = $padT + $plotH - $h;
                $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW * 0.82, 1) . '" height="' . round($h, 1) . '" rx="3" fill="' . $couleur . '"/>';
                $si++;
            }
            $xLabel = $padL + $groupW * $gi + $groupW / 2;
            $lignesLabel = self::wrapLabel((string) $label, 15);
            foreach ($lignesLabel as $li => $ligne) {
                $svg .= '<text x="' . round($xLabel, 1) . '" y="' . ($hauteur - 22 + $li * 11) . '" font-size="9" fill="#999" text-anchor="middle">' . htmlspecialchars($ligne) . '</text>';
            }
        }
        $svg .= '</svg>';
        return $svg;
    }

    /** Découpe un libellé trop long sur 2 lignes (labels d'axe X), en coupant sur un espace. */
    private static function wrapLabel(string $s, int $maxCharsParLigne): array
    {
        if (mb_strlen($s) <= $maxCharsParLigne) {
            return [$s];
        }
        $mots = explode(' ', $s);
        $ligne1 = '';
        $ligne2 = '';
        foreach ($mots as $mot) {
            if ($ligne1 === '' || mb_strlen($ligne1 . ' ' . $mot) <= $maxCharsParLigne) {
                $ligne1 = trim($ligne1 . ' ' . $mot);
            } else {
                $ligne2 = trim($ligne2 . ' ' . $mot);
            }
        }
        return $ligne2 !== '' ? [$ligne1, $ligne2] : [$ligne1];
    }

    /** Anneau (donut) en CSS conic-gradient — $lignes = [['label'=>,'montant'=>,'part'=>], ...]. */
    public static function cssDonut(array $lignes): string
    {
        $segments = [];
        $cursor = 0.0;
        foreach ($lignes as $i => $l) {
            $couleur = self::couleurPour($i);
            $fin = $cursor + $l['part'];
            $segments[] = $couleur . ' ' . round($cursor, 2) . '% ' . round($fin, 2) . '%';
            $cursor = $fin;
        }
        if ($cursor < 100 && !empty($segments)) {
            $segments[] = '#e5e7eb ' . round($cursor, 2) . '% 100%';
        }
        $gradient = empty($segments) ? '#e5e7eb' : 'conic-gradient(' . implode(', ', $segments) . ')';
        return $gradient;
    }

    // -------------------------------------------------------------------
    // Résolution des filtres communs
    // -------------------------------------------------------------------

    /**
     * Normalise les filtres reçus de la requête HTTP : résout la période en
     * bornes de dates concrètes, valide la base et la devise. Toujours
     * appelé une seule fois par le contrôleur, le résultat est ensuite
     * transmis tel quel à toutes les méthodes de cette classe.
     */
    public static function resoudreFiltres(array $input): array
    {
        $periode = in_array($input['periode'] ?? '', array_keys(self::PERIODES), true) ? $input['periode'] : 'mois';
        $base = in_array($input['base'] ?? '', array_keys(self::BASES), true) ? $input['base'] : 'previsionnel';
        $devise = trim(self::champScalaire($input['devise'] ?? '')) ?: 'EUR';

        [$dateDebut, $dateFin, $periodeLabel] = self::bornesPeriode(
            $periode,
            self::dateValide($input['date_debut'] ?? ''),
            self::dateValide($input['date_fin'] ?? '')
        );

        return [
            'periode' => $periode,
            'periode_label' => $periodeLabel,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'activite' => trim(self::champScalaire($input['activite'] ?? '')),
            'responsable_id' => self::champScalaire($input['responsable_id'] ?? ''),
            'base' => $base,
            'devise' => $devise,
            'role' => in_array($input['role'] ?? '', array_keys(self::ROLES_COLLABORATEUR), true) ? $input['role'] : 'commercial',
        ];
    }

    /**
     * Un filtre reçu depuis $_GET peut être un tableau (ex. ?responsable_id[]=1
     * dans l'URL) au lieu de la chaîne attendue — PDO lève alors un warning
     * "Array to string conversion" en paramètre de requête préparée. On
     * neutralise cette entrée en chaîne vide plutôt que de la laisser remonter
     * telle quelle jusqu'à la requête SQL.
     */
    private static function champScalaire($valeur): string
    {
        return is_scalar($valeur) ? (string) $valeur : '';
    }

    /**
     * Valide qu'une date saisie (ex. ?date_debut=...) est bien une date
     * Y-m-d réelle avant de l'utiliser. Une date invalide ou absente est
     * neutralisée en chaîne vide : bornesPeriode() applique alors son
     * défaut (début du mois / aujourd'hui) au lieu de fabriquer une date
     * du type 1970-01-01 via strtotime(false).
     */
    private static function dateValide($valeur): string
    {
        $valeur = self::champScalaire($valeur);
        if ($valeur === '') {
            return '';
        }
        $ts = strtotime($valeur);
        return $ts !== false ? date('Y-m-d', $ts) : '';
    }

    private static function bornesPeriode(string $periode, string $debutSaisi, string $finSaisi): array
    {
        $aujourdhui = date('Y-m-d');
        switch ($periode) {
            case 'trimestre':
                $moisCourant = (int) date('n');
                $debutTrimestre = (int) ((floor(($moisCourant - 1) / 3)) * 3) + 1;
                $debut = date('Y') . '-' . str_pad((string) $debutTrimestre, 2, '0', STR_PAD_LEFT) . '-01';
                $fin = $aujourdhui;
                return [$debut, $fin, 'Ce trimestre (' . date('d/m/Y', strtotime($debut)) . ' – ' . date('d/m/Y', strtotime($fin)) . ')'];
            case 'annee':
                $debut = date('Y') . '-01-01';
                $fin = $aujourdhui;
                return [$debut, $fin, 'Cette année (' . date('d/m/Y', strtotime($debut)) . ' – ' . date('d/m/Y', strtotime($fin)) . ')'];
            case 'personnalise':
                $debut = $debutSaisi ?: date('Y-m-01');
                $fin = $finSaisi ?: $aujourdhui;
                return [$debut, $fin, 'Du ' . date('d/m/Y', strtotime($debut)) . ' au ' . date('d/m/Y', strtotime($fin))];
            case 'mois':
            default:
                $debut = date('Y-m-01');
                $fin = $aujourdhui;
                return [$debut, $fin, 'Ce mois (' . date('d/m/Y', strtotime($debut)) . ' – ' . date('d/m/Y', strtotime($fin)) . ')'];
        }
    }

    // -------------------------------------------------------------------
    // Conversion de devise
    // -------------------------------------------------------------------

    /**
     * Charge parametres.taux_eur_fcfa pour un ensemble de filiales, une
     * seule fois par requête Pilotage (évite le N+1).
     */
    private static function chargerTaux(array $filialeIds): array
    {
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT filiale_id, taux_eur_fcfa, taux_date_maj FROM parametres WHERE filiale_id IN ($placeholders)"
        );
        $stmt->execute($filialeIds);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['filiale_id']] = $row;
        }
        return $out;
    }

    /**
     * Convertit un montant vers la devise de reporting. Retourne toujours un
     * résultat explicite : ['montant'=>float|null, 'convertible'=>bool,
     * 'taux'=>float|null, 'taux_date'=>string|null]. Ne fabrique jamais un
     * taux — si aucune conversion n'est possible, convertible=false et
     * montant=null (à ne jamais additionner dans un total consolidé).
     */
    private static function convertir(float $montant, string $deviseOrigine, string $deviseCible, int $filialeId, array $tauxParFiliale): array
    {
        $origine = strtoupper(trim($deviseOrigine)) ?: 'EUR';
        $cible = strtoupper(trim($deviseCible)) ?: 'EUR';

        if ($origine === $cible) {
            return ['montant' => $montant, 'convertible' => true, 'taux' => 1.0, 'taux_date' => null, 'devise_origine' => $origine];
        }

        $pairesGerees = ['EUR', 'FCFA'];
        if (!in_array($origine, $pairesGerees, true) || !in_array($cible, $pairesGerees, true)) {
            return ['montant' => null, 'convertible' => false, 'taux' => null, 'taux_date' => null, 'devise_origine' => $origine];
        }

        $param = $tauxParFiliale[$filialeId] ?? null;
        if (!$param || empty($param['taux_eur_fcfa'])) {
            return ['montant' => null, 'convertible' => false, 'taux' => null, 'taux_date' => null, 'devise_origine' => $origine];
        }

        $taux = (float) $param['taux_eur_fcfa']; // 1 EUR = $taux FCFA
        if ($origine === 'EUR' && $cible === 'FCFA') {
            return ['montant' => $montant * $taux, 'convertible' => true, 'taux' => $taux, 'taux_date' => $param['taux_date_maj'], 'devise_origine' => $origine];
        }
        if ($origine === 'FCFA' && $cible === 'EUR') {
            return ['montant' => $taux > 0 ? $montant / $taux : null, 'convertible' => $taux > 0, 'taux' => $taux, 'taux_date' => $param['taux_date_maj'], 'devise_origine' => $origine];
        }
        return ['montant' => null, 'convertible' => false, 'taux' => null, 'taux_date' => null, 'devise_origine' => $origine];
    }

    /**
     * Agrège une liste de lignes {montant, devise, filiale_id} vers la
     * devise cible. Retourne le total consolidé, le détail des montants non
     * convertis (jamais perdus, jamais additionnés au total), et les taux
     * utilisés (pour affichage "taux et date de conversion").
     */
    public static function agregerMontants(array $lignes, string $deviseCible, array $filialeIds): array
    {
        $tauxParFiliale = self::chargerTaux($filialeIds);
        $total = 0.0;
        $nonConvertis = []; // [devise => ['n'=>int, 'total'=>float]]
        $tauxUtilises = []; // [devise_origine => ['taux'=>..,'date'=>..]]

        foreach ($lignes as $ligne) {
            $r = self::convertir((float) $ligne['montant'], (string) ($ligne['devise'] ?? ''), $deviseCible, (int) $ligne['filiale_id'], $tauxParFiliale);
            if ($r['convertible']) {
                $total += $r['montant'];
                if ($r['taux'] !== null && $r['taux'] !== 1.0) {
                    $tauxUtilises[$r['devise_origine']] = ['taux' => $r['taux'], 'date' => $r['taux_date']];
                }
            } else {
                $devise = $r['devise_origine'] ?: 'Non renseignée';
                $nonConvertis[$devise] = $nonConvertis[$devise] ?? ['n' => 0, 'total' => 0.0];
                $nonConvertis[$devise]['n']++;
                $nonConvertis[$devise]['total'] += (float) $ligne['montant'];
            }
        }

        return ['total' => $total, 'non_convertis' => $nonConvertis, 'taux_utilises' => $tauxUtilises, 'devise' => $deviseCible];
    }

    // -------------------------------------------------------------------
    // Population A — « Commandes confirmées » (base commune Vue générale + Activités)
    // -------------------------------------------------------------------

    /**
     * Lignes brutes de la population A, avec toutes les colonnes nécessaires
     * au calcul des cartes/graphiques/tableaux + au drill-down. Une ligne =
     * une commande confirmée = une cotation.
     */
    public static function commandesConfirmees(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT cmd.id AS commande_id, cmd.reference AS commande_reference, cmd.created_at AS commande_date,
                       co.id AS cotation_id, co.montant_total, co.marge_montant, co.devise,
                       d.id AS dossier_id, d.reference AS dossier_reference, d.objet AS dossier_objet, d.responsable_id, d.filiale_id,
                       COALESCE(dm.activite,'') AS activite,
                       cl.nom AS client_nom
                FROM commandes cmd
                INNER JOIN cotations co ON co.id = cmd.cotation_id
                INNER JOIN dossiers d ON d.id = cmd.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                INNER JOIN clients cl ON cl.id = co.client_id
                WHERE cmd.filiale_id IN ($placeholders)
                  AND cmd.created_at >= ? AND cmd.created_at <= ?";
        $params = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59']);

        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------
    // Onglet 1 — Vue générale
    // -------------------------------------------------------------------

    public static function vueGenerale(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        $lignesCommandes = self::commandesConfirmees($user, $filters);

        // Carte 1 — Montant HT des commandes confirmées
        $montantHtCommandes = self::agregerMontants(
            array_map(fn($l) => ['montant' => $l['montant_total'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']], $lignesCommandes),
            $filters['devise'],
            $filialeIds
        );

        // Carte 2 — Marge (prévisionnelle ou réalisée selon la base)
        $marge = self::margeSelonBase($user, $filters);

        // Carte 3 — Taux de transformation des cotations
        $transformation = self::tauxTransformation($user, $filters);

        // Carte 4 — Valeur des dossiers actifs (photographie à date, hors période)
        $valeurActive = self::valeurDossiersActifs($user, $filters);

        // Tendances vs période précédente de même durée (cartes 1-3 uniquement ;
        // la carte 4 est une photographie à date, sans sens à comparer dans le temps).
        $filtresPrec = self::filtresPeriodePrecedente($filters);
        $lignesCommandesPrec = self::commandesConfirmees($user, $filtresPrec);
        $montantHtPrec = self::agregerMontants(
            array_map(fn($l) => ['montant' => $l['montant_total'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']], $lignesCommandesPrec),
            $filters['devise'],
            $filialeIds
        );
        $margePrec = self::margeSelonBase($user, $filtresPrec);
        $transformationPrec = self::tauxTransformation($user, $filtresPrec);
        $transformationTendance = self::tendance($transformation['taux'], $transformationPrec['taux']);

        $resultatsActivite = self::tableauResultatsActivite($lignesCommandes, $filters['devise'], $filialeIds);
        $resultatsActivitePrec = self::detailParActivite($lignesCommandesPrec, $filters['devise'], $filialeIds);

        return [
            'montant_ht_commandes' => $montantHtCommandes,
            'montant_ht_commandes_tendance' => self::tendance($montantHtCommandes['total'], $montantHtPrec['total']),
            'nb_commandes' => count($lignesCommandes),
            'marge' => $marge,
            'marge_tendance' => self::tendance($marge['montant']['total'], $margePrec['montant']['total']),
            'transformation' => $transformation,
            'transformation_tendance' => $transformationTendance,
            'valeur_dossiers_actifs' => $valeurActive,
            'evolution_mensuelle' => self::evolutionMensuelle($user, $filters, 6),
            'repartition_activite' => self::repartitionParActivite($lignesCommandes, $filters['devise'], $filialeIds),
            'cotations_statuts' => self::cotationsParStatut($user, $filters),
            'cotations_urgentes' => self::cotationsEnAttenteUrgentes($user, $filters, 7),
            'delais' => self::delaisMoyens($user, $filters),
            'resultats_activite' => $resultatsActivite,
            'recommandations' => self::recommandationsRentabilite($resultatsActivite, $resultatsActivitePrec, $transformation, $transformationTendance),
        ];
    }

    /**
     * Nombre de cotations "en attente de réponse" (statut envoyee) sur la
     * période, envoyées depuis plus de $seuilJours jours — sous-ensemble du
     * bucket "En attente" du graphique "Cotations émises", pour signaler ce
     * qui risque de traîner (alerte, pas juste un comptage).
     */
    public static function cotationsEnAttenteUrgentes(array $user, array $filters, int $seuilJours): int
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $seuilDate = date('Y-m-d H:i:s', strtotime("-$seuilJours days"));
        $sql = "SELECT COUNT(*) FROM cotations co
                INNER JOIN dossiers d ON d.id = co.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                WHERE co.filiale_id IN ($placeholders) AND co.statut = 'envoyee'
                  AND co.created_at >= ? AND co.created_at <= ? AND co.created_at <= ?";
        $params = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59', $seuilDate]);
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Marge selon la base sélectionnée — voir doc de classe (populations B/C).
     * Retourne aussi le détail "encaissements" (sous-ensemble de la base
     * réalisée) pour respecter la distinction commandes confirmées / ventes
     * réalisées / encaissements exigée par la spec.
     */
    public static function margeSelonBase(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return ['montant' => self::agregerMontants([], $filters['devise'], []), 'base' => $filters['base'], 'population' => 0];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));

        if ($filters['base'] === 'previsionnel') {
            $sql = "SELECT co.id, co.marge_montant AS montant, co.devise, co.filiale_id
                    FROM cotations co
                    INNER JOIN dossiers d ON d.id = co.dossier_id
                    INNER JOIN demandes dm ON dm.id = d.demande_id
                    WHERE co.filiale_id IN ($placeholders) AND co.statut = 'acceptee'
                      AND co.created_at >= ? AND co.created_at <= ?";
            $params = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59']);
        } else {
            // Réalisé : cotations acceptées avec >= 1 facture non annulée sur la
            // période — dédupliquées par cotation (GROUP BY) pour ne jamais
            // compter deux fois la même marge (acompte + solde = 1 cotation).
            $sql = "SELECT co.id, co.marge_montant AS montant, co.devise, co.filiale_id
                    FROM cotations co
                    INNER JOIN dossiers d ON d.id = co.dossier_id
                    INNER JOIN demandes dm ON dm.id = d.demande_id
                    INNER JOIN factures fa ON fa.cotation_id = co.id AND fa.statut != 'annulee'
                                            AND fa.date_emission >= ? AND fa.date_emission <= ?
                    WHERE co.filiale_id IN ($placeholders) AND co.statut = 'acceptee'
                    GROUP BY co.id, co.marge_montant, co.devise, co.filiale_id";
            $params = array_merge([$filters['date_debut'], $filters['date_fin']], $filialeIds);
        }
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $nonRenseignees = 0;
        $lignesValides = [];
        foreach ($rows as $r) {
            if ($r['montant'] === null) {
                $nonRenseignees++;
                continue;
            }
            $lignesValides[] = $r;
        }

        $agg = self::agregerMontants($lignesValides, $filters['devise'], $filialeIds);

        return [
            'montant' => $agg,
            'base' => $filters['base'],
            'population' => count($rows),
            'non_renseignees' => $nonRenseignees, // cotations acceptées/facturées sans montant_achat -> marge inconnue
            'encaissements' => $filters['base'] === 'realise' ? self::encaissements($user, $filters) : null,
        ];
    }

    /** Encaissements réels (factures payées) — indicateur complémentaire, jamais confondu avec "ventes réalisées". */
    public static function encaissements(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return self::agregerMontants([], $filters['devise'], []);
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT fa.montant AS montant, fa.devise, fa.filiale_id
                FROM factures fa
                WHERE fa.filiale_id IN ($placeholders) AND fa.statut = 'payee'
                  AND fa.date_emission >= ? AND fa.date_emission <= ?";
        $params = array_merge($filialeIds, [$filters['date_debut'], $filters['date_fin']]);
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return self::agregerMontants($stmt->fetchAll(), $filters['devise'], $filialeIds);
    }

    /**
     * Taux de transformation des cotations = acceptées / (envoyées +
     * acceptées + refusées) sur la période — population = cotations dont le
     * statut a quitté "brouillon" (donc réellement soumises au client),
     * comptées UNE fois chacune (jamais plusieurs versions d'une même
     * cotation additionnées : chaque ligne de `cotations` est déjà une
     * version unique en base). Numérateur/dénominateur toujours affichés.
     */
    public static function tauxTransformation(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return ['numerateur' => 0, 'denominateur' => 0, 'taux' => null];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT co.statut, COUNT(*) AS n
                FROM cotations co
                INNER JOIN dossiers d ON d.id = co.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                WHERE co.filiale_id IN ($placeholders) AND co.statut IN ('envoyee','acceptee','refusee')
                  AND co.created_at >= ? AND co.created_at <= ?";
        $params = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59']);
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        $sql .= ' GROUP BY co.statut';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $parStatut = ['envoyee' => 0, 'acceptee' => 0, 'refusee' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $parStatut[$row['statut']] = (int) $row['n'];
        }
        $denominateur = array_sum($parStatut);
        $numerateur = $parStatut['acceptee'];
        return [
            'numerateur' => $numerateur,
            'denominateur' => $denominateur,
            'taux' => $denominateur > 0 ? round($numerateur / $denominateur * 100, 1) : null,
            'par_statut' => $parStatut,
        ];
    }

    /**
     * Valeur des dossiers actifs — PHOTOGRAPHIE À DATE, ne dépend pas de la
     * période sélectionnée (voir doc de classe, population D). Respecte
     * uniquement activité/responsable.
     */
    public static function valeurDossiersActifs(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return self::agregerMontants([], $filters['devise'], []);
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT co.montant_total AS montant, co.devise, co.filiale_id
                FROM cotations co
                INNER JOIN dossiers d ON d.id = co.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                WHERE co.filiale_id IN ($placeholders) AND co.statut = 'acceptee' AND d.statut = 'actif'
                  AND co.id = (SELECT MAX(co2.id) FROM cotations co2 WHERE co2.dossier_id = d.id AND co2.statut = 'acceptee')";
        $params = $filialeIds;
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return self::agregerMontants($stmt->fetchAll(), $filters['devise'], $filialeIds);
    }

    /** Répartition des montants HT (population A) par activité — pour le donut/graphique. */
    public static function repartitionParActivite(array $lignesCommandes, string $devise, array $filialeIds): array
    {
        $parActivite = [];
        foreach ($lignesCommandes as $l) {
            $a = $l['activite'] !== '' ? $l['activite'] : 'Non renseigné';
            $parActivite[$a] = $parActivite[$a] ?? [];
            $parActivite[$a][] = ['montant' => $l['montant_total'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']];
        }
        $rows = [];
        $totalGeneral = 0.0;
        $aggByActivite = [];
        foreach ($parActivite as $a => $lignes) {
            $agg = self::agregerMontants($lignes, $devise, $filialeIds);
            $aggByActivite[$a] = $agg;
            $totalGeneral += $agg['total'];
        }
        foreach ($aggByActivite as $a => $agg) {
            $rows[] = [
                'activite' => $a,
                'montant' => $agg['total'],
                'part' => $totalGeneral > 0 ? round($agg['total'] / $totalGeneral * 100, 1) : 0.0,
                'non_convertis' => $agg['non_convertis'],
            ];
        }
        usort($rows, fn($a, $b) => $b['montant'] <=> $a['montant']);
        return ['total' => $totalGeneral, 'lignes' => $rows];
    }

    /** Cotations par statut (émises/acceptées/refusées/en attente) sur la période — pour le graphique dédié. */
    public static function cotationsParStatut(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return ['acceptee' => 0, 'refusee' => 0, 'envoyee' => 0, 'total_emis' => 0];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT co.statut, COUNT(*) AS n
                FROM cotations co
                INNER JOIN dossiers d ON d.id = co.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                WHERE co.filiale_id IN ($placeholders) AND co.statut != 'brouillon'
                  AND co.created_at >= ? AND co.created_at <= ?";
        $params = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59']);
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        $sql .= ' GROUP BY co.statut';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $out = ['acceptee' => 0, 'refusee' => 0, 'envoyee' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['statut']] = (int) $row['n'];
        }
        $out['total_emis'] = array_sum($out);
        return $out;
    }

    /**
     * Délais moyens (jours calendaires) sur étapes TERMINÉES uniquement,
     * sur la période :
     *  - Qualification : demandes.recue_le -> demandes.qualified_at
     *  - Réponse fournisseur : consultations_fournisseur.date_envoi -> offres.created_at (1re offre reçue)
     *  - Préparation de cotation : dossiers.created_at -> cotations.created_at (1re cotation du dossier)
     * Les jours sont calendaires (pas ouvrés) — précisé à l'affichage.
     */
    public static function delaisMoyens(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return ['qualification' => null, 'reponse_fournisseur' => null, 'preparation_cotation' => null];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));

        // Qualification
        $sql = "SELECT recue_le, qualified_at FROM demandes
                WHERE filiale_id IN ($placeholders) AND qualified_at IS NOT NULL AND recue_le IS NOT NULL
                  AND qualified_at >= ? AND qualified_at <= ?";
        $params = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59']);
        if (!empty($filters['activite'])) {
            $sql .= ' AND activite = ?';
            $params[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND responsable_id = ?';
            $params[] = $filters['responsable_id'];
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $delaisQualif = [];
        foreach ($stmt->fetchAll() as $row) {
            $delaisQualif[] = (strtotime($row['qualified_at']) - strtotime($row['recue_le'])) / 86400;
        }

        // Réponse fournisseur : première offre par consultation
        $sql = "SELECT cf.date_envoi, MIN(o.created_at) AS premiere_offre
                FROM consultations_fournisseur cf
                INNER JOIN offres o ON o.consultation_id = cf.id
                INNER JOIN dossiers d ON d.id = cf.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                WHERE cf.filiale_id IN ($placeholders) AND cf.date_envoi IS NOT NULL
                  AND o.created_at >= ? AND o.created_at <= ?";
        $params2 = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59']);
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params2[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params2[] = $filters['responsable_id'];
        }
        $sql .= ' GROUP BY cf.id, cf.date_envoi';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params2);
        $delaisReponse = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!$row['premiere_offre']) {
                continue;
            }
            $delaisReponse[] = (strtotime($row['premiere_offre']) - strtotime($row['date_envoi'])) / 86400;
        }

        // Préparation de cotation : création du dossier -> première cotation
        $sql = "SELECT d.created_at AS dossier_cree, MIN(co.created_at) AS premiere_cotation
                FROM dossiers d
                INNER JOIN demandes dm ON dm.id = d.demande_id
                INNER JOIN cotations co ON co.dossier_id = d.id
                WHERE d.filiale_id IN ($placeholders)
                  AND co.created_at >= ? AND co.created_at <= ?";
        $params3 = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59']);
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params3[] = $filters['activite'];
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params3[] = $filters['responsable_id'];
        }
        $sql .= ' GROUP BY d.id, d.created_at';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params3);
        $delaisPrepa = [];
        foreach ($stmt->fetchAll() as $row) {
            $delaisPrepa[] = (strtotime($row['premiere_cotation']) - strtotime($row['dossier_cree'])) / 86400;
        }

        $moyenne = fn(array $vals) => count($vals) > 0 ? round(array_sum($vals) / count($vals), 1) : null;

        return [
            'qualification' => ['moyenne_jours' => $moyenne($delaisQualif), 'n' => count($delaisQualif)],
            'reponse_fournisseur' => ['moyenne_jours' => $moyenne($delaisReponse), 'n' => count($delaisReponse)],
            'preparation_cotation' => ['moyenne_jours' => $moyenne($delaisPrepa), 'n' => count($delaisPrepa)],
            'unite' => 'jours calendaires',
        ];
    }

    /** Tableau "Résultats par activité" (Vue générale) — mêmes données que la population A. */
    public static function tableauResultatsActivite(array $lignesCommandes, string $devise, array $filialeIds): array
    {
        return self::detailParActivite($lignesCommandes, $devise, $filialeIds);
    }

    private static function detailParActivite(array $lignesCommandes, string $devise, array $filialeIds): array
    {
        $parActivite = [];
        foreach ($lignesCommandes as $l) {
            $a = $l['activite'] !== '' ? $l['activite'] : 'Non renseigné';
            $parActivite[$a] = $parActivite[$a] ?? ['commandes' => [], 'marges' => []];
            $parActivite[$a]['commandes'][] = ['montant' => $l['montant_total'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']];
            if ($l['marge_montant'] !== null) {
                $parActivite[$a]['marges'][] = ['montant' => $l['marge_montant'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']];
            }
        }

        $rows = [];
        $totalVentes = 0.0;
        $totalMarge = 0.0;
        $totalCommandes = 0;
        foreach ($parActivite as $a => $data) {
            $aggVentes = self::agregerMontants($data['commandes'], $devise, $filialeIds);
            $aggMarge = self::agregerMontants($data['marges'], $devise, $filialeIds);
            $nbCommandes = count($data['commandes']);
            $rows[] = [
                'activite' => $a,
                'commandes' => $nbCommandes,
                'ventes_ht' => $aggVentes['total'],
                'ventes_ht_agg' => $aggVentes,
                'marge' => $aggMarge['total'],
                'marge_manquante' => $nbCommandes - count($data['marges']),
                'marge_pct' => $aggVentes['total'] > 0 ? round($aggMarge['total'] / $aggVentes['total'] * 100, 1) : null,
            ];
            $totalVentes += $aggVentes['total'];
            $totalMarge += $aggMarge['total'];
            $totalCommandes += $nbCommandes;
        }
        usort($rows, fn($a, $b) => $b['ventes_ht'] <=> $a['ventes_ht']);

        foreach ($rows as &$r) {
            $r['part_ventes'] = $totalVentes > 0 ? round($r['ventes_ht'] / $totalVentes * 100, 1) : 0.0;
        }
        unset($r);

        return [
            'lignes' => $rows,
            'total' => [
                'commandes' => $totalCommandes,
                'ventes_ht' => $totalVentes,
                'marge' => $totalMarge,
                // Pourcentage global calculé à partir des totaux, PAS de la
                // moyenne des pourcentages par ligne (exigence spec §3).
                'marge_pct' => $totalVentes > 0 ? round($totalMarge / $totalVentes * 100, 1) : null,
            ],
        ];
    }

    /**
     * Évolution mensuelle (N derniers mois) du montant HT et de la marge des
     * commandes confirmées — ignore la période du filtre commun (fenêtre
     * propre, comme en v1), respecte activité/responsable.
     */
    public static function evolutionMensuelle(array $user, array $filters, int $mois = 6): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $debut = date('Y-m-01', strtotime('-' . ($mois - 1) . ' months'));
        $f = $filters;
        $f['date_debut'] = $debut;
        $f['date_fin'] = date('Y-m-d');
        $lignes = self::commandesConfirmees($user, $f);

        $parMois = [];
        foreach ($lignes as $l) {
            $cle = substr((string) $l['commande_date'], 0, 7);
            $parMois[$cle] = $parMois[$cle] ?? ['ventes' => [], 'marges' => []];
            $parMois[$cle]['ventes'][] = ['montant' => $l['montant_total'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']];
            if ($l['marge_montant'] !== null) {
                $parMois[$cle]['marges'][] = ['montant' => $l['marge_montant'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']];
            }
        }

        $rows = [];
        for ($i = $mois - 1; $i >= 0; $i--) {
            $cle = date('Y-m', strtotime("-$i months"));
            $ventes = self::agregerMontants($parMois[$cle]['ventes'] ?? [], $filters['devise'], $filialeIds);
            $marges = self::agregerMontants($parMois[$cle]['marges'] ?? [], $filters['devise'], $filialeIds);
            $rows[] = ['mois' => $cle, 'label' => self::libelleMois($cle), 'ventes' => $ventes['total'], 'marge' => $marges['total']];
        }
        return $rows;
    }

    private static function libelleMois(string $ym): string
    {
        $noms = [1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'];
        [$y, $m] = explode('-', $ym);
        return $noms[(int) $m] . ' ' . substr($y, 2);
    }

    /**
     * Points d'attention rentabilité — carte "aide à la décision" de la Vue
     * générale. Détection basée sur des règles fixes appliquées aux données
     * déjà calculées (jamais de valeur inventée, jamais d'appel externe) :
     *   - Marge faible : activité dont la marge/ventes HT est nettement
     *     sous la moyenne pondérée de la période (écart >= 8 points),
     *     seulement si l'activité pèse au moins 5% des ventes (évite le
     *     bruit sur des lignes anecdotiques).
     *   - Marge en baisse : marge/ventes HT d'une activité en recul d'au
     *     moins 5 points vs la période précédente de même durée.
     *   - Concentration : une activité représente 60% ou plus des ventes
     *     HT de la période (risque de dépendance).
     *   - Transformation des cotations en baisse : taux d'acceptation en
     *     recul d'au moins 10% (relatif) vs la période précédente.
     * Chaque règle est indépendante et ne se déclenche que si les données
     * nécessaires sont réellement disponibles (marge_pct non null, etc.) —
     * silence plutôt qu'un calcul approximatif.
     */
    public static function recommandationsRentabilite(array $tableau, array $tableauPrec, array $transformation, array $transformationTendance): array
    {
        $alertes = [];
        $moyenneMarge = $tableau['total']['marge_pct'];

        $precByActivite = [];
        foreach ($tableauPrec['lignes'] as $p) {
            $precByActivite[$p['activite']] = $p;
        }

        foreach ($tableau['lignes'] as $r) {
            if ($r['ventes_ht'] <= 0 || $r['part_ventes'] < 5) {
                continue; // volume trop faible pour être une base de décision fiable
            }

            if ($r['marge_pct'] !== null && $moyenneMarge !== null && ($moyenneMarge - $r['marge_pct']) >= 8) {
                $alertes[] = [
                    'type' => 'marge_faible',
                    'icone' => 'alert-triangle',
                    'niveau' => 'attention',
                    'titre' => 'Marge faible sur ' . $r['activite'],
                    'message' => 'Marge de ' . self::fmtPct($r['marge_pct']) . ' contre ' . self::fmtPct($moyenneMarge) . ' en moyenne sur la période — vérifiez les prix d\'achat/vente sur cette activité.',
                    'activite' => $r['activite'],
                    'poids' => $moyenneMarge - $r['marge_pct'],
                ];
            }

            $prec = $precByActivite[$r['activite']] ?? null;
            if ($prec && $r['marge_pct'] !== null && $prec['marge_pct'] !== null) {
                $delta = $r['marge_pct'] - $prec['marge_pct'];
                if ($delta <= -5) {
                    $alertes[] = [
                        'type' => 'marge_baisse',
                        'icone' => 'trending-down',
                        'niveau' => 'attention',
                        'titre' => 'Marge en baisse sur ' . $r['activite'],
                        'message' => 'Passée de ' . self::fmtPct($prec['marge_pct']) . ' à ' . self::fmtPct($r['marge_pct']) . ' vs la période précédente (' . number_format(abs($delta), 1, ',', ' ') . ' pts).',
                        'activite' => $r['activite'],
                        'poids' => abs($delta),
                    ];
                }
            }

            if ($r['part_ventes'] >= 60) {
                $alertes[] = [
                    'type' => 'concentration',
                    'icone' => 'target',
                    'niveau' => 'info',
                    'titre' => 'Forte concentration sur ' . $r['activite'],
                    'message' => $r['activite'] . ' représente ' . $r['part_ventes'] . ' % des ventes HT de la période — dépendance forte à une seule activité, à surveiller.',
                    'activite' => $r['activite'],
                    'poids' => $r['part_ventes'],
                ];
            }
        }

        if ($transformationTendance['direction'] === 'down' && $transformationTendance['delta_pct'] !== null && $transformationTendance['delta_pct'] <= -10) {
            $alertes[] = [
                'type' => 'transformation_baisse',
                'icone' => 'trending-down',
                'niveau' => 'attention',
                'titre' => 'Taux de transformation des cotations en baisse',
                'message' => number_format(abs($transformationTendance['delta_pct']), 1, ',', ' ') . ' % vs la période précédente (' . $transformation['numerateur'] . ' acceptée(s) / ' . $transformation['denominateur'] . ' soumise(s)) — relancez les cotations en attente.',
                'activite' => null,
                'poids' => abs($transformationTendance['delta_pct']),
            ];
        }

        // Attention avant info, puis par écart décroissant (le plus significatif en premier).
        usort($alertes, function ($a, $b) {
            if ($a['niveau'] !== $b['niveau']) {
                return $a['niveau'] === 'attention' ? -1 : 1;
            }
            return $b['poids'] <=> $a['poids'];
        });

        return array_slice($alertes, 0, 4);
    }

    // -------------------------------------------------------------------
    // Onglet 2 — Activités
    // -------------------------------------------------------------------

    public static function activites(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        $lignesCommandes = self::commandesConfirmees($user, $filters);
        $tableau = self::detailParActivite($lignesCommandes, $filters['devise'], $filialeIds);

        // Tendances (totaux uniquement) vs période précédente de même durée.
        $filtresPrec = self::filtresPeriodePrecedente($filters);
        $lignesCommandesPrec = self::commandesConfirmees($user, $filtresPrec);
        $tableauPrec = self::detailParActivite($lignesCommandesPrec, $filters['devise'], $filialeIds);
        $tendances = [
            'ventes_ht' => self::tendance($tableau['total']['ventes_ht'], $tableauPrec['total']['ventes_ht']),
            'marge' => self::tendance($tableau['total']['marge'], $tableauPrec['total']['marge']),
            'commandes' => self::tendance((float) $tableau['total']['commandes'], (float) $tableauPrec['total']['commandes']),
        ];

        // Évolution mensuelle PAR activité (courbes multiples, couleur stable par activité).
        $debut = date('Y-m-01', strtotime('-5 months'));
        $f = $filters;
        $f['date_debut'] = $debut;
        $f['date_fin'] = date('Y-m-d');
        unset($f['activite']);
        $lignesEvo = self::commandesConfirmees($user, $f);
        $evolutionParActivite = self::evolutionParActivite($lignesEvo, $filters['devise'], $filialeIds, 6);

        return [
            'tableau' => $tableau,
            'tendances' => $tendances,
            'activites_disponibles' => self::activitesConfigurees($lignesCommandes),
            'evolution_par_activite' => $evolutionParActivite,
        ];
    }

    /** Liste des activités réellement configurées/présentes — jamais les exemples des maquettes. */
    public static function activitesConfigurees(array $lignesCommandes = []): array
    {
        $activites = Demande::ACTIVITES;
        foreach ($lignesCommandes as $l) {
            if ($l['activite'] !== '' && !in_array($l['activite'], $activites, true)) {
                $activites[] = $l['activite'];
            }
        }
        return $activites;
    }

    private static function evolutionParActivite(array $lignes, string $devise, array $filialeIds, int $mois): array
    {
        $parActiviteMois = [];
        foreach ($lignes as $l) {
            $a = $l['activite'] !== '' ? $l['activite'] : 'Non renseigné';
            $cle = substr((string) $l['commande_date'], 0, 7);
            $parActiviteMois[$a][$cle] = $parActiviteMois[$a][$cle] ?? [];
            $parActiviteMois[$a][$cle][] = ['montant' => $l['montant_total'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']];
        }

        $moisCles = [];
        for ($i = $mois - 1; $i >= 0; $i--) {
            $moisCles[] = date('Y-m', strtotime("-$i months"));
        }

        $series = [];
        foreach ($parActiviteMois as $activite => $parMois) {
            $points = [];
            foreach ($moisCles as $cle) {
                $agg = self::agregerMontants($parMois[$cle] ?? [], $devise, $filialeIds);
                $points[] = ['mois' => $cle, 'label' => self::libelleMois($cle), 'valeur' => $agg['total']];
            }
            $series[$activite] = $points;
        }
        return ['mois' => array_map(fn($c) => self::libelleMois($c), $moisCles), 'series' => $series];
    }

    // -------------------------------------------------------------------
    // Onglet 3 — Collaborateurs
    // -------------------------------------------------------------------

    /**
     * Performance et contribution par collaborateur.
     *
     * RÈGLE D'ATTRIBUTION (spec §5) : la vente/marge d'un dossier est
     * attribuée EXCLUSIVEMENT à son responsable désigné (dossiers.responsable_id
     * — un seul par dossier, champ explicite et déjà vérifiable dans
     * l'application). Un collaborateur additionnel suivant un fournisseur
     * précis sur ce dossier (table dossier_collaborateurs) apparaît dans son
     * décompte de "dossiers suivis" mais N'HÉRITE JAMAIS de la marge du
     * dossier — évite exactement le double-comptage que la spec proscrit
     * ("ne pas attribuer toute la marge à chacun").
     *
     * COÛTS AFFECTÉS : AUCUNE source de données n'existe aujourd'hui dans le
     * schéma (pas de suivi du temps, pas de table de commissions, pas de
     * frais professionnels individualisés). Conformément à la spec, ceci
     * est donc TOUJOURS affiché "Non renseigné" — jamais remplacé par 0 —
     * et la Contribution nette (qui en dépend) l'est donc également.
     */
    public static function collaborateurs(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return ['role' => $filters['role'], 'lignes' => [], 'totaux' => null];
        }

        $lignesCommandes = self::commandesConfirmees($user, $filters);
        $parResponsable = [];
        foreach ($lignesCommandes as $l) {
            $rid = (int) ($l['responsable_id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            $parResponsable[$rid] = $parResponsable[$rid] ?? ['ventes' => [], 'marges' => [], 'dossiers' => []];
            $parResponsable[$rid]['ventes'][] = ['montant' => $l['montant_total'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']];
            if ($l['marge_montant'] !== null) {
                $parResponsable[$rid]['marges'][] = ['montant' => $l['marge_montant'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']];
            }
            $parResponsable[$rid]['dossiers'][$l['dossier_id']] = true;
        }

        // Transformation par responsable (mêmes règles que tauxTransformation, scopé par responsable).
        $transformationParResponsable = self::transformationParResponsable($user, $filters, array_keys($parResponsable));

        $lignes = [];
        $totalVentes = 0.0;
        $totalMarge = 0.0;
        foreach ($parResponsable as $rid => $data) {
            $aggVentes = self::agregerMontants($data['ventes'], $filters['devise'], $filialeIds);
            $aggMarge = self::agregerMontants($data['marges'], $filters['devise'], $filialeIds);
            $lignes[] = [
                'utilisateur_id' => $rid,
                'nom' => Utilisateur::nameOf($rid),
                'dossiers' => count($data['dossiers']),
                'ventes_ht' => $aggVentes['total'],
                'marge_attribuee' => $aggMarge['total'],
                'couts_affectes' => null, // Non renseigné — aucune source de données (voir doc de méthode)
                'contribution_nette' => null, // dépend des coûts -> non renseigné également
                'transformation' => $transformationParResponsable[$rid] ?? null,
            ];
            $totalVentes += $aggVentes['total'];
            $totalMarge += $aggMarge['total'];
        }
        usort($lignes, fn($a, $b) => $b['ventes_ht'] <=> $a['ventes_ht']);

        // Tendances (totaux uniquement) vs période précédente de même durée.
        // Même périmètre que le total courant ci-dessus : seules les commandes
        // dont le dossier a un responsable assigné (rid > 0) sont comptées,
        // des deux côtés de la comparaison — sinon une commande sans
        // responsable présente sur une seule des deux périodes fausserait
        // le badge de tendance (comparaison de deux populations différentes).
        $filtresPrec = self::filtresPeriodePrecedente($filters);
        $lignesCommandesPrecAvecResponsable = array_filter(
            self::commandesConfirmees($user, $filtresPrec),
            fn($l) => (int) ($l['responsable_id'] ?? 0) > 0
        );
        $ventesPrec = self::agregerMontants(
            array_map(fn($l) => ['montant' => $l['montant_total'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']], $lignesCommandesPrecAvecResponsable),
            $filters['devise'],
            $filialeIds
        );
        $margesPrec = self::agregerMontants(
            array_map(fn($l) => ['montant' => $l['marge_montant'], 'devise' => $l['devise'], 'filiale_id' => $l['filiale_id']], array_filter($lignesCommandesPrecAvecResponsable, fn($l) => $l['marge_montant'] !== null)),
            $filters['devise'],
            $filialeIds
        );

        return [
            'role' => $filters['role'],
            'lignes' => $lignes,
            'totaux' => [
                'ventes_ht' => $totalVentes,
                'marge_attribuee' => $totalMarge,
                'couts_affectes' => null,
                'contribution_nette' => null,
            ],
            'tendances' => [
                'ventes_ht' => self::tendance($totalVentes, $ventesPrec['total']),
                'marge_attribuee' => self::tendance($totalMarge, $margesPrec['total']),
            ],
            'collaborateurs_secondaires' => self::collaborateursSecondaires($user, $filters),
        ];
    }

    private static function transformationParResponsable(array $user, array $filters, array $responsableIds): array
    {
        if (empty($responsableIds)) {
            return [];
        }
        $out = [];
        foreach ($responsableIds as $rid) {
            $f = $filters;
            $f['responsable_id'] = $rid;
            $out[$rid] = self::tauxTransformation($user, $f);
        }
        return $out;
    }

    /**
     * Collaborateurs additionnels (table dossier_collaborateurs, suivi d'un
     * fournisseur précis) sur la période — comptés à part car ils n'ont pas
     * de règle d'attribution financière (spec §5 : "Non renseigné" plutôt
     * que 0 ou une estimation).
     */
    private static function collaborateursSecondaires(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT dc.utilisateur_id, u.nom AS utilisateur_nom, COUNT(DISTINCT dc.dossier_id) AS nb_dossiers
                FROM dossier_collaborateurs dc
                INNER JOIN utilisateurs u ON u.id = dc.utilisateur_id
                INNER JOIN dossiers d ON d.id = dc.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                WHERE dc.filiale_id IN ($placeholders)
                  AND dc.created_at >= ? AND dc.created_at <= ?";
        $params = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59']);
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        $sql .= ' GROUP BY dc.utilisateur_id, u.nom ORDER BY nb_dossiers DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Indicateurs Achats (délais de réponse fournisseur, conformité des
     * offres) par collaborateur ayant saisi des offres (offres.created_by).
     * "Économies" volontairement absent : aucune base de prix de référence
     * comparable n'existe dans le schéma pour la calculer honnêtement.
     */
    public static function collaborateursAchats(array $user, array $filters): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT o.created_by AS uid, o.conformite_technique, o.created_at,
                       cf.date_envoi
                FROM offres o
                INNER JOIN consultations_fournisseur cf ON cf.id = o.consultation_id
                INNER JOIN dossiers d ON d.id = o.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                WHERE o.filiale_id IN ($placeholders) AND o.created_by IS NOT NULL
                  AND o.created_at >= ? AND o.created_at <= ?";
        $params = array_merge($filialeIds, [$filters['date_debut'] . ' 00:00:00', $filters['date_fin'] . ' 23:59:59']);
        if (!empty($filters['activite'])) {
            $sql .= ' AND dm.activite = ?';
            $params[] = $filters['activite'];
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $parUid = [];
        foreach ($rows as $r) {
            $uid = (int) $r['uid'];
            $parUid[$uid] = $parUid[$uid] ?? ['offres' => 0, 'delais' => [], 'conformite' => ['conforme' => 0, 'partielle' => 0, 'non_conforme' => 0, 'non_renseigne' => 0]];
            $parUid[$uid]['offres']++;
            if ($r['date_envoi']) {
                $parUid[$uid]['delais'][] = (strtotime($r['created_at']) - strtotime($r['date_envoi'])) / 86400;
            }
            $c = $r['conformite_technique'] ?: 'non_renseigne';
            $parUid[$uid]['conformite'][$c] = ($parUid[$uid]['conformite'][$c] ?? 0) + 1;
        }

        $lignes = [];
        foreach ($parUid as $uid => $data) {
            $total = array_sum($data['conformite']);
            $lignes[] = [
                'utilisateur_id' => $uid,
                'nom' => Utilisateur::nameOf($uid),
                'offres_saisies' => $data['offres'],
                'delai_moyen_reponse' => count($data['delais']) > 0 ? round(array_sum($data['delais']) / count($data['delais']), 1) : null,
                'conformite_pct' => $total > 0 ? round($data['conformite']['conforme'] / $total * 100, 1) : null,
                'economies' => null, // pas de base de prix de référence comparable -> non calculable honnêtement
            ];
        }
        usort($lignes, fn($a, $b) => $b['offres_saisies'] <=> $a['offres_saisies']);
        return $lignes;
    }

    // -------------------------------------------------------------------
    // Drill-down — détail des opérations composant un résultat
    // -------------------------------------------------------------------

    /**
     * Détail des opérations pour un clic sur carte/barre/portion/ligne.
     * $axe = 'activite'|'responsable'|'tout' ; $valeur = la valeur de l'axe
     * cliqué (nom d'activité ou id responsable), ignoré si axe='tout'.
     */
    public static function detailOperations(array $user, array $filters, string $axe, string $valeur): array
    {
        $f = $filters;
        if ($axe === 'activite') {
            $f['activite'] = $valeur === 'Non renseigné' ? '__aucune__' : $valeur;
        } elseif ($axe === 'responsable') {
            $f['responsable_id'] = (int) $valeur;
        }

        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $sql = "SELECT cmd.id AS commande_id, cmd.reference AS commande_reference, cmd.created_at AS date,
                       d.id AS dossier_id, d.reference AS dossier_reference,
                       COALESCE(dm.activite,'Non renseigné') AS activite, d.responsable_id,
                       co.montant_total, co.marge_montant, co.devise,
                       cl.nom AS client_nom, cmd.statut
                FROM commandes cmd
                INNER JOIN cotations co ON co.id = cmd.cotation_id
                INNER JOIN dossiers d ON d.id = cmd.dossier_id
                INNER JOIN demandes dm ON dm.id = d.demande_id
                INNER JOIN clients cl ON cl.id = co.client_id
                WHERE cmd.filiale_id IN ($placeholders)
                  AND cmd.created_at >= ? AND cmd.created_at <= ?";
        $params = array_merge($filialeIds, [$f['date_debut'] . ' 00:00:00', $f['date_fin'] . ' 23:59:59']);
        if (!empty($f['activite']) && $f['activite'] !== '__aucune__') {
            $sql .= ' AND dm.activite = ?';
            $params[] = $f['activite'];
        } elseif (($f['activite'] ?? '') === '__aucune__') {
            $sql .= " AND (dm.activite IS NULL OR dm.activite = '')";
        }
        if (!empty($f['responsable_id'])) {
            $sql .= ' AND d.responsable_id = ?';
            $params[] = $f['responsable_id'];
        }
        $sql .= ' ORDER BY cmd.created_at DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
