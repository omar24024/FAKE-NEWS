# 🔧 STABILIZATION GUIDE — Plateforme OSINT Facebook

## État du Système (27 Mai 2026)

### ✅ STABILISÉ
- [x] Base de données MySQL (schema complet)
- [x] Authentification (login/logout/sessions)
- [x] API REST JSON stable (`facebook_post_api.php`)
- [x] Gestion des notifications
- [x] Tables et relations BD

### 🔄 EN COURS
- [ ] Extracteur Python (Playwright)
- [ ] Analyse IA (transformers)
- [ ] Pages frontend
- [ ] Synchronisation données

### ❌ À CORRIGER
- Playwright event loop (Windows Python 3.10)
- Relations account_id dans facebook_posts
- Données hardcodées vs réelles
- Pages 404
- Frontend bugs

---

## 1️⃣ INITIALISATION DU SYSTÈME

### 1.1 Créer Base de Données
```
Accédez à: http://localhost/fake-news-platform-b/INITIALIZE_SYSTEM.php
```

Cela va:
- ✅ Créer la base de données
- ✅ Créer toutes les tables
- ✅ Créer les comptes (admin/admin123, analyst/analyst123)
- ✅ Charger les données par défaut

### 1.2 Vérifier l'État
```
Accédez à: http://localhost/fake-news-platform-b/STABILITY_DIAGNOSTIC.php
```

Cela vérifiera:
- Connexion MySQL
- Tables existantes
- Comptes utilisateurs
- Environnement Python
- Modules requis

---

## 2️⃣ ENDPOINTS API STABLES

### 2.1 GET /api/facebook_post_api.php?action=get_recent_posts
```json
GET /api/facebook_post_api.php?action=get_recent_posts&limit=20

Response:
{
  "success": true,
  "message": "Posts récupérés",
  "data": {
    "posts": [...],
    "count": 20
  },
  "timestamp": "2026-05-27T10:30:00+00:00"
}
```

### 2.2 POST /api/facebook_post_api.php?action=extract
```json
POST /api/facebook_post_api.php?action=extract

Body:
{
  "url": "https://www.facebook.com/..."
}

Response:
{
  "success": true,
  "message": "Extraction terminée",
  "data": {
    "fb_post_url": "...",
    "author_name": "...",
    "content": "...",
    "status": "success"
  }
}
```

### 2.3 POST /api/facebook_post_api.php?action=analyze
```json
POST /api/facebook_post_api.php?action=analyze

Body:
{
  "post_id": 123
}

Response:
{
  "success": true,
  "message": "Analyse terminée",
  "data": {
    "category": "fake_news|reliable|...",
    "confidence": 0.85,
    "risk_level": "high|medium|low"
  }
}
```

### 2.4 GET /api/facebook_post_api.php?action=get_stats
```json
GET /api/facebook_post_api.php?action=get_stats

Response:
{
  "success": true,
  "message": "Statistiques récupérées",
  "data": {
    "total": 150,
    "analyzed": 120,
    "unanalyzed": 30,
    "by_category": {
      "fake_news": 45,
      "reliable": 65,
      "hate_speech": 10
    },
    "last_fetch": "2026-05-27T10:15:00"
  }
}
```

---

## 3️⃣ WORKFLOWS IMPORTANTS

### 3.1 Flux d'Extraction Complète

```
1. Utilisateur saisit URL Facebook
   ↓
2. Frontend POST /api/facebook_post_api.php?action=extract
   ↓
3. PHP exécute Python: facebook_post_extractor.py
   - Lance Chromium (Playwright)
   - Récupère contenu page
   - Extrait post données
   ↓
4. Python sauvegarde dans facebook_posts (DB)
   ↓
5. API retourne JSON avec post_id
   ↓
6. Frontend affiche post
```

### 3.2 Flux d'Analyse IA

```
1. Admin clique "Analyser" sur un post
   ↓
2. Frontend POST /api/facebook_post_api.php?action=analyze&post_id=123
   ↓
3. PHP exécute Python: analyze.py
   - Charge contenu du post
   - Applique modèle NLP (transformers)
   - Retourne category + confidence
   ↓
4. PHP sauvegarde dans ai_analysis (DB)
   ↓
5. API retourne résultat
   ↓
6. Frontend met à jour UI avec analyse
```

---

## 4️⃣ PROBLÈMES CONNUS & SOLUTIONS

### 4.1 Playwright EventLoop (Windows Python 3.10)

**Problème:** Event loop closed, RuntimeError, I/O operation on closed pipe

**Solution:** Voir `facebook_scraper.py` (version corrigée):
```python
if sys.platform == "win32":
    asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())

loop = asyncio.new_event_loop()
asyncio.set_event_loop(loop)
try:
    loop.run_until_complete(main())
finally:
    # Proper cleanup
    loop.close()
```

### 4.2 Relations Account_ID Manquantes

**Problème:** `facebook_posts.account_id` peut être NULL

**Solution:** 
```sql
-- Récupérer les posts sans account_id
SELECT * FROM facebook_posts WHERE account_id IS NULL;

-- Créer des comptes par défaut si nécessaire
INSERT INTO facebook_accounts (fb_id, name, type, fb_url)
VALUES ('fb_extracted', 'Publications extraites', 'page', 'https://facebook.com');

-- Mettre à jour les posts
UPDATE facebook_posts SET account_id = (
    SELECT id FROM facebook_accounts WHERE fb_id = 'fb_extracted'
) WHERE account_id IS NULL;
```

### 4.3 Données Hardcodées

**Problème:** Certaines pages retournent des données fictives

**Solution:** Toutes les données doivent venir de la DB:
```php
// ❌ MAUVAIS
$data = [
  ['title' => 'Post 1', ...],
];

// ✅ BON
$result = getPostsList($page, $perPage, $filters);
$posts = $result['posts'];
```

---

## 5️⃣ CHECKLIST STABILITÉ

### Avant de Livrer:

- [ ] INITIALIZE_SYSTEM.php exécuté
- [ ] Comptes admin/analyst créés
- [ ] STABILITY_DIAGNOSTIC.php: 100% OK
- [ ] Python extractor testé
- [ ] IA analyzer testé
- [ ] API endpoints testés (Postman)
- [ ] Login/logout fonctionnel
- [ ] Pages principales chargent
- [ ] Pas d'erreurs en console browser
- [ ] Base de données cohérente
- [ ] Pas de données hardcodées
- [ ] Notifications vraies (pas fictives)
- [ ] Statistiques vraies (pas fakes)

---

## 6️⃣ COMMANDES UTILES

### Python Tests
```bash
cd python-ai

# Tester imports
python -c "import playwright, mysql.connector, transformers; print('OK')"

# Tester extractor
python facebook_post_extractor.py --url "https://..." --help

# Tester analyzer
python analyze.py --text "test text" --json
```

### MySQL
```sql
-- Vérifier données
SELECT COUNT(*) FROM facebook_posts;
SELECT COUNT(*) FROM ai_analysis;
SELECT * FROM users;

-- Nettoyage
DELETE FROM facebook_posts;
DELETE FROM ai_analysis;
```

### Browser Console (DevTools)
```javascript
// Test API
fetch('/fake-news-platform-b/api/facebook_post_api.php?action=get_stats')
  .then(r => r.json())
  .then(d => console.log(d))
```

---

## 7️⃣ DOCUMENTATION LIÉE

- `README.md` - Vue d'ensemble
- `ARCHITECTURE.md` - Architecture système
- `API_REFERENCE.md` - Référence API complète
- `DEPLOYMENT.md` - Guide déploiement

---

**État:** 🟡 Stabilisation en cours
**Dernière mise à jour:** 27 Mai 2026
**Responsable:** Équipe Modernisation
