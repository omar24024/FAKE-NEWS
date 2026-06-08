<?php
/**
 * Génération de rapports PDF pour une publication analysée.
 */

function resolveChromiumExecutable(): ?string {
    $candidates = [
        dotEnvValue('CHROMIUM_EXECUTABLE'),
        '/home/kali/gbert_project/playwright-browsers/chromium_headless_shell-1223/chrome-headless-shell-linux64/chrome-headless-shell',
        '/home/kali/gbert_project/playwright-browsers/chromium-1223/chrome-linux64/chrome',
    ];
    foreach ($candidates as $path) {
        if ($path && is_file($path) && is_executable($path)) {
            return $path;
        }
    }
    $base = dotEnvValue('PLAYWRIGHT_BROWSERS_PATH', '/home/kali/gbert_project/playwright-browsers');
    if ($base) {
        foreach (glob($base . '/chromium_headless_shell-*/chrome-headless-shell-linux64/chrome-headless-shell') ?: [] as $p) {
            if (is_executable($p)) return $p;
        }
        foreach (glob($base . '/chromium-*/chrome-linux64/chrome') ?: [] as $p) {
            if (is_executable($p)) return $p;
        }
    }
    return null;
}

function buildPostReportHtml(array $post, ?array $user = null): string {
    $cat = $post['category'] ?? 'reliable';
    $risk = $post['risk_level'] ?? 'low';
    $author = postAuthorName($post);
    $conf = number_format((float)($post['confidence_score'] ?? 0), 1);
    $generatedBy = htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Analyste');
    $generatedAt = date('d/m/Y H:i');

    $catColor = match($cat) {
        'fake_news' => '#c62828',
        'disinformation' => '#e65100',
        'hate_speech' => '#6a1b9a',
        'cyberbullying' => '#ad1457',
        default => '#2e7d32',
    };

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Rapport publication #<?= (int)$post['id'] ?></title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 12px; color: #1a1a2e; margin: 0; padding: 28px 32px; line-height: 1.55; }
    h1 { font-size: 20px; margin: 0 0 4px; color: #1a237e; }
    h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #283593; border-bottom: 2px solid #e8eaf6; padding-bottom: 6px; margin: 22px 0 12px; }
    .header { border-bottom: 3px solid #1a237e; padding-bottom: 14px; margin-bottom: 18px; }
    .subtitle { color: #5c6bc0; font-size: 12px; }
    .meta { color: #666; font-size: 11px; margin-top: 6px; }
    .badge { display: inline-block; padding: 4px 12px; border-radius: 99px; font-weight: 700; font-size: 11px; color: #fff; background: <?= $catColor ?>; }
    .score { font-size: 36px; font-weight: 800; color: #1a237e; margin: 8px 0 2px; }
    .grid { display: table; width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .grid-row { display: table-row; }
    .grid-label, .grid-value { display: table-cell; padding: 5px 0; border-bottom: 1px solid #eee; vertical-align: top; }
    .grid-label { width: 38%; color: #666; font-size: 11px; }
    .grid-value { font-weight: 600; }
    .content-box { background: #f5f7fb; border: 1px solid #dde3f0; border-radius: 8px; padding: 14px; white-space: pre-wrap; word-wrap: break-word; font-size: 12px; }
    .policy, .law { margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    .code { display: inline-block; background: #e8eaf6; color: #283593; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; margin-right: 6px; }
    .fb-code { background: #1877F2; color: #fff; }
    .kw { display: inline-block; background: #fff3e0; border: 1px solid #ffe0b2; padding: 3px 10px; border-radius: 99px; margin: 3px 4px 3px 0; font-size: 11px; }
    .disclaimer { background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px; padding: 12px; font-size: 11px; color: #5d4037; margin-top: 20px; }
    .gbert { background: linear-gradient(135deg,#1a237e,#283593); color: #fff; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; font-size: 11px; }
    a { color: #1877F2; word-break: break-all; }
    .footer { margin-top: 24px; font-size: 10px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 10px; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Rapport d'analyse — Publication #<?= (int)$post['id'] ?></h1>
    <div class="subtitle">Plateforme Détection du Fake News · GBERT Hassaniya</div>
    <div class="meta">Généré le <?= $generatedAt ?> par <?= $generatedBy ?></div>
  </div>

  <div class="disclaimer">
    <strong>Aide à l'analyse —</strong> Ce rapport assiste l'analyste humain. Les résultats IA ne constituent pas une décision automatique et doivent être validés manuellement.
  </div>

  <h2>Analyse IA</h2>
  <?php if (isGbertModel($post['model_used'] ?? null)): ?>
  <div class="gbert"><strong>GBERT Hassaniya</strong> — AraBERT + AraGPT2 · Darija &amp; Hassaniya</div>
  <?php endif; ?>
  <div style="text-align:center;margin:12px 0 16px;">
    <span class="badge"><?= htmlspecialchars(categoryLabel($cat)) ?></span>
    <div class="score"><?= $conf ?>%</div>
    <div style="color:#666;font-size:11px;">Score de confiance</div>
  </div>
  <div class="grid">
    <div class="grid-row"><div class="grid-label">Niveau de risque</div><div class="grid-value"><?= htmlspecialchars(riskLabel($risk)) ?></div></div>
    <div class="grid-row"><div class="grid-label">Modèle IA</div><div class="grid-value"><?= htmlspecialchars(modelLabel($post['model_used'] ?? null)) ?></div></div>
    <?php if (!empty($post['model_used'])): ?>
    <div class="grid-row"><div class="grid-label">Identifiant technique</div><div class="grid-value" style="font-family:monospace;font-size:10px;"><?= htmlspecialchars($post['model_used']) ?></div></div>
    <?php endif; ?>
  </div>

  <h2>Publication</h2>
  <div class="grid">
    <div class="grid-row"><div class="grid-label">Auteur</div><div class="grid-value"><?= htmlspecialchars($author) ?></div></div>
    <div class="grid-row"><div class="grid-label">Type de contenu</div><div class="grid-value"><?= htmlspecialchars(contentTypeLabel($post['content_type'] ?? 'text')) ?></div></div>
    <div class="grid-row"><div class="grid-label">Statut du lien</div><div class="grid-value"><?= htmlspecialchars(linkStatusLabel($post['link_status'] ?? 'unknown')) ?></div></div>
    <div class="grid-row"><div class="grid-label">Abonnés</div><div class="grid-value"><?= number_format((int)($post['followers_count'] ?? 0)) ?></div></div>
    <div class="grid-row"><div class="grid-label">Date publication</div><div class="grid-value"><?= htmlspecialchars(formatDate($post['published_at'] ?? '')) ?></div></div>
    <div class="grid-row"><div class="grid-label">J'aime</div><div class="grid-value"><?= number_format((int)($post['likes_count'] ?? 0)) ?></div></div>
    <div class="grid-row"><div class="grid-label">Partages</div><div class="grid-value"><?= number_format((int)($post['shares_count'] ?? 0)) ?></div></div>
    <div class="grid-row"><div class="grid-label">Commentaires</div><div class="grid-value"><?= number_format((int)($post['comments_count'] ?? 0)) ?></div></div>
    <div class="grid-row"><div class="grid-label">Date extraction</div><div class="grid-value"><?= htmlspecialchars(formatDate($post['fetched_at'] ?? '')) ?></div></div>
    <div class="grid-row"><div class="grid-label">URL Facebook</div><div class="grid-value"><a href="<?= htmlspecialchars($post['fb_post_url'] ?? '') ?>"><?= htmlspecialchars($post['fb_post_url'] ?? '') ?></a></div></div>
  </div>
  <p style="font-weight:600;margin:14px 0 6px;">Contenu extrait</p>
  <div class="content-box"><?= htmlspecialchars($post['content'] ?? '') ?></div>
  <?php if (!empty($post['image_url'])): ?>
  <p style="margin-top:10px;font-size:11px;color:#666;">Image : <a href="<?= htmlspecialchars($post['image_url']) ?>"><?= htmlspecialchars($post['image_url']) ?></a></p>
  <?php endif; ?>

  <?php if (!empty($post['comments'])): ?>
  <h2>Commentaires analysés (<?= count($post['comments']) ?>)</h2>
  <?php foreach ($post['comments'] as $comment): ?>
    <div class="policy" style="border-left:3px solid #283593;padding-left:10px;">
      <strong><?= htmlspecialchars($comment['author_name'] ?? 'Anonyme') ?></strong>
      <?php if (!empty($comment['is_analyzed'])): ?>
        <span class="code"><?= htmlspecialchars(categoryLabel($comment['category'] ?? 'reliable')) ?> · <?= number_format((float)($comment['confidence_score'] ?? 0), 0) ?>%</span>
      <?php endif; ?>
      <p style="margin:6px 0 0;font-size:11px;color:#444;white-space:pre-wrap;"><?= htmlspecialchars($comment['content'] ?? '') ?></p>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($post['keywords'])): ?>
  <h2>Mots-clés détectés</h2>
  <div>
    <?php foreach ($post['keywords'] as $kw): ?>
      <span class="kw"><?= htmlspecialchars($kw['keyword']) ?> (<?= number_format((float)$kw['weight'] * 100, 0) ?>%)</span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($post['facebook_policies'])): ?>
  <h2>Politiques Facebook (Meta)</h2>
  <?php foreach ($post['facebook_policies'] as $policy): ?>
    <div class="policy">
      <span class="code fb-code"><?= htmlspecialchars($policy['policy_code']) ?></span>
      <strong><?= htmlspecialchars($policy['title']) ?></strong>
      <p style="margin:6px 0 0;font-size:11px;color:#444;"><?= htmlspecialchars($policy['description'] ?? '') ?></p>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($post['legal_refs'])): ?>
  <h2>Textes juridiques (Mauritanie)</h2>
  <?php foreach ($post['legal_refs'] as $law): ?>
    <div class="law">
      <span class="code"><?= htmlspecialchars($law['reference_code']) ?></span>
      <strong><?= htmlspecialchars($law['title']) ?></strong>
      <p style="margin:6px 0 0;font-size:11px;color:#444;"><?= htmlspecialchars($law['description'] ?? '') ?></p>
      <div style="font-size:10px;color:#888;margin-top:4px;"><?= htmlspecialchars($law['source'] ?? '') ?> · <?= (int)($law['year'] ?? 0) ?: '' ?></div>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="footer">
    <?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(APP_VERSION) ?> — Rapport généré automatiquement
  </div>
</body>
</html>
    <?php
    return (string)ob_get_clean();
}

function htmlToPdf(string $html): ?string {
    $chrome = resolveChromiumExecutable();
    if (!$chrome) {
        return null;
    }

    $tmpDir = sys_get_temp_dir();
    $htmlFile = tempnam($tmpDir, 'rpt_') . '.html';
    $pdfFile = tempnam($tmpDir, 'rpt_') . '.pdf';
    file_put_contents($htmlFile, $html);

    $cmd = sprintf(
        '%s --headless --disable-gpu --no-sandbox --print-to-pdf=%s %s 2>&1',
        escapeshellarg($chrome),
        escapeshellarg($pdfFile),
        escapeshellarg('file://' . $htmlFile)
    );
    exec($cmd, $output, $code);
    @unlink($htmlFile);

    if ($code !== 0 || !is_file($pdfFile) || filesize($pdfFile) < 100) {
        @unlink($pdfFile);
        return null;
    }

    $pdf = file_get_contents($pdfFile);
    @unlink($pdfFile);
    return $pdf !== false ? $pdf : null;
}

function generatePostReportPdf(array $post, ?array $user = null): ?string {
    return htmlToPdf(buildPostReportHtml($post, $user));
}
