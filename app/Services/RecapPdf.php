<?php

namespace App\Services;

use App\Models\Filiale;

/**
 * PDF du récapitulatif de consultation : même contenu que la page vue par
 * le fournisseur (PartagePublicController::donneesRecap), respectant le
 * masquage du client. Les pièces jointes sont seulement listées (elles
 * restent accessibles par le lien sécurisé).
 */
class RecapPdf
{
    public static function generer(array $d): string
    {
        $consultation = $d['consultation'];
        $dossier = $d['dossier'] ?? [];
        $demande = $d['demande'] ?? [];
        $partage = $d['partage'];
        $masquer = !empty($d['masquerClient']);
        $em = $d['emetteur'] ?? ['nom' => (string) (Filiale::find((int) ($consultation['filiale_id'] ?? 0))['nom'] ?? '')];
        $emetteur = (string) ($em['nom'] ?? '');

        $pdf = new PdfSimple(trim($emetteur . ' - Demande d\'offre ' . ($consultation['reference'] ?? '')));
        $pdf->couleur(\App\Models\EntrepriseInfo::couleurEffective(is_array($em) ? $em : []));
        $pdf->piedEntreprise(DevisPdf::ligneEntreprise($em));
        $logo = null;
        if (!empty($em['logo'])) {
            $chemin = \App\Core\Storage::path('uploads/logos/' . basename((string) $em['logo']));
            $logo = is_file($chemin) ? PhotoProduit::jpeg($chemin, 400) : null;
        }
        $mode = \App\Models\EntrepriseInfo::enteteMode(is_array($em) ? $em : [], $logo !== null);
        $coord = array_values(array_filter([
            trim(preg_replace('/\s*\R\s*/u', ', ', (string) ($em['adresse'] ?? ''))),
            !empty($em['telephone']) ? 'Tél. ' . $em['telephone'] : '',
            (string) ($em['email'] ?? ''),
            (string) ($em['site_web'] ?? ''),
        ]));
        $droite = array_values(array_filter([
            'Date : ' . date('d/m/Y'),
            !empty($partage['echeance_reponse']) ? 'Réponse avant le ' . date('d/m/Y', strtotime($partage['echeance_reponse'])) : '',
        ]));
        $pdf->enteteDevis($mode === 'nom' ? null : $logo, $mode === 'logo' ? '' : $emetteur, $coord, "DEMANDE D'OFFRE " . ($consultation['reference'] ?? ''), $droite);
        $pdf->sousTitre('Objet : ' . ($dossier['objet'] ?? '') . '   |   Dossier : ' . ($dossier['reference'] ?? ''));

        if (!$masquer) {
            $pdf->section('Client');
            $pdf->paragraphe((string) ($demande['expediteur_entreprise'] ?? $demande['expediteur_nom'] ?? '—'));
        }

        $besoins = trim((string) ($consultation['articles_demandes'] ?? ''));
        if ($besoins !== '') {
            $pdf->section('Besoins communiqués');
            $pdf->paragraphe($besoins);
        }
        if (!empty($d['articles'])) {
            $pdf->section('Articles demandés');
        }

        if (!empty($d['articles'])) {
            $lignes = [];
            foreach ($d['articles'] as $a) {
                $q = (float) ($a['quantite'] ?? 0);
                $lignes[] = [
                    $a['designation'] ?? '',
                    rtrim(rtrim(number_format($q, 2, ',', ''), '0'), ','),
                    $a['unite'] ?? '',
                    $a['conditionnement'] ?? '',
                    $a['reference'] ?? '',
                    $a['marque'] ?? '',
                    $a['caracteristiques'] ?? '',
                ];
            }
            $pdf->tableau(['Désignation', 'Qté', 'Unité', 'Conditionnement', 'Référence', 'Marque', 'Caractéristiques'], $lignes, [4.2, 1.1, 1.4, 3.3, 1.8, 1.8, 3.4]);
        }

        if (!empty($d['detailsDemande'])) {
            $pdf->section('Détails de la demande');
            $pdf->paires($d['detailsDemande']);
        }

        $pdf->section('Spécifications logistiques');
        $liv = \App\Models\ConsultationFournisseur::livraison($consultation, $demande);
        $logi = [];
        $adr = $liv['etape_adresse'] !== '' ? preg_replace('/\s*\R\s*/u', ', ', $liv['etape_adresse']) : '';
        if ($liv['etape']) {
            $logi['Livrer à'] = $liv['etape_pays'];
            if ($adr !== '') { $logi['Adresse de livraison'] = $adr; }
            $logi['Destination finale'] = $liv['finale'] ?: 'Non précisée';
        } else {
            $logi['Destination'] = $liv['finale'] ?: 'Non précisée';
            if ($adr !== '') {
                $logi['Adresse de livraison'] = $adr;
            } elseif (!$masquer) {
                $logi['Lieu de livraison'] = ($demande['lieu_livraison'] ?? '') ?: 'Non précisé';
            }
        }
        $logi['Incoterm souhaité'] = $liv['incoterm'] ?: 'Non précisé';
        $logi['Échéance de réponse souhaitée'] = !empty($partage['echeance_reponse']) ? date('d/m/Y', strtotime($partage['echeance_reponse'])) : 'Non précisée';
        $pdf->paires($logi);

        if (!empty($d['pieces'])) {
            $pdf->section('Pièces jointes (disponibles via le lien sécurisé)');
            foreach ($d['pieces'] as $p) {
                $pdf->paragraphe('• ' . ($p['nom_original'] ?? ''));
            }
        }

        $pdf->section('Votre offre');
        $pdf->paragraphe("Merci de nous indiquer, pour chaque article :", 9);
        foreach (\App\Models\ConsultationFournisseur::infos($consultation) as $inf) {
            $pdf->paragraphe('- ' . $inf, 9);
        }
        $ex = $d['expediteur'] ?? null;
        $cont = ($ex && !empty($ex['email'])) ? trim(implode(' - ', array_filter([(string) ($ex['nom'] ?? ''), (string) $ex['email']]))) : '';
        $pdf->paragraphe(($cont !== '' ? "Merci de répondre directement à " . $cont . ". " : "Merci de répondre directement par le canal utilisé pour vous transmettre cette demande. ") . "Demande émise par " . $emetteur . ".", 8.5);
        return $pdf->rendre();
    }

    /** Envoie le PDF au navigateur (téléchargement) et termine la requête. */
    public static function sortir(string $contenu, string $nom): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('Content-Length: ' . strlen($contenu));
        header('Cache-Control: private, no-store');
        echo $contenu;
        exit;
    }

    public static function nomFichier(array $consultation): string
    {
        return 'Recapitulatif-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($consultation['reference'] ?? 'consultation')) . '.pdf';
    }
}
