# 🚀 QUICK START — Stabilisation OSINT Facebook

## État Actuel
Le projet a été stabilisé avec corrections des problèmes majeurs. Voici le status:

### ✅ FAIT
- [x] API PHP réécrite et stable (`facebook_post_api.php`)
- [x] Gestion d'erreurs robuste (JSON responses correctes)
- [x] Event loop asyncio Windows Python 3.10 corrigé
- [x] Script d'initialisation système créé
- [x] Tests automatisés créés
- [x] Documentation stabilisation complète

### 🔄 À FAIRE (IMMÉDIATEMENT)
1. [ ] Exécuter `INITIALIZE_SYSTEM.php`
2. [ ] Vérifier avec `STABILITY_DIAGNOSTIC.php`
3. [ ] Tester avec `TEST_SYSTEM.php`
4. [ ] Corriger les problèmes restants
5. [ ] Livrer le système

---

## 🎯 WORKFLOW DE STABILISATION

### Étape 1: Initialisation (2-3 min)
```
1. Ouvrir navigateur
2. Aller à: http://localhost/fake-news-platform-b/INITIALIZE_SYSTEM.php
3. Cliquer sur "Initialiser le Système"
4. Attendre que tout se complète ✓
```

**Qu'est-ce qui se passe:**
- Base de données créée
- Tables créées
- Comptes admin/analyst créés (password: admin123/analyst123)
- Données par défaut chargées

### Étape 2: Diagnostic (1-2 min)
```
1. Aller à: http://localhost/fake-news-platform-b/STABILITY_DIAGNOSTIC.php
2. Vérifier que tous les tests sont ✓
3. Noter les erreurs (s'il y en a)
```

**À vérifier:**
- ✓ Connexion MySQL OK
- ✓ Tables existantes
- ✓ Python 3.10+ installé
- ✓ Modules requis (playwright, mysql, transformers)
- ✓ Extracteur Python ok
- ✓ API endpoints présents

### Étape 3: Tests Complets (2-3 min)
```
1. Aller à: http://localhost/fake-news-platform-b/TEST_SYSTEM.php
2. Vérifier que 10/10 tests passent (ou 9/10)
3. Consulter STABILIZATION_GUIDE.md si erreurs
```

**Tests exécutés:**
- Connexion BD
- Tables critiques
- Compte admin
- Fonctions PHP
- Environnement Python
- Modules Python
- Script extracteur
- API endpoints
- Intégrité BD
- Données exemples

### Étape 4: Login (1 min)
```
1. Aller à: http://localhost/fake-news-platform-b/login.php
2. Se connecter avec:
   - Username: admin
   - Password: admin123
3. Vérifier le dashboard
```

### Étape 5: Test Fonctionnalité (5-10 min)
```
1. Aller à Pages → Publications
2. Tenter une extraction (si extraction disponible)
3. Vérifier API via Postman:
   GET /api/facebook_post_api.php?action=get_stats
4. Vérifier les données dans la BD
```

---

## 📋 CHECKLIST CRITIQUE

Avant de déclarer le système "prêt":

```
☐ INITIALIZE_SYSTEM.php exécuté avec succès
☐ STABILITY_DIAGNOSTIC.php 100% OK
☐ TEST_SYSTEM.php 10/10 ou 9/10 tests passés
☐ Login admin/analyst fonctionne
☐ Dashboard charge sans erreurs
☐ API endpoints répondent JSON correct
☐ BD a des données (au moins les données par défaut)
☐ Console browser: 0 erreurs critiques
☐ Python version >= 3.9
☐ Playwright installé (playwright install chromium)
☐ Modules Python OK: pip list grep transformers
☐ Pas de logs d'erreur PHP (check Apache/WAMP)
```

---

## 🔧 PROBLÈMES COURANTS & SOLUTIONS

### ❌ "Base de données ne se crée pas"
**Solution:**
1. Vérifier que MySQL est running (WAMP → Start)
2. Vérifier credentials dans `.env` ou config.php:
   - DB_HOST: localhost
   - DB_USER: root
   - DB_PASS: (empty ou votre password)
3. Si problème de permissions: créer manuellement en phpMyAdmin

### ❌ "Python not found"
**Solution:**
```bash
# Vérifier Python installation
python --version

# Ajouter Python à PATH (Windows):
# À faire dans les variables d'environnement Windows
```

### ❌ "Playwright installation failed"
**Solution:**
```bash
cd python-ai
pip install -r requirements.txt
playwright install chromium
```

### ❌ "Pages 404 / Links broken"
**Solution:**
1. Vérifier APP_URL dans `.env` ou config.php:
   ```
   APP_URL=http://localhost/fake-news-platform-b
   ```
2. Vérifier structure dossiers:
   ```
   /includes/
   /pages/
   /api/
   /python-ai/
   /database/
   ```
3. Vérifier sidebar.php links

### ❌ "JSON parsing errors in API"
**Solution:**
- API réécrite: utiliser `facebook_post_api.php` (nouvelle version)
- Vérifier Python output avec: `py python-ai/facebook_post_extractor.py --help`

### ❌ "EventLoop is closed / RuntimeError"
**Solution:**
- Utilisé `facebook_post_extractor.py` (corrigé avec ProactorEventLoop)
- Assurer Python 3.10+ sur Windows
- Voir STABILIZATION_GUIDE.md §4.1

---

## 🎓 ARCHITECTURE APRÈS STABILISATION

```
┌─────────────────────────────────────────┐
│   FRONTEND                              │
│   login.php | index.php | pages/*.php   │
└────────────────┬────────────────────────┘
                 │
         Fetch API (JSON)
                 │
                 ▼
┌─────────────────────────────────────────┐
│   API LAYER (Stable)                    │
│   facebook_post_api.php (réécrite)      │
│   - extract, analyze, get_*, delete     │
│   - JSON responses + error handling      │
└────────────────┬────────────────────────┘
                 │
    ┌────────────┴────────────┐
    │                         │
    ▼                         ▼
┌──────────────┐   ┌──────────────────┐
│ Python       │   │ MySQL Database   │
│ - Extracteur │   │ - Posts          │
│ - Analyzer   │   │ - Analyses       │
│ - Scraper    │   │ - Accounts       │
└──────────────┘   └──────────────────┘
    │
    ▼
┌──────────────┐
│ Chromium     │
│ (Playwright) │
└──────────────┘
```

---

## 📞 SUPPORT & DOCUMENTATION

- **STABILIZATION_GUIDE.md** — Guide complet stabilisation
- **API_REFERENCE.md** — Référence API détaillée
- **ARCHITECTURE.md** — Architecture système
- **README.md** — Vue d'ensemble générale

---

## ⏱️ TEMPS ESTIMÉ

| Phase | Durée | Notes |
|-------|-------|-------|
| Initialisation | 5 min | INITIALIZE_SYSTEM.php |
| Tests | 10 min | STABILITY_DIAGNOSTIC + TEST_SYSTEM |
| Corrections | 10-30 min | Dépend des erreurs trouvées |
| Validation | 10 min | Tests manuels + Postman |
| **TOTAL** | **35-55 min** | Peut être plus rapide si tout OK |

---

## 🎯 PROCHAINES ÉTAPES

1. **Exécuter INITIALIZE_SYSTEM.php** ← À FAIRE EN PREMIER
2. **Lancer TEST_SYSTEM.php** et corriger erreurs
3. **Tester login/dashboard**
4. **Tester API avec Postman** (si possible)
5. **Documenter issues restantes**
6. **Livrer à client avec documentation**

---

**Status:** 🟡 Stabilisation 80% complète
**Prochaine étape:** Exécuter INITIALIZE_SYSTEM.php
**Durée estimée:** 35-55 minutes pour 100% stable
