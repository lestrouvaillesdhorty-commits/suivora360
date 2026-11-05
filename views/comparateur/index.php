<?php use App\Core\View; use App\Models\Offre; use App\Models\Client; use App\Models\Dossier; ?>
<?php
// [modifié 06/10, étape 2 du découpage Dossiers] Le lien de retour isolé
// et le h1/subtitle propres à cette page sont remplacés par la coquille
// commune de la fiche Dossier (_dossier_header.php) + les sous-onglets de
// "Achats et offres" (_achats_subtabs.php, "Comparaison" actif) — cette
// page garde sa propre route (/dossiers/{id}/comparateur, accès direct
// depuis le menu, décision du 29/09) mais s'affiche désormais comme un
// sous-onglet intégré plutôt qu'une page isolée.
$onglet = 'achats';
$sousOngletAchats = 'comparaison';
$nbOffres = count($offres);
?>
<?php include __DIR__ . '/../folders/_dossier_header.php'; ?>
<?php include __DIR__ . '/../folders/_achats_subtabs.php'; ?>

<?php
function afficheOuNonRenseigne($valeur, string $suffixe = ''): string
{
    if ($valeur === null || $valeur === '' || (is_numeric($valeur) && (float) $valeur == 0.0 && $suffixe !== '')) {
        return '<span style="color:#9ca3af;font-style:italic">Non renseigné</span>';
    }
    return View::e((string) $valeur) . $suffixe;
}
$typeDossier = $dossier['type_dossier'] ?? 'autre';
$libelleFournisseur = Dossier::libelleFournisseur($typeDossier);
?>

<?php if (count($offres) < 2 && empty($aDesLignesLiees)): ?>
  <div class="card"><div class="alert alert-erreur" style="margin:0">Ce dossier a <?= count($offres) ?> offre(s) reçue(s) exploitable(s). Une comparaison fiable nécessite au moins deux offres — enregistrez d'autres offres reçues avant de comparer, sinon vous risqueriez de choisir sans point de comparaison réel.</div></div>
<?php endif; ?>

<?php if (empty($offres)): ?>
  <div class="card"><div class="empty-state">Aucune offre reçue pour ce dossier pour le moment. Envoyez des consultations aux <?= View::e(mb_strtolower($libelleFournisseur)) ?>s depuis le dossier, puis enregistrez leurs offres ici.</div></div>
<?php else: ?>

  <?php if ($decisionExistante && $peutValider): ?>
  <div class="card" style="background:#fffbeb;border:1px solid #fde68a">
    <details>
      <summary style="cursor:pointer;font-weight:600;color:#92400e">Revenir sur la décision déjà prise (réservé à Propriétaire/Achats)</summary>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/revenir" style="margin-top:12px">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group">
          <label>Motif (obligatoire)</label>
          <textarea name="motif_revenir" rows="2" required placeholder="ex: le fournisseur retenu ne peut finalement pas honorer le délai annoncé"></textarea>
        </div>
        <button type="submit" class="btn btn-secondary">Annuler la décision et recomparer</button>
      </form>
    </details>
  </div>
  <?php endif; ?>

  <div class="card" style="background:#eff6ff;border:1px solid #bfdbfe;font-size:13px;color:#085560">
    Le meilleur résultat (prix marchandises et coût rendu les plus bas) est surligné <strong>par devise</strong> — les montants dans des devises différentes ne sont pas encore convertis automatiquement (multi-devises prévu plus tard), donc comparez d'abord les offres dans la même devise.
  </div>

  <?php if (!empty($parArticleDispo)): ?>
  <div id="articles"></div>
  <h2 style="margin:18px 0 6px">Comparaison article par article</h2>
  <div style="font-size:13px;color:#555;margin-bottom:8px">On compare un même article chez plusieurs fournisseurs. Choisissez le gagnant de chaque article : son fournisseur est retenu automatiquement et la cotation reprend uniquement les articles gagnants.</div>
  <?php if (!empty($couvertureDifferente)): ?>
    <div class="alert alert-erreur">Ces offres ne portent pas toutes sur les mêmes articles : leurs totaux ne sont pas comparables. Comparez article par article ci-dessous (aucun « meilleur total » n'est surligné dans le tableau global).</div>
  <?php endif; ?>
  <?php if (!empty($offresSansLien)): ?>
    <div class="alert alert-erreur">Offre(s) sans article rattaché : <?= View::e(implode(', ', array_map(fn($x) => $x['fournisseur_nom'] . ' (' . $x['reference'] . ')', $offresSansLien))) ?>. Ouvrez l'offre (Modifier) et choisissez, pour chaque ligne, l'article de la demande concerné pour qu'elle apparaisse ici.</div>
  <?php endif; ?>
  <?php if (!empty($nbDirects) && $peutValider): ?>
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/retenir-directs" style="margin:10px 0">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <button type="submit" class="btn art-ok">Retenir les <?= (int) $nbDirects ?> article(s) qui n'ont qu'une seule offre</button>
    <span style="font-size:12.5px;color:#555;margin-left:8px">Pas de comparaison à faire pour eux.</span>
  </form>
  <?php endif; ?>
  <?php foreach ($parArticle as $aid => $bloc): $a = $bloc['article']; ?>
  <div class="card cmp-article"<?= count($bloc['lignes']) === 1 ? ' style="border-left-color:#1FA463"' : '' ?>>
    <h3><?= View::e($a['designation']) ?><?php if ($a['quantite'] !== null && $a['quantite'] !== ''): ?> <span style="font-weight:400;color:#555">— <?= View::e(rtrim(rtrim(number_format((float) $a['quantite'], 2, '.', ''), '0'), '.')) ?> <?= View::e((string) ($a['unite'] ?? '')) ?></span><?php endif; ?></h3>
    <?php if (empty($bloc['lignes'])): ?>
      <div style="font-size:13px;color:#666">Aucune offre ne couvre encore cet article.</div>
    <?php else: ?>
    <?php if (count($bloc['lignes']) === 1): ?><div style="font-size:12.5px;color:#0A5F6B;margin-bottom:6px">Une seule offre pour cet article : rien à comparer.</div><?php endif; ?>
    <div class="table-scroll"><table class="dtable">
      <thead><tr><th>Fournisseur</th><th class="num">Prix unitaire</th><th class="num">Quantité proposée</th><th class="num">Montant</th><th>Offre</th><th>Décision</th></tr></thead>
      <tbody>
      <?php $gagnant = false; foreach ($bloc['lignes'] as $l): $o = $l['offre']; $it = $l['item'];
        $dv = $o['devise'] ?: '—';
        $best = isset($bloc['meilleur'][$dv]) && $it['prix_unitaire'] !== null && (float) $it['prix_unitaire'] == $bloc['meilleur'][$dv] && count($bloc['lignes']) > 1;
        if (!empty($it['retenu'])) { $gagnant = true; } ?>
        <tr<?= !empty($it['retenu']) ? ' style="background:#f0fdf4"' : '' ?>>
          <td><?= View::e($o['fournisseur_nom']) ?></td>
          <td class="num <?= $best ? 'cmp-meilleur' : '' ?>"><?= $it['prix_unitaire'] !== null ? number_format((float) $it['prix_unitaire'], 2, ',', ' ') . ' ' . View::e($dv) : '—' ?></td>
          <td class="num"><?= $it['quantite'] !== null ? View::e(rtrim(rtrim(number_format((float) $it['quantite'], 2, '.', ''), '0'), '.')) : '—' ?></td>
          <td class="num"><?= $it['montant'] !== null ? number_format((float) $it['montant'], 2, ',', ' ') . ' ' . View::e($dv) : '—' ?></td>
          <td><?= View::e($o['reference']) ?></td>
          <td>
            <?php if (!empty($it['retenu'])): ?>
              <span class="badge" style="background:#1FA463;color:#fff">✓ Retenu pour cet article</span>
            <?php elseif ($peutValider): ?>
              <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/retenir-article" style="margin:0">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <input type="hidden" name="offre_id" value="<?= (int) $o['id'] ?>">
                <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                <input type="hidden" name="motif_decision" value="Meilleur prix pour cet article">
                <button type="submit" class="btn btn-sm"><?= count($bloc['lignes']) === 1 ? 'Retenir cette offre' : 'Retenir pour cet article' ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($gagnant && $peutValider): ?>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/retenir-article" style="margin-top:8px">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="hidden" name="article_id" value="<?= (int) $aid ?>">
        <button type="submit" name="action" value="annuler" class="btn btn-sm btn-secondary">Annuler le choix pour cet article</button>
      </form>
    <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  <?php if (empty($masquerGlobal)): ?>
  <?php if (!empty($parArticleDispo)): ?><h2 style="margin:22px 0 6px">Comparaison globale des offres</h2><?php endif; ?>

  <?php
    // Tableau transposé (décision Marie Laure, 29/09) : les critères en
    // lignes, chaque offre en colonne — pour comparer toutes les offres sur
    // un même critère en lisant horizontalement.
    $labelCol = 220;
  ?>
  <div style="overflow-x:auto">
  <table style="table-layout:fixed">
    <colgroup>
      <col style="width:<?= $labelCol ?>px">
      <?php foreach ($offres as $o): ?><col style="width:200px"><?php endforeach; ?>
    </colgroup>
    <thead>
      <tr>
        <th style="position:sticky;left:0;background:#fafbfd;z-index:1">Critère</th>
        <?php foreach ($offres as $o): ?>
          <th style="<?= $o['statut'] === 'retenue' ? 'background:#f0fdf4' : '' ?>">
            <div style="text-transform:none;font-size:13.5px;font-weight:700;color:#111"><?= View::e($o['fournisseur_nom']) ?></div>
            <div style="text-transform:none;font-weight:400;color:#888">Réf. <?= View::e($o['reference']) ?></div>
            <?php if (!empty($o['created_by'])): ?>
              <div style="text-transform:none;font-weight:400;color:#888">Saisie par <?= View::e(\App\Models\Utilisateur::nameOf((int) $o['created_by'])) ?></div>
            <?php endif; ?>
          </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <tr>
        <th style="position:sticky;left:0;background:#fff"><?= View::e($libelleFournisseur) ?> — statut</th>
        <?php foreach ($offres as $o): ?>
          <td style="<?= $o['statut'] === 'retenue' ? 'background:#f0fdf4' : '' ?>">
            <span class="badge <?= $o['statut'] === 'retenue' ? 'badge-green' : ($o['statut'] === 'rejetee' ? 'badge-red' : 'badge-blue') ?>">
              <?= Offre::STATUTS[$o['statut']] ?? $o['statut'] ?>
            </span>
          </td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Conformité</th>
        <?php foreach ($offres as $o): ?>
          <td><?= $o['conformite_technique'] ? View::e(Offre::CONFORMITE[$o['conformite_technique']] ?? $o['conformite_technique']) : afficheOuNonRenseigne(null) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Devise</th>
        <?php foreach ($offres as $o): ?>
          <td><?= View::e($o['devise'] ?: '—') ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Prix marchandises</th>
        <?php foreach ($offres as $o):
          $devise = $o['devise'] ?: '—';
          $estMeilleurPrix = isset($meilleurPrixParDevise[$devise]) && (float) $o['montant_total'] === $meilleurPrixParDevise[$devise];
        ?>
          <td style="<?= $estMeilleurPrix ? 'background:#dcfce7;font-weight:600' : '' ?>"><?= number_format((float) $o['montant_total'], 2, ',', ' ') ?> <?= View::e($devise) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Coût rendu estimé</th>
        <?php foreach ($offres as $o):
          $devise = $o['devise'] ?: '—';
          $coutRendu = Offre::coutRendu($o);
          $estMeilleurCoutRendu = isset($meilleurCoutRenduParDevise[$devise]) && $coutRendu === $meilleurCoutRenduParDevise[$devise];
        ?>
          <td style="<?= $estMeilleurCoutRendu ? 'background:#dcfce7;font-weight:600' : '' ?>"><?= number_format($coutRendu, 2, ',', ' ') ?> <?= View::e($devise) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Incoterm</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['incoterm_negocie']) ?></td>
        <?php endforeach; ?>
      </tr>

      <?php if ($typeDossier === 'transport_logistique'): ?>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Mode de transport</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['mode_transport'] ?? null) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php elseif ($typeDossier === 'prestation_entreprise'): ?>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Périmètre de mission proposé</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['perimetre_mission'] ?? null) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php endif; ?>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Délai</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['delai_livraison']) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Validité de l'offre</th>
        <?php foreach ($offres as $o): ?>
          <td><?= $o['validite_offre'] ? date('d/m/Y', strtotime($o['validite_offre'])) : afficheOuNonRenseigne(null) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Note <?= View::e(mb_strtolower($libelleFournisseur)) ?></th>
        <?php foreach ($offres as $o):
          $noteFournisseur = \App\Models\Fournisseur::noteGlobale($o);
        ?>
          <td><?= $noteFournisseur !== null ? number_format($noteFournisseur, 1) . '/5' : afficheOuNonRenseigne(null) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr><td colspan="<?= count($offres) + 1 ?>" style="background:#fafbfc;padding:6px 14px;font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.04em">Coût rendu — détail des frais complémentaires</td></tr>

      <?php foreach ([
        'transport_montant' => 'Transport',
        'assurance_montant' => 'Assurance',
        'emballage_montant' => 'Emballage',
        'douane_montant' => 'Douane / droits estimés',
        'dedouanement_montant' => 'Dédouanement',
        'autres_frais_montant' => 'Autres frais',
      ] as $champ => $label): ?>
      <tr>
        <th style="position:sticky;left:0;background:#fff"><?= $label ?></th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o[$champ] ?? null) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>

      <tr><td colspan="<?= count($offres) + 1 ?>" style="background:#fafbfc;padding:6px 14px;font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.04em">Logistique et conditions</td></tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Pays d'origine</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['pays_origine']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Lieu de départ</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['lieu_depart']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Quantité minimale</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['quantite_min']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Disponibilité</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['disponibilite']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Poids / colis / volume</th>
        <?php foreach ($offres as $o): ?>
          <td style="font-size:12px"><?= afficheOuNonRenseigne($o['poids_kg'], ' kg') ?> — <?= afficheOuNonRenseigne($o['nombre_colis']) ?> colis — <?= afficheOuNonRenseigne($o['volume_m3'], ' m³') ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Conditions de paiement</th>
        <?php foreach ($offres as $o): ?>
          <td><?= $o['conditions_paiement'] ? View::e(Client::CONDITIONS_PAIEMENT[$o['conditions_paiement']] ?? $o['conditions_paiement']) : afficheOuNonRenseigne(null) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Garantie</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['garantie']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Articles</th>
        <?php foreach ($offres as $o): ?>
          <td>
            <?php if (!empty($itemsByOffre[$o['id']])): ?>
              <details>
                <summary style="cursor:pointer;font-size:12px;color:#0B7C8A">Voir le détail (<?= count($itemsByOffre[$o['id']]) ?>)</summary>
                <table style="margin-top:8px;box-shadow:none;font-size:11.5px">
                  <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Px unit.</th><th>Montant</th></tr></thead>
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
              </details>
            <?php else: ?>
              <span style="color:#9ca3af;font-style:italic">Aucun</span>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Décision</th>
        <?php foreach ($offres as $o): ?>
          <td>
            <?php if ($o['statut'] === 'retenue'): ?>
              <span style="font-size:12px;color:#065f46;font-weight:600">✓ Retenue</span>
              <?php if (!empty($o['motif_decision'])): ?>
                <div style="font-size:11px;color:#666;margin-top:4px"><?= View::e($o['motif_decision']) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <?php if ($o['statut'] === 'rejetee'): ?>
                <span class="badge badge-gray">Écartée</span>
                <?php if (!empty($o['motif_decision'])): ?>
                  <div style="font-size:11px;color:#666;margin:4px 0"><?= View::e($o['motif_decision']) ?></div>
                <?php endif; ?>
              <?php endif; ?>

              <?php if ($decisionExistante && $peutValider && $o['statut'] === 'recue'): ?>
              <details style="margin-bottom:6px">
                <summary style="cursor:pointer" class="btn btn-sm btn-secondary">Retenir aussi…</summary>
                <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/retenir-en-plus" style="margin-top:8px;min-width:200px">
                  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                  <input type="hidden" name="offre_id" value="<?= $o['id'] ?>">
                  <div style="font-size:12px;color:#555;margin-bottom:4px">Pour des articles pris chez un autre fournisseur : cette offre s'ajoute à celle déjà retenue.</div>
                  <label style="font-size:12px">Motif (obligatoire)</label>
                  <textarea name="motif_decision" rows="2" required placeholder="ex: article X moins cher chez ce fournisseur"></textarea>
                  <button type="submit" class="btn btn-sm" style="margin-top:6px">Confirmer — retenir aussi</button>
                </form>
              </details>
              <?php endif; ?>
              <?php if (!$decisionExistante && $peutValider): ?>
              <details style="margin-bottom:6px">
                <summary style="cursor:pointer" class="btn btn-sm">Retenir…</summary>
                <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/retenir" style="margin-top:8px;min-width:200px">
                  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                  <input type="hidden" name="offre_id" value="<?= $o['id'] ?>">
                  <label style="font-size:12px">Motif de la décision (obligatoire)</label>
                  <textarea name="motif_decision" rows="2" required placeholder="ex: meilleur coût rendu, délai compatible"></textarea>
                  <button type="submit" class="btn btn-sm" style="margin-top:6px">Confirmer — retenir cette offre</button>
                </form>
              </details>
              <?php if ($o['statut'] === 'recue'): ?>
              <details style="margin-bottom:6px">
                <summary style="cursor:pointer" class="btn btn-sm btn-secondary">Écarter…</summary>
                <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/ecarter" style="margin-top:8px;min-width:200px">
                  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                  <input type="hidden" name="offre_id" value="<?= $o['id'] ?>">
                  <label style="font-size:12px">Motif (obligatoire)</label>
                  <textarea name="motif_ecart" rows="2" required placeholder="ex: prix trop élevé, délai trop long"></textarea>
                  <button type="submit" class="btn btn-sm btn-secondary" style="margin-top:6px">Confirmer — écarter cette offre</button>
                </form>
              </details>
              <?php else: ?>
              <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/reexaminer" style="margin-bottom:6px">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <input type="hidden" name="offre_id" value="<?= $o['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Remettre en comparaison</button>
              </form>
              <?php endif; ?>
              <?php elseif ($o['statut'] === 'recue'): ?>
                <span style="color:#9ca3af;font-style:italic">—</span>
              <?php endif; ?>

              <?php if (\App\Core\Auth::canWrite()): ?>
              <div style="font-size:12px;display:flex;gap:10px;flex-wrap:wrap">
                <a href="/index.php?r=offres/<?= (int) $o['id'] ?>/modifier">Modifier</a>
                <?php if (!empty($o['consultation_id'])): ?><a href="/index.php?r=consultations/<?= (int) $o['consultation_id'] ?>/offres/nouvelle&version_de=<?= (int) $o['id'] ?>">+ Nouvelle version</a><?php endif; ?>
              </div>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <div class="card" style="font-size:13px;color:#555">Pas de comparaison globale : les offres portent sur des articles différents, donc il n'y a rien à comparer entre elles. Chaque article n'ayant qu'une offre se retient directement ci-dessus.</div>
  <?php endif; ?>
<?php endif; ?>
