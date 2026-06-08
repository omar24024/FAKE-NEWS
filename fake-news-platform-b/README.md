# � Fake News Platform — OSINT Intelligence System v2.0

## 📋 Vue d'ensemble

**Fake News Platform** est un système intelligent d'OSINT (Open Source Intelligence) pour l'analyse de publications Facebook publiques. La plateforme détecte automatiquement :

- ✅ Les fausses informations (fake news)
- ✅ La désinformation
- ✅ Le discours haineux
- ✅ Le cyberharcèlement
- ✅ Les contenus fiables

## 🎯 Fonctionnement

### 1. **Extraction des données**
L'analyste colle une URL de publication Facebook publique:
```
https://facebook.com/...
```

### 2. **Extraction automatique**
Le système **facebook_post_extractor.py** récupère:
- Texte du post
- Nom de l'auteur
- Images
- Date de publication
- URL de la publication

### 3. **Analyse IA**
Le module d'IA analyse le contenu pour détecter:
- Catégorie (fake_news, disinformation, hate_speech, reliable)
- Score de confiance
- Niveau de risque (low, medium, high)

### 4. **Affichage dans le Dashboard**
Les résultats s'affichent dans une interface moderne:
- 📱 Cartes de publication élégantes
- 🏷️ Badges d'analyse colorés
- 🔍 Filtres (analysés, à analyser)
- ⏸️ Réanalyse à la demande

## 🛠️ Architecture

```
fake-news-platform-b/
├── api/
│   ├── facebook_post_api.php     ← API OSINT (extract, analyze, get_post)
│   └── ai_analyze.php             ← Module IA
├── pages/
│   ├── publications.php            ← Dashboard moderne (nouveauté!)
│   ├── detail.php                  ← Détail d'une publication
│   └── analyse.php                 ← Analyses
├── python-ai/
│   ├── facebook_post_extractor.py  ← Extracteur (nouveauté!)
│   └── analyze.py                  ← Module IA Python
├── database/
│   ├── schema.sql                  ← Schéma BD
│   └── init_facebook_posts.php      ← Init tables (nouveauté!)
└── includes/
    ├── config.php
    ├── auth.php
    └── functions.php
```

## 📦 Base de données

Tables créées automatiquement:

### `facebook_posts`
```sql
- id (PRIMARY KEY)
- fb_post_url (UNIQUE) — URL de la publication
- author_name — Auteur du post
- content — Texte complet
- image_url — Image associée
- published_at — Date publication
- extracted_at — Date extraction
- is_analyzed — Flag analyse
```

### `ai_analysis`
```sql
- id (PRIMARY KEY)
- post_id (FOREIGN KEY)
- category — Catégorie détectée
- confidence_score — Score IA (0-100%)
- risk_level — Niveau risque (low/medium/high)
- analyzed_at — Timestamp analyse
```

### `osint_reports` (optionnel)
Grouper les analyses en rapports OSINT thématiques

## 🚀 Utilisation

### Installation initiale

```bash
# 1. Initialiser les tables
php database/init_facebook_posts.php

# 2. Installer les dépendances Python
pip install -r python-ai/requirements.txt

# 3. (Optionnel) Installer Playwright
playwright install
```

### Workflow

1. Aller dans **Publications** (menu latéral)
2. Cliquer sur **➕ Ajouter une publication**
3. Coller l'URL Facebook
4. Cliquer **Extraire et analyser**
5. Attendre le résultat
6. Voir l'analyse IA automatique

## 🔧 API Endpoints

### `POST api/facebook_post_api.php`

#### `action=extract`
Extraire une publication Facebook
```php
POST /api/facebook_post_api.php
{
    "action": "extract",
    "url": "https://facebook.com/..."
}

// Réponse
{
    "success": true,
    "post": {
        "id": 1,
        "fb_post_url": "...",
        "author_name": "...",
        "content": "...",
        "image_url": "...",
        "published_at": "2026-05-26T10:00:00",
        "extracted_at": "2026-05-26T15:41:00",
        "is_analyzed": false
    }
}
```

#### `action=analyze`
Analyser une publication avec l'IA
```php
POST /api/facebook_post_api.php
{
    "action": "analyze",
    "post_id": 1
}

// Réponse
{
    "success": true,
    "analysis": {
        "category": "fake_news",
        "confidence": 0.95,
        "risk_level": "high"
    }
}
```

#### `action=get_post`
Récupérer détails d'une publication
```php
GET /api/facebook_post_api.php?action=get_post&id=1
```

#### `action=get_recent_posts`
Lister publications avec pagination
```php
GET /api/facebook_post_api.php?action=get_recent_posts&page=1&limit=20&filter=all|analyzed|unanalyzed
```

#### `action=delete_post`
Supprimer une publication
```php
POST /api/facebook_post_api.php
{
    "action": "delete_post",
    "id": 1
}
```

## 🐍 Extracteur Python

### Usage CLI

```bash
# Extraire et sauvegarder une publication
python facebook_post_extractor.py --url "https://facebook.com/..." --json

# Tester connexion BD
python facebook_post_extractor.py --test

# Extraire sans sauvegarder
python facebook_post_extractor.py --url "..." --save-db
```

### Format de sortie

```json
{
    "fb_post_url": "https://facebook.com/...",
    "author_name": "Jean Dupont",
    "content": "Texte du post...",
    "image_url": "https://...",
    "published_at": "2026-05-26T10:00:00",
    "extracted_at": "2026-05-26T15:41:00",
    "status": "success"
}
```

## ⚖️ Légal & Ethique

✅ **Uniquement contenus publics**
- Les publications scrapées doivent être légalement visibles
- Pas d'accès aux contenus privés/restreints
- Respect des ToS de Facebook

✅ **OSINT Responsable**
- Analyse de contexte
- Pas de surveillance personnelle
- Utilisation institutionnelle/académique

## 🔒 Sécurité

- ✅ Authentification requise
- ✅ Validation d'URL stricte
- ✅ Injection SQL préventive (prepared statements)
- ✅ Échappement HTML/URL
- ✅ Rate limiting automatique

## 📊 Statistiques

- Posts extraits: **Voir Dashboard**
- Posts analysés: **Voir Dashboard**
- Catégories détectées: fake_news, disinformation, hate_speech, reliable
- Accuracy IA: Dépend du modèle

## 🎨 Dashboard Features

- 📱 Cartes de publication élégantes
- 🔍 Filtrage (tous/analysés/à analyser)
- 🏷️ Badges colorés par catégorie
- ⚡ Réanalyse à la demande
- 🗑️ Suppression en masse
- 📄 Pagination fluide
- 🎯 Modal moderne d'ajout

## 📈 Roadmap

- [ ] Export rapports OSINT (PDF/Excel)
- [ ] Alertes temps réel
- [ ] Clustering de contenus similaires
- [ ] Graphes de propagation
- [ ] API publique
- [ ] Intégration Slack/Teams

## 🤝 Contribution

Corrections et améliorations bienvenues!

## 📝 Licence

Projet académique/institutionnel

---

**Version**: 2.0  
**Dernière mise à jour**: 26 Mai 2026  
**Auteur**: OSINT Intelligence Platform
