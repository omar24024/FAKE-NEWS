<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/sync_service.php';
requireLogin();
$user = getCurrentUser();
$db = getDB();
$currentPage = 'parametres';
$syncStatus = getSyncStatus($db);

// Get API settings
$settings = [];
$rows = $db->query("SELECT setting_key, setting_value FROM api_settings")->fetchAll();
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['fb_app_id', 'fb_app_secret', 'fb_access_token', 'confidence_threshold', 'auto_sync_interval'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $stmt = $db->prepare("INSERT INTO api_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
            $stmt->execute([$k, $_POST[$k], $_POST[$k]]);
        }
    }
    $success = 'Paramètres enregistrés avec succès.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Paramètres — Détection du Fake News</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">Paramètres</div>
        <div class="page-subtitle">Configuration de la plateforme et des intégrations</div>
      </div>
      <div class="topbar-right">
        <div class="user-pill">
          <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?></div>
          <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
        </div>
      </div>
    </div>
    <div class="content">
      <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:16px;"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <div style="max-width:680px;display:flex;flex-direction:column;gap:16px;">

        <!-- Facebook API -->
        <div class="detail-card">
          <div class="detail-card-title">Facebook Graph API</div>
          <form method="POST">
            <div class="form-group">
              <label class="form-label">App ID Facebook</label>
              <input class="form-input" type="text" name="fb_app_id" value="<?= htmlspecialchars($settings['fb_app_id'] ?? '') ?>" placeholder="Votre App ID">
            </div>
            <div class="form-group">
              <label class="form-label">App Secret Facebook</label>
              <input class="form-input" type="password" name="fb_app_secret" value="<?= htmlspecialchars($settings['fb_app_secret'] ?? '') ?>" placeholder="Votre App Secret">
            </div>
            <div class="form-group">
              <label class="form-label">Access Token</label>
              <input class="form-input" type="text" name="fb_access_token" value="<?= htmlspecialchars($settings['fb_access_token'] ?? '') ?>" placeholder="Token d'accès Facebook">
              <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">Générez un token long via <a href="https://developers.facebook.com/tools/explorer" target="_blank" style="color:var(--accent);">Graph API Explorer</a></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="form-group">
                <label class="form-label">Seuil de confiance (%)</label>
                <input class="form-input" type="number" name="confidence_threshold" min="50" max="99" value="<?= htmlspecialchars($settings['confidence_threshold'] ?? '70') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Sync auto (secondes)</label>
                <input class="form-input" type="number" name="auto_sync_interval" min="300" value="<?= htmlspecialchars($settings['auto_sync_interval'] ?? '3600') ?>">
              </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
              <button type="submit" class="btn btn-primary">Enregistrer les paramètres</button>
              <button type="button" class="btn btn-ghost" id="graphTestBtn" onclick="testGraphApi()">Tester Graph API</button>
            </div>
          </form>
          <div id="graphTestResult" style="display:none;margin-top:14px;padding:12px 14px;border-radius:var(--radius-sm);font-size:13px;line-height:1.6;"></div>
          <p style="font-size:11.5px;color:var(--text-muted);margin:12px 0 0;line-height:1.6;">
            Le test appelle <code style="font-family:var(--mono);">GET /me</code> sur Meta. L'extraction des publications utilise toujours le <strong>Scraper OSINT</strong>.
          </p>
        </div>

        <!-- Sync automatique Pages surveillées -->
        <div class="detail-card">
          <div class="detail-card-title">Synchronisation automatique</div>
          <p style="font-size:12.5px;color:var(--text-muted);line-height:1.6;margin:0 0 14px;">
            Importe les nouvelles publications des Pages Facebook marquées <strong>surveillées</strong> dans Comptes.
            Planifiez le cron ou lancez une sync manuelle ci-dessous.
          </p>
          <div class="meta-row">
            <span class="meta-label">Pages surveillées</span>
            <span class="meta-value" id="syncMonitoredCount"><?= (int)$syncStatus['monitored_pages_count'] ?></span>
          </div>
          <div class="meta-row">
            <span class="meta-label">Dernière sync</span>
            <span class="meta-value" id="syncLastRun"><?= $syncStatus['last_run_at'] ? htmlspecialchars(formatDate($syncStatus['last_run_at'])) : '—' ?></span>
          </div>
          <div class="meta-row">
            <span class="meta-label">Prochaine sync (auto)</span>
            <span class="meta-value" id="syncNextRun"><?= $syncStatus['next_run_at'] ? htmlspecialchars(formatDate($syncStatus['next_run_at'])) : '—' ?></span>
          </div>
          <div class="meta-row">
            <span class="meta-label">Intervalle</span>
            <span class="meta-value"><?= (int)$syncStatus['interval_seconds'] ?> s</span>
          </div>
          <?php if (!empty($syncStatus['last_result'])): ?>
          <div class="meta-row">
            <span class="meta-label">Dernier résultat</span>
            <span class="meta-value" style="font-size:12px;">
              <?= (int)($syncStatus['last_result']['imported_count'] ?? 0) ?> importée(s),
              <?= (int)($syncStatus['last_result']['analyzed_count'] ?? 0) ?> analysée(s)
            </span>
          </div>
          <?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
            <button type="button" class="btn btn-primary" id="syncNowBtn" onclick="runSyncNow()">Synchroniser maintenant</button>
            <a href="<?= APP_URL ?>/pages/comptes.php" class="btn btn-ghost">Gérer les Pages surveillées</a>
          </div>
          <div id="syncResult" style="display:none;margin-top:14px;padding:12px 14px;border-radius:var(--radius-sm);font-size:13px;line-height:1.6;"></div>
          <p style="font-size:11.5px;color:var(--text-muted);margin:12px 0 0;line-height:1.6;">
            Cron serveur : <code style="font-family:var(--mono);font-size:11px;">0 * * * * php <?= realpath(__DIR__ . '/../cron/sync_monitored_pages.php') ?: 'cron/sync_monitored_pages.php' ?></code>
          </p>
        </div>

        <!-- Info système -->
        <div class="detail-card">
          <div class="detail-card-title">Informations système</div>
          <div class="meta-row"><span class="meta-label">Version</span><span class="meta-value" style="font-family:var(--mono);"><?= APP_VERSION ?></span></div>
          <div class="meta-row"><span class="meta-label">PHP</span><span class="meta-value" style="font-family:var(--mono);"><?= PHP_VERSION ?></span></div>
          <div class="meta-row"><span class="meta-label">Base de données</span><span class="meta-value" style="font-family:var(--mono);"><?= DB_NAME ?></span></div>
          <div class="meta-row"><span class="meta-label">Modèle IA</span><span class="meta-value" style="font-family:var(--mono);"><?= htmlspecialchars($settings['ai_model'] ?? 'arabert-multilingual') ?></span></div>
        </div>

       
        <!-- AI Detection Rules -->
        <div class="detail-card">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <div class="detail-card-title">Règles de détection IA</div>
            <button type="button" class="btn btn-primary" onclick="showRuleModal()">+ Ajouter une règle</button>
          </div>
          
          <div id="ai-rules-container">
            <div style="text-align:center;padding:20px;color:var(--text-muted);">Chargement des règles...</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal pour ajouter/éditer une règle -->
<div id="rule-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
  <div style="background:var(--surface);border-radius:var(--radius);padding:24px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="font-size:18px;font-weight:600;margin-bottom:16px;">Gestion des règles de détection</div>
    
    <form id="rule-form" onsubmit="saveRule(event)">
      <div class="form-group">
        <label class="form-label">Catégorie *</label>
        <select id="rule_category" class="form-input" required>
          <option value="">-- Sélectionner --</option>
          <option value="fake_news">Fake News</option>
          <option value="disinformation">Désinformation</option>
          <option value="hate_speech">Discours haineux</option>
          <option value="misinformation">Mauvaise information</option>
          <option value="propaganda">Propagande</option>
          <option value="violence">Violence</option>
          <option value="cyberbullying">Cyberharcèlement</option>
          <option value="neutral_indicators">Indicateurs neutres</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Mot-clé / Phrase *</label>
        <input id="rule_keyword" class="form-input" type="text" placeholder="ex: 'share before deletion'" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label">Poids (0.0 - 1.0)</label>
          <input id="rule_weight" class="form-input" type="number" min="0" max="1" step="0.01" value="0.15">
        </div>
        <div class="form-group">
          <label class="form-label">Priorité</label>
          <input id="rule_priority" class="form-input" type="number" min="1" max="100" value="1">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Type de règle</label>
        <select id="rule_type" class="form-input">
          <option value="keyword">Mot-clé</option>
          <option value="phrase">Phrase</option>
          <option value="regex">Expression régulière</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="rule_description" class="form-input" rows="3" placeholder="Notes et contexte..."></textarea>
      </div>

      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeRuleModal()">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<style>
.rules-category {
  margin-bottom: 20px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.rules-category-header {
  background: var(--surface-2);
  padding: 12px 16px;
  font-weight: 600;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.rules-category-body {
  max-height: 400px;
  overflow-y: auto;
}

.rule-item {
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.rule-item:last-child {
  border-bottom: none;
}

.rule-info {
  flex: 1;
}

.rule-keyword {
  font-weight: 500;
  color: var(--text-primary);
  font-family: var(--mono);
  font-size: 13px;
}

.rule-meta {
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 4px;
}

.rule-weight {
  background: var(--accent);
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}

.rule-actions {
  display: flex;
  gap: 6px;
}

.rule-btn {
  padding: 4px 8px;
  border: 1px solid var(--border);
  background: var(--surface);
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  transition: all 0.2s;
}

.rule-btn:hover {
  background: var(--surface-2);
  border-color: var(--accent);
}

.rule-btn-delete {
  color: #ef4444;
}

.rule-btn-delete:hover {
  background: #ef4444;
  color: white;
  border-color: #ef4444;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 24px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: 0.3s;
  border-radius: 24px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background-color: var(--accent);
}

input:checked + .toggle-slider:before {
  transform: translateX(16px);
}

#rule-modal {
  animation: fadeIn 0.2s;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.category-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  background: var(--surface-2);
  color: var(--text-secondary);
}
</style>

<script>
let currentRuleId = null;
const CATEGORIES = ['fake_news', 'disinformation', 'hate_speech', 'misinformation', 'propaganda', 'violence', 'cyberbullying', 'neutral_indicators'];
const CATEGORY_LABELS = {
  'fake_news': 'Fake News',
  'disinformation': 'Désinformation',
  'hate_speech': 'Discours haineux',
  'misinformation': 'Mauvaise information',
  'propaganda': 'Propagande',
  'violence': 'Violence',
  'cyberbullying': 'Cyberharcèlement',
  'neutral_indicators': 'Indicateurs neutres'
};

// Charger les règles au chargement de la page
document.addEventListener('DOMContentLoaded', loadRules);

function loadRules() {
  fetch('<?= APP_URL ?>/api/ai_rules.php?action=list')
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        renderRules(res.data);
      }
    })
    .catch(err => console.error('Erreur:', err));
}

function renderRules(data) {
  const container = document.getElementById('ai-rules-container');
  
  if (Object.keys(data).length === 0) {
    container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">Aucune règle définie. Créez-en une pour commencer.</div>';
    return;
  }

  let html = '';
  
  CATEGORIES.forEach(category => {
    const rules = data[category] || [];
    if (rules.length === 0) return;
    
    html += `<div class="rules-category">
      <div class="rules-category-header">
        <span>${CATEGORY_LABELS[category]} (${rules.length})</span>
        <span class="category-badge" style="background:var(--accent);color:white;">${rules.reduce((s, r) => s + parseFloat(r.weight), 0).toFixed(2)}</span>
      </div>
      <div class="rules-category-body">`;
    
    rules.forEach(rule => {
      html += `<div class="rule-item">
        <div class="rule-info">
          <div class="rule-keyword">${escapeHtml(rule.keyword)}</div>
          <div class="rule-meta">
            Poids: <span class="rule-weight">${parseFloat(rule.weight).toFixed(3)}</span>
            | Type: ${rule.rule_type}
            | Priorité: ${rule.priority}
            ${rule.description ? ` | ${escapeHtml(rule.description)}` : ''}
          </div>
        </div>
        <div class="rule-actions">
          <button class="rule-btn" onclick="editRule(${rule.id}, '${escapeHtml(rule.category)}', '${escapeHtml(rule.keyword)}', ${rule.weight}, ${rule.priority}, '${rule.rule_type}', '${escapeHtml(rule.description || '')}')">Éditer</button>
          <button class="rule-btn rule-btn-delete" onclick="deleteRule(${rule.id})">Supprimer</button>
        </div>
      </div>`;
    });
    
    html += `</div></div>`;
  });
  
  container.innerHTML = html;
}

function showRuleModal() {
  currentRuleId = null;
  document.getElementById('rule-form').reset();
  document.getElementById('rule-modal').style.display = 'flex';
}

function closeRuleModal() {
  document.getElementById('rule-modal').style.display = 'none';
  currentRuleId = null;
}

function editRule(id, category, keyword, weight, priority, type, description) {
  currentRuleId = id;
  document.getElementById('rule_category').value = category;
  document.getElementById('rule_keyword').value = keyword;
  document.getElementById('rule_weight').value = weight;
  document.getElementById('rule_priority').value = priority;
  document.getElementById('rule_type').value = type;
  document.getElementById('rule_description').value = description;
  document.getElementById('rule-modal').style.display = 'flex';
}

function saveRule(e) {
  e.preventDefault();
  
  const data = {
    category: document.getElementById('rule_category').value,
    keyword: document.getElementById('rule_keyword').value,
    weight: parseFloat(document.getElementById('rule_weight').value),
    priority: parseInt(document.getElementById('rule_priority').value),
    rule_type: document.getElementById('rule_type').value,
    description: document.getElementById('rule_description').value
  };

  const url = currentRuleId 
    ? `<?= APP_URL ?>/api/ai_rules.php?action=update&id=${currentRuleId}`
    : `<?= APP_URL ?>/api/ai_rules.php?action=create`;

  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      closeRuleModal();
      loadRules();
      alert(res.message || 'Règle sauvegardée avec succès');
    } else {
      alert('Erreur: ' + (res.error || 'Erreur inconnue'));
    }
  })
  .catch(err => alert('Erreur: ' + err.message));
}

function deleteRule(id) {
  if (!confirm('Êtes-vous sûr de vouloir supprimer cette règle ?')) return;
  
  fetch(`<?= APP_URL ?>/api/ai_rules.php?action=delete&id=${id}`, { method: 'POST' })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        loadRules();
        alert('Règle supprimée');
      } else {
        alert('Erreur: ' + (res.error || 'Erreur inconnue'));
      }
    })
    .catch(err => alert('Erreur: ' + err.message));
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

async function runSyncNow() {
  const btn = document.getElementById('syncNowBtn');
  const box = document.getElementById('syncResult');
  if (!confirm('Synchroniser toutes les Pages surveillées maintenant ? Cela peut prendre plusieurs minutes.')) return;

  btn.disabled = true;
  btn.textContent = 'Synchronisation…';
  box.style.display = 'block';
  box.style.background = 'var(--surface-2)';
  box.style.border = '1px solid var(--border)';
  box.style.color = 'var(--text-secondary)';
  box.innerHTML = 'Import des publications en cours (Graph API + Scraper OSINT)…';

  try {
    const r = await fetch('<?= APP_URL ?>/api/sync.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=run&force=1'
    });
    const data = await r.json();

    if (data.success) {
      box.style.background = '#e8f5e9';
      box.style.border = '1px solid #a5d6a7';
      box.style.color = '#1b5e20';
      const queueUrl = '<?= htmlspecialchars(publicationsQueueUrl(), ENT_QUOTES) ?>';
      box.innerHTML = `
        <strong>✓ ${escapeHtml(data.message || 'Sync terminée')}</strong><br>
        ${data.imported_count ?? 0} importée(s) · ${data.analyzed_count ?? 0} analysée(s) · ${data.pages_count ?? 0} Page(s)<br>
        <a href="${queueUrl}" style="color:inherit;font-weight:700;">Voir la file d'attente →</a>
      `;
      if (data.finished_at) {
        document.getElementById('syncLastRun').textContent = data.finished_at;
      }
    } else {
      box.style.background = '#fff8e1';
      box.style.border = '1px solid #ffe082';
      box.style.color = '#e65100';
      box.innerHTML = '<strong>⚠ ' + escapeHtml(data.message || 'Sync non effectuée') + '</strong>';
    }
  } catch (err) {
    box.style.background = '#ffebee';
    box.style.border = '1px solid #ef9a9a';
    box.style.color = '#b71c1c';
    box.innerHTML = 'Erreur : ' + escapeHtml(err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Synchroniser maintenant';
  }
}

async function testGraphApi() {
  const btn = document.getElementById('graphTestBtn');
  const box = document.getElementById('graphTestResult');
  const tokenInput = document.querySelector('input[name="fb_access_token"]');
  const token = tokenInput ? tokenInput.value.trim() : '';

  btn.disabled = true;
  btn.textContent = 'Test en cours...';
  box.style.display = 'block';
  box.style.background = 'var(--surface-2)';
  box.style.border = '1px solid var(--border)';
  box.style.color = 'var(--text-secondary)';
  box.innerHTML = 'Connexion à graph.facebook.com…';

  try {
    const body = new URLSearchParams();
    body.set('action', 'test');
    if (token) body.set('access_token', token);

    const r = await fetch('<?= APP_URL ?>/api/graph_test.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    });
    const data = await r.json();

    if (data.success) {
      box.style.background = '#e8f5e9';
      box.style.border = '1px solid #a5d6a7';
      box.style.color = '#1b5e20';
      const perms = (data.permissions || []).slice(0, 8).join(', ') || '—';
      const tokenLabel = data.token_type === 'page' ? 'Token Page' : 'Token utilisateur';
      box.innerHTML = `
        <strong>✓ ${escapeHtml(data.message || 'OK')}</strong><br>
        Version : ${escapeHtml(data.graph_version || '?')} · App ID : ${escapeHtml(data.app_id || '—')} · ${escapeHtml(tokenLabel)}<br>
        Compte : <strong>${escapeHtml(data.profile?.name || '?')}</strong> (ID ${escapeHtml(data.profile?.id || '?')})<br>
        Permissions accordées : ${escapeHtml(perms)}<br>
        <span style="font-size:12px;opacity:.85;">${escapeHtml(data.note || '')}</span>
      `;
    } else {
      box.style.background = '#ffebee';
      box.style.border = '1px solid #ef9a9a';
      box.style.color = '#b71c1c';
      box.innerHTML = `
        <strong>✗ Échec Graph API</strong><br>
        ${escapeHtml(data.message || data.error || 'Erreur inconnue')}<br>
        <span style="font-size:12px;">Vérifiez le token sur <a href="https://developers.facebook.com/tools/explorer" target="_blank" style="color:inherit;">Graph API Explorer</a>, puis enregistrez-le.</span>
      `;
    }
  } catch (err) {
    box.style.background = '#ffebee';
    box.style.border = '1px solid #ef9a9a';
    box.style.color = '#b71c1c';
    box.innerHTML = 'Erreur réseau : ' + escapeHtml(err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Tester Graph API';
  }
}
</script>
