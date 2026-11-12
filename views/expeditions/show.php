<?php
use App\Core\View;
use App\Models\Expedition;
/** Fiche expédition. Variables : $exp, $reservations, $bilan, $dossiers, $voitMarges, $peutEcrire, $csrfToken. */
$fmt = fn($n, $d = 0) => number_format((float) $n, $d, ',', ' ');
$dev = View::e($exp['devise']);
$base = '/index.php?r=expeditions/' . (int) $exp['id'];
$ouverte = $exp['statut'] === 'ouverte';
$complet = $exp['type'] === 'conteneur_complet';
?>
<div class="page-header">
  <h1><?= View::e($exp['reference']) ?> · <?= View::e($exp['origine']) ?> → <?= View::e($exp['destination']) ?></h1>
  <?php if ($peutEcrire): ?><a class="btn" href="<?= $base ?>/modifier">Modifier</a><?php endif; ?>
</div>
<div class="card">
  <div class="kv-row"><span class="label">Type</span><span class="value"><?= View::e(Expedition::TYPES[$exp['type']]) ?> · <?= View::e(Expedition::MODES[$exp['mode']]) ?></span></div>
  <?php if ($exp['partenaire']): ?><div class="kv-row"><span class="label">Partenaire</span><span class="value"><?= View::e($exp['partenaire']) ?></span></div><?php endif; ?>
  <div class="kv-row"><span class="label">Dates</span><span class="value">Clôture <?= $exp['cloture_reservations'] ? date('d/m/Y', strtotime($exp['cloture_reservations'])) : '—' ?> · Départ <?= $exp['date_depart'] ? date('d/m/Y', strtotime($exp['date_depart'])) : '—' ?> · Arrivée <?= $exp['date_arrivee'] ? date('d/m/Y', strtotime($exp['date_arrivee'])) : '—' ?></span></div>
  <?php if ($exp['numero_suivi']): ?><div class="kv-row"><span class="label">N° de suivi</span><span class="value"><?= View::e($exp['numero_suivi']) ?></span></div><?php endif; ?>
  <div class="kv-row"><span class="label">Statut</span><span class="value">
    <?php if ($peutEcrire): ?>
    <form method="post" action="<?= $base ?>/statut" style="display:inline-flex;gap:6px;align-items:center">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <select name="statut"><?php foreach (Expedition::STATUTS as $k => $l): ?><option value="<?= $k ?>" <?= $exp['statut'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      <button class="btn" type="submit">Changer</button>
    </form>
    <?php else: ?><?= View::e(Expedition::STATUTS[$exp['statut']]) ?><?php endif; ?>
  </span></div>
</div>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:12px 0">
  <div class="card"><div class="label">Remplissage</div><strong style="font-size:1.4em"><?= $bilan['remplissage'] !== null ? (int) $bilan['remplissage'] . ' %' : '—' ?></strong><div class="hint"><?= $fmt($bilan['volume'], 2) ?> m³ · <?= $fmt($bilan['poids']) ?> kg</div></div>
  <div class="card"><div class="label">Vendu</div><strong style="font-size:1.4em"><?= $fmt($bilan['vente']) ?> <?= $dev ?></strong><div class="hint"><?= (int) $bilan['nb'] ?> réservation(s)</div></div>
  <?php if ($voitMarges): ?>
  <div class="card"><div class="label">Coût d'achat</div><strong style="font-size:1.4em"><?= $fmt($exp['cout_achat']) ?> <?= $dev ?></strong></div>
  <div class="card"><div class="label">Place libre (perte)</div><strong style="font-size:1.4em"><?= $fmt($bilan['libre']) ?> <?= $dev ?></strong></div>
  <div class="card"><div class="label">Résultat</div><strong style="font-size:1.4em;color:<?= $bilan['resultat'] < 0 ? '#b3261e' : '#1e7a3c' ?>"><?= $fmt($bilan['resultat']) ?> <?= $dev ?></strong></div>
  <?php endif; ?>
</div>
<?php if ($bilan['surcharge']): ?><div class="alert alert-erreur">La capacité de l'expédition est dépassée.</div><?php endif; ?>

<div class="card">
  <h2>Réservations</h2>
  <?php if (!$reservations): ?>
    <div class="empty-state">Aucune réservation pour le moment.</div>
  <?php else: ?>
  <table class="table responsive-cards">
    <thead><tr><th>Client / dossier</th><th>Marchandise</th><th>Volume</th><th>Poids</th><th>Colis / pal.</th><th>Prix vendu</th><?php if ($voitMarges): ?><th>Coût imputé</th><th>Marge</th><?php endif; ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($reservations as $r): $l = $bilan['lignes'][(int) $r['id']] ?? ['cout' => 0, 'marge' => 0]; ?>
      <tr>
        <td data-label="Client"><?= View::e($r['client_nom'] ?: '—') ?><?php if (!empty($r['dossier_id'])): ?> · <a href="/index.php?r=dossiers/<?= (int) $r['dossier_id'] ?>">dossier</a><?php endif; ?><?php if ($r['description']): ?><div class="hint"><?= View::e($r['description']) ?></div><?php endif; ?></td>
        <td data-label="Marchandise"><?= View::e(Expedition::MARCHANDISES[$r['type_marchandise']] ?? '') ?></td>
        <td data-label="Volume"><?= $r['volume'] !== null ? $fmt($r['volume'], 2) . ' m³' : '—' ?></td>
        <td data-label="Poids"><?= $r['poids'] !== null ? $fmt($r['poids']) . ' kg' : '—' ?></td>
        <td data-label="Colis / pal."><?= (int) $r['colis'] ?> / <?= (int) $r['palettes'] ?></td>
        <td data-label="Prix vendu"><?= $fmt($r['prix_vente']) ?> <?= $dev ?><div class="hint"><?= View::e(Expedition::TARIFICATIONS[$r['tarification']] ?? '') ?></div></td>
        <?php if ($voitMarges): ?>
        <td data-label="Coût imputé"><?= $fmt($l['cout']) ?></td>
        <td data-label="Marge" style="color:<?= $l['marge'] < 0 ? '#b3261e' : 'inherit' ?>"><?= $fmt($l['marge']) ?></td>
        <?php endif; ?>
        <td><?php if ($peutEcrire): ?>
          <form method="post" action="<?= $base ?>/supprimer-reservation" onsubmit="return confirm('Retirer cette réservation ?')">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-sm" type="submit">Retirer</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if ($peutEcrire && $ouverte && !($complet && $reservations)): ?>
<div class="card">
  <h2>Ajouter une réservation</h2>
  <?php include __DIR__ . '/_form_reservation.php'; ?>
</div>
<?php endif; ?>
