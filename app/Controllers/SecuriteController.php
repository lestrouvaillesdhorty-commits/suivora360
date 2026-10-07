<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Filiale;

/**
 * [ajouté 06/10] Page Sécurité — journal d'audit centralisé (spec du 29/09,
 * claude/page-securite-audit.md). Réservée au Propriétaire et à l'Admin
 * d'organisation (Auth::requireAdmin(), refus HTTP 403 côté serveur, pas
 * seulement un menu masqué). Le périmètre est toujours limité aux filiales
 * de l'organisation de l'utilisateur (Filiale::visibleIdsFor).
 *
 * Exports : CSV ouvrable dans Excel (pas de bibliothèque .xlsx sans Composer
 * sur l'hébergement IONOS, même choix que Pilotage/Demandes) et vue
 * imprimable pour le PDF via le navigateur. Chaque export est lui-même
 * journalisé (action export_journal_audit).
 */
class SecuriteController
{
    private const PAR_PAGE = 50;
    private const EXPORT_MAX = 5000;

    public function index(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();
        $filialeIds = Filiale::visibleIdsFor($user);
        $filters = $this->filtres($filialeIds);

        $total = AuditLog::compter($filialeIds, $filters);
        $totalPages = max(1, (int) ceil($total / self::PAR_PAGE));
        $page = min(max(1, (int) ($_GET['page'] ?? 1)), $totalPages);
        $evenements = AuditLog::recherche($filialeIds, $filters, self::PAR_PAGE, ($page - 1) * self::PAR_PAGE);

        View::render('security/index', [
            'evenements' => $evenements,
            'filters' => $filters,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'utilisateurs' => AuditLog::utilisateursDuJournal($filialeIds),
            'filiales' => Filiale::visibleFor($user),
        ]);
    }

    public function exportCsv(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();
        $filialeIds = Filiale::visibleIdsFor($user);
        $filters = $this->filtres($filialeIds);
        $total = AuditLog::compter($filialeIds, $filters);
        $lignes = AuditLog::recherche($filialeIds, $filters, self::EXPORT_MAX, 0);
        AuditLog::logAdmin($user, 'export_journal_audit', 'journal_audit', null, 'CSV — ' . count($lignes) . ' événement(s)');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="journal_audit_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Journal d\'audit — Suivora360'], ';', '"', '\\');
        fputcsv($out, ['Exporté le', date('d/m/Y H:i'), 'par', $user['nom']], ';', '"', '\\');
        fputcsv($out, ['Filtres', $this->resumeFiltres($filters)], ';', '"', '\\');
        fputcsv($out, ['Événements', count($lignes) . ($total > count($lignes) ? ' sur ' . $total . ' (export limité aux ' . self::EXPORT_MAX . ' plus récents)' : '')], ';', '"', '\\');
        fputcsv($out, [], ';', '"', '\\');
        fputcsv($out, ['Date', 'Heure', 'Utilisateur', 'Filiale', 'Action', 'Code action', 'Type', 'Identifiant', 'Détails'], ';', '"', '\\');
        foreach ($lignes as $l) {
            fputcsv($out, [
                date('d/m/Y', strtotime($l['created_at'])),
                date('H:i:s', strtotime($l['created_at'])),
                $l['utilisateur_nom'] ?? '',
                $l['filiale_nom'] ?? '',
                AuditLog::libelle($l['action']),
                $l['action'],
                AuditLog::ENTITES[$l['entite_type']] ?? $l['entite_type'],
                $l['entite_id'] ?? '',
                \App\Core\Csv::cell((string) ($l['details'] ?? '')),
            ], ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    public function imprimable(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();
        $filialeIds = Filiale::visibleIdsFor($user);
        $filters = $this->filtres($filialeIds);
        $total = AuditLog::compter($filialeIds, $filters);
        $lignes = AuditLog::recherche($filialeIds, $filters, self::EXPORT_MAX, 0);
        AuditLog::logAdmin($user, 'export_journal_audit', 'journal_audit', null, 'PDF (impression) — ' . count($lignes) . ' événement(s)');

        View::renderPlain('security/imprimable', [
            'lignes' => $lignes,
            'total' => $total,
            'max' => self::EXPORT_MAX,
            'resume' => $this->resumeFiltres($filters),
            'par' => $user['nom'],
        ]);
    }

    /** Filtres validés : toute valeur inattendue est ignorée (jamais une erreur ni un résultat faussé). */
    private function filtres(array $filialeIds): array
    {
        $scalaire = fn($k) => (isset($_GET[$k]) && is_scalar($_GET[$k])) ? trim((string) $_GET[$k]) : '';
        $date = function (string $v): string {
            $d = \DateTime::createFromFormat('Y-m-d', $v);
            return ($d && $d->format('Y-m-d') === $v) ? $v : '';
        };
        $f = [
            'utilisateur_id' => ctype_digit($scalaire('utilisateur_id')) ? (int) $scalaire('utilisateur_id') : 0,
            'entite_type' => array_key_exists($scalaire('entite_type'), AuditLog::ENTITES) ? $scalaire('entite_type') : '',
            'action' => array_key_exists($scalaire('action'), AuditLog::LIBELLES) ? $scalaire('action') : '',
            'filiale_id' => (ctype_digit($scalaire('filiale_id')) && in_array((int) $scalaire('filiale_id'), $filialeIds, true)) ? (int) $scalaire('filiale_id') : 0,
            'date_debut' => $date($scalaire('date_debut')),
            'date_fin' => $date($scalaire('date_fin')),
            'q' => mb_substr($scalaire('q'), 0, 100),
        ];
        return $f;
    }

    private function resumeFiltres(array $f): string
    {
        $p = [];
        if ($f['utilisateur_id']) { $p[] = 'utilisateur #' . $f['utilisateur_id']; }
        if ($f['entite_type']) { $p[] = 'type : ' . AuditLog::ENTITES[$f['entite_type']]; }
        if ($f['action']) { $p[] = 'action : ' . AuditLog::libelle($f['action']); }
        if ($f['filiale_id']) { $p[] = 'filiale #' . $f['filiale_id']; }
        if ($f['date_debut']) { $p[] = 'du ' . date('d/m/Y', strtotime($f['date_debut'])); }
        if ($f['date_fin']) { $p[] = 'au ' . date('d/m/Y', strtotime($f['date_fin'])); }
        if ($f['q']) { $p[] = 'texte : « ' . $f['q'] . ' »'; }
        return $p ? implode(' ; ', $p) : 'Aucun (tout le journal)';
    }
}
