<?php

use App\Controllers\AuthController;
use App\Controllers\BonCommandeFournisseurController;
use App\Controllers\ClientController;
use App\Controllers\PortailClientController;
use App\Controllers\CommandeController;
use App\Controllers\ComparateurController;
use App\Controllers\ConsultationController;
use App\Controllers\CotationController;
use App\Controllers\DashboardController;
use App\Controllers\DemandeController;
use App\Controllers\DossierController;
use App\Controllers\FactureController;
use App\Controllers\FilialeController;
use App\Controllers\FournisseurController;
use App\Controllers\ListesController;
use App\Controllers\NotificationController;
use App\Controllers\OffreController;
use App\Controllers\ParametresController;
use App\Controllers\SecuriteController;
use App\Controllers\PartagePublicController;
use App\Controllers\PilotageController;
use App\Controllers\SimulateurController;
use App\Controllers\SuivoraAdminController;
use App\Controllers\UtilisateurController;
use App\Core\Auth;
use App\Core\Env;
use App\Core\Router;

require __DIR__ . '/../app/autoload.php';

Env::load(__DIR__ . '/../.env');
error_reporting(E_ALL);
ini_set('display_errors', Env::bool('APP_DEBUG', false) ? '1' : '0');

Auth::start();

$router = new Router();

// Authentification
$router->get('/login', fn() => (new AuthController())->showLogin());
$router->post('/login', fn() => (new AuthController())->login());
$router->post('/logout', fn() => (new AuthController())->logout());
$router->get('/mon-mot-de-passe', function () {
    Auth::requireLogin();
    (new AuthController())->showChangePassword();
});
$router->post('/mon-mot-de-passe', function () {
    Auth::requireLogin();
    (new AuthController())->changePassword();
});

// Filiale active globale (sélecteur de la barre du haut)
$router->post('/filiale-active', function () {
    Auth::requireLogin();
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        header('Location: /index.php');
        exit;
    }
    $user = Auth::user();
    $id = (int) ($_POST['filiale_id'] ?? 0);
    if (!\App\Models\Filiale::definirActive($user, $id)) {
        \App\Core\View::flash('erreur', 'Filiale inaccessible.');
    }
    // Retour sur le même module (route simple uniquement, sans filtre ni identifiant).
    $retour = (string) ($_POST['retour'] ?? '');
    $retour = preg_match('#^[a-z0-9\-/]{0,60}$#', $retour) && !preg_match('#/\d+#', $retour) ? $retour : '';
    header('Location: /index.php' . ($retour !== '' ? '?r=' . $retour : ''));
    exit;
});

// Tableau de bord
$router->get('/', function () {
    Auth::requireLogin();
    (new DashboardController())->index();
});

// Demandes
$router->get('/demandes', function () {
    Auth::requireLogin();
    (new DemandeController())->index();
});
$router->get('/demandes/nouvelle', function () {
    Auth::requireLogin();
    (new DemandeController())->create();
});
$router->get('/demandes/export.csv', function () {
    Auth::requireLogin();
    (new DemandeController())->exportCsv();
});
$router->get('/demandes/importer', function () {
    Auth::requireLogin();
    (new DemandeController())->importForm();
});
$router->post('/demandes/importer', function () {
    Auth::requireLogin();
    (new DemandeController())->importStore();
});
$router->get('/demandes/importer/modele.csv', function () {
    Auth::requireLogin();
    (new DemandeController())->importModele();
});
$router->post('/demandes', function () {
    Auth::requireLogin();
    (new DemandeController())->store();
});
$router->get('/demandes/{id}', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->show($params);
});
$router->get('/demandes/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->edit($params);
});
$router->post('/demandes/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->update($params);
});
$router->post('/demandes/{id}/supprimer', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->supprimer($params);
});
$router->post('/demandes/{id}/archiver', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->archiver($params);
});
$router->post('/demandes/{id}/desarchiver', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->desarchiver($params);
});
$router->post('/demandes/{id}/articles', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->ajouterArticle($params);
});
$router->post('/demandes/{id}/articles/{articleId}/modifier', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->modifierArticle($params);
});
$router->post('/demandes/{id}/articles/{articleId}/supprimer', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->supprimerArticle($params);
});
$router->get('/demandes/{id}/qualifier', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->qualifierForm($params);
});
$router->post('/demandes/{id}/qualifier/nouvelle', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->qualifierNouvelle($params);
});
$router->post('/demandes/{id}/qualifier/complement', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->qualifierComplement($params);
});
$router->post('/demandes/{id}/qualifier/reprise', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->qualifierReprise($params);
});
$router->post('/demandes/{id}/creer-dossier', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->creerDossier($params);
});
$router->post('/demandes/{id}/rejeter', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->rejeter($params);
});
$router->post('/demandes/{id}/pieces', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->uploadPiece($params);
});
$router->get('/demandes/{id}/pieces/{pieceId}/telecharger', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->telechargerPiece($params);
});
$router->post('/demandes/{id}/pieces/{pieceId}/supprimer', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->supprimerPiece($params);
});
$router->post('/demandes/{id}/extraction-ia', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->extraireIa($params);
});
$router->post('/demandes/{id}/extraction-ia/confirmer', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->confirmerExtractionIa($params);
});

// Clients
$router->get('/clients', function () {
    Auth::requireLogin();
    (new ClientController())->index();
});
$router->get('/clients/nouveau', function () {
    Auth::requireLogin();
    (new ClientController())->create();
});
$router->post('/clients', function () {
    Auth::requireLogin();
    (new ClientController())->store();
});
$router->post('/clients/creation-rapide', function () {
    Auth::requireLogin();
    (new ClientController())->creationRapide();
});
$router->get('/clients/export.csv', function () {
    Auth::requireLogin();
    (new ClientController())->exportCsv();
});
$router->get('/clients/{id}', function ($params) {
    Auth::requireLogin();
    (new ClientController())->show($params);
});
$router->get('/clients/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new ClientController())->edit($params);
});
$router->post('/clients/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new ClientController())->update($params);
});
$router->post('/clients/{id}/desactiver', function ($params) {
    Auth::requireLogin();
    (new ClientController())->desactiver($params);
});
$router->post('/clients/{id}/activer', function ($params) {
    Auth::requireLogin();
    (new ClientController())->activer($params);
});

// Espace client externe (07/10) — PUBLIC : accès par lien privé, aucune connexion
$router->get('/espace-client/{token}', fn($params) => (new PortailClientController())->show($params));
$router->get('/espace-client/{token}/cotations/{id}', fn($params) => (new PortailClientController())->cotation($params));
$router->post('/espace-client/{token}/cotations/{id}/decision', fn($params) => (new PortailClientController())->decision($params));
$router->get('/espace-client/{token}/factures/{id}', fn($params) => (new PortailClientController())->facture($params));
$router->post('/espace-client/{token}/demande', fn($params) => (new PortailClientController())->nouvelleDemande($params));

// Gestion des liens depuis la fiche client (personnel connecté)
$router->post('/clients/{id}/portail', function ($params) {
    Auth::requireLogin();
    (new ClientController())->creerLienPortail($params);
});
$router->post('/clients/{id}/portail/{lienId}/revoquer', function ($params) {
    Auth::requireLogin();
    (new ClientController())->revoquerLienPortail($params);
});

// Fiche client : contacts, adresses, documents (07/10)
$router->post('/clients/{id}/contacts', function ($params) {
    Auth::requireLogin();
    (new ClientController())->enregistrerContact($params);
});
foreach (['principal', 'desactiver', 'reactiver'] as $actionContact) {
    $router->post('/clients/{id}/contacts/{contactId}/' . $actionContact, function ($params) use ($actionContact) {
        Auth::requireLogin();
        $params['action'] = $actionContact;
        (new ClientController())->actionContact($params);
    });
}
$router->post('/clients/{id}/adresses', function ($params) {
    Auth::requireLogin();
    (new ClientController())->enregistrerAdresse($params);
});
foreach (['defaut', 'desactiver', 'reactiver'] as $actionAdresse) {
    $router->post('/clients/{id}/adresses/{adresseId}/' . $actionAdresse, function ($params) use ($actionAdresse) {
        Auth::requireLogin();
        $params['action'] = $actionAdresse;
        (new ClientController())->actionAdresse($params);
    });
}
$router->post('/clients/{id}/pieces', function ($params) {
    Auth::requireLogin();
    (new ClientController())->uploadPiece($params);
});
$router->get('/clients/{id}/pieces/{pieceId}/telecharger', function ($params) {
    Auth::requireLogin();
    (new ClientController())->telechargerPiece($params);
});
$router->post('/clients/{id}/pieces/{pieceId}/supprimer', function ($params) {
    Auth::requireLogin();
    (new ClientController())->supprimerPiece($params);
});

// Fournisseurs
$router->get('/fournisseurs', function () {
    Auth::requireLogin();
    (new FournisseurController())->index();
});
$router->get('/fournisseurs/nouveau', function () {
    Auth::requireLogin();
    (new FournisseurController())->create();
});
$router->post('/fournisseurs', function () {
    Auth::requireLogin();
    (new FournisseurController())->store();
});
$router->post('/fournisseurs/creation-rapide', function () {
    Auth::requireLogin();
    (new FournisseurController())->creationRapide();
});
$router->get('/fournisseurs/export.csv', function () {
    Auth::requireLogin();
    (new FournisseurController())->exportCsv();
});
$router->get('/fournisseurs/{id}', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->show($params);
});
$router->get('/fournisseurs/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->edit($params);
});
$router->post('/fournisseurs/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->update($params);
});
$router->post('/fournisseurs/{id}/desactiver', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->desactiver($params);
});
$router->post('/fournisseurs/{id}/activer', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->activer($params);
});
// Fiche fournisseur : contacts, adresses, qualification, évaluations (07/10)
$router->post('/fournisseurs/{id}/contacts', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->enregistrerContact($params);
});
foreach (['principal', 'desactiver', 'reactiver'] as $actionContactF) {
    $router->post('/fournisseurs/{id}/contacts/{contactId}/' . $actionContactF, function ($params) use ($actionContactF) {
        Auth::requireLogin();
        $params['action'] = $actionContactF;
        (new FournisseurController())->actionContact($params);
    });
}
$router->post('/fournisseurs/{id}/adresses', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->enregistrerAdresse($params);
});
foreach (['principale', 'desactiver', 'reactiver'] as $actionAdresseF) {
    $router->post('/fournisseurs/{id}/adresses/{adresseId}/' . $actionAdresseF, function ($params) use ($actionAdresseF) {
        Auth::requireLogin();
        $params['action'] = $actionAdresseF;
        (new FournisseurController())->actionAdresse($params);
    });
}
$router->post('/fournisseurs/{id}/qualification', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->enregistrerQualification($params);
});
$router->post('/fournisseurs/{id}/evaluations', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->ajouterEvaluation($params);
});
$router->post('/fournisseurs/{id}/pieces', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->uploadPiece($params);
});
$router->get('/fournisseurs/{id}/pieces/{pieceId}/telecharger', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->telechargerPiece($params);
});
$router->post('/fournisseurs/{id}/pieces/{pieceId}/supprimer', function ($params) {
    Auth::requireLogin();
    (new FournisseurController())->supprimerPiece($params);
});

// Bons de commande fournisseur (07/10) — routes exactes avant les routes à paramètre
$router->get('/bons-commande', function () {
    Auth::requireLogin();
    (new BonCommandeFournisseurController())->index();
});
$router->get('/fournisseurs/{id}/bons-commande/nouveau', function ($params) {
    Auth::requireLogin();
    (new BonCommandeFournisseurController())->create($params);
});
$router->post('/fournisseurs/{id}/bons-commande', function ($params) {
    Auth::requireLogin();
    (new BonCommandeFournisseurController())->store($params);
});
$router->get('/bons-commande/{id}', function ($params) {
    Auth::requireLogin();
    (new BonCommandeFournisseurController())->show($params);
});
$router->get('/bons-commande/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new BonCommandeFournisseurController())->edit($params);
});
$router->post('/bons-commande/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new BonCommandeFournisseurController())->update($params);
});
$router->post('/bons-commande/{id}/statut', function ($params) {
    Auth::requireLogin();
    (new BonCommandeFournisseurController())->statut($params);
});
$router->get('/bons-commande/{id}/imprimable', function ($params) {
    Auth::requireLogin();
    (new BonCommandeFournisseurController())->imprimable($params);
});

// Dossiers
$router->get('/dossiers', function () {
    Auth::requireLogin();
    (new DossierController())->index();
});
$router->get('/dossiers/export.csv', function () {
    Auth::requireLogin();
    (new DossierController())->exportCsv();
});
$router->get('/dossiers/importer', function () {
    Auth::requireLogin();
    (new DossierController())->importForm();
});
$router->post('/dossiers/importer', function () {
    Auth::requireLogin();
    (new DossierController())->importStore();
});
$router->get('/dossiers/importer/modele.csv', function () {
    Auth::requireLogin();
    (new DossierController())->importModele();
});
$router->get('/dossiers/{id}', function ($params) {
    Auth::requireLogin();
    (new DossierController())->show($params);
});
$router->post('/dossiers/{id}/etape', function ($params) {
    Auth::requireLogin();
    (new DossierController())->updateEtape($params);
});
$router->post('/dossiers/{id}/annuler', function ($params) {
    Auth::requireLogin();
    (new DossierController())->annuler($params);
});
$router->post('/dossiers/{id}/reactiver', function ($params) {
    Auth::requireLogin();
    (new DossierController())->reactiver($params);
});
$router->post('/dossiers/{id}/type', function ($params) {
    Auth::requireLogin();
    (new DossierController())->changerType($params);
});
$router->post('/dossiers/{id}/prestation', function ($params) {
    Auth::requireLogin();
    (new DossierController())->updatePrestation($params);
});
$router->post('/dossiers/{id}/budget', function ($params) {
    Auth::requireLogin();
    (new DossierController())->updateBudget($params);
});
$router->post('/dossiers/{id}/articles', function ($params) {
    Auth::requireLogin();
    (new DossierController())->addArticle($params);
});
$router->post('/dossiers/{id}/notes', function ($params) {
    Auth::requireLogin();
    (new DossierController())->updateNotes($params);
});
$router->post('/dossiers/{id}/pieces', function ($params) {
    Auth::requireLogin();
    (new DossierController())->uploadPiece($params);
});
$router->get('/dossiers/{id}/pieces/{pieceId}/telecharger', function ($params) {
    Auth::requireLogin();
    (new DossierController())->telechargerPiece($params);
});
$router->post('/dossiers/{id}/pieces/{pieceId}/supprimer', function ($params) {
    Auth::requireLogin();
    (new DossierController())->supprimerPiece($params);
});
$router->post('/dossiers/{id}/pieces/{pieceId}/classer', function ($params) {
    Auth::requireLogin();
    (new DossierController())->classerPiece($params);
});
$router->post('/dossiers/{id}/collaborateurs', function ($params) {
    Auth::requireLogin();
    (new DossierController())->assignerCollaborateur($params);
});
$router->post('/dossiers/{id}/collaborateurs/{collaborateurId}/retirer', function ($params) {
    Auth::requireLogin();
    (new DossierController())->retirerCollaborateur($params);
});

// Consultations fournisseurs (sourcing)
$router->get('/dossiers/{id}/consultations/nouvelle', function ($params) {
    Auth::requireLogin();
    (new ConsultationController())->create($params);
});
$router->post('/dossiers/{id}/consultations', function ($params) {
    Auth::requireLogin();
    (new ConsultationController())->store($params);
});
$router->get('/consultations/{id}', function ($params) {
    Auth::requireLogin();
    (new ConsultationController())->show($params);
});
$router->post('/consultations/{id}/statut', function ($params) {
    Auth::requireLogin();
    (new ConsultationController())->updateStatut($params);
});

// Parcours "Demander une offre" — récapitulatif PDF/HTML + lien fournisseur + WhatsApp
$router->get('/consultations/{id}/partages/nouvelle', function ($params) {
    Auth::requireLogin();
    (new ConsultationController())->preparerPartage($params);
});
$router->post('/consultations/{id}/partages', function ($params) {
    Auth::requireLogin();
    (new ConsultationController())->creerPartage($params);
});
$router->get('/consultations/{id}/partages/{partageId}', function ($params) {
    Auth::requireLogin();
    (new ConsultationController())->afficherPartage($params);
});
$router->post('/consultations/{id}/partages/{partageId}/envoye', function ($params) {
    Auth::requireLogin();
    (new ConsultationController())->marquerPartageEnvoye($params);
});
$router->post('/consultations/{id}/partages/{partageId}/revoquer', function ($params) {
    Auth::requireLogin();
    (new ConsultationController())->revoquerPartage($params);
});

// Page publique (aucune authentification) consultée par le fournisseur via
// son lien sécurisé — token aléatoire, expirable, révocable.
$router->get('/partage-public/{token}', function ($params) {
    (new PartagePublicController())->show($params);
});
$router->get('/partage-public/{token}/pieces/{pieceId}', function ($params) {
    (new PartagePublicController())->telechargerPiece($params);
});

// Offres fournisseurs
$router->get('/consultations/{id}/offres/nouvelle', function ($params) {
    Auth::requireLogin();
    (new OffreController())->create($params);
});
$router->get('/dossiers/{id}/offres/manuelle', function ($params) {
    Auth::requireLogin();
    (new OffreController())->createManuelle($params);
});
$router->post('/dossiers/{id}/offres/manuelle', function ($params) {
    Auth::requireLogin();
    (new OffreController())->storeManuelle($params);
});
$router->post('/consultations/{id}/offres', function ($params) {
    Auth::requireLogin();
    (new OffreController())->store($params);
});

// Listes transverses du menu (Offres, Cotations, Commandes, Factures, Comparateur)
$router->get('/offres/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new OffreController())->edit($params);
});
$router->post('/offres/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new OffreController())->update($params);
});
$router->post('/offres/extraire-ia', function () {
    Auth::requireLogin();
    (new OffreController())->extraireIa([]);
});
$router->get('/offres', function () {
    Auth::requireLogin();
    (new ListesController())->offres();
});
$router->get('/cotations', function () {
    Auth::requireLogin();
    (new ListesController())->cotations();
});
$router->get('/commandes', function () {
    Auth::requireLogin();
    (new ListesController())->commandes();
});
$router->get('/factures', function () {
    Auth::requireLogin();
    (new ListesController())->factures();
});
$router->get('/comparateur', function () {
    Auth::requireLogin();
    (new ListesController())->comparateur();
});

// Comparateur d'offres
$router->get('/dossiers/{id}/comparateur', function ($params) {
    Auth::requireLogin();
    (new ComparateurController())->index($params);
});
$router->post('/dossiers/{id}/comparateur/retenir', function ($params) {
    Auth::requireLogin();
    (new ComparateurController())->retenir($params);
});
$router->post('/dossiers/{id}/comparateur/revenir', function ($params) {
    Auth::requireLogin();
    (new ComparateurController())->revenir($params);
});

// Cotations client
$router->get('/dossiers/{id}/cotations/nouvelle', function ($params) {
    Auth::requireLogin();
    (new CotationController())->create($params);
});
$router->post('/dossiers/{id}/cotations', function ($params) {
    Auth::requireLogin();
    (new CotationController())->store($params);
});
$router->get('/cotations/{id}', function ($params) {
    Auth::requireLogin();
    (new CotationController())->show($params);
});
$router->post('/cotations/{id}/statut', function ($params) {
    Auth::requireLogin();
    (new CotationController())->updateStatut($params);
});

// Commande (suivi opérationnel)
$router->post('/dossiers/{id}/commande', function ($params) {
    Auth::requireLogin();
    (new CommandeController())->store($params);
});
$router->get('/dossiers/{id}/commande', function ($params) {
    Auth::requireLogin();
    (new CommandeController())->show($params);
});
$router->post('/commandes/{id}/etapes/{stepId}', function ($params) {
    Auth::requireLogin();
    (new CommandeController())->updateStep($params);
});
$router->post('/commandes/{id}/suivi', function ($params) {
    Auth::requireLogin();
    (new CommandeController())->updateSuivi($params);
});
$router->post('/commandes/{id}/logistique', function ($params) {
    Auth::requireLogin();
    (new CommandeController())->updateLogistique($params);
});
$router->post('/commandes/{id}/livrables', function ($params) {
    Auth::requireLogin();
    (new CommandeController())->updateLivrables($params);
});

// Factures
$router->get('/dossiers/{id}/factures/nouvelle', function ($params) {
    Auth::requireLogin();
    (new FactureController())->create($params);
});
$router->post('/dossiers/{id}/factures', function ($params) {
    Auth::requireLogin();
    (new FactureController())->store($params);
});
$router->post('/factures/{id}/statut', function ($params) {
    Auth::requireLogin();
    (new FactureController())->updateStatut($params);
});

// Sécurité — journal d'audit (Propriétaire / Admin d'organisation)
$router->get('/securite', function () {
    Auth::requireLogin();
    (new SecuriteController())->index();
});
$router->get('/securite/export', function () {
    Auth::requireLogin();
    (new SecuriteController())->exportCsv();
});
$router->get('/securite/imprimable', function () {
    Auth::requireLogin();
    (new SecuriteController())->imprimable();
});

// Administration Suivora (compte is_super_admin uniquement — au-dessus des entreprises clientes)
$router->get('/admin-suivora', function () {
    Auth::requireLogin();
    (new SuivoraAdminController())->index();
});
$router->post('/admin-suivora', function () {
    Auth::requireLogin();
    (new SuivoraAdminController())->store();
});
$router->post('/admin-suivora/{id}/basculer', function ($params) {
    Auth::requireLogin();
    (new SuivoraAdminController())->basculer($params);
});
$router->post('/admin-suivora/{id}/abonnement', function ($params) {
    Auth::requireLogin();
    (new SuivoraAdminController())->abonnement($params);
});

// Filiales (Propriétaire / Admin d'organisation)
$router->get('/filiales', function () {
    Auth::requireLogin();
    (new FilialeController())->index();
});
$router->post('/filiales', function () {
    Auth::requireLogin();
    (new FilialeController())->store();
});
$router->post('/filiales/{id}/renommer', function ($params) {
    Auth::requireLogin();
    (new FilialeController())->renommer($params);
});
$router->post('/filiales/{id}/supprimer', function ($params) {
    Auth::requireLogin();
    (new FilialeController())->supprimer($params);
});

// Utilisateurs et gestion des accès (Propriétaire / Admin d'organisation)
$router->get('/utilisateurs', function () {
    Auth::requireLogin();
    (new UtilisateurController())->index();
});
$router->post('/utilisateurs', function () {
    Auth::requireLogin();
    (new UtilisateurController())->store();
});
$router->post('/utilisateurs/{id}/acces', function ($params) {
    Auth::requireLogin();
    (new UtilisateurController())->updateAcces($params);
});
$router->post('/utilisateurs/{id}/role', function ($params) {
    Auth::requireLogin();
    (new UtilisateurController())->updateRole($params);
});
$router->post('/utilisateurs/{id}/modifier', function ($params) {
    Auth::requireLogin();
    (new UtilisateurController())->update($params);
});
$router->post('/utilisateurs/{id}/desactiver', function ($params) {
    Auth::requireLogin();
    (new UtilisateurController())->desactiver($params);
});
$router->post('/utilisateurs/{id}/activer', function ($params) {
    Auth::requireLogin();
    (new UtilisateurController())->activer($params);
});
$router->post('/utilisateurs/{id}/mot-de-passe', function ($params) {
    Auth::requireLogin();
    (new UtilisateurController())->resetPassword($params);
});

// Paramètres de calcul (Propriétaire / Admin d'organisation / Finance)
$router->get('/parametres', function () {
    Auth::requireLogin();
    (new ParametresController())->index();
});
$router->post('/parametres', function () {
    Auth::requireLogin();
    (new ParametresController())->update();
});

// Simulateur de prix
$router->get('/simulateur', function () {
    Auth::requireLogin();
    (new SimulateurController())->index();
});

// Pilotage (analyse par période/activité/responsable — Propriétaire / Admin d'organisation / Finance)
$router->get('/pilotage', function () {
    Auth::requireLogin();
    (new PilotageController())->index();
});
$router->get('/pilotage/detail', function () {
    Auth::requireLogin();
    (new PilotageController())->detail();
});
$router->get('/pilotage/export.csv', function () {
    Auth::requireLogin();
    (new PilotageController())->exportCsv();
});
$router->get('/pilotage/export.pdf', function () {
    Auth::requireLogin();
    (new PilotageController())->exportPdf();
});

// Notifications internes (cloche)
$router->get('/notifications', function () {
    Auth::requireLogin();
    (new NotificationController())->index();
});
$router->post('/notifications/{id}/lue', function ($params) {
    Auth::requireLogin();
    (new NotificationController())->marquerLue($params);
});
$router->post('/notifications/tout-marquer-lu', function () {
    Auth::requireLogin();
    (new NotificationController())->marquerToutesLues();
});

$routeParam = $_GET['r'] ?? '/';
$routePath = $routeParam === '' ? '/' : '/' . ltrim($routeParam, '/');
$router->dispatch($_SERVER['REQUEST_METHOD'], $routePath);
