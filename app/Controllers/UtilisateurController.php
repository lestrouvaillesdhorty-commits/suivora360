<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Permissions;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Filiale;
use App\Models\Utilisateur;

// [corrigé 03/10] store() et updateAcces() passaient `filiale_ids` tel que
// soumis à Utilisateur::setFiliales() sans jamais vérifier qu'il s'agit bien
// de filiales de l'organisation de l'admin qui agit — voir
// Filiale::filterIdsForOrganisation() et le correctif sur Filiale::visibleFor().

/**
 * Gestion des utilisateurs et de leurs accès aux filiales — réservée aux
 * rôles Propriétaire et Admin d'organisation.
 */
class UtilisateurController
{
    /** Page « Accès aux modules » : grille rôle × module (Complet / Lecture / Caché) et exceptions par personne. */
    public function accesModules(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();
        $orgId = (int) $user['organisation_id'];
        $dispo = \App\Core\AccesModules::disponible();
        $cles = \App\Models\RolePerso::clesReglables($orgId);
        $niveaux = [];
        if ($dispo) {
            foreach (\App\Core\AccesModules::GROUPES as $mods) {
                foreach (array_keys($mods) as $mod) {
                    foreach ($cles as $role) {
                        $niveaux[$mod][$role] = \App\Core\AccesModules::niveauRole($orgId, $role, $mod);
                    }
                }
            }
        }
        $perms = [];
        if ($dispo) {
            foreach (array_keys(\App\Core\AccesModules::PERMISSIONS) as $code) {
                foreach ($cles as $role) {
                    $perms[$code][$role] = \App\Core\AccesModules::permissionRole($orgId, $role, $code);
                }
            }
        }
        $libelles = [];
        foreach ($cles as $c) {
            $libelles[$c] = \App\Models\RolePerso::libelleCle($c);
        }
        // Activités visibles : réglage réservé au Propriétaire de l'entreprise.
        $activites = array_values(array_filter(\App\Models\Activite::toutes($orgId), fn ($a) => $a['actif']));
        $actNiveaux = [];
        $actPersonnes = [];
        if ($dispo && count($activites) > 1) {
            foreach ($cles as $role) {
                $actNiveaux[$role] = \App\Models\ActiviteAcces::niveauxRole($orgId, $role);
            }
        }
        $utilisateursReglables = array_values(array_filter(Utilisateur::allForOrganisation($orgId), fn($u) => !in_array($u['role'], ['proprietaire', 'admin_organisation'], true) && empty($u['is_super_admin'])));
        if ($dispo && count($activites) > 1) {
            foreach ($utilisateursReglables as $u) {
                $actPersonnes[(int) $u['id']] = \App\Models\ActiviteAcces::listePersonne((int) $u['id'], $orgId);
            }
        }
        View::render('users/acces_modules', [
            'activites' => $activites,
            'actNiveaux' => $actNiveaux,
            'actPersonnes' => $actPersonnes,
            'estProprietaire' => $user['role'] === 'proprietaire',
            'cles' => $cles,
            'libelles' => $libelles,
            'rolesPerso' => \App\Models\RolePerso::allForOrganisation($orgId),
            'rolePersoDispo' => \App\Models\RolePerso::disponible(),
            'perms' => $perms,
            'dispo' => $dispo,
            'niveaux' => $niveaux,
            'exceptions' => $dispo ? \App\Core\AccesModules::exceptionsOrganisation($orgId) : [],
            'utilisateurs' => array_values(array_filter(Utilisateur::allForOrganisation($orgId), fn($u) => !in_array($u['role'], ['proprietaire', 'admin_organisation'], true) && empty($u['is_super_admin']))),
        ]);
    }

    public function accesModulesUpdate(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();
        $orgId = (int) $user['organisation_id'];
        $retour = '/index.php?r=utilisateurs/acces-modules';
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null) || !\App\Core\AccesModules::disponible()) {
            View::flash('erreur', 'Session expirée ou migrate_v52.php non lancée.');
            header('Location: ' . $retour);
            exit;
        }
        switch ($_POST['action'] ?? '') {
            case 'grille':
                foreach (\App\Core\AccesModules::GROUPES as $mods) {
                    foreach (array_keys($mods) as $mod) {
                        foreach (\App\Models\RolePerso::clesReglables($orgId) as $role) {
                            $n = (string) ($_POST['n'][$mod][$role] ?? '');
                            if ($n !== '') {
                                \App\Core\AccesModules::enregistrerRole($orgId, $role, $mod, $n);
                            }
                        }
                    }
                }
                foreach (array_keys(\App\Core\AccesModules::PERMISSIONS) as $code) {
                    foreach (\App\Models\RolePerso::clesReglables($orgId) as $role) {
                        if (\App\Models\RolePerso::baseDeCle($role) === 'lecture_seule') {
                            continue;
                        }
                        \App\Core\AccesModules::enregistrerPermission($orgId, $role, $code, !empty($_POST['p'][$code][$role]));
                    }
                }
                AuditLog::logAdmin($user, 'acces_modules_grille', 'organisation', $orgId, 'Grille mise à jour');
                View::flash('succes', 'Accès aux modules enregistrés.');
                break;
            case 'activites_grille':
            case 'activites_personne':
                if ($user['role'] !== 'proprietaire') {
                    View::flash('erreur', "Seul le Propriétaire de l'entreprise décide qui voit quelle activité.");
                    break;
                }
                if (($_POST['action'] ?? '') === 'activites_grille') {
                    foreach (\App\Models\RolePerso::clesReglables($orgId) as $role) {
                        foreach (\App\Models\Activite::toutes($orgId) as $a) {
                            $n = (string) ($_POST['a'][$role][md5($a['nom'])] ?? '');
                            if ($n !== '') {
                                \App\Models\ActiviteAcces::enregistrerRole($orgId, $role, $a['nom'], $n);
                            }
                        }
                    }
                    AuditLog::logAdmin($user, 'activites_visibles_roles', 'organisation', $orgId, 'Activités visibles par rôle mises à jour');
                    View::flash('succes', 'Activités visibles par rôle enregistrées.');
                } else {
                    $cible = (int) ($_POST['utilisateur_id'] ?? 0);
                    $niv = null;
                    if (($_POST['mode'] ?? '') === 'perso') {
                        $niv = [];
                        foreach (\App\Models\Activite::toutes($orgId) as $a) {
                            $niv[$a['nom']] = (string) ($_POST['a'][md5($a['nom'])] ?? 'complet');
                        }
                    }
                    $ok = \App\Models\ActiviteAcces::definirPersonne($orgId, $cible, $niv);
                    if ($ok) {
                        AuditLog::logAdmin($user, 'activites_visibles_personne', 'utilisateur', $cible, $niv === null ? 'Suit son rôle' : 'Liste propre');
                    }
                    View::flash($ok ? 'succes' : 'erreur', $ok ? 'Activités visibles enregistrées pour cette personne.' : 'Personne introuvable.');
                }
                break;
            case 'role_creer':
                $err = \App\Models\RolePerso::creer($orgId, (string) ($_POST['nom'] ?? ''), (string) ($_POST['base'] ?? ''), (string) ($_POST['copie'] ?? ''));
                if ($err) {
                    View::flash('erreur', $err);
                } else {
                    AuditLog::logAdmin($user, 'role_perso_cree', 'organisation', $orgId, trim((string) $_POST['nom']));
                    View::flash('succes', 'Rôle créé. Réglez ses accès dans la grille ci-dessous, puis attribuez-le dans Utilisateurs.');
                }
                break;
            case 'role_renommer':
                $err = \App\Models\RolePerso::renommer($orgId, (int) ($_POST['id'] ?? 0), (string) ($_POST['nom'] ?? ''));
                $err ? View::flash('erreur', $err) : View::flash('succes', 'Rôle renommé.');
                break;
            case 'role_supprimer':
                $err = \App\Models\RolePerso::supprimer($orgId, (int) ($_POST['id'] ?? 0));
                if (!$err) {
                    AuditLog::logAdmin($user, 'role_perso_supprime', 'organisation', $orgId, 'Rôle personnalisé supprimé');
                }
                $err ? View::flash('erreur', $err) : View::flash('succes', 'Rôle supprimé.');
                break;
            case 'prereglage':
                $nom = ($_POST['prereglage'] ?? '') === 'petite' ? 'petite' : 'structuree';
                \App\Core\AccesModules::appliquerPrereglage($orgId, $nom);
                AuditLog::logAdmin($user, 'acces_modules_prereglage', 'organisation', $orgId, $nom);
                View::flash('succes', $nom === 'petite' ? 'Préréglage « Petite équipe » appliqué : tout le monde fait tout. Ajustez ensuite case par case.' : 'Préréglage « Équipe structurée » appliqué (réglages de départ).');
                break;
            case 'reinit':
                \App\Core\AccesModules::reinitialiser($orgId);
                AuditLog::logAdmin($user, 'acces_modules_reinit', 'organisation', $orgId, 'Réglages de départ');
                View::flash('succes', 'Réglages de départ rétablis.');
                break;
            case 'exception_ajouter':
                $ok = \App\Core\AccesModules::ajouterException($orgId, (int) ($_POST['utilisateur_id'] ?? 0), (string) ($_POST['module'] ?? ''), (string) ($_POST['niveau'] ?? ''));
                View::flash($ok ? 'succes' : 'erreur', $ok ? 'Exception enregistrée.' : 'Exception impossible (choisissez une personne, un module et un niveau).');
                break;
            case 'exception_supprimer':
                \App\Core\AccesModules::supprimerException($orgId, (int) ($_POST['id'] ?? 0));
                View::flash('succes', 'Exception supprimée.');
                break;
        }
        header('Location: ' . $retour);
        exit;
    }

    public function index(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();
        $utilisateurs = Utilisateur::allForOrganisation((int) $user['organisation_id']);
        $filiales = Filiale::allForOrganisation((int) $user['organisation_id']);

        $accesParUtilisateur = [];
        foreach ($utilisateurs as $u) {
            $accesParUtilisateur[$u['id']] = Utilisateur::filialeIds((int) $u['id']);
        }

        View::render('users/index', [
            'utilisateurs' => $utilisateurs,
            'filiales' => $filiales,
            'accesParUtilisateur' => $accesParUtilisateur,
            'currentUserId' => (int) $user['id'],
            'rolesPerso' => \App\Models\RolePerso::allForOrganisation((int) $user['organisation_id']),
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $user = Auth::user();
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $role = $_POST['role'] ?? 'lecture_seule';
        $filialeIds = $_POST['filiale_ids'] ?? [];

        $res = \App\Models\RolePerso::resoudre((int) $user['organisation_id'], (string) $role) ?? ['lecture_seule', null];
        [$role, $rolePersoId] = $res;
        if (!Permissions::canAssignRole(Auth::role(), $role)) {
            View::flash('erreur', "Seul un Propriétaire peut attribuer le rôle Propriétaire.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        if ($nom === '' || $email === '' || strlen($motDePasse) < 8) {
            View::flash('erreur', 'Nom, email et mot de passe (8 caractères min.) sont obligatoires.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        if (Utilisateur::findByEmail($email)) {
            View::flash('erreur', 'Cet email est déjà utilisé.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $newId = Utilisateur::create([
            'organisation_id' => $user['organisation_id'],
            'nom' => $nom,
            'email' => $email,
            'mot_de_passe' => $motDePasse,
            'role' => $role,
            'role_perso_id' => $rolePersoId,
            'doit_changer_mdp' => 1,
        ]);

        if (!Permissions::seesAllFiliales($role)) {
            Utilisateur::setFiliales($newId, Filiale::filterIdsForOrganisation($filialeIds, (int) $user['organisation_id']));
        }

        AuditLog::logAdmin($user, 'creation_utilisateur', 'utilisateur', (int) $newId, $nom . ' — ' . ($rolePersoId ? \App\Models\RolePerso::libelleCle(\App\Models\RolePerso::cle($rolePersoId)) : Permissions::label($role)));
        View::flash('succes', 'Utilisateur créé. Il devra changer son mot de passe à sa première connexion.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    public function updateAcces(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        // [corrigé 03/10] Il manquait la vérification que l'utilisateur ciblé
        // appartient bien à l'organisation de l'admin qui agit (présente sur
        // update()/desactiver()/activer()/resetPassword(), oubliée ici) —
        // sans ça, un admin pouvait changer les accès filiale de n'importe
        // quel utilisateur, même d'une autre organisation, en devinant un id.
        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $filialeIds = $_POST['filiale_ids'] ?? [];
        Utilisateur::setFiliales((int) $cible['id'], Filiale::filterIdsForOrganisation($filialeIds, (int) $user['organisation_id']));

        AuditLog::logAdmin($user, 'acces_filiales_utilisateur', 'utilisateur', (int) $cible['id'], $cible['nom']);
        View::flash('succes', 'Accès mis à jour.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    /**
     * Changement de rôle d'un utilisateur existant — introduit avec les
     * rôles fins pour permettre de répartir les comptes "employé" migrés
     * automatiquement vers Commercial (voir migrate_v10.php) vers leur rôle
     * définitif, un par un et sans se presser.
     *
     * [corrigé 03/10] Deux trous trouvés en revoyant ce module :
     * 1. Isolation multi-organisations : contrairement à update()/
     *    desactiver()/activer()/resetPassword()/updateAcces() (déjà corrigée
     *    plus tôt le même jour), cette action ne vérifiait jamais que
     *    l'utilisateur ciblé appartient à l'organisation de l'admin qui agit
     *    — un admin pouvait changer le rôle (y compris attribuer ou retirer
     *    "Propriétaire") de n'importe quel utilisateur, même d'une autre
     *    entreprise cliente, en devinant un id. Même famille de bug que celui
     *    déjà corrigé sur updateAcces(), resté ici.
     * 2. Dernier Propriétaire actif : desactiver() empêche déjà de désactiver
     *    le dernier Propriétaire actif d'une organisation, mais rien
     *    n'empêchait de lui retirer le rôle Propriétaire par ce formulaire —
     *    ce qui revient au même résultat (plus personne pour administrer
     *    l'organisation) tout en étant pire : canAssignRole() exige déjà
     *    d'être Propriétaire pour attribuer ce rôle à quelqu'un, donc une
     *    fois le dernier retiré, plus personne ne peut jamais le redonner
     *    depuis cet écran. Corrigé en bloquant ce changement précis, même
     *    logique que desactiver().
     */
    public function updateRole(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $res = \App\Models\RolePerso::resoudre((int) $user['organisation_id'], (string) ($_POST['role'] ?? ''));
        if ($res === null) {
            View::flash('erreur', 'Rôle invalide.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }
        [$role, $rolePersoId] = $res;
        if (!Permissions::canAssignRole(Auth::role(), $role)) {
            View::flash('erreur', "Seul un Propriétaire peut attribuer le rôle Propriétaire.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        // [corrigé 03/10] canAssignRole() ne protège que la PROMOTION vers
        // Propriétaire (cible le rôle demandé), pas un compte Propriétaire
        // déjà existant : un Admin d'organisation pouvait rétrograder un
        // Propriétaire vers n'importe quel autre rôle (tant que ce n'était
        // pas le dernier actif, cf. check suivant). Trouvé en revoyant le
        // reste de l'écran après les deux corrections ci-dessus. Seul un
        // Propriétaire peut désormais toucher au rôle d'un autre Propriétaire.
        if ($cible['role'] === 'proprietaire' && Auth::role() !== 'proprietaire') {
            View::flash('erreur', "Seul un Propriétaire peut modifier le rôle d'un compte Propriétaire.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        if ($cible['role'] === 'proprietaire'
            && $role !== 'proprietaire'
            && Utilisateur::countProprietairesActifs((int) $user['organisation_id'], (int) $cible['id']) === 0
        ) {
            View::flash('erreur', "Impossible de retirer le rôle Propriétaire au dernier compte Propriétaire actif.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        Utilisateur::updateRole((int) $cible['id'], $role, $rolePersoId);
        if (Permissions::seesAllFiliales($role)) {
            Utilisateur::setFiliales((int) $cible['id'], []);
        }

        AuditLog::logAdmin($user, 'changement_role_utilisateur', 'utilisateur', (int) $cible['id'], $cible['nom'] . ' : ' . \App\Models\RolePerso::libelle($cible) . ' → ' . ($rolePersoId ? \App\Models\RolePerso::libelleCle(\App\Models\RolePerso::cle($rolePersoId)) : Permissions::label($role)));
        View::flash('succes', 'Rôle mis à jour.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    /**
     * Modification du nom / email d'un utilisateur existant.
     *
     * [corrigé 03/10] Même trou que sur updateRole()/resetPassword()/
     * desactiver() : un Admin d'organisation pouvait modifier le nom/email
     * d'un compte Propriétaire — combiné à resetPassword() (corrigé lui
     * aussi), ça permettait une prise de contrôle complète du compte.
     */
    public function update(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if ($cible['role'] === 'proprietaire' && Auth::role() !== 'proprietaire') {
            View::flash('erreur', "Seul un Propriétaire peut modifier un compte Propriétaire.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($nom === '' || $email === '') {
            View::flash('erreur', 'Nom et email sont obligatoires.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $existant = Utilisateur::findByEmail($email);
        if ($existant && (int) $existant['id'] !== (int) $cible['id']) {
            View::flash('erreur', 'Cet email est déjà utilisé par un autre compte.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        Utilisateur::updateInfo((int) $cible['id'], $nom, $email);

        AuditLog::logAdmin($user, 'modification_utilisateur', 'utilisateur', (int) $cible['id'], $cible['nom'] . ' → ' . $nom);
        View::flash('succes', 'Utilisateur modifié.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    /**
     * Désactivation d'un compte — bloque la connexion (voir Auth::attempt)
     * sans supprimer les données. On empêche de se désactiver soi-même et
     * de désactiver le dernier Propriétaire actif de l'organisation.
     */
    public function desactiver(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if ((int) $cible['id'] === (int) $user['id']) {
            View::flash('erreur', 'Vous ne pouvez pas désactiver votre propre compte.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        // [corrigé 03/10] Même trou que sur update()/updateRole()/
        // resetPassword() : un Admin d'organisation pouvait désactiver un
        // compte Propriétaire (sabotage), tant que ce n'était pas le
        // dernier actif (check suivant, inchangé).
        if ($cible['role'] === 'proprietaire' && Auth::role() !== 'proprietaire') {
            View::flash('erreur', "Seul un Propriétaire peut désactiver un compte Propriétaire.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        if ($cible['role'] === 'proprietaire'
            && Utilisateur::countProprietairesActifs((int) $user['organisation_id'], (int) $cible['id']) === 0
        ) {
            View::flash('erreur', "Impossible de désactiver le dernier compte Propriétaire actif.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        Utilisateur::setActive((int) $cible['id'], false);
        AuditLog::logAdmin($user, 'desactivation_utilisateur', 'utilisateur', (int) $cible['id'], $cible['nom']);
        View::flash('succes', 'Compte désactivé. Il ne peut plus se connecter mais reste visible et peut être réactivé.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    public function activer(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Utilisateur::setActive((int) $cible['id'], true);
        AuditLog::logAdmin($user, 'reactivation_utilisateur', 'utilisateur', (int) $cible['id'], $cible['nom']);
        View::flash('succes', 'Compte réactivé.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }

    /**
     * Réinitialisation du mot de passe par un admin/propriétaire — le mot
     * de passe existant n'est jamais affiché (il n'est même pas stocké en
     * clair), on ne peut qu'en définir un nouveau.
     *
     * [corrigé 03/10] La plus grave des quatre failles trouvées en revoyant
     * ce module : sans protection, un Admin d'organisation pouvait
     * réinitialiser le mot de passe d'un compte Propriétaire, se connecter
     * à sa place, et obtenir ainsi tous les droits Propriétaire — ce qui
     * contourne complètement canAssignRole() ("seul un Propriétaire attribue
     * le rôle Propriétaire"), puisque l'attaquant devient littéralement ce
     * Propriétaire au lieu de devoir se le faire attribuer.
     */
    public function resetPassword(array $params): void
    {
        Auth::requireAdmin();

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $cible = Utilisateur::find((int) $params['id']);
        $user = Auth::user();
        if (!$cible || (int) $cible['organisation_id'] !== (int) $user['organisation_id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if ($cible['role'] === 'proprietaire' && Auth::role() !== 'proprietaire') {
            View::flash('erreur', "Seul un Propriétaire peut réinitialiser le mot de passe d'un compte Propriétaire.");
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        $motDePasse = $_POST['mot_de_passe'] ?? '';
        if (strlen($motDePasse) < 8) {
            View::flash('erreur', 'Le mot de passe doit contenir au moins 8 caractères.');
            header('Location: /index.php?r=utilisateurs');
            exit;
        }

        Utilisateur::updatePassword((int) $cible['id'], $motDePasse, true);
        AuditLog::logAdmin($user, 'reset_mot_de_passe_utilisateur', 'utilisateur', (int) $cible['id'], $cible['nom']);
        View::flash('succes', 'Mot de passe réinitialisé. L\'utilisateur devra le changer à sa prochaine connexion.');
        header('Location: /index.php?r=utilisateurs');
        exit;
    }
}
