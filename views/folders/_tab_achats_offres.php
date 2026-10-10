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
?>
<div class="info-note"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> Un fournisseur retenu dans le comparateur ne signifie pas que le client a accepté la cotation — l'accord se formalise dans l'onglet Cotations client.</div>

<?php if (\App\Core\Auth::canWrite()): ?>
<div style="display:flex;justify-content:flex-end;margin:14px 0 0">
  <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/offres/manuelle" class="btn btn-secondary">+ Saisir une offre (prix trouvé en ligne…)</a>
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
              <?php if (!empty($o['source_type'])): ?><br><span class="badge badge-gray"><?= View::e(Offre::SOURCES[$o['source_type']] ?? 'Saisie manuelle') ?></span><?php if (!empty($o['source_url']) && preg_match('#^https?://#i', $o['source_url'])): ?> <a href="<?= View::e($o['source_url']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px">Voir la source</a><?php endif; ?>
              <?php if (!empty($o['source_date'])): ?><br><span style="font-size:12px;color:#6b7280">Prix consulté le <?= date('d/m/Y', strtotime($o['source_date'])) ?></span><?php endif; endif; ?></td>
            <td><?= View::e($o['reference']) ?></td>
            <td>v<?= (int) $o['version'] ?></td>
            <td><?= $nbLignes ?></td>
            <td style="font-weight:700"><?= number_format((float) $o['montant_total'], 2, ',', ' ') ?></td>
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
            <td><span style="color:#9ca3af;font-style:italic">—</span></td>
            <td>
              <?php if (\App\Core\Auth::canWrite() && $o['statut'] !== 'retenue'): ?><a href="/index.php?r=offres/<?= (int) $o['id'] ?>/modifier" class="btn btn-sm btn-secondary">Modifier</a><?php endif; ?>
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
