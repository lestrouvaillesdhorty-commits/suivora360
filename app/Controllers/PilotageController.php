<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Demande;
use App\Models\Filiale;
use App\Models\FonctionEquipe;
use App\Models\ObjectifCollaborateur;
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
    private const ONGLETS = ['general', 'activites', 'collaborateurs', 'objectifs'];

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
        } elseif ($onglet === 'objectifs') {
            $mois = ObjectifCollaborateur::moisValide($_GET['mois'] ?? '');
            $data['mois'] = $mois;
            $data['objectifsDispo'] = ObjectifCollaborateur::disponible();
            $data['objectifsData'] = $data['objectifsDispo'] ? Pilotage::objectifsMois($user, $mois, $filters['devise']) : null;
        } else {
            $data['collaborateursData'] = Pilotage::collaborateurs($user, $filters);
            if ($filters['role'] === 'achats') {
                $data['achatsData'] = Pilotage::collaborateursAchats($user, $filters);
            }
        }

        View::render('pilotage/index', $data);
    }

    /** Ajoute, modifie ou supprime les fonctions de l'équipe (libellés propres à l'entreprise). */
    public function enregistrerFonctions(): void
    {
        Auth::requirePilotage();
        $user = Auth::user();
        $orgId = (int) $user['organisation_id'];
        $mois = ObjectifCollaborateur::moisValide($_POST['mois'] ?? '');
        $retour = '/index.php?r=pilotage&onglet=objectifs&mois=' . $mois . '&devise=' . rawurlencode((string) ($_POST['devise'] ?? 'EUR'));
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null) || !FonctionEquipe::disponible()) {
            View::flash('erreur', 'Action impossible (session expirée ou migration v42 non lancée).');
            header('Location: ' . $retour);
            exit;
        }
        $nombre = fn($v) => (float) str_replace([' ', ','], ['', '.'], (string) $v);
        $action = (string) ($_POST['action'] ?? '');
        if (str_starts_with($action, 'supprimer:')) {
            FonctionEquipe::supprimer($orgId, (int) substr($action, 10));
            View::flash('succes', 'Fonction supprimée. Les personnes rattachées n\'ont plus de fonction.');
        } else {
            foreach ((array) ($_POST['f_libelle'] ?? []) as $id => $lib) {
                FonctionEquipe::modifier($orgId, (int) $id, (string) $lib, (string) ($_POST['f_indicateur'][$id] ?? 'marge'), (string) ($_POST['f_base'][$id] ?? 'responsable'), $nombre($_POST['f_taux'][$id] ?? 0));
            }
            if (trim((string) ($_POST['n_libelle'] ?? '')) !== '') {
                FonctionEquipe::creer($orgId, (string) $_POST['n_libelle'], (string) ($_POST['n_indicateur'] ?? 'marge'), (string) ($_POST['n_base'] ?? 'responsable'), $nombre($_POST['n_taux'] ?? 0));
            }
            View::flash('succes', 'Fonctions enregistrées.');
        }
        header('Location: ' . $retour);
        exit;
    }

    /** Enregistre les objectifs du mois, taux et base de commission (direction). */
    public function enregistrerObjectifs(): void
    {
        Auth::requirePilotage();
        $user = Auth::user();
        $mois = ObjectifCollaborateur::moisValide($_POST['mois'] ?? '');
        $devise = trim((string) ($_POST['devise'] ?? '')) ?: 'EUR';
        $retour = '/index.php?r=pilotage&onglet=objectifs&mois=' . $mois . '&devise=' . rawurlencode($devise);
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            View::flash('erreur', 'Session expirée, réessayez.');
            header('Location: ' . $retour);
            exit;
        }
        if (!ObjectifCollaborateur::disponible()) {
            View::flash('erreur', 'Lancez d\'abord la migration v40.');
            header('Location: ' . $retour);
            exit;
        }
        $orgId = (int) $user['organisation_id'];
        if (($_POST['action'] ?? '') === 'copier') {
            $n = ObjectifCollaborateur::copierMoisPrecedent($orgId, $mois);
            View::flash('succes', $n > 0 ? "$n objectif(s) copié(s) du mois précédent." : 'Rien à copier : le mois précédent est vide ou déjà repris.');
            header('Location: ' . $retour);
            exit;
        }
        $valides = [];
        foreach (Utilisateur::allForOrganisation($orgId) as $u) {
            $valides[(int) $u['id']] = true;
        }
        $nombre = fn($v) => (float) str_replace([' ', ','], ['', '.'], (string) $v);
        $n = 0;
        foreach ((array) ($_POST['objectif'] ?? []) as $uid => $obj) {
            $uid = (int) $uid;
            if (!isset($valides[$uid])) {
                continue;
            }
            $objVal = trim((string) $obj);
            $taux = $nombre($_POST['taux'][$uid] ?? 0);
            $base = (string) ($_POST['base'][$uid] ?? 'responsable');
            $indicateur = (string) ($_POST['indicateur'][$uid] ?? 'marge');
            $fonctionId = (int) ($_POST['fonction'][$uid] ?? 0) ?: null;
            if ($objVal === '' && $taux <= 0 && !$fonctionId) {
                continue;
            }
            ObjectifCollaborateur::enregistrer($orgId, $uid, $mois, $nombre($objVal), $devise, $taux, $base, $indicateur, $fonctionId);
            $n++;
        }
        View::flash('succes', 'Objectifs et commissions enregistrés (' . $n . ').');
        header('Location: ' . $retour);
        exit;
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
            fputcsv($out, ['Activité', 'Commandes', 'Montant HT', 'Marge sur commandes confirmées', 'Marge / ventes HT (%)'], ';', '"', '\\');
            foreach ($vue['resultats_activite']['lignes'] as $r) {
                fputcsv($out, \App\Core\Csv::row([$r['activite'], $r['commandes'], $r['ventes_ht'], $r['marge'], $r['marge_pct']]), ';', '"', '\\');
            }
            $t = $vue['resultats_activite']['total'];
            fputcsv($out, ['Total', $t['commandes'], $t['ventes_ht'], $t['marge'], $t['marge_pct']], ';', '"', '\\');
        } elseif ($onglet === 'objectifs') {
            $mois = ObjectifCollaborateur::moisValide($_GET['mois'] ?? '');
            $d = Pilotage::objectifsMois($user, $mois, $filters['devise']);
            fputcsv($out, ['Mois', $mois], ';', '"', '\\');
            fputcsv($out, [], ';', '"', '\\');
            fputcsv($out, ['Collaborateur', 'Fonction', 'Indicateur', 'Base de calcul', 'Objectif', 'Réalisé', 'Avancement (%)', 'Reste à faire', 'Statut', 'Taux commission (%)', 'Commission', 'Unité'], ';', '"', '\\');
            foreach ($d['lignes'] as $r) {
                $unite = $r['type'] === 'money' ? $filters['devise'] : 'commandes';
                fputcsv($out, \App\Core\Csv::row([
                    $r['nom'], $r['fonction'] ?? '', ObjectifCollaborateur::INDICATEURS[$r['indicateur']][0] ?? $r['indicateur'], ObjectifCollaborateur::BASES[$r['base']] ?? $r['base'],
                    $r['objectif'] ?? '', round($r['realise'], 2), $r['pct'] ?? '', $r['reste'] !== null ? round($r['reste'], 2) : '',
                    $r['statut'], $r['taux'] > 0 ? $r['taux'] : '', $r['commission'] ?? '', $unite,
                ]), ';', '"', '\\');
            }
            $t = $d['totaux'];
            fputcsv($out, ['Total équipe (montants)', '', '', '', $t['objectif'], round($t['realise'], 2), $t['pct'] ?? '', round($t['reste'], 2), '', '', round($t['commission'], 2), $filters['devise']], ';', '"', '\\');
        } elseif ($onglet === 'activites') {
            $data = Pilotage::activites($user, $filters);
            fputcsv($out, ['Activité', 'Commandes', 'Ventes HT', 'Marge sur commandes confirmées', 'Marge / ventes HT (%)', 'Part des ventes (%)'], ';', '"', '\\');
            foreach ($data['tableau']['lignes'] as $r) {
                fputcsv($out, \App\Core\Csv::row([$r['activite'], $r['commandes'], $r['ventes_ht'], $r['marge'], $r['marge_pct'], $r['part_ventes']]), ';', '"', '\\');
            }
            $t = $data['tableau']['total'];
            fputcsv($out, ['Total', $t['commandes'], $t['ventes_ht'], $t['marge'], $t['marge_pct'], 100], ';', '"', '\\');
        } else {
            $data = Pilotage::collaborateurs($user, $filters);
            fputcsv($out, ['Rôle du filtre', Pilotage::ROLES_COLLABORATEUR[$data['role']] ?? $data['role']], ';', '"', '\\');
            fputcsv($out, ['Collaborateur', 'Dossiers traités', 'Ventes HT attribuées', 'Marge attribuée', 'Coûts affectés', 'Contribution nette', 'Transformation (%)'], ';', '"', '\\');
            foreach ($data['lignes'] as $r) {
                fputcsv($out, \App\Core\Csv::row([
                    $r['nom'], $r['dossiers'], $r['ventes_ht'], $r['marge_attribuee'],
                    $r['couts_affectes'] ?? 'Non renseigné',
                    $r['contribution_nette'] ?? 'Non renseigné',
                    $r['transformation']['taux'] ?? '—',
                ]), ';', '"', '\\');
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
