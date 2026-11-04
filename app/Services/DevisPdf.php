<?php

namespace App\Services;

use App\Models\Demande;

/** Devis client (PDF) : en-tête émetteur, bloc client, tableau d'articles, totaux HT / TVA / TTC, conditions, pied de page. */
class DevisPdf
{
    public static function fmt($n, int $dec = 2): string
    {
        return number_format((float) $n, $dec, ',', ' ');
    }

    /** Lignes du tableau (partagées avec la page d'aperçu). */
    public static function details(array $it): string
    {
        $p = [];
        foreach (['conditionnement', 'reference', 'marque', 'caracteristiques'] as $k) {
            $v = trim((string) ($it[$k] ?? ''));
            if ($v !== '') {
                $p[] = ($k === 'reference' ? 'Réf. ' : '') . $v;
            }
        }
        return implode(' - ', $p);
    }

    /** Totaux HT / TVA / TTC, même pour les anciennes cotations (sans HT enregistré). */
    public static function totaux(array $c): array
    {
        $ttc = (float) $c['montant_total'];
        $tva = isset($c['tva_taux']) && $c['tva_taux'] !== null ? (float) $c['tva_taux'] : null;
        $ht = isset($c['montant_ht']) && $c['montant_ht'] !== null ? (float) $c['montant_ht'] : null;
        if ($ht === null) {
            $ht = $tva !== null ? round($ttc / (1 + $tva / 100), 2) : $ttc;
        }
        return ['ht' => $ht, 'tva_taux' => $tva, 'tva' => round($ttc - $ht, 2), 'ttc' => $ttc];
    }

    /** Date de validité : celle de la cotation, sinon date du devis + durée par défaut de la fiche entreprise. */
    public static function validite(array $c, array $emetteur = []): ?string
    {
        if (!empty($c['validite_devis'])) {
            return (string) $c['validite_devis'];
        }
        $j = (int) ($emetteur['devis_validite_jours'] ?? 0);
        return $j > 0 ? date('Y-m-d', strtotime((string) $c['created_at'] . ' +' . $j . ' days')) : null;
    }

    /** Coordonnées de l'entreprise sur une ligne (pied de page). */
    public static function ligneEntreprise(array $e): string
    {
        $p = [];
        foreach (['nom'] as $k) { if (!empty($e[$k])) { $p[] = $e[$k]; } }
        if (!empty($e['adresse'])) { $p[] = trim(preg_replace('/\s*\R\s*/u', ', ', (string) $e['adresse'])); }
        if (!empty($e['telephone'])) { $p[] = 'Tél. ' . $e['telephone']; }
        if (!empty($e['email'])) { $p[] = $e['email']; }
        if (!empty($e['site_web'])) { $p[] = $e['site_web']; }
        if (!empty($e['identifiants'])) { $p[] = trim(preg_replace('/\s*\R\s*/u', ' - ', (string) $e['identifiants'])); }
        return implode('  |  ', $p);
    }

    public static function generer(array $c, array $items, array $emetteur, array $client): string
    {
        $dev = (string) ($c['devise'] ?? '');
        $t = self::totaux($c);
        $pdf = new PdfSimple(trim(($emetteur['nom'] ?? '') . ' - Devis ' . $c['reference'] . ((int) ($c['version'] ?? 1) > 1 ? ' v' . $c['version'] : '')));
        $pdf->piedEntreprise(self::ligneEntreprise($emetteur));
        $pdf->sousTitre((string) ($emetteur['nom'] ?? ''));
        $enteteInfos = trim(implode('   |   ', array_filter([
            trim(preg_replace('/\s*\R\s*/u', ', ', (string) ($emetteur['adresse'] ?? ''))),
            !empty($emetteur['telephone']) ? 'Tél. ' . $emetteur['telephone'] : '',
            (string) ($emetteur['email'] ?? ''),
        ])));
        if ($enteteInfos !== '') { $pdf->paragraphe($enteteInfos, 8.5); }
        $validite = self::validite($c, $emetteur);
        $pdf->titre('DEVIS ' . $c['reference'] . ((int) ($c['version'] ?? 1) > 1 ? ' (version ' . $c['version'] . ')' : ''));
        $pdf->sousTitre('Date : ' . date('d/m/Y', strtotime((string) $c['created_at'])) . ($validite ? '   |   Valable jusqu\'au ' . date('d/m/Y', strtotime($validite)) : '') . '   |   Dossier : ' . ($c['dossier_reference'] ?? ''));

        $pdf->section('Client');
        $adr = trim(implode(', ', array_filter([(string) ($client['adresse'] ?? ''), trim(($client['code_postal'] ?? '') . ' ' . ($client['ville'] ?? '')), (string) ($client['pays'] ?? '')])));
        $paires = ['Client' => (string) ($client['nom'] ?? $c['client_nom'] ?? '')];
        if ($adr !== '') { $paires['Adresse'] = $adr; }
        if (!empty($client['email'])) { $paires['E-mail'] = $client['email']; }
        if (!empty($client['telephone'])) { $paires['Téléphone'] = $client['telephone']; }
        $pdf->paires($paires);

        $pdf->section('Détail de l\'offre');
        if ($items) {
            $lignes = [];
            foreach ($items as $i => $it) {
                $lignes[] = [(string) ($i + 1), $it['designation'], self::details($it),
                    rtrim(rtrim(number_format((float) $it['quantite'], 2, ',', ''), '0'), ','), (string) ($it['unite'] ?? ''),
                    $it['prix_unitaire'] !== null ? self::fmt($it['prix_unitaire']) : '-', $it['montant'] !== null ? self::fmt($it['montant']) : '-'];
            }
            $pdf->tableau(['N°', 'Désignation', 'Détails', 'Qté', 'Unité', 'PU HT', 'Total HT'], $lignes, [0.8, 4, 3.6, 1.1, 1.3, 2, 2.2]);
        } else {
            $pdf->paragraphe('Offre globale (voir le montant ci-dessous).');
        }

        $pdf->section('Montants');
        $tot = ['Total HT' => self::fmt($t['ht']) . ' ' . $dev];
        $tot['TVA' . ($t['tva_taux'] !== null ? ' (' . rtrim(rtrim(number_format($t['tva_taux'], 2, ',', ''), '0'), ',') . ' %)' : '')] = self::fmt($t['tva']) . ' ' . $dev;
        $tot['TOTAL TTC'] = self::fmt($t['ttc']) . ' ' . $dev;
        $pdf->paires($tot);

        $cond = [];
        if (!empty($c['incoterm_client'])) { $cond['Incoterm'] = Demande::INCOTERMS[$c['incoterm_client']] ?? $c['incoterm_client']; }
        if (!empty($c['mode_paiement_negocie'])) { $cond['Paiement'] = Demande::MODES_PAIEMENT[$c['mode_paiement_negocie']] ?? $c['mode_paiement_negocie']; }
        if ($validite) { $cond['Validité du devis'] = date('d/m/Y', strtotime($validite)); }
        if ($cond) {
            $pdf->section('Conditions');
            $pdf->paires($cond);
        }
        if (trim((string) ($c['notes'] ?? '')) !== '') {
            $pdf->section('Remarques');
            $pdf->paragraphe((string) $c['notes']);
        }
        if (trim((string) ($emetteur['devis_conditions'] ?? '')) !== '') {
            $pdf->section('Conditions générales');
            $pdf->paragraphe((string) $emetteur['devis_conditions'], 9);
        }
        if (trim((string) ($emetteur['coordonnees_bancaires'] ?? '')) !== '') {
            $pdf->section('Règlement par virement');
            $pdf->paragraphe((string) $emetteur['coordonnees_bancaires'], 9);
        }
        $pdf->section('Bon pour accord');
        $pdf->paragraphe("Date :                                   Nom et qualité :                                   Signature (précédée de la mention « Bon pour accord ») :", 9);
        $pdf->paragraphe(' ', 24);
        $pdf->paragraphe('Devis établi par ' . ($emetteur['nom'] ?? '') . '. Merci de nous confirmer votre accord en réponse à ce message.', 8.5);
        return $pdf->rendre();
    }

    public static function nomFichier(array $c): string
    {
        return 'Devis-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $c['reference']) . ((int) ($c['version'] ?? 1) > 1 ? '-v' . $c['version'] : '') . '.pdf';
    }
}
