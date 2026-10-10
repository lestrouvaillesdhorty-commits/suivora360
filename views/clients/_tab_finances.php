<?php
use App\Core\View;
use App\Models\Cotation;
use App\Models\Facture;

$fm = fn($m, $d) => number_format((float) $m, 0, ',', ' ') . ' ' . ($d !== null && $d !== '' ? $d : '—');
$libellesPeriode = ['tout' => 'Toutes périodes', 'annee' => 'Année en cours', '12m' => '12 derniers mois'];
$badgeCot = ['brouillon' => 'gray', 'envoyee' => 'blue', 'acceptee' => 'green', 'refusee' => 'red', 'remplacee' => 'gray'];
$badgeFac = ['emise' => 'yellow', 'payee' => 'green', 'annulee' => 'gray'];
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
    <h2 style="margin:0">Indicateurs financiers</h2>
    <form method="get" action="/index.php" style="display:flex;gap:8px;align-items:center">
      <input type="hidden" name="r" value="clients/<?= (int) $client['id'] ?>">
      <input type="hidden" name="onglet" value="finances">
      <label for="periode" style="margin:0;font-size:13px">Période</label>
      <select id="periode" name="periode" onchange="this.form.submit()">
        <?php foreach ($libellesPeriode as $code => $lib): ?><option value="<?= $code ?>" <?= $periode === $code ? 'selected' : '' ?>><?= $lib ?></option><?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm" type="submit">Appliquer</button></noscript>
    </form>
  </div>
  <div style="margin-top:12px"><?php $lienDetail = false; include __DIR__ . '/_finances_tableau.php'; ?></div>

  <?php if ($finances !== null): ?>
  <div style="font-size:12px;color:#666;margin-top:12px;line-height:1.6">
    <strong>Bases de calcul — <?= View::e($libellesPeriode[$periode]) ?></strong>
    <ul style="margin:6px 0 0 18px">
      <li><strong>Cotations acceptées</strong> : montant de la dernière version acceptée de chaque dossier (une version remplacée n’est jamais comptée deux fois). Ce n’est pas un chiffre d’affaires.</li>
      <li><strong>Facturé</strong> : factures émises ou payées, hors annulées<?= $finances['nb_annulees'] > 0 ? ' (' . (int) $finances['nb_annulees'] . ' annulée(s) exclue(s))' : '' ?>.</li>
      <li><strong>Encaissé</strong> : factures marquées « payée ». <strong>Reste à encaisser</strong> : factures émises non payées.</li>
      <li><strong>Avoirs et règlements partiels</strong> : non disponibles (non gérés pour l’instant) — ils ne sont donc pas déduits.</li>
      <li>Aucune conversion de devises : chaque devise est présentée séparément.</li>
    </ul>
  </div>
  <?php endif; ?>
</div>

<?php if ($finances !== null): ?>
<div class="card">
  <h2>Cotations</h2>
  <?php if (empty($finances['cotations'])): ?><div class="empty-state">Aucune cotation.</div><?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Cotation</th><th>Dossier</th><th>Statut</th><th class="num">Montant</th><th>Prise en compte</th></tr></thead>
    <tbody>
    <?php foreach ($finances['cotations'] as $c): $compte = in_array((int) $c['id'], $finances['acceptees_ids'], true); ?>
      <tr>
        <td data-label="Cotation"><a href="/index.php?r=cotations/<?= (int) $c['id'] ?>"><?= View::e($c['reference']) ?></a><?= (int) $c['version'] > 1 ? ' <span style="font-size:12px;color:#888">v' . (int) $c['version'] . '</span>' : '' ?></td>
        <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $c['dossier_id'] ?>"><?= View::e($c['dossier_reference']) ?></a></td>
        <td data-label="Statut"><span class="badge badge-<?= $badgeCot[$c['statut']] ?? 'gray' ?>"><?= View::e(Cotation::STATUTS[$c['statut']] ?? $c['statut']) ?></span></td>
        <td data-label="Montant" class="num"><?= $fm($c['montant_total'], $c['devise']) ?></td>
        <td data-label="Prise en compte"><?= $compte ? 'Oui (acceptée)' : '<span style="color:#999">Non</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Commandes</h2>
  <?php if (empty($finances['commandes'])): ?><div class="empty-state">Aucune commande.</div><?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Commande</th><th>Dossier</th><th>Statut</th><th>Étape</th></tr></thead>
    <tbody>
    <?php foreach ($finances['commandes'] as $m): ?>
      <tr>
        <td data-label="Commande"><a href="/index.php?r=dossiers/<?= (int) $m['dossier_id'] ?>/commande"><?= View::e($m['reference']) ?></a></td>
        <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $m['dossier_id'] ?>"><?= View::e($m['dossier_reference']) ?></a></td>
        <td data-label="Statut"><?= View::e(ucfirst(str_replace('_', ' ', (string) $m['statut']))) ?></td>
        <td data-label="Étape"><?= View::e(ucfirst(str_replace('_', ' ', (string) $m['etape']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Factures et règlements</h2>
  <?php if (empty($finances['factures'])): ?><div class="empty-state">Aucune facture.</div><?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Facture</th><th>Dossier</th><th>Statut</th><th class="num">Montant</th><th>Émission</th><th>Échéance</th></tr></thead>
    <tbody>
    <?php foreach ($finances['factures'] as $fa): ?>
      <tr>
        <td data-label="Facture"><a href="/index.php?r=dossiers/<?= (int) $fa['dossier_id'] ?>/commande"><?= View::e($fa['reference']) ?></a></td>
        <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $fa['dossier_id'] ?>"><?= View::e($fa['dossier_reference']) ?></a></td>
        <td data-label="Statut"><span class="badge badge-<?= $badgeFac[$fa['statut']] ?? 'gray' ?>"><?= View::e(Facture::STATUTS[$fa['statut']] ?? $fa['statut']) ?></span></td>
        <td data-label="Montant" class="num"><?= $fm($fa['montant'], $fa['devise']) ?></td>
        <td data-label="Émission"><?= !empty($fa['date_emission']) ? date('d/m/Y', strtotime($fa['date_emission'])) : '—' ?></td>
        <td data-label="Échéance"><?= !empty($fa['date_echeance']) ? date('d/m/Y', strtotime($fa['date_echeance'])) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <div style="font-size:12px;color:#888;margin-top:8px">Règlements : seul le statut « payée » d’une facture est suivi (pas de suivi des paiements partiels).</div>
</div>
<?php endif; ?>
