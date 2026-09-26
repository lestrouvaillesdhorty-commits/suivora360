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
use App\Controllers\OffreController;
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

// Filiales (dirigeant uniquement)
$router->get('/filiales', function () {
    Auth::requireLogin();
    (new FilialeController())->index();
});
$router->post('/filiales', function () {
    Auth::requireLogin();
    (new FilialeController())->store();
});

// Utilisateurs et gestion des accès (dirigeant uniquement)
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

$routeParam = $_GET['r'] ?? '/';
$routePath = $routeParam === '' ? '/' : '/' . ltrim($routeParam, '/');
$router->dispatch($_SERVER['REQUEST_METHOD'], $routePath);
