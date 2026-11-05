<?php
use App\Core\View;
use App\Models\Offre;
use App\Models\OffreItem;

// [ajouté 06/10, étape 2 du découpage Dossiers] Sous-onglet "Offres
// reçues" — reprend $offres déjà chargées par DossierController::show()
// (Offre::forDossier(), qui exclut les versions "remplacee" — seule la
// dernière version de chaque offre apparaît ici). "Transport inclus" est
// dérivé de l'incoterm négocié (Offre::transportInclus(), décision Marie
// Laure 06/10) plutôt que stocké. "Documents" : aucun système de pièce
// jointe par offre n'existe encore (seules les consultations en ont, via
// ConsultationPartage) — colonne affichée pour coller à la maquette mais
// toujours vide pour l'instant ; à construire plus tard si besoin.
$piecesParOffre = \App\Models\DossierPieceJointe::parOffre($piecesJointes ?? []);
?>
<div class="info-note"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> Un fournisseur retenu dans le comparateur ne signifie pas que le client a accepté la cotation — l'accord se formalise dans l'onglet Cotations client.</div>

<?php if (\App\Core\Auth::canWrite()): ?>
<div style="display:flex;justify-content:flex-end;margin:14px 0 0">
  <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/offres/manuelle" class="btn btn-secondary">+ Saisir une offre (prix trouvé en ligne…)</a>
</div>
<?php endif; ?>
<?php
$offresRecues = array_values(array_filter($offres ?? [], fn($o) => ($o['statut'] ?? '') === 'recue'));
$dejaRetenue = array_filter($offres ?? [], fn($o) => ($o['statut'] ?? '') === 'retenue');
if (count($offresRecues) === 1 && !$dejaRetenue && \App\Core\Auth::canValiderOffres()): $uniq = $offresRecues[0]; ?>
<form method="post" action="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>/comparateur/retenir" class="art-ligne-offre-unique">
  <input type="hidden" name="csrf_token" value="<?= View::e(\App\Core\Auth::csrfToken()) ?>">
  <input type="hidden" name="offre_id" value="<?= (int) $uniq['id'] ?>">
  <input type="hidden" name="retour" value="dossier">
  <div style="flex:1;min-width:220px"><strong>Une seule offre reçue (<?= View::e($uniq['fournisseur_nom'] ?? '') ?>) : pas besoin de comparer.</strong>
    <div style="font-size:12.5px;color:#0A5F6B;margin-top:2px">Retenez-la pour préparer la cotation client avec son prix d'achat.</div></div>
  <div class="art-champ" style="min-width:220px"><label>Motif</label><input type="text" name="motif_decision" value="Offre unique reçue" required></div>
  <button type="submit" class="btn art-ok">Retenir cette offre</button>
</form>
<?php endif; ?>
<?php
$retenuesDossier = \App\Models\Offre::retenuesForDossier((int) $dossier['id']);
if ($retenuesDossier):
  $fraisDossier = \App\Models\DossierFraisCommuns::forDossier((int) $dossier['id']);
  $etatTaux = \App\Models\DossierTauxChange::etat($dossier, $retenuesDossier, $fraisDossier);
  $calc = \App\Models\DossierFraisCommuns::calculer($retenuesDossier, $fraisDossier, array_filter(array_map(fn($d) => $d['taux'], $etatTaux['devises'])));
  $dv = $calc['devise'];
  $m2 = fn($x) => number_format((float) $x, 2, ',', ' ') . ' ' . $dv;
  $peutFrais = (\App\Core\Auth::canValiderOffres() || \App\Core\Auth::canGererCotations()) && \App\Models\DossierFraisCommuns::disponible();
?>
<div class="card" style="margin-top:14px;border-left:5px solid #1FA463">
  <h3 style="margin:0 0 4px">Coût d'achat du dossier</h3>
  <div style="font-size:12.5px;color:#555;margin-bottom:10px">Offres retenues (une par fournisseur) + frais payés une seule fois pour tout le dossier (ex. expédition finale unique). Ce total est la base de la cotation client.</div>
  <div class="table-scroll"><table class="dtable">
    <thead><tr><th>Fournisseur retenu</th><th class="num">Prix fournisseur</th><th class="num">Ses frais (transport…)</th><th class="num">Part des frais communs</th><th class="num">Coût total</th></tr></thead>
    <tbody>
    <?php foreach ($calc['offres'] as $oid => $l): ?>
      <tr><td><?= View::e($l['fournisseur']) ?><?php if (($l['taux'] ?? 1) != 1): ?><div style="font-size:11px;color:#667">converti de <?= View::e($l['devise_origine']) ?> (taux <?= View::e(rtrim(rtrim(number_format($l['taux'], 6, '.', ''), '0'), '.')) ?>)</div><?php endif; ?></td><td class="num"><?= $m2($l['prix']) ?></td><td class="num"><?= $l['frais_propres'] > 0.004 ? $m2($l['frais_propres']) : '—' ?></td><td class="num"><?= $l['part_communs'] > 0.004 ? $m2($l['part_communs']) : '—' ?></td><td class="num"><strong><?= $m2($l['total']) ?></strong></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="4"><strong>Coût total d'achat du dossier</strong></td><td class="num"><strong><?= $m2($calc['total']) ?></strong></td></tr></tfoot>
  </table></div>
  <?php if ($etatTaux['devises']): ?>
  <div class="alert <?= $calc['ignores'] ? 'alert-erreur' : 'alert-succes' ?>" style="margin-top:10px">
    <strong>Devises différentes</strong> : tout est converti en <?= View::e($dv) ?>. Vérifiez le taux de chaque devise<?= $calc['ignores'] ? ' (un taux manque, voir ci-dessous)' : '' ?>.
    <?php if ((\App\Core\Auth::canValiderOffres() || \App\Core\Auth::canGererCotations()) && \App\Models\DossierTauxChange::disponible()): ?>
    <form method="post" action="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>/taux-change" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:8px">
      <input type="hidden" name="csrf_token" value="<?= View::e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="ref" value="<?= View::e($dv) ?>">
      <?php foreach ($etatTaux['devises'] as $dev => $e): ?>
        <div class="art-champ" style="width:200px"><label>1 <?= View::e($dev) ?> = ? <?= View::e($dv) ?></label><input type="text" inputmode="decimal" name="taux[<?= View::e($dev) ?>]" value="<?= $e['taux'] ? View::e(rtrim(rtrim(number_format($e['taux'], 6, '.', ''), '0'), '.')) : '' ?>" placeholder="taux" required></div>
      <?php endforeach; ?>
      <button type="submit" class="btn art-ok">Enregistrer les taux</button>
    </form>
    <?php elseif (!\App\Models\DossierTauxChange::disponible()): ?><div style="margin-top:6px">Lancez la migration v46 pour enregistrer les taux.</div><?php endif; ?>
    <?php if ($calc['ignores']): ?><div style="margin-top:6px;color:#9b1c1c">Non additionné : <?= View::e(implode(' ; ', $calc['ignores'])) ?>.</div><?php endif; ?>
  </div>
  <?php endif; ?>

  <h4 style="margin:16px 0 6px">Frais communs du dossier</h4>
  <?php if ($fraisDossier): ?>
    <ul style="margin:0 0 10px;padding-left:18px">
    <?php foreach ($fraisDossier as $f): ?>
      <li><?= View::e($f['libelle']) ?> : <strong><?= number_format((float) $f['montant'], 2, ',', ' ') ?> <?= View::e($f['devise']) ?></strong>
        <?php if ($peutFrais): ?><form method="post" action="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>/frais-communs/<?= (int) $f['id'] ?>/supprimer" style="display:inline" onsubmit="return confirm('Supprimer ce frais ?')"><input type="hidden" name="csrf_token" value="<?= View::e(\App\Core\Auth::csrfToken()) ?>"><button type="submit" class="btn btn-sm" title="Supprimer">Supprimer</button></form><?php endif; ?></li>
    <?php endforeach; ?>
    </ul>
  <?php else: ?><div style="font-size:13px;color:#666;margin-bottom:8px">Aucun frais commun. Ajoutez par exemple l'expédition finale si tous les articles partent en une seule fois.</div><?php endif; ?>
  <?php if ($peutFrais): ?>
  <form method="post" action="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>/frais-communs" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="csrf_token" value="<?= View::e(\App\Core\Auth::csrfToken()) ?>">
    <div class="art-champ" style="min-width:220px"><label>Libellé</label><input type="text" name="libelle" value="Expédition finale (tous articles)" maxlength="120"></div>
    <div class="art-champ" style="width:140px"><label>Montant</label><input type="text" inputmode="decimal" name="montant" required placeholder="0"></div>
    <div class="art-champ" style="width:100px"><label>Devise</label><input type="text" name="devise" value="<?= View::e($dv) ?>" maxlength="10"></div>
    <button type="submit" class="btn art-ok">Ajouter ce frais</button>
  </form>
  <?php endif; ?>
  <div style="font-size:12.5px;margin-top:10px;color:#0A5F6B">Un autre article chez un autre fournisseur ? <a href="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>/comparateur">Ouvrez le comparateur et utilisez « Retenir aussi… »</a>. La cotation reprend tous les articles retenus.</div>
</div>
<?php endif; ?>
<?php if (empty($offres)): ?>
  <div class="card" style="margin-top:14px"><div class="empty-state">Aucune offre reçue pour ce dossier pour le moment.</div></div>
<?php else: ?>
  <div class="card" style="padding:0;overflow:hidden;margin-top:14px">
    <div class="table-scroll">
    <table class="dtable">
      <thead><tr><th>Fournisseur</th><th>Référence offre</th><th>Version</th><th>Lignes</th><th>Prix total</th><th>Devise</th><th>Transport inclus</th><th>Délai</th><th>Validité</th><th>Documents</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($offres as $o): ?>
          <?php
            $transportInclus = Offre::transportInclus($o['incoterm_negocie'] ?? null);
            $nbLignes = count(OffreItem::forOffre((int) $o['id']));
          ?>
          <tr>
            <td class="row-title"><?= View::e($o['fournisseur_nom']) ?>
              <?php if (!empty($o['source_type'])): ?><br><span class="badge badge-gray"><?= View::e(Offre::SOURCES[$o['source_type']] ?? 'Saisie manuelle') ?></span><?php if (!empty($o['source_url']) && preg_match('#^https?://#i', $o['source_url'])): ?> <a href="<?= View::e($o['source_url']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px">Voir le produit ↗</a><?php endif; ?>
              <?php if (!empty($o['source_date'])): ?><br><span style="font-size:12px;color:#6b7280">Prix consulté le <?= date('d/m/Y', strtotime($o['source_date'])) ?></span><?php endif; elseif (!empty($o['source_url']) && preg_match('#^https?://#i', $o['source_url'])): ?><br><a href="<?= View::e($o['source_url']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px">Voir le produit ↗</a><?php endif; ?></td>
            <td><?= View::e($o['reference']) ?></td>
            <td>v<?= (int) $o['version'] ?></td>
            <td><?= $nbLignes ?></td>
            <td style="font-weight:700"><?= number_format((float) $o['montant_total'], 2, ',', ' ') ?><?php $ct = Offre::coutRendu($o); if ($ct > (float) $o['montant_total'] + 0.004): ?><div style="font-size:11.5px;font-weight:600;color:#0A5F6B">Coût total : <?= number_format($ct, 2, ',', ' ') ?></div><?php endif; ?></td>
            <td><?= View::e($o['devise'] ?: '—') ?></td>
            <td>
              <?php if ($transportInclus === true): ?>
                <span class="badge badge-green">Oui — <?= View::e($o['incoterm_negocie']) ?></span>
              <?php elseif ($transportInclus === false): ?>
                <span class="badge badge-gray">Non — <?= View::e($o['incoterm_negocie']) ?></span>
              <?php else: ?>
                <span style="color:#9ca3af;font-style:italic">Non renseigné</span>
              <?php endif; ?>
            </td>
            <td><?= $o['delai_livraison'] ? View::e($o['delai_livraison']) : '—' ?></td>
            <td><?= $o['validite_offre'] ? date('d/m/Y', strtotime($o['validite_offre'])) : '—' ?></td>
            <td data-label="Documents">
              <?php $pjOffre = $piecesParOffre[(int) $o['id']] ?? []; ?>
              <?php if (!$pjOffre): ?><span style="color:#9ca3af;font-style:italic">—</span>
              <?php else: foreach ($pjOffre as $pj): ?>
                <div style="margin-bottom:3px"><a href="/index.php?r=dossiers/<?= (int) $o['dossier_id'] ?>/pieces/<?= (int) $pj['id'] ?>/telecharger<?= \App\Core\Storage::estPrevisualisable((string) $pj['type_mime']) ? '&apercu=1' : '' ?>" target="_blank" rel="noopener" style="font-size:12px;word-break:break-all"><?= \App\Core\Icon::svg('paperclip', 'icon', 12) ?> <?= View::e(mb_strimwidth((string) $pj['nom_original'], 0, 28, '…')) ?></a></div>
              <?php endforeach; endif; ?>
            </td>
            <td>
              <?php if (\App\Core\Auth::canWrite() && $o['statut'] !== 'retenue'): ?><a href="/index.php?r=offres/<?= (int) $o['id'] ?>/modifier" class="btn btn-sm btn-secondary" title="Modifier">Modifier</a><?php endif; ?>
              <a href="/index.php?r=consultations/<?= $o['consultation_id'] ?>/offres/nouvelle&version_de=<?= $o['id'] ?>" class="btn btn-sm btn-secondary">+ Nouvelle version</a>
              <?php if ($o['statut'] === 'retenue' && \App\Core\Auth::canVoirFinancesFournisseur() && \App\Models\BonCommandeFournisseur::schemaPret()):
                $bonOffre = \App\Models\BonCommandeFournisseur::actifPourOffre((int) $o['id']); ?>
                <?php if ($bonOffre): ?><a href="/index.php?r=bons-commande/<?= (int) $bonOffre['id'] ?>" class="btn btn-sm btn-secondary">Bon <?= View::e($bonOffre['reference']) ?></a>
                <?php elseif (\App\Core\Auth::canWrite() && \App\Core\Auth::canGererBonCommandeFournisseur()): ?><a href="/index.php?r=fournisseurs/<?= (int) $o['fournisseur_id'] ?>/bons-commande/nouveau&offre_id=<?= (int) $o['id'] ?>" class="btn btn-sm">Créer le bon de commande</a><?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
  <div class="hint" style="margin-top:10px">Montants affichés dans la devise d'origine de chaque offre — la conversion et le taux utilisé sont documentés dans le comparateur.</div>
<?php endif; ?>
