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
        $filiale = Filiale::find((int) ($consultation['filiale_id'] ?? 0));
        $emetteur = $filiale['nom'] ?? '';

        $pdf = new PdfSimple(trim($emetteur . ' — ' . ($consultation['reference'] ?? '')));
        if ($emetteur !== '') {
            $pdf->sousTitre($emetteur);
        }
        $pdf->titre("Demande d'offre — " . ($dossier['objet'] ?? ''));
        $pdf->sousTitre('Référence dossier : ' . ($dossier['reference'] ?? '') . '   |   Référence consultation : ' . ($consultation['reference'] ?? '') . '   |   Édité le ' . date('d/m/Y'));

        if (!$masquer) {
            $pdf->section('Client');
            $pdf->paragraphe((string) ($demande['expediteur_entreprise'] ?? $demande['expediteur_nom'] ?? '—'));
        }

        $pdf->section('Besoins communiqués');
        $besoins = trim((string) ($consultation['articles_demandes'] ?? ''));
        $pdf->paragraphe($besoins !== '' ? $besoins : '—');

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
        $logi = [
            'Destination' => ($demande['destination_pays'] ?? '') ?: 'Non précisée',
        ];
        if (!$masquer) {
            $logi['Lieu de livraison'] = ($demande['lieu_livraison'] ?? '') ?: 'Non précisé';
        }
        $logi['Incoterm souhaité'] = ($demande['incoterm_souhaite'] ?? '') ?: 'Non précisé';
        $logi['Échéance de réponse souhaitée'] = !empty($partage['echeance_reponse']) ? date('d/m/Y', strtotime($partage['echeance_reponse'])) : 'Non précisée';
        $pdf->paires($logi);

        if (!empty($d['pieces'])) {
            $pdf->section('Pièces jointes (disponibles via le lien sécurisé)');
            foreach ($d['pieces'] as $p) {
                $pdf->paragraphe('• ' . ($p['nom_original'] ?? ''));
            }
        }

        $pdf->paragraphe("Merci de répondre directement par le canal utilisé pour vous transmettre cette demande.", 8.5);
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
