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
    /** Traductions anglaises des libellés du PDF (les textes saisis par l'utilisateur ne sont jamais traduits). */
    private const EN = [
        'Lieu d\'enlèvement' => 'Pickup location', 'Destination' => 'Destination', 'Marchandises' => 'Goods', 'Poids estimé' => 'Estimated weight',
        'Volume estimé' => 'Estimated volume', 'Mode de transport souhaité' => 'Requested transport mode', 'Nature du service' => 'Nature of the service',
        'Type de prestation' => 'Type of service', 'Site d\'intervention' => 'Site of intervention', 'Visite préalable nécessaire' => 'Prior visit required',
        'Date souhaitée' => 'Requested date',
        'Prix unitaire et devise' => 'Unit price and currency', 'Délai de production / livraison' => 'Production / delivery lead time',
        'Quantité minimale de commande' => 'Minimum order quantity', 'Validité de l\'offre' => 'Offer validity', 'Conditions de paiement' => 'Payment terms',
        'Incoterm proposé' => 'Proposed Incoterm', 'Échantillon possible' => 'Sample available', 'Emballage export' => 'Export packaging',
        'Poids et volume par colis' => 'Weight and volume per package', 'Certificats (origine, conformité)' => 'Certificates (origin, compliance)',
    ];

    public static function generer(array $d, string $lang = 'fr'): string
    {
        $en = $lang === 'en';
        $L = fn(string $fr, string $anglais) => $en ? $anglais : $fr;
        $T = fn(string $fr) => $en ? (self::EN[$fr] ?? $fr) : $fr;
        $consultation = $d['consultation'];
        $dossier = $d['dossier'] ?? [];
        $demande = $d['demande'] ?? [];
        $partage = $d['partage'];
        $masquer = !empty($d['masquerClient']);
        $em = $d['emetteur'] ?? ['nom' => (string) (Filiale::find((int) ($consultation['filiale_id'] ?? 0))['nom'] ?? '')];
        $emetteur = (string) ($em['nom'] ?? '');

        $pdf = new PdfSimple(trim($emetteur . $L(' - Demande d\'offre ', ' - Request for quotation ') . ($consultation['reference'] ?? '')));
        $pdf->couleur(\App\Models\EntrepriseInfo::couleurEffective(is_array($em) ? $em : []));
        $pdf->modele('classique'); // identique à la page vue à l'écran : fond blanc + filet de couleur
        $pdf->piedEntreprise(DevisPdf::ligneEntreprise($em));
        $logo = null;
        if (!empty($em['logo'])) {
            $chemin = \App\Core\Storage::path('uploads/logos/' . basename((string) $em['logo']));
            $logo = is_file($chemin) ? PhotoProduit::jpeg($chemin, 400) : null;
        }
        $mode = \App\Models\EntrepriseInfo::enteteMode(is_array($em) ? $em : [], $logo !== null);
        $coord = array_values(array_filter([
            trim(preg_replace('/\s*\R\s*/u', ', ', (string) ($em['adresse'] ?? ''))),
            !empty($em['telephone']) ? $L('Tél. ', 'Tel. ') . $em['telephone'] : '',
            (string) ($em['email'] ?? ''),
            (string) ($em['site_web'] ?? ''),
        ]));
        $droite = array_values(array_filter([
            $L('Date : ' . date('d/m/Y'), 'Date: ' . date('d/m/Y')),
            !empty($partage['echeance_reponse']) ? $L('Réponse avant le ', 'Please reply by ') . date('d/m/Y', strtotime($partage['echeance_reponse'])) : '',
        ]));
        $pdf->enteteDevis($mode === 'nom' ? null : $logo, $mode === 'logo' ? '' : $emetteur, $coord, $L("DEMANDE D'OFFRE ", 'REQUEST FOR QUOTATION ') . ($consultation['reference'] ?? ''), $droite, 0.52);
        $liv = \App\Models\ConsultationFournisseur::livraison($consultation, $demande);
        $adr = $liv['etape_adresse'];
        $objTxt = trim(preg_replace('/^\s*Demande\s*:\s*/iu', '', (string) ($dossier['objet'] ?? '')));
        $objet = [$L('Demande : ', 'Request: ') . $objTxt, $L('Dossier : ', 'File: ') . ($dossier['reference'] ?? '')];
        if (!$masquer) {
            $objet[] = $L('Client : ', 'Customer: ') . (($demande['expediteur_entreprise'] ?? '') ?: ($demande['expediteur_nom'] ?? '—'));
        }
        $livL = [];
        if ($liv['etape']) {
            $livL[] = $L('Livrer à : ', 'Deliver to: ') . $liv['etape_pays'];
            foreach (preg_split('/\R/u', $adr) as $x) { if (trim($x) !== '') { $livL[] = trim($x); } }
            $livL[] = $L('Destination finale : ', 'Final destination: ') . ($liv['finale'] ?: $L('Non précisée', 'Not specified'));
        } else {
            $livL[] = $L('Destination : ', 'Destination: ') . ($liv['finale'] ?: $L('Non précisée', 'Not specified'));
            if ($adr !== '') {
                foreach (preg_split('/\R/u', $adr) as $x) { if (trim($x) !== '') { $livL[] = trim($x); } }
            } elseif (!$masquer) {
                $livL[] = $L('Lieu : ', 'Place: ') . (($demande['lieu_livraison'] ?? '') ?: $L('Non précisé', 'Not specified'));
            }
        }
        $livL[] = $L('Incoterm : ', 'Incoterm: ') . ($liv['incoterm'] ?: $L('Non précisé', 'Not specified'));
        $pdf->blocsInfo([[$L('Objet', 'Subject'), $objet, true, true], [$L('Livraison souhaitée', 'Requested delivery'), $livL, false, true]]);

        $besoins = trim((string) ($consultation['articles_demandes'] ?? ''));
        if ($besoins !== '') {
            $pdf->section($L('Besoins communiqués', 'Requirements'));
            $pdf->paragraphe($besoins);
        }
        if (!empty($d['articles'])) {
            $pdf->section($L('Articles demandés', 'Requested items'));
        }

        if (!empty($d['articles'])) {
            $lignes = [];
            $num = 0;
            foreach ($d['articles'] as $a) {
                $num++;
                $q = (float) ($a['quantite'] ?? 0);
                $lignes[] = [
                    (string) $num,
                    $a['designation'] ?? '',
                    rtrim(rtrim(number_format($q, 2, $en ? '.' : ',', ''), '0'), $en ? '.' : ','),
                    $a['unite'] ?? '',
                    $a['conditionnement'] ?? '',
                    $a['reference'] ?? '',
                    $a['marque'] ?? '',
                    $a['caracteristiques'] ?? '',
                ];
            }
            $pdf->tableau($en ? ['No.', 'Description', 'Qty', 'Unit', 'Packaging', 'Reference', 'Brand', 'Specifications'] : ['N°', 'Désignation', 'Qté', 'Unité', 'Conditionnement', 'Référence', 'Marque', 'Caractéristiques'], $lignes, [0.8, 4.0, 1.1, 1.4, 3.2, 1.8, 1.8, 3.2]);
        }

        if (!empty($d['detailsDemande'])) {
            $pdf->section($L('Détails de la demande', 'Request details'));
            $pdf->pairesTeintees($en ? array_combine(array_map($T, array_keys($d['detailsDemande'])), array_values($d['detailsDemande'])) : $d['detailsDemande']);
        }

        if (!empty($d['pieces'])) {
            $pdf->section(\App\Models\ConsultationPartage::sansLien($partage) || empty($partage['token']) ? $L('Pièces jointes', 'Attachments') : $L('Pièces jointes (disponibles via le lien sécurisé)', 'Attachments (available via the secure link)'));
            foreach ($d['pieces'] as $p) {
                $pdf->paragraphe('• ' . ($p['nom_original'] ?? ''));
            }
        }

        $pdf->section($L('Votre offre', 'Your offer'));
        $pdf->paragraphe($L("Merci de nous indiquer, pour chaque article :", 'Please indicate, for each item:'), 9);
        foreach (\App\Models\ConsultationFournisseur::infos($consultation) as $inf) {
            $pdf->paragraphe('•  ' . $T($inf), 9);
        }
        $ex = $d['expediteur'] ?? null;
        $cont = ($ex && !empty($ex['email'])) ? trim(implode(' - ', array_filter([(string) ($ex['nom'] ?? ''), (string) $ex['email']]))) : '';
        $pdf->paragraphe(($cont !== '' ? $L('Merci de répondre directement à ', 'Please reply directly to ') . $cont . '. ' : $L('Merci de répondre directement par le canal utilisé pour vous transmettre cette demande. ', 'Please reply directly through the channel used to send you this request. ')), 8.5);
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

    public static function nomFichier(array $consultation, string $lang = 'fr'): string
    {
        return ($lang === 'en' ? 'Request-for-quotation-' : 'Recapitulatif-') . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($consultation['reference'] ?? 'consultation')) . '.pdf';
    }
}
