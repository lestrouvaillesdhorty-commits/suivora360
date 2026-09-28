<?php

use App\Controllers\AuthController;
use App\Controllers\ClientController;
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
use App\Controllers\NotificationController;
use App\Controllers\OffreController;
use App\Controllers\ParametresController;
use App\Controllers\PartagePublicController;
use App\Controllers\PilotageController;
use App\Controllers\SimulateurController;
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
$router->post('/demandes', function () {
    Auth::requireLogin();
    (new DemandeController())->store();
});
$router->get('/demandes/{id}', function ($params) {
    Auth::requireLogin();
    (new DemandeController())->show($params);
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

// Dossiers
$router->get('/dossiers', function () {
    Auth::requireLogin();
    (new DossierController())->index();
});
$router->get('/dossiers/{id}', function ($params) {
    Auth::requireLogin();
    (new DossierController())->show($params);
});
$router->post('/dossiers/{id}/etape', function ($params) {
    Auth::requireLogin();
    (new DossierController())->updateEtape($params);
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
$router->post('/consultations/{id}/offres', function ($params) {
    Auth::requireLogin();
    (new OffreController())->store($params);
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

// Filiales (Propriétaire / Admin d'organisation)
$router->get('/filiales', function () {
    Auth::requireLogin();
    (new FilialeController())->index();
});
$router->post('/filiales', function () {
    Auth::requireLogin();
    (new FilialeController())->store();
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
