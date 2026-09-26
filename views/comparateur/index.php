<?php use App\Core\View; use App\Models\Offre; ?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<h1 style="margin-top:8px">Comparateur d'offres</h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?></div>

<?php if (empty($offres)): ?>
  <div class="card"><div class="empty-state">Aucune offre reçue pour ce dossier pour le moment. Envoyez des consultations aux fournisseurs depuis le dossier, puis enregistrez leurs offres ici.</div></div>
<?php else: ?>
  <div style="overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th>Fournisseur</th>
        <th>Réf. offre</th>
        <th>Consultation</th>
        <th>Montant</th>
        <th>Incoterm négocié</th>
        <th>Délai</th>
        <th>Validité</th>
        <th>Statut</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($offres as $o): ?>
      <tr style="<?= $o['statut'] === 'retenue' ? 'background:#f0fdf4' : '' ?>">
        <td><strong><?= View::e($o['fournisseur_nom']) ?></strong></td>
        <td><?= View::e($o['reference']) ?></td>
        <td><?= View::e($o['consultation_reference']) ?></td>
        <td><?= number_format((float) $o['montant_total'], 2, ',', ' ') ?> <?= View::e($o['devise']) ?></td>
        <td><?= View::e($o['incoterm_negocie']) ?: '—' ?></td>
        <td><?= View::e($o['delai_livraison']) ?: '—' ?></td>
        <td><?= $o['validite_offre'] ? date('d/m/Y', strtotime($o['validite_offre'])) : '—' ?></td>
        <td>
          <span class="badge <?= $o['statut'] === 'retenue' ? 'badge-green' : ($o['statut'] === 'rejetee' ? 'badge-red' : 'badge-blue') ?>">
            <?= Offre::STATUTS[$o['statut']] ?? $o['statut'] ?>
          </span>
        </td>
        <td>
          <?php if ($o['statut'] !== 'retenue'): ?>
          <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/retenir">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="offre_id" value="<?= $o['id'] ?>">
            <button type="submit" class="btn btn-sm">Retenir</button>
          </form>
          <?php else: ?>
            <span style="font-size:12px;color:#065f46;font-weight:600">✓ Retenue</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php if (!empty($itemsByOffre[$o['id']])): ?>
        <tr>
          <td colspan="9" style="background:#fafbfc">
            <table style="margin:4px 0;box-shadow:none">
              <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Prix unitaire</th><th>Montant</th></tr></thead>
              <tbody>
              <?php foreach ($itemsByOffre[$o['id']] as $item): ?>
                <tr>
                  <td><?= View::e($item['designation']) ?></td>
                  <td><?= View::e((string) $item['quantite']) ?></td>
                  <td><?= View::e($item['unite']) ?></td>
                  <td><?= $item['prix_unitaire'] !== null ? number_format((float) $item['prix_unitaire'], 2, ',', ' ') : '—' ?></td>
                  <td><?= $item['montant'] !== null ? number_format((float) $item['montant'], 2, ',', ' ') : '—' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </td>
        </tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
