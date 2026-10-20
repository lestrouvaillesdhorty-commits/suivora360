<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\ConsultationFournisseur;
use App\Models\Cotation;
use App\Models\Demande;
use App\Models\DemandeArticle;
use App\Models\Offre;

/**
 * Parcours guidé d'un dossier : à partir de l'état réel (articles, consultations, offres, cotation, commande),
 * détermine l'unique « prochaine action » à proposer, avec le bouton correspondant et un petit état d'avancement.
 * Ne modifie rien : c'est un calcul de lecture, affiché en haut de la fiche dossier.
 *
 * @return array{titre:string,texte:string,bouton:?string,url:?string,post:?array,secondaire:?array,compteurs:string[],ton:string}
 */
class ProchaineAction
{
    public static function calculer(array $dossier): array
    {
        $id = (int) $dossier['id'];
        $base = '/index.php?r=dossiers/' . $id;
        $etape = (string) $dossier['etape'];

        if (($dossier['statut'] ?? '') === 'cloture' || $etape === 'cloture') {
            return self::res('Dossier clôturé', 'Toutes les étapes sont terminées. Le dossier reste consultable pour l\'historique.', null, null, [], 'ok');
        }

        $demande = !empty($dossier['demande_id']) ? Demande::find((int) $dossier['demande_id']) : null;
        $nbArticles = $demande ? count(DemandeArticle::forDemande((int) $demande['id'])) : 0;
        $consultations = ConsultationFournisseur::forDossier($id);
        $aEnvoyer = array_values(array_filter($consultations, fn($c) => $c['statut'] === 'a_envoyer'));
        $envoyees = array_values(array_filter($consultations, fn($c) => $c['statut'] !== 'a_envoyer'));
        $avecOffre = count(array_filter($consultations, fn($c) => (int) $c['nb_offres'] > 0));
        $offres = Offre::forDossier($id);
        $offresEnCours = array_values(array_filter($offres, fn($o) => $o['statut'] === 'recue'));
        $retenue = Offre::retenueForDossier($id);
        $cotation = Cotation::latestForDossier($id);
        $commande = Commande::findByDossier($id);

        $cpt = [];
        if ($consultations) {
            $cpt[] = count($envoyees) . ' consultation' . (count($envoyees) > 1 ? 's' : '') . ' envoyée' . (count($envoyees) > 1 ? 's' : '') . ' sur ' . count($consultations);
        }
        if ($envoyees) {
            $cpt[] = $avecOffre . ' réponse' . ($avecOffre > 1 ? 's' : '') . ' sur ' . count($envoyees);
        }

        // 1. Le besoin doit contenir des articles.
        if ($nbArticles === 0 && !$retenue && !$cotation && !$commande) {
            return self::res('Compléter le besoin', 'Ajoutez les articles demandés par le client (ou faites-les lire par l\'IA) : ils seront repris dans les consultations et les cotations.', 'Ouvrir le besoin', $base . '&onglet=besoin', $cpt);
        }
        // 2. Commande en cours.
        if ($commande) {
            if ($etape === 'livraison') {
                return self::res('Suivre la livraison et clôturer', 'Mettez à jour les étapes de la commande (expédition, douane, livraison, solde). Quand tout est livré et payé, clôturez le dossier.', 'Voir le suivi de la commande', $base . '/commande', $cpt, 'info', null,
                    ['bouton' => 'Clôturer le dossier', 'post' => ['url' => $base . '/etape', 'champs' => ['etape' => 'cloture'], 'confirm' => 'Clôturer ce dossier ?']]);
            }
            return self::res('Suivre la commande', 'La commande est créée : mettez à jour le paiement, l\'expédition et la livraison.', 'Voir le suivi de la commande', $base . '/commande', $cpt, 'info', null,
                ['bouton' => 'Passer à la livraison', 'post' => ['url' => $base . '/etape', 'champs' => ['etape' => 'livraison'], 'confirm' => null]]);
        }
        // 3. Cotation client.
        if ($cotation) {
            $cUrl = '/index.php?r=cotations/' . (int) $cotation['id'];
            switch ($cotation['statut']) {
                case 'brouillon':
                    return self::res('Envoyer la cotation au client', 'La cotation ' . $cotation['reference'] . ' est prête en brouillon : vérifiez-la puis envoyez-la au client.', 'Ouvrir la cotation', $cUrl, $cpt);
                case 'envoyee':
                    return self::res('Attendre la réponse du client', 'La cotation ' . $cotation['reference'] . ' est envoyée. Dès que le client répond, enregistrez sa décision (acceptée ou refusée).', 'Enregistrer la décision', $cUrl, $cpt, 'attente');
                case 'acceptee':
                    return self::res('Créer la commande', 'Le client a accepté la cotation ' . $cotation['reference'] . '. Créez la commande pour démarrer le suivi (paiement, expédition, livraison).', 'Créer la commande', null, $cpt, 'info', ['url' => $base . '/commande', 'champs' => ['cotation_id' => (int) $cotation['id']], 'confirm' => null]);
                case 'refusee':
                    return self::res('Réviser la cotation', 'Le client a refusé la cotation ' . $cotation['reference'] . '. Créez une nouvelle version (nouvelle marge ou nouvelles conditions).', 'Nouvelle version', $base . '/cotations/nouvelle&version_de=' . (int) $cotation['id'], $cpt, 'alerte');
            }
        }
        // 4. Offre retenue : cotation à créer.
        if ($retenue) {
            return self::res('Créer la cotation client', 'L\'offre de ' . $retenue['fournisseur_nom'] . ' est retenue. La cotation reprend ses articles et son prix d\'achat : saisissez la marge.', 'Créer la cotation', $base . '/cotations/nouvelle', $cpt);
        }
        // 5. Sourcing.
        if ($aEnvoyer) {
            $c = $aEnvoyer[0];
            $autres = count($aEnvoyer) - 1;
            return self::res('Envoyer la consultation à ' . $c['fournisseur_nom'], 'Le message est prêt (articles, quantités, conditions demandées). Choisissez le contact et le canal, relisez, puis envoyez.' . ($autres > 0 ? ' ' . $autres . ' autre' . ($autres > 1 ? 's' : '') . ' consultation' . ($autres > 1 ? 's' : '') . ' à envoyer ensuite.' : ''), 'Envoyer', '/index.php?r=consultations/' . (int) $c['id'] . '/partages/nouvelle', $cpt, 'info', null,
                ['bouton' => 'Toutes les consultations', 'url' => $base . '&onglet=achats']);
        }
        if ($offresEnCours) {
            $nb = count($offresEnCours);
            return self::res('Comparer les offres et retenir la meilleure', $nb . ' offre' . ($nb > 1 ? 's' : '') . ' reçue' . ($nb > 1 ? 's' : '') . '. Comparez les prix, délais et conditions, puis retenez celle qui convient.', 'Ouvrir le comparateur', $base . '/comparateur', $cpt, 'info', null,
                $envoyees && $avecOffre < count($envoyees) ? ['bouton' => 'Saisir une autre offre', 'url' => $base . '&onglet=achats'] : null);
        }
        if ($envoyees) {
            $c = $envoyees[0];
            return self::res('Attendre les réponses des fournisseurs', 'Les consultations sont envoyées. Quand un fournisseur répond, saisissez son offre (vous pouvez joindre son fichier ou le faire lire par l\'IA).', 'Saisir une offre reçue', '/index.php?r=consultations/' . (int) $c['id'] . '/offres/nouvelle', $cpt, 'attente', null,
                ['bouton' => 'Voir les consultations', 'url' => $base . '&onglet=achats']);
        }
        return self::res('Choisir les fournisseurs à consulter', 'Sélectionnez les fournisseurs et les articles à leur demander : Suivora prépare le message et le récapitulatif.', 'Nouvelle consultation', $base . '/consultations/nouvelle', $cpt, 'info', null,
            ['bouton' => 'J\'ai déjà un prix (saisir une offre)', 'url' => $base . '/offres/manuelle']);
    }

    private static function res(string $titre, string $texte, ?string $bouton, ?string $url, array $compteurs, string $ton = 'info', ?array $post = null, ?array $secondaire = null): array
    {
        return ['titre' => $titre, 'texte' => $texte, 'bouton' => $bouton, 'url' => $url, 'post' => $post, 'secondaire' => $secondaire, 'compteurs' => $compteurs, 'ton' => $ton];
    }
}
