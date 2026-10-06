<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Demande;
use App\Models\Filiale;
use App\Models\Pilotage;
use App\Models\Utilisateur;

/**
 * Module Pilotage v2 — 3 onglets (Vue générale / Activités / Collaborateurs).
 * Réservé à Propriétaire/Admin d'organisation/Finance (Auth::requirePilotage,
 * inchangé) : le module expose la valeur active, la marge et — dans l'onglet
 * Collaborateurs — la contribution individuelle, données réservées à la
 * direction (cf. §5 de la spec : "Réserver les coûts individuels et
 * contributions nettes aux rôles autorisés, y compris dans les exports").
 * Comme l'accès au module entier est déjà filtré à ces rôles, aucun masquage
 * supplémentaire par widget n'est nécessaire au sein des vues.
 */
class PilotageController
{
    private const ONGLETS = ['general', 'activites', 'collaborateurs'];

    public function index(): void
    {
        Auth::requirePilotage();
        $user = Auth::user();

        $onglet = in_array($_GET['onglet'] ?? '', self::ONGLETS, true) ? $_GET['onglet'] : 'general';
        $filters = Pilotage::resoudreFiltres($_GET);

        $data = [
            'onglet' => $onglet,
            'filters' => $filters,
            'utilisateurs' => Utilisateur::allForOrganisation((int) $user['organisation_id']),
            'filiales' => Filiale::visibleFor($user),
            'activites' => Pilotage::activitesConfigurees(),
            'queryString' => $this->querystringSansOnglet($_GET),
        ];

        if ($onglet === 'general') {
            $data['vueGenerale'] = Pilotage::vueGenerale($user, $filters);
        } elseif ($onglet === 'activites') {
            $data['activitesData'] = Pilotage::activites($user, $filters);
        } else {
            $data['collaborateursData'] = Pilotage::collaborateurs($user, $filters);
            if ($filters['role'] === 'achats') {
                $data['achatsData'] = Pilotage::collaborateursAchats($user, $filters);
            }
        }

        View::render('pilotage/index', $data);
    }

    /**
     * Drill-down : liste des opérations qui composent un résultat cliqué
     * (carte, barre, portion de graphique, ligne de tableau). Le retour vers
     * Pilotage conserve les filtres (querystring d'origine transmise en
     * paramètre "retour").
     */
    public function detail(): void
    {
        Auth::requirePilotage();
        $user = Auth::user();

        $filters = Pilotage::resoudreFiltres($_GET);
        $axe = in_array($_GET['axe'] ?? '', ['activite', 'responsable', 'tout'], true) ? $_GET['axe'] : 'tout';
        $valeur = trim($_GET['valeur'] ?? '');
        $retour = trim($_GET['retour'] ?? '');

        $operations = Pilotage::detailOperations($user, $filters, $axe, $valeur);

        View::render('pilotage/detail', [
            'operations' => $operations,
            'axe' => $axe,
            'valeur' => $valeur,
            'retour' => $retour,
            'filters' => $filters,
        ]);
    }

    /**
     * Export CSV (ouvrable directement dans Excel/LibreOffice — aucune
     * bibliothèque de génération .xlsx n'est disponible dans cette
     * application, qui n'utilise pas Composer). La période et la base
     * utilisées sont toujours indiquées en en-tête du fichier, comme exigé
     * par la spec §2. L'export PDF est proposé via une vue imprimable
     * dédiée (voir lien "Imprimer / PDF" du bouton Exporter), le navigateur
     * assurant la génération réelle du PDF (Ctrl+P / Enregistrer en PDF) —
     * même absence de bibliothèque serveur.
     */
    public function exportCsv(): void
    {
        Auth::requirePilotage();
        $user = Auth::user();
        $onglet = in_array($_GET['onglet'] ?? '', self::ONGLETS, true) ? $_GET['onglet'] : 'general';
        $filters = Pilotage::resoudreFiltres($_GET);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pilotage_' . $onglet . '_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel

        fputcsv($out, ['Pilotage — Suivora360'], ';', '"', '\\');
        fputcsv($out, ['Onglet', $onglet], ';', '"', '\\');
        fputcsv($out, ['Période', $filters['periode_label']], ';', '"', '\\');
        fputcsv($out, ['Base', Pilotage::BASES[$filters['base']] ?? $filters['base']], ';', '"', '\\');
        fputcsv($out, ['Devise de reporting', $filters['devise']], ';', '"', '\\');
        fputcsv($out, ['Activité', $filters['activite'] ?: 'Toutes'], ';', '"', '\\');
        fputcsv($out, ['Exporté le', date('d/m/Y H:i')], ';', '"', '\\');
        fputcsv($out, [], ';', '"', '\\');

        if ($onglet === 'general') {
            $vue = Pilotage::vueGenerale($user, $filters);
            fputcsv($out, ['Activité', 'Commandes', 'Montant HT', 'Marge', 'Marge / ventes HT (%)'], ';', '"', '\\');
            foreach ($vue['resultats_activite']['lignes'] as $r) {
                fputcsv($out, [$r['activite'], $r['commandes'], $r['ventes_ht'], $r['marge'], $r['marge_pct']], ';', '"', '\\');
            }
            $t = $vue['resultats_activite']['total'];
            fputcsv($out, ['Total', $t['commandes'], $t['ventes_ht'], $t['marge'], $t['marge_pct']], ';', '"', '\\');
        } elseif ($onglet === 'activites') {
            $data = Pilotage::activites($user, $filters);
            fputcsv($out, ['Activité', 'Commandes', 'Ventes HT', 'Marge', 'Marge / ventes HT (%)', 'Part des ventes (%)'], ';', '"', '\\');
            foreach ($data['tableau']['lignes'] as $r) {
                fputcsv($out, [$r['activite'], $r['commandes'], $r['ventes_ht'], $r['marge'], $r['marge_pct'], $r['part_ventes']], ';', '"', '\\');
            }
            $t = $data['tableau']['total'];
            fputcsv($out, ['Total', $t['commandes'], $t['ventes_ht'], $t['marge'], $t['marge_pct'], 100], ';', '"', '\\');
        } else {
            $data = Pilotage::collaborateurs($user, $filters);
            fputcsv($out, ['Rôle du filtre', Pilotage::ROLES_COLLABORATEUR[$data['role']] ?? $data['role']], ';', '"', '\\');
            fputcsv($out, ['Collaborateur', 'Dossiers traités', 'Ventes HT attribuées', 'Marge attribuée', 'Coûts affectés', 'Contribution nette', 'Transformation (%)'], ';', '"', '\\');
            foreach ($data['lignes'] as $r) {
                fputcsv($out, [
                    $r['nom'], $r['dossiers'], $r['ventes_ht'], $r['marge_attribuee'],
                    $r['couts_affectes'] ?? 'Non renseigné',
                    $r['contribution_nette'] ?? 'Non renseigné',
                    $r['transformation']['taux'] ?? '—',
                ], ';', '"', '\\');
            }
        }

        fclose($out);
        exit;
    }

    /**
     * Vue imprimable (support de l'export "PDF" — voir docblock exportCsv).
     */
    public function exportPdf(): void
    {
        Auth::requirePilotage();
        $user = Auth::user();
        $onglet = in_array($_GET['onglet'] ?? '', self::ONGLETS, true) ? $_GET['onglet'] : 'general';
        $filters = Pilotage::resoudreFiltres($_GET);

        $data = ['onglet' => $onglet, 'filters' => $filters];
        if ($onglet === 'general') {
            $data['vueGenerale'] = Pilotage::vueGenerale($user, $filters);
        } elseif ($onglet === 'activites') {
            $data['activitesData'] = Pilotage::activites($user, $filters);
        } else {
            $data['collaborateursData'] = Pilotage::collaborateurs($user, $filters);
        }

        View::renderPlain('pilotage/imprimable', $data);
    }

    private function querystringSansOnglet(array $params): string
    {
        unset($params['onglet'], $params['r']);
        return http_build_query($params);
    }
}
