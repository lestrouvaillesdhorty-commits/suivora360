<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Filiale;
use App\Models\Organisation;
use App\Models\Utilisateur;

/**
 * Espace « Administrateur Suivora » — au-dessus de toutes les entreprises
 * clientes (organisations). Réservé aux comptes marqués is_super_admin
 * (voir migrate_v21.php).
 *
 * Périmètre volontairement étroit (décision de conception du 29/09) :
 *  - voir la liste des entreprises avec des compteurs d'ensemble (jamais le
 *    contenu métier : demandes, clients, montants...) ;
 *  - créer une nouvelle entreprise cliente avec sa première filiale et son
 *    premier compte Propriétaire (onboarding « concierge », pas
 *    d'inscription publique) ;
 *  - suspendre / réactiver l'accès d'une entreprise (impayé, résiliation).
 * Pas d'« usurpation » de compte : le support se fait avec l'entreprise, pas à
 * sa place.
 */
class SuivoraAdminController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('suivora_admin/index', [
            'organisations' => Organisation::toutesAvecStatistiques(),
            'offres' => Organisation::OFFRES,
            'devisesAbonnement' => Organisation::DEVISES_ABONNEMENT,
            'csrfToken' => Auth::csrfToken(),
            'monOrganisationId' => (int) Auth::user()['organisation_id'],
        ]);
    }

    public function store(): void
    {
        Auth::requireSuperAdmin();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=admin-suivora');
            exit;
        }

        $nomOrg = trim($_POST['nom_organisation'] ?? '');
        $nomFiliale = trim($_POST['nom_filiale'] ?? '');
        $nomProprio = trim($_POST['nom_proprietaire'] ?? '');
        $email = strtolower(trim($_POST['email_proprietaire'] ?? ''));
        $mdp = (string) ($_POST['mot_de_passe'] ?? '');

        $erreur = null;
        if ($nomOrg === '' || $nomFiliale === '' || $nomProprio === '') {
            $erreur = "Le nom de l'entreprise, le nom de sa première filiale et le nom du Propriétaire sont obligatoires.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur = "L'adresse e-mail du Propriétaire n'est pas valide.";
        } elseif (strlen($mdp) < 8) {
            $erreur = 'Le mot de passe provisoire doit contenir au moins 8 caractères.';
        } elseif (Utilisateur::findByEmail($email)) {
            $erreur = 'Cette adresse e-mail est déjà utilisée par un autre compte.';
        }
        if ($erreur) {
            View::flash('erreur', $erreur);
            header('Location: /index.php?r=admin-suivora');
            exit;
        }

        try {
            $ids = Organisation::creerAvecProprietaire($nomOrg, $nomFiliale, $nomProprio, $email, $mdp);
        } catch (\Throwable $e) {
            View::flash('erreur', "La création a échoué : l'entreprise n'a pas été créée.");
            header('Location: /index.php?r=admin-suivora');
            exit;
        }

        $acteur = Auth::user();
        // Tracé dans le journal de la nouvelle entreprise : son Propriétaire voit qui a créé son espace.
        AuditLog::log($ids['filiale_id'], (int) $acteur['id'], 'creation_organisation', 'organisation', $ids['organisation_id'], $nomOrg);
        View::flash('succes', "Entreprise « $nomOrg » créée. Transmettez au Propriétaire son adresse e-mail et son mot de passe provisoire par un canal sûr. Il devra le changer dès sa première connexion.");
        header('Location: /index.php?r=admin-suivora');
        exit;
    }

    /** Suivi manuel de l'abonnement d'une entreprise : offre, prix mensuel, échéance, note. */
    public function abonnement(array $params): void
    {
        Auth::requireSuperAdmin();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=admin-suivora');
            exit;
        }
        $org = Organisation::find((int) $params['id']);
        if (!$org) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $offre = (string) ($_POST['offre'] ?? '');
        $prixBrut = trim(str_replace([' ', ','], ['', '.'], (string) ($_POST['prix'] ?? '')));
        $devise = (string) ($_POST['devise'] ?? 'XAF');
        $echeance = trim((string) ($_POST['echeance'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        $erreur = null;
        if ($offre !== '' && !isset(Organisation::OFFRES[$offre])) {
            $erreur = 'Offre inconnue.';
        } elseif ($prixBrut !== '' && (!is_numeric($prixBrut) || (float) $prixBrut < 0)) {
            $erreur = 'Le prix mensuel doit être un nombre positif.';
        } elseif (!isset(Organisation::DEVISES_ABONNEMENT[$devise])) {
            $erreur = 'Devise inconnue.';
        } elseif ($echeance !== '' && !(($d = \DateTime::createFromFormat('Y-m-d', $echeance)) && $d->format('Y-m-d') === $echeance)) {
            $erreur = "La date d'échéance n'est pas valide.";
        } elseif (mb_strlen($notes) > 255) {
            $erreur = 'La note est limitée à 255 caractères.';
        }
        if ($erreur) {
            View::flash('erreur', $erreur);
            header('Location: /index.php?r=admin-suivora');
            exit;
        }

        try {
            Organisation::definirAbonnement(
                (int) $org['id'],
                $offre !== '' ? $offre : null,
                $prixBrut !== '' ? (float) $prixBrut : null,
                $devise,
                $echeance !== '' ? $echeance : null,
                $notes !== '' ? $notes : null
            );
        } catch (\Throwable $e) {
            View::flash('erreur', "Enregistrement impossible : la migration v21 n'a pas encore été lancée sur ce site.");
            header('Location: /index.php?r=admin-suivora');
            exit;
        }

        $acteur = Auth::user();
        $filiales = Filiale::allForOrganisation((int) $org['id']);
        if (!empty($filiales)) {
            AuditLog::log((int) $filiales[0]['id'], (int) $acteur['id'], 'modification_abonnement', 'organisation', (int) $org['id'],
                (Organisation::OFFRES[$offre] ?? 'sans offre') . ($echeance !== '' ? ' — échéance ' . $echeance : ''));
        }
        View::flash('succes', "Abonnement de « {$org['nom']} » enregistré.");
        header('Location: /index.php?r=admin-suivora');
        exit;
    }

    public function basculer(array $params): void
    {
        Auth::requireSuperAdmin();
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=admin-suivora');
            exit;
        }
        $acteur = Auth::user();
        $org = Organisation::find((int) $params['id']);
        if (!$org) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        if ((int) $org['id'] === (int) $acteur['organisation_id']) {
            View::flash('erreur', "Vous ne pouvez pas suspendre l'entreprise à laquelle votre propre compte est rattaché.");
            header('Location: /index.php?r=admin-suivora');
            exit;
        }

        $actuellementActive = Organisation::estActive((int) $org['id']);
        Organisation::definirActive((int) $org['id'], !$actuellementActive);

        $filiales = Filiale::allForOrganisation((int) $org['id']);
        if (!empty($filiales)) {
            AuditLog::log(
                (int) $filiales[0]['id'],
                (int) $acteur['id'],
                $actuellementActive ? 'suspension_organisation' : 'reactivation_organisation',
                'organisation',
                (int) $org['id'],
                $org['nom']
            );
        }
        View::flash('succes', $actuellementActive
            ? "L'accès de « {$org['nom']} » est suspendu : ses utilisateurs ne peuvent plus se connecter."
            : "L'accès de « {$org['nom']} » est rétabli.");
        header('Location: /index.php?r=admin-suivora');
        exit;
    }
}
