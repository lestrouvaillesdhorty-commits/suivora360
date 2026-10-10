<?php
use App\Core\View;
use App\Models\Fournisseur;
use App\Models\FournisseurPieceJointe;

$p = $performance;
$libPeriode = ['tout' => 'Toutes périodes', 'annee' => 'Année en cours', '12m' => '12 derniers mois'][$periode];
$carte = function (string $titre, ?string $valeur, string $detail, string $regle, string $vide = 'Données insuffisantes') {
    ?>
    <div class="stat-tile">
      <div class="label" style="margin:0 0 6px;font-weight:600;color:#333"><?= View::e($titre) ?></div>
      <?php if ($valeur !== null): ?><div class="value"><?= View::e($valeur) ?></div>
      <?php else: ?><div class="value" style="font-size:16px;color:#999"><?= View::e($vide) ?></div><?php endif; ?>
      <div class="label"><?= View::e($detail) ?></div>
      <details style="margin-top:6px"><summary style="cursor:pointer;font-size:12px;color:var(--primary)">Règle de calcul</summary><div style="font-size:12px;color:#666;margin-top:4px"><?= View::e($regle) ?></div></details>
    </div>
    <?php
};
$dernierQual = $qualifications[0] ?? null;
$qual = Fournisseur::qualificationDe($fournisseur);
$mediane = $p['delai_median']['valeur'];
?>
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
  <span style="font-size:13px;color:#666">Période des indicateurs :</span>
  <?php foreach (['tout' => 'Toutes périodes', 'annee' => 'Année en cours', '12m' => '12 derniers mois'] as $code => $lib): ?>
    <a class="btn btn-sm <?= $periode === $code ? '' : 'btn-secondary' ?>" href="<?= $base ?>&onglet=evaluation<?= $code === 'tout' ? '' : '&periode=' . $code ?>"><?= View::e($lib) ?></a>
  <?php endforeach; ?>
</div>

<div class="grid-3" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
  <?php
  $carte('Réponses aux consultations', $p['taux_reponse']['valeur'] !== null ? $p['taux_reponse']['valeur'] . ' %' : null, $p['taux_reponse']['detail'],
      'Consultations closes (réponse reçue ou classée « sans réponse ») ; part ayant reçu une réponse. Les consultations encore ouvertes ne sont pas comptées. Période : date de création de la consultation. Source : consultations du fournisseur.');
  $carte('Délai médian de réponse', $mediane !== null ? (rtrim(rtrim(number_format($mediane, 1, ',', ''), '0'), ',') . ' jour' . ($mediane >= 2 ? 's' : '')) : null, $p['delai_median']['detail'],
      'Médiane, en jours, entre la date d’envoi de la consultation et la date de la première offre reçue. Écartées : date d’envoi absente ou incohérente.');
  $carte('Livraisons dans le délai', $p['livraison_delai']['valeur'] !== null ? $p['livraison_delai']['valeur'] . ' %' : null, $p['livraison_delai']['detail'],
      'Commandes issues des offres retenues dont l’étape « livraison » est terminée : date réelle ≤ date prévue. Exclues : commandes non livrées, annulées, ou sans date prévue / réelle. Source : suivi de commande du dossier.');
  $carte('Réceptions conformes', null, 'Aucune réception enregistrée dans l’application.', 'Nécessite l’enregistrement des réceptions (quantités reçues, réserves, non-conformités) : à connecter.', 'Non disponible');
  $carte('Incidents ouverts', null, 'Aucun incident enregistré dans l’application.', 'Nécessite un registre des incidents fournisseur : à connecter.', 'Non disponible');
  ?>
</div>
<div style="font-size:12px;color:#888;margin:-8px 0 16px"><?= View::e($libPeriode) ?>. « Données insuffisantes » = aucune opération mesurable sur la période ; un indicateur inconnu n’est jamais remplacé par 0.</div>

<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Qualification</h2>
      <div style="margin-bottom:10px">
        Niveau actuel : <span class="badge <?= Fournisseur::QUALIFICATION_BADGES[$qual] ?>"><?= View::e(Fournisseur::QUALIFICATIONS[$qual]) ?></span>
        <?php if (!empty($fournisseur['reexamen_le'])): ?> · réexamen prévu le <strong><?= date('d/m/Y', strtotime($fournisseur['reexamen_le'])) ?></strong><?= $fournisseur['reexamen_le'] < date('Y-m-d') ? ' <span class="badge badge-red">dépassé</span>' : '' ?><?php endif; ?>
      </div>
      <div style="font-size:12px;color:#888;margin-bottom:10px">La qualification est indépendante du statut actif/inactif : un fournisseur « non retenu » reste actif et garde toutes ses relations, et un refus sur une consultation ne le déqualifie jamais automatiquement.</div>

      <?php if (empty($qualifications)): ?>
        <div class="empty-state">Aucune décision de qualification enregistrée.</div>
      <?php else: foreach ($qualifications as $q): ?>
        <div class="info-row" style="display:block">
          <span class="badge <?= Fournisseur::QUALIFICATION_BADGES[$q['decision']] ?>"><?= View::e(Fournisseur::QUALIFICATIONS[$q['decision']] ?? $q['decision']) ?></span>
          <strong><?= date('d/m/Y', strtotime($q['date_decision'])) ?></strong> — <?= View::e($q['evaluateur_nom'] ?? 'Reprise automatique') ?>
          <?php if (!empty($q['date_reexamen'])): ?> · réexamen le <?= date('d/m/Y', strtotime($q['date_reexamen'])) ?><?php endif; ?>
          <?php if (!empty($q['commentaire'])): ?><div style="font-size:13px;margin-top:4px"><?= nl2br(View::e($q['commentaire'])) ?></div><?php endif; ?>
          <?php if (!empty($q['criteres_liste'])): ?>
            <details style="margin-top:4px"><summary style="cursor:pointer;font-size:12px;color:var(--primary)">Critères examinés (<?= count($q['criteres_liste']) ?>)</summary>
              <ul style="margin:6px 0 0 18px;font-size:13px">
                <?php foreach ($q['criteres_liste'] as $c): ?><li><?= View::e($c['libelle']) ?> — <strong><?= View::e(Fournisseur::RESULTATS_CRITERE[$c['resultat']] ?? $c['resultat']) ?></strong></li><?php endforeach; ?>
              </ul></details>
          <?php endif; ?>
          <?php if (!empty($q['justificatifs'])): $ids = array_filter(explode(',', $q['justificatifs'])); ?>
            <div style="font-size:12px;margin-top:4px">Justificatifs :
              <?php foreach ($pieces as $pj): if (in_array((string) $pj['id'], $ids, true)): ?><a href="<?= $base ?>/pieces/<?= (int) $pj['id'] ?>/telecharger"><?= View::e($pj['nom_original']) ?></a> <?php endif; endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>

      <?php if ($peutQualifier && $schemaPret): ?>
      <details style="margin-top:12px"><summary style="cursor:pointer;font-weight:600;color:var(--primary)">+ Enregistrer une décision de qualification</summary>
        <form method="post" action="<?= $base ?>/qualification" style="margin-top:10px">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <div style="font-size:13px;margin-bottom:6px">Critères adaptés à : <?= $types ? View::e(implode(', ', array_map(fn($t) => Fournisseur::TYPES[$t], $types))) : 'tous types (aucun type renseigné)' ?></div>
          <?php foreach ($criteresQualification as $code => $libelle): ?>
            <div class="form-row" style="align-items:center;margin-bottom:4px">
              <div style="flex:2;font-size:14px"><?= View::e($libelle) ?></div>
              <div style="flex:1"><select name="criteres[<?= $code ?>]" aria-label="<?= View::e($libelle) ?>"><option value="">— Non examiné —</option>
                <?php foreach (Fournisseur::RESULTATS_CRITERE as $rc => $rl): ?><option value="<?= $rc ?>"><?= View::e($rl) ?></option><?php endforeach; ?></select></div>
            </div>
          <?php endforeach; ?>
          <div class="form-row" style="margin-top:10px">
            <div class="form-group"><label for="decision">Décision *</label>
              <select id="decision" name="decision" required><option value="">— Choisir —</option>
                <?php foreach (Fournisseur::QUALIFICATIONS as $code => $lib): ?><option value="<?= $code ?>"><?= View::e($lib) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="date_reexamen">Réexamen à prévoir le (facultatif)</label><input type="date" id="date_reexamen" name="date_reexamen"></div>
          </div>
          <div class="form-group"><label for="commentaire_q">Commentaire (obligatoire pour « Validé » et « Non retenu »)</label><textarea id="commentaire_q" name="commentaire" rows="3"></textarea></div>
          <?php if (!empty($pieces)): ?>
          <div class="form-group"><label>Justificatifs (documents déjà ajoutés)</label>
            <div style="display:flex;flex-direction:column;gap:4px">
              <?php foreach ($pieces as $pj): ?><label style="font-weight:400;display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="justificatifs[]" value="<?= (int) $pj['id'] ?>"> <?= View::e($pj['nom_original']) ?> <span style="color:#888">(<?= View::e(FournisseurPieceJointe::CATEGORIES[$pj['categorie']] ?? $pj['categorie']) ?>)</span></label><?php endforeach; ?>
            </div></div>
          <?php endif; ?>
          <button type="submit" class="btn btn-sm">Enregistrer la décision</button>
        </form>
      </details>
      <?php elseif ($peutEcrire && $schemaPret): ?>
        <div style="font-size:12px;color:#888;margin-top:10px">Les décisions de qualification sont réservées aux rôles Propriétaire, Admin d’organisation et Achats.</div>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Évaluations documentées</h2>
      <?php if (empty($evaluations)): ?>
        <div class="empty-state">Aucune évaluation manuelle enregistrée.</div>
      <?php else: ?>
      <table class="responsive-cards">
        <thead><tr><th>Date</th><th>Dossier</th><th>Critère</th><th>Résultat</th><th>Évaluateur</th></tr></thead>
        <tbody>
        <?php foreach ($evaluations as $e): $lib = Fournisseur::CRITERES_EVALUATION[$e['critere']][0] ?? $e['critere']; ?>
          <tr>
            <td data-label="Date"><?= date('d/m/Y', strtotime($e['date_evaluation'])) ?></td>
            <td data-label="Dossier"><?= !empty($e['dossier_id']) ? '<a href="/index.php?r=dossiers/' . (int) $e['dossier_id'] . '">' . View::e($e['dossier_reference']) . '</a>' : '—' ?></td>
            <td data-label="Critère"><?= View::e($lib) ?><?php if (!empty($e['commentaire'])): ?><br><span style="font-size:12px;color:#666"><?= View::e($e['commentaire']) ?></span><?php endif; ?><?php if (!empty($e['piece_nom'])): ?><br><span style="font-size:12px;color:#888">Justificatif : <?= View::e($e['piece_nom']) ?></span><?php endif; ?></td>
            <td data-label="Résultat"><span class="badge <?= Fournisseur::RESULTATS_EVALUATION_BADGES[$e['resultat']] ?? 'badge-gray' ?>"><?= View::e(Fournisseur::RESULTATS_EVALUATION[$e['resultat']] ?? $e['resultat']) ?></span></td>
            <td data-label="Évaluateur"><?= View::e($e['evaluateur_nom'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
      <div style="font-size:12px;color:#888;margin-top:8px">Aucune note globale n’est inventée : seules ces évaluations (et les appréciations chiffrées de la fiche, si renseignées) sont affichées.</div>

      <?php if ($peutEcrire && $schemaPret): ?>
      <details style="margin-top:12px"><summary style="cursor:pointer;font-weight:600;color:var(--primary)">+ Ajouter une évaluation</summary>
        <form method="post" action="<?= $base ?>/evaluations" style="margin-top:10px">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <div class="form-group"><label for="ev_dossier">Dossier concerné (facultatif)</label>
            <select id="ev_dossier" name="dossier_id"><option value="">— Aucun —</option>
              <?php $vus = []; foreach ($consultations as $c): if (isset($vus[$c['dossier_id']])) { continue; } $vus[$c['dossier_id']] = 1; ?><option value="<?= (int) $c['dossier_id'] ?>"><?= View::e($c['dossier_reference']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="form-row">
            <div class="form-group"><label for="ev_critere">Critère *</label>
              <select id="ev_critere" name="critere" required><option value="">— Choisir —</option>
                <?php foreach ($criteresEvaluation as $code => $lib): ?><option value="<?= $code ?>"><?= View::e($lib) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="ev_resultat">Résultat *</label>
              <select id="ev_resultat" name="resultat" required><option value="">— Choisir —</option>
                <?php foreach (Fournisseur::RESULTATS_EVALUATION as $code => $lib): ?><option value="<?= $code ?>"><?= View::e($lib) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label for="ev_date">Date</label><input type="date" id="ev_date" name="date_evaluation" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group"><label for="ev_piece">Justificatif (document déjà ajouté)</label>
              <select id="ev_piece" name="piece_id"><option value="">— Aucun —</option>
                <?php foreach ($pieces as $pj): ?><option value="<?= (int) $pj['id'] ?>"><?= View::e($pj['nom_original']) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="form-group"><label for="ev_comm">Commentaire</label><textarea id="ev_comm" name="commentaire" rows="2"></textarea></div>
          <button type="submit" class="btn btn-sm">Enregistrer l’évaluation</button>
        </form>
      </details>
      <?php endif; ?>
    </div>
  </div>
</div>
