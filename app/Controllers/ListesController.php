<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Filiale;
use App\Models\Listes;

/**
 * Listes transverses du menu (Offres, Cotations, Commandes, Factures,
 * Comparateur) : tous dossiers confondus, dans le périmètre de filiales de
 * l'utilisateur. Lecture seule : les actions restent sur la fiche du dossier.
 */
class ListesController
{
    private function filtres(array $cles): array
    {
        $f = [];
        foreach ($cles as $c) {
            $v = $_GET[$c] ?? '';
            $f[$c] = is_string($v) ? trim($v) : '';
        }
        return $f;
    }

    private function rendre(string $vue, array $donnees, array $filtres): void
    {
        $user = Auth::user();
        View::render('listes/' . $vue, $donnees + [
            'filters' => $filtres,
            'filiales' => Filiale::visibleFor($user),
        ]);
    }

    public function offres(): void
    {
        $f = $this->filtres(['q', 'statut', 'validite', 'filiale_id', 'date_debut', 'date_fin']);
        $this->rendre('offres', ['lignes' => Listes::offres(Auth::user(), $f)], $f);
    }

    public function cotations(): void
    {
        $f = $this->filtres(['q', 'statut', 'validite', 'filiale_id', 'date_debut', 'date_fin']);
        $this->rendre('cotations', [
            'lignes' => Listes::cotations(Auth::user(), $f),
            'voirMarges' => Auth::canSeeMarges(),
        ], $f);
    }

    public function commandes(): void
    {
        $f = $this->filtres(['q', 'statut', 'etape', 'filiale_id', 'date_debut', 'date_fin']);
        $this->rendre('commandes', ['lignes' => Listes::commandes(Auth::user(), $f)], $f);
    }

    public function factures(): void
    {
        $f = $this->filtres(['q', 'statut', 'type', 'filiale_id', 'date_debut', 'date_fin']);
        $this->rendre('factures', ['lignes' => Listes::factures(Auth::user(), $f)], $f);
    }

    public function comparateur(): void
    {
        $f = $this->filtres(['q', 'decision', 'filiale_id', 'date_debut', 'date_fin']);
        $this->rendre('comparateur', ['lignes' => Listes::comparateurs(Auth::user(), $f)], $f);
    }
}
