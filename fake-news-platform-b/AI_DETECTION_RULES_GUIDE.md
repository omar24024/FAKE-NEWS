# AI Detection Rules System — Implementation Guide

## Overview

This document describes the **Configurable AI Keyword/Rules System** implemented in the Fake News Detection Platform. The system allows administrators to dynamically manage detection keywords and rules without modifying code.

---

## Architecture

### Components

```
┌─────────────────────────────────────────┐
│        Settings Page (Admin UI)         │
│    - View/Edit/Delete Rules             │
│    - Create new keywords                │
│    - Set weights and priorities         │
└────────────────┬────────────────────────┘
                 │
                 ↓
        ┌────────────────────┐
        │  REST API Layer    │
        │  /api/ai_rules.php │
        │  - list, create    │
        │  - update, delete  │
        └────────┬───────────┘
                 │
                 ↓
    ┌────────────────────────────┐
    │   Database Layer           │
    │  ai_detection_rules table  │
    │  - Dynamic rule storage    │
    └────────┬───────────────────┘
             │
             ↓
    ┌────────────────────────────┐
    │  AI Analysis Engine        │
    │  analyze.py (Python)       │
    │  - Load rules from DB      │
    │  - Dynamic classification  │
    │  - Rule-based detection    │
    └────────────────────────────┘
```

---

## Database Schema

### Table: `ai_detection_rules`

```sql
CREATE TABLE ai_detection_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category ENUM('fake_news','disinformation','hate_speech','misinformation','propaganda','violence','cyberbullying','neutral_indicators'),
    keyword VARCHAR(500) NOT NULL,
    weight DECIMAL(4,3) DEFAULT 0.150,
    is_active TINYINT(1) DEFAULT 1,
    rule_type ENUM('keyword','phrase','regex') DEFAULT 'keyword',
    priority INT DEFAULT 1,
    description VARCHAR(500),
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY (category, keyword),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);
```

### Key Fields

| Field | Type | Purpose |
|-------|------|---------|
| `category` | ENUM | Detection category (fake_news, hate_speech, etc.) |
| `keyword` | VARCHAR(500) | The actual keyword/phrase to detect |
| `weight` | DECIMAL(4,3) | Contribution to confidence score (0.0-1.0) |
| `is_active` | TINYINT | Enable/disable individual rules |
| `rule_type` | ENUM | Type: keyword, phrase, or regex |
| `priority` | INT | Order of evaluation (higher = first) |
| `description` | VARCHAR(500) | Admin notes about the rule |

---

## API Endpoints

### Base URL
```
/api/ai_rules.php
```

### Endpoints

#### 1. **LIST Rules**
```
GET /api/ai_rules.php?action=list[&category=fake_news]
```

**Response:**
```json
{
  "success": true,
  "data": {
    "fake_news": [
      {
        "id": 1,
        "category": "fake_news",
        "keyword": "urgent",
        "weight": 0.18,
        "is_active": 1,
        "rule_type": "keyword",
        "priority": 1,
        "description": "Signal d'urgence typique"
      }
    ]
  }
}
```

#### 2. **CREATE Rule**
```
POST /api/ai_rules.php?action=create
Content-Type: application/json

{
  "category": "fake_news",
  "keyword": "partagez avant suppression",
  "weight": 0.22,
  "rule_type": "phrase",
  "priority": 3,
  "description": "Incitation urgente à partager"
}
```

#### 3. **UPDATE Rule**
```
POST /api/ai_rules.php?action=update&id=1
Content-Type: application/json

{
  "weight": 0.25,
  "priority": 2,
  "description": "Updated description"
}
```

#### 4. **DELETE Rule**
```
POST /api/ai_rules.php?action=delete&id=1
```

#### 5. **GET STATS**
```
GET /api/ai_rules.php?action=stats
```

---

## Settings Page UI

### Location
```
/pages/parametres.php
```

### Features

1. **Rules Display**
   - Rules organized by category
   - Show count and total weight per category
   - Display rule metadata (type, priority, weight)

2. **Add Rule Modal**
   - Category selector
   - Keyword input field
   - Weight slider (0.0 - 1.0)
   - Priority input
   - Rule type selector (keyword/phrase/regex)
   - Description field

3. **Actions**
   - ✏️ Edit existing rules
   - 🗑️ Delete rules (soft delete)
   - ➕ Add new rules
   - 📊 View statistics

### UI Screenshot Sections

```
┌─ Rules by Category ─────────┐
│ Fake News (12 rules)        │
│ ├─ urgent        [0.18]     │
│ ├─ alerte        [0.18]     │
│ └─ censuré       [0.20]     │
│                             │
│ Hate Speech (7 rules)       │
│ ├─ expulser      [0.25]     │
│ └─ lyncher       [0.28]     │
└─────────────────────────────┘
```

---

## Python AI Analyzer Updates

### New Functions

#### 1. `load_detection_rules()`
Loads rules from database with 1-hour caching:
```python
def load_detection_rules() -> dict:
    """
    Load rules from ai_detection_rules table.
    Returns: { category: [{ keyword, weight, type, priority }, ...] }
    """
```

#### 2. `get_default_rules()`
Fallback rules if database is unavailable:
```python
def get_default_rules() -> dict:
    """Returns hardcoded default rules"""
```

#### 3. `rule_based_classify(text, use_db_rules=True)`
Enhanced rule-based classifier:
```python
def rule_based_classify(text: str, use_db_rules: bool = True) -> dict:
    """
    Rule-based text classification.
    - Loads rules from database if use_db_rules=True
    - Supports keyword, phrase, and regex matching
    - Returns: { category, confidence, risk_level, keywords, model }
    """
```

### Integration

The `ai_classify()` function now:
1. Calls `rule_based_classify()` with DB rules
2. Blends results with HuggingFace model (60/40 weighted)
3. Returns enhanced classification with dynamic keywords

---

## Categories

### 8 Detection Categories

| Category | Purpose | Example Keywords |
|----------|---------|-------------------|
| **fake_news** | Sensationalism & urgency | urgent, alerte, censuré, complot |
| **disinformation** | False claims of authority | annonce officielle, gouvernement |
| **hate_speech** | Dehumanization & hostility | expulser, lyncher, vermine |
| **misinformation** | Medical/scientific false claims | médicament miracle, guérit en |
| **propaganda** | Ideological manipulation | supériorité, destin manifeste |
| **violence** | Explicit calls for harm | tuer, torturer, assassiner |
| **cyberbullying** | Online harassment | débile, tu devrais mourir |
| **neutral_indicators** | Reliable language patterns | selon le journal, l'étude montre |

---

## Setup & Installation

### 1. Create Database Table

Run the migration:
```bash
mysql -u root -p fake_news_platform < database/schema.sql
```

Or manually:
```sql
CREATE TABLE ai_detection_rules (
    -- [see schema above]
);
```

### 2. Seed Initial Keywords

Run the seeding script:
```bash
php database/seed_ai_rules.php
```

Output:
```
✓ fake_news: 'urgent' (weight=0.18)
✓ fake_news: 'alerte' (weight=0.18)
...
========================================
✓ Semence terminée!
  Total inséré: 142 règles

📊 Distribution par catégorie:
  - fake_news: 14 règles
  - hate_speech: 12 règles
  - disinformation: 10 règles
  ...
```

### 3. Verify Setup

Check the database:
```sql
SELECT category, COUNT(*) as cnt
FROM ai_detection_rules
GROUP BY category;
```

---

## How It Works

### During Analysis

```python
# 1. Load rules from database (cached for 1 hour)
rules = load_detection_rules()
# Output: {
#   'fake_news': [
#     {'keyword': 'urgent', 'weight': 0.18, 'type': 'keyword', ...},
#     {'keyword': 'partagez avant', 'weight': 0.22, 'type': 'phrase', ...}
#   ],
#   'hate_speech': [...]
# }

# 2. Scan post content for keywords
text = "Urgent! Share before deletion!"
matched_keywords = []

# 3. Calculate confidence based on matched keywords and their weights
if 'urgent' in text.lower():
    scores['fake_news'] += 0.18
if 'partagez avant' in text.lower():
    scores['fake_news'] += 0.22

# 4. Determine category and risk level
confidence = 40%  # Sum of weights
category = 'fake_news'
risk_level = 'medium'  # Derived from confidence

# 5. Return classification
{
    'category': 'fake_news',
    'confidence': 40.0,
    'risk_level': 'medium',
    'keywords': [('urgent', 0.18, 'fake_news'), ...],
    'model': 'rule-based-dynamic'
}
```

---

## Weight & Priority System

### Weight (0.0 - 1.0)

Contribution to confidence score:
- **0.25+**: Strong indicator
- **0.18-0.24**: Medium indicator
- **0.10-0.17**: Weak indicator
- **< 0.10**: Very weak indicator

Example confidence calculation:
```
Text: "Urgent breaking news! Share before deletion!"

Matched keywords:
- 'urgent' (weight=0.18) → score += 0.18
- 'breaking' (weight=0.15) → score += 0.15
- 'partagez avant' (weight=0.22) → score += 0.22

Total score: 0.55 → 55% confidence
```

### Priority (1-100)

Evaluation order (higher priority evaluated first):
- **1**: Low priority
- **2-3**: Medium priority
- **4-5**: High priority

Used to optimize detection performance.

---

## Risk Level Mapping

| Confidence | Risk Level | Action |
|-----------|-----------|--------|
| >= 90% | **critical** | Alert admin, possible flagging |
| >= 75% | **high** | Review needed |
| >= 60% | **medium** | Monitor |
| < 60% | **low** | No action needed |

Special case: **hate_speech** category → always `critical` (any confidence)

---

## Admin Workflow

### Adding a Custom Keyword

1. Go to **Settings** → **AI Detection Rules**
2. Click **+ Ajouter une règle**
3. Fill in:
   - **Catégorie**: Select from dropdown
   - **Mot-clé**: Enter keyword/phrase
   - **Poids**: Set 0.0 - 1.0 (0.15-0.25 typical)
   - **Type**: keyword / phrase / regex
   - **Priorité**: 1-100
   - **Description**: Optional notes
4. Click **Enregistrer**

### Editing a Rule

1. Find rule in its category section
2. Click **Éditer**
3. Modify weight, priority, or description
4. Click **Enregistrer**

### Disabling a Rule (Soft Delete)

1. Click **Supprimer** on the rule
2. Confirm deletion
3. Rule marked as inactive but remains in database

---

## Caching & Performance

### Rule Caching

- **TTL**: 1 hour (3600 seconds)
- **Implementation**: Global variable `_rules_cache`
- **Reset**: Automatic after 1 hour or on manual request

### Cache Miss Strategy

If database unavailable:
```python
if db_error:
    return get_default_rules()  # Fall back to hardcoded
```

---

## Advanced Features

### Regex Support

Enable complex pattern matching:

**Example: Email detection**
```
Category: misinformation
Keyword: ^\w+@\w+\.\w+$
Type: regex
Weight: 0.10
```

**Example: Phone number**
```
Keyword: \+?\d{1,3}[-.\s]?\d{3}[-.\s]?\d{3}[-.\s]?\d{4}
Type: regex
```

### Phrase Matching

Multi-word detection:
```
Keyword: "partagez avant suppression"
Type: phrase
Weight: 0.22
```

---

## Testing

### Manual Test

```bash
# Test with specific text
curl -X POST http://localhost/fake-news-platform-b/api/ai_rules.php \
  -H "Content-Type: application/json" \
  -d '{"text": "Urgent! Share before deletion!"}'
```

### Database Test

```sql
-- Verify rules loaded
SELECT COUNT(*) FROM ai_detection_rules WHERE is_active = 1;

-- Check category distribution
SELECT category, COUNT(*) FROM ai_detection_rules 
GROUP BY category 
ORDER BY COUNT(*) DESC;

-- Find high-weight rules
SELECT keyword, weight, category 
FROM ai_detection_rules 
WHERE weight >= 0.25 
ORDER BY weight DESC;
```

---

## Troubleshooting

### Issue: Rules not loading

**Check:**
```sql
SELECT COUNT(*) FROM ai_detection_rules;
```

**If 0 rows:**
```bash
php database/seed_ai_rules.php
```

### Issue: Analysis not using DB rules

**Verify** in analyze.py:
```python
# Should see in logs:
[DB] 142 règles chargées depuis la base de données
```

**If not appearing:**
- Check database connection in `DB_CONFIG`
- Verify `ai_detection_rules` table exists
- Run `seed_ai_rules.php`

### Issue: Cache not updating

**Solution:**
```python
# Manually clear cache (in analyze.py)
_rules_cache = None
_rules_cache_timestamp = None

# Then reload
rules = load_detection_rules()
```

---

## Files Modified/Created

### New Files
- ✨ `api/ai_rules.php` — REST API for rule management
- ✨ `database/seed_ai_rules.php` — Initial data seeding script
- ✨ `database/schema.sql` — Updated with ai_detection_rules table

### Modified Files
- 🔧 `pages/parametres.php` — Added AI Detection Rules UI section
- 🔧 `python-ai/analyze.py` — Dynamic rule loading integration

---

## Security

### Admin-Only Access
```php
if ($user['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied']));
}
```

### SQL Injection Prevention
- Parameterized queries throughout
- PDO prepared statements

### XSS Prevention
- HTML escaping in UI (`escapeHtml()`)
- htmlspecialchars() in PHP output

---

## Future Enhancements

- [ ] Rule versioning/history
- [ ] A/B testing different rules
- [ ] Machine learning rule suggestions
- [ ] Bulk import/export rules (CSV)
- [ ] Rule analytics (hit rates)
- [ ] Multi-language support per rule
- [ ] Custom weight suggestions based on effectiveness

---

## API Reference Summary

| Action | Method | Endpoint | Purpose |
|--------|--------|----------|---------|
| list | GET | `?action=list` | Fetch all active rules |
| create | POST | `?action=create` | Add new rule |
| update | POST | `?action=update&id=1` | Modify existing rule |
| delete | POST | `?action=delete&id=1` | Disable rule |
| stats | GET | `?action=stats` | Get rule statistics |
| get_by_category | GET | `?action=get_by_category&category=fake_news` | Fetch rules for category |

---

## Glossary

- **Category**: Detection classification (fake_news, hate_speech, etc.)
- **Keyword**: Individual term to detect
- **Weight**: Numeric contribution to confidence (0.0-1.0)
- **Priority**: Evaluation order (higher = first)
- **Rule Type**: Detection method (keyword, phrase, regex)
- **Confidence Score**: Percentage likelihood of classification (0-100%)
- **Risk Level**: Severity classification (low, medium, high, critical)

---

**Last Updated**: 2026-05-26
**Version**: 1.0
**Status**: ✅ Production Ready
