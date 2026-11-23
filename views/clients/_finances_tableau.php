<?php
use App\Core\View;

/** Tableau des indicateurs financiers par devise. Variables : $finances (null = indisponible), $lienDetail (bool). */
$fm = fn($m, $d) => number_format((float) $m, 0, ',', ' ') . ' ' . View::dev($d);
/** Petite ligne grise : équivalent en euros (taux de la cotation) et part sans taux. */
$eq = function (array $t, string $k, string $devise) use ($fm): string {
    $h = '';
    if (isset($t['eur_' . $k])) {
        $h .= '≈ ' . number_format((float) $t['eur_' . $k], 0, ',', ' ') . ' €';
    }
    if (!empty($t['sans_taux_' . $k])) {
        $h .= ($h !== '' ? ' · ' : '') . 'dont ' . $fm($t['sans_taux_' . $k], $devise) . ' sans taux';
    }
    return $h === '' ? '' : '<div style="font-size:12px;color:#777;font-weight:400;white-space:normal">' . $h . '</div>';
};
if ($finances === null): ?>
  <div class="empty-state">Non disponible — les données financières ne peuvent pas être lues pour le moment.</div>
<?php elseif (empty($finances['totaux'])): ?>
  <div class="empty-state">Aucune cotation acceptée ni facture sur cette période.</div>
<?php elseif (!empty($lienDetail)): /* Vue d'ensemble : colonne étroite, donc une carte par devise (un tableau y déborde) */ ?>
  <?php foreach ($finances['totaux'] as $devise => $t): ?>
    <div style="border:1px solid #e5e9ef;border-radius:10px;padding:8px 12px;margin-bottom:8px">
      <div style="font-weight:700;margin-bottom:4px"><?= View::dev($devise) ?></div>
      <?php foreach (['acceptees' => 'Cotations acceptées', 'facture' => 'Facturé', 'encaisse' => 'Encaissé', 'reste' => 'Reste à encaisser'] as $k => $lib): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:3px 0;font-size:14px;<?= $k === 'reste' ? 'border-top:1px solid #eef0f4;margin-top:3px;padding-top:6px' : '' ?>">
          <span style="color:#666"><?= $lib ?></span>
          <span style="text-align:right;<?= ($k === 'reste' && !empty($t['reste'])) ? 'color:#991b1b;font-weight:600' : '' ?>"><span style="white-space:nowrap"><?= isset($t[$k]) ? $fm($t[$k], $devise) : '—' ?></span><?= $eq($t, $k, $devise) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if (count($finances['totaux']) > 1): ?><div style="font-size:12px;color:#888;margin-top:6px">Plusieurs devises : montants présentés séparément, sans conversion.</div><?php endif; ?>
  <?php if (array_filter($finances['totaux'], fn($t) => count(array_filter(array_keys($t), fn($k) => strpos($k, 'eur_') === 0)) > 0)): ?><div style="font-size:12px;color:#888;margin-top:6px">« ≈ € » : équivalent calculé avec le taux de chaque cotation (1 € = … FCFA), à titre indicatif.</div><?php endif; ?>
<?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Devise</th><th class="num">Cotations acceptées</th><th class="num">Facturé</th><th class="num">Encaissé</th><th class="num">Reste à encaisser</th></tr></thead>
    <tbody>
    <?php foreach ($finances['totaux'] as $devise => $t): ?>
      <tr>
        <td data-label="Devise"><strong><?= View::dev($devise) ?></strong></td>
        <td data-label="Cotations acceptées" class="num"><?= isset($t['acceptees']) ? $fm($t['acceptees'], $devise) : '—' ?><?= $eq($t, 'acceptees', $devise) ?></td>
        <td data-label="Facturé" class="num"><?= isset($t['facture']) ? $fm($t['facture'], $devise) : '—' ?><?= $eq($t, 'facture', $devise) ?></td>
        <td data-label="Encaissé" class="num"><?= isset($t['encaisse']) ? $fm($t['encaisse'], $devise) : '—' ?><?= $eq($t, 'encaisse', $devise) ?></td>
        <td data-label="Reste à encaisser" class="num" style="white-space:nowrap;<?= !empty($t['reste']) ? 'color:#991b1b;font-weight:600' : '' ?>"><?= isset($t['reste']) ? $fm($t['reste'], $devise) : '—' ?><?= $eq($t, 'reste', $devise) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (count($finances['totaux']) > 1): ?><div style="font-size:12px;color:#888;margin-top:6px">Plusieurs devises : montants présentés séparément, sans conversion.</div><?php endif; ?>
  <?php if (array_filter($finances['totaux'], fn($t) => count(array_filter(array_keys($t), fn($k) => strpos($k, 'eur_') === 0)) > 0)): ?><div style="font-size:12px;color:#888;margin-top:6px">« ≈ € » : équivalent calculé avec le taux de chaque cotation (1 € = … FCFA), à titre indicatif.</div><?php endif; ?>
<?php endif; ?>
