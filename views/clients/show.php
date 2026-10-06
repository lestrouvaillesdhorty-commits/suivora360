<?php use App\Core\View;
use App\Models\Client;
use App\Models\Demande;

$waNumber = preg_replace('/[^0-9]/', '', $client['telephone'] ?? '');
$statutInfo = Client::STATUTS[$client['statut'] ?? 'actif'] ?? ucfirst($client['statut'] ?? '');
$statutBadge = Client::STATUT_BADGES[$client['statut'] ?? 'actif'] ?? 'badge-gray';
?>
<a href="/index.php?r=clients" style="font-size:13px;color:#666">&larr; Retour aux clients</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($client['nom']) ?> <span class="badge <?= $statutBadge ?>"><?= $statutInfo ?></span></h1>
    <div class="subtitle">
      <?= View::e($client['code'] ?? '') ?: '' ?>
      <?= !empty($client['type']) ? ' — ' . View::e(Client::TYPES[$client['type']] ?? $client['type']) : (!empty($client['secteur']) ? ' — ' . View::e($client['secteur']) : '') ?>
    </div>
  </div>
  <div style="white-space:nowrap">
    <a href="/index.php?r=clients/<?= $client['id'] ?>/modifier" class="btn btn-secondary">Modifier</a>
    <?php if ((int) $client['is_active'] === 1): ?>
      <form method="post" action="/index.php?r=clients/<?= $client['id'] ?>/desactiver" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn btn-secondary">Désactiver</button>
      </form>
    <?php else: ?>
      <form method="post" action="/index.php?r=clients/<?= $client['id'] ?>/activer" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn">Réactiver</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div style="margin:12px 0">
  <?php if ($waNumber): ?>
    <a href="https://wa.me/<?= $waNumber ?>" target="_blank" class="btn btn-sm" style="background:#2E7D5B">WhatsApp</a>
  <?php endif; ?>
  <?php if (!empty($client['email'])): ?>
    <a href="mailto:<?= View::e($client['email']) ?>" class="btn btn-sm btn-secondary">E-mail</a>
  <?php endif; ?>
</div>

<div class="grid-3">
  <div class="stat-tile">
    <?php if (empty($caTotal)): ?>
      <div class="value">0</div>
    <?php else: ?>
      <?php foreach ($caTotal as $devise => $montant): ?>
        <div class="value"><?= number_format($montant, 0, ',', ' ') ?> <?= View::e($devise) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <div class="label">CA total (cotations acceptées)</div>
  </div>
  <div class="stat-tile">
    <?php if (empty($resteAPayer)): ?>
      <div class="value">0</div>
    <?php else: ?>
      <?php foreach ($resteAPayer as $devise => $montant): ?>
        <div class="value" style="<?= $montant > 0 ? 'color:#991b1b' : '' ?>"><?= number_format($montant, 0, ',', ' ') ?> <?= View::e($devise) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <div class="label">Reste à payer (factures émises)</div>
  </div>
</div>
<?php if (count($caTotal) > 1 || count($resteAPayer) > 1): ?>
  <div style="font-size:12px;color:#888;margin:-6px 0 12px">Montants affichés séparément par devise (pas de conversion automatique).</div>
<?php endif; ?>

<div class="card">
  <h2>Coordonnées</h2>
  <div class="info-row"><span class="label">Fonction du contact</span><span><?= View::e($client['fonction_contact'] ?? '') ?: '—' ?></span></div>
  <div class="info-row"><span class="label">E-mail</span><span><?= View::e($client['email']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Téléphone</span><span><?= View::e($client['telephone']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Pays</span><span><?= View::e($client['pays']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Ville</span><span><?= View::e($client['ville']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Adresse</span><span><?= View::e($client['adresse']) ?: '—' ?><?= !empty($client['code_postal']) ? ' — ' . View::e($client['code_postal']) : '' ?></span></div>
  <?php if (!empty($client['adresse_livraison'])): ?>
    <div class="info-row"><span class="label">Adresse de livraison</span><span><?= View::e($client['adresse_livraison']) ?></span></div>
  <?php endif; ?>
</div>

<?php if (!empty($client['siret']) || !empty($client['tva']) || !empty($client['incoterm_habituel']) || !empty($client['mode_transport_habituel']) || !empty($client['conditions_paiement'])): ?>
<div class="card">
  <h2>Informations commerciales</h2>
  <?php if (!empty($client['siret'])): ?><div class="info-row"><span class="label">SIREN / SIRET</span><span><?= View::e($client['siret']) ?></span></div><?php endif; ?>
  <?php if (!empty($client['tva'])): ?><div class="info-row"><span class="label">N° TVA</span><span><?= View::e($client['tva']) ?></span></div><?php endif; ?>
  <?php if (!empty($client['incoterm_habituel'])): ?><div class="info-row"><span class="label">Incoterm habituel</span><span><?= View::e(Demande::INCOTERMS[$client['incoterm_habituel']] ?? $client['incoterm_habituel']) ?></span></div><?php endif; ?>
  <?php if (!empty($client['mode_transport_habituel'])): ?><div class="info-row"><span class="label">Transport habituel</span><span><?= View::e(Client::MODES_TRANSPORT[$client['mode_transport_habituel']] ?? $client['mode_transport_habituel']) ?></span></div><?php endif; ?>
  <?php if (!empty($client['conditions_paiement'])): ?><div class="info-row"><span class="label">Conditions de paiement</span><span><?= View::e(Client::CONDITIONS_PAIEMENT[$client['conditions_paiement']] ?? $client['conditions_paiement']) ?></span></div><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Historique des demandes</h2>
  <?php if (empty($demandes)): ?>
    <div class="empty-state">Aucune demande rattachée à ce client pour le moment.</div>
  <?php else: ?>
    <?php
      $statutsDemande = [
          'a_qualifier' => ['À qualifier', 'badge-yellow'],
          'en_attente_info' => ["En attente d'infos", 'badge-yellow'],
          'qualifiee' => ['Qualifiée', 'badge-blue'],
          'rattachee' => ['Rattachée', 'badge-blue'],
          'transformee' => ['Transformée en dossier', 'badge-green'],
          'rejetee' => ['Rejetée', 'badge-red'],
          'archivee' => ['Archivée', 'badge-gray'],
      ];
    ?>
    <table>
      <thead><tr><th>Référence</th><th>Objet</th><th>Statut</th><th>Dossier</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($demandes as $de): ?>
          <?php $si = $statutsDemande[$de['statut']] ?? [ucfirst($de['statut']), 'badge-gray']; ?>
          <tr onclick="window.location='/index.php?r=demandes/<?= $de['id'] ?>'" style="cursor:pointer">
            <td><?= View::e($de['reference']) ?></td>
            <td><?= View::e($de['objet']) ?></td>
            <td><span class="badge <?= $si[1] ?>"><?= View::e($si[0]) ?></span></td>
            <td><?= !empty($de['dossier_id']) ? View::e($de['dossier_reference']) : '—' ?></td>
            <td><?= date('d/m/Y', strtotime($de['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if (!empty($client['notes'])): ?>
<div class="card">
  <h2>Notes</h2>
  <div><?= nl2br(View::e($client['notes'])) ?></div>
</div>
<?php endif; ?>
