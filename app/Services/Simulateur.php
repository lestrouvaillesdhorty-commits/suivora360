<?php

namespace App\Services;

/**
 * Simulateur de prix — calcule le coût de revient et le prix de vente à
 * partir d'un achat, des frais annexes, d'une majoration et d'une TVA, puis
 * convertit le résultat en FCFA. Pur calcul, sans effet de bord : rien
 * n'est enregistré, donc une simulation ne modifie jamais une simulation,
 * une offre ou une commande déjà existante.
 */
class Simulateur
{
    public static function calculer(array $input): array
    {
        $achat = (float) ($input['achat'] ?? 0);
        $poids = (float) ($input['poids'] ?? 0);
        $transport = (float) ($input['transport'] ?? 0);
        $emballage = (float) ($input['emballage'] ?? 0);
        $assurance = (float) ($input['assurance'] ?? 0);
        $douane = (float) ($input['douane'] ?? 0);
        $dedouanement = (float) ($input['dedouanement'] ?? 0);
        $autresFrais = (float) ($input['autres_frais'] ?? 0);
        $quantite = (float) ($input['quantite'] ?? 0);
        $majorationPourcentage = (float) ($input['majoration_pourcentage'] ?? 0);
        $tvaPourcentage = (float) ($input['tva_pourcentage'] ?? 0);
        $tauxFcfa = (float) ($input['taux_fcfa'] ?? 0);

        $coutRevientTotal = $achat + $transport + $emballage + $assurance + $douane + $dedouanement + $autresFrais;
        $coutRevientUnitaire = $quantite > 0 ? $coutRevientTotal / $quantite : null;

        $majorationMontant = round($coutRevientTotal * $majorationPourcentage / 100, 2);
        $prixHt = round($coutRevientTotal + $majorationMontant, 2);
        $tvaMontant = round($prixHt * $tvaPourcentage / 100, 2);
        $prixTtc = round($prixHt + $tvaMontant, 2);

        $prixTtcFcfa = $tauxFcfa > 0 ? (int) round($prixTtc * $tauxFcfa) : null;
        $coutRevientUnitaireFcfa = ($tauxFcfa > 0 && $coutRevientUnitaire !== null) ? (int) round($coutRevientUnitaire * $tauxFcfa) : null;

        return [
            'achat' => $achat,
            'poids' => $poids,
            'transport' => $transport,
            'emballage' => $emballage,
            'assurance' => $assurance,
            'douane' => $douane,
            'dedouanement' => $dedouanement,
            'autres_frais' => $autresFrais,
            'quantite' => $quantite,
            'cout_revient_total' => round($coutRevientTotal, 2),
            'cout_revient_unitaire' => $coutRevientUnitaire !== null ? round($coutRevientUnitaire, 2) : null,
            'majoration_pourcentage' => $majorationPourcentage,
            'majoration_montant' => $majorationMontant,
            'prix_ht' => $prixHt,
            'tva_pourcentage' => $tvaPourcentage,
            'tva_montant' => $tvaMontant,
            'prix_ttc' => $prixTtc,
            'taux_fcfa' => $tauxFcfa,
            'prix_ttc_fcfa' => $prixTtcFcfa,
            'cout_revient_unitaire_fcfa' => $coutRevientUnitaireFcfa,
        ];
    }
}
