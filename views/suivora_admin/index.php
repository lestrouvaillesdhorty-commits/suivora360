<?php use App\Core\View; ?>
<h1>Administration Suivora</h1>
<div class="subtitle">Les entreprises clientes de Suivora360. Vous voyez ici l'espace de chaque entreprise (nombre d'utilisateurs, de demandes, de dossiers), jamais leurs données.</div>

<?php
$aujourdhui = date('Y-m-d');
$bientot = date('Y-m-d', strtotime('+15 days'));
$etatEcheance = function (?string $e) use ($aujourdhui, $bientot): array {
    if (!$e) { return ['—', 'badge-gray']; }
    $txt = date('d/m/Y', strtotime($e));
    if ($e < $aujourdhui) { return [$txt . ' — échue', 'badge-red']; }
    if ($e <= $bientot) { return [$txt . ' — bientôt', 'badge-orange']; }
    return [$txt, 'badge-green'];
};
$aRelancer = array_filter($organisations, fn($o) => !empty($o['abonnement_echeance']) && $o['abonnement_echeance'] <= $bientot);
?>
<?php if (!empty($aRelancer)): ?>
<div class="alert alert-erreur">
  <strong>Échéances d'abonnement à traiter :</strong>
  <?php foreach ($aRelancer as $o): ?>
    <?= View::e($o['nom']) ?> (<?= date('d/m/Y', strtotime($o['abonnement_echeance'])) ?>)<?= $o === end($aRelancer) ? '' : ' ; ' ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Entreprises clientes</h2>
  <?php if (empty($organisations)): ?>
    <div class="empty-state">Aucune entreprise.</div>
  <?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Entreprise</th><th class="num">Filiales</th><th class="num">Utilisateurs actifs</th><th class="num">Demandes</th><th class="num">Dossiers</th><th>Dernière activité</th><th>Accès</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($organisations as $o): $active = !isset($o['actif']) || (int) $o['actif'] === 1; ?>
      <tr>
        <td data-label="Entreprise"><strong><?= View::e($o['nom']) ?></strong><br><span style="font-size:12px;color:#888">créée le <?= date('d/m/Y', strtotime($o['created_at'])) ?></span></td>
        <td data-label="Filiales" class="num"><?= (int) $o['nb_filiales'] ?></td>
        <td data-label="Utilisateurs actifs" class="num"><?= (int) $o['nb_utilisateurs'] ?></td>
        <td data-label="Demandes" class="num"><?= (int) $o['nb_demandes'] ?></td>
        <td data-label="Dossiers" class="num"><?= (int) $o['nb_dossiers'] ?></td>
        <td data-label="Dernière activité"><?= $o['derniere_activite'] ? date('d/m/Y H:i', strtotime($o['derniere_activite'])) : '—' ?></td>
        <td data-label="Accès"><span class="badge <?= $active ? 'badge-green' : 'badge-red' ?>"><?= $active ? 'Actif' : 'Suspendu' ?></span></td>
        <td>
          <?php if ((int) $o['id'] !== $monOrganisationId): ?>
          <form method="post" action="/index.php?r=admin-suivora/<?= (int) $o['id'] ?>/basculer" style="margin:0" onsubmit="return confirm('<?= $active ? 'Suspendre' : 'Rétablir' ?> l\'accès de « <?= View::e(addslashes($o['nom'])) ?> » ?');">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn btn-sm btn-secondary"><?= $active ? 'Suspendre' : 'Rétablir' ?></button>
          </form>
          <?php else: ?>
            <span style="font-size:12px;color:#888">Votre entreprise</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Abonnements</h2>
  <p style="font-size:13px;color:#666;margin-top:0">Suivi manuel (aucun paiement en ligne) : notez l'offre, le prix mensuel et la prochaine échéance de chaque entreprise. Les échéances échues ou à moins de 15 jours sont signalées en haut de page.</p>
  <table class="responsive-cards">
    <thead><tr><th>Entreprise</th><th>Offre</th><th class="num">Prix mensuel</th><th>Échéance</th><th>Note</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($organisations as $o): [$txtEch, $clsEch] = $etatEcheance($o['abonnement_echeance'] ?? null); ?>
      <tr>
        <td data-label="Entreprise"><strong><?= View::e($o['nom']) ?></strong></td>
        <td data-label="Offre"><?= !empty($o['abonnement_offre']) && isset($offres[$o['abonnement_offre']]) ? View::e($offres[$o['abonnement_offre']]) : '—' ?></td>
        <td data-label="Prix mensuel" class="num"><?= $o['abonnement_prix'] !== null && $o['abonnement_prix'] !== '' ? number_format((float) $o['abonnement_prix'], 0, ',', ' ') . ' ' . View::e($devisesAbonnement[$o['abonnement_devise'] ?? 'XAF'] ?? '') : '—' ?></td>
        <td data-label="Échéance"><span class="badge <?= $clsEch ?>"><?= View::e($txtEch) ?></span></td>
        <td data-label="Note" style="font-size:12.5px;color:#666"><?= View::e($o['abonnement_notes'] ?? '') ?></td>
        <td>
          <details>
            <summary class="btn btn-sm btn-secondary" style="display:inline-block;cursor:pointer">Modifier</summary>
            <form method="post" action="/index.php?r=admin-suivora/<?= (int) $o['id'] ?>/abonnement" style="margin-top:10px;min-width:260px">
              <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
              <div class="form-group"><label>Offre</label>
                <select name="offre"><option value="">— aucune —</option>
                <?php foreach ($offres as $k => $lib): ?><option value="<?= View::e($k) ?>" <?= ($o['abonnement_offre'] ?? '') === $k ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
                </select></div>
              <div class="form-row">
                <div class="form-group"><label>Prix mensuel</label><input type="text" name="prix" inputmode="decimal" value="<?= View::e($o['abonnement_prix'] !== null ? (string) (float) $o['abonnement_prix'] : '') ?>"></div>
                <div class="form-group"><label>Devise</label>
                  <select name="devise"><?php foreach ($devisesAbonnement as $k => $lib): ?><option value="<?= View::e($k) ?>" <?= ($o['abonnement_devise'] ?? 'XAF') === $k ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="form-group"><label>Prochaine échéance</label><input type="date" name="echeance" value="<?= View::e($o['abonnement_echeance'] ?? '') ?>"></div>
              <div class="form-group"><label>Note (mode de paiement, contact...)</label><input type="text" name="notes" maxlength="255" value="<?= View::e($o['abonnement_notes'] ?? '') ?>"></div>
              <button type="submit" class="btn btn-sm">Enregistrer</button>
            </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2>Créer une entreprise cliente</h2>
  <p style="font-size:13px;color:#666;margin-top:0">Crée l'espace de l'entreprise, sa première filiale et son compte Propriétaire. Le Propriétaire gère ensuite seul ses utilisateurs, ses filiales et ses données.</p>
  <form method="post" action="/index.php?r=admin-suivora" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-row">
      <div class="form-group"><label>Nom de l'entreprise *</label><input type="text" name="nom_organisation" required></div>
      <div class="form-group"><label>Première filiale *</label><input type="text" name="nom_filiale" required placeholder="Ex : Siège"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Nom du Propriétaire *</label><input type="text" name="nom_proprietaire" required></div>
      <div class="form-group"><label>E-mail du Propriétaire *</label><input type="email" name="email_proprietaire" required></div>
    </div>
    <div class="form-group" style="max-width:420px"><label>Mot de passe provisoire * (8 caractères minimum)</label><input type="password" name="mot_de_passe" minlength="8" required autocomplete="new-password"></div>
    <button type="submit" class="btn">Créer l'entreprise</button>
  </form>
</div>
