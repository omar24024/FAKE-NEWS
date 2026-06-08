# 🏗️ OSINT Platform — Architecture System

## Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend Layer (JavaScript)               │
│                    publications.php                          │
│  - Modal input dialog                                        │
│  - Post grid with analysis badges                           │
│  - Filter tabs + pagination                                 │
└─────────────────────────────────────────────────────────────┘
                            │
                    Fetch API (JSON)
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    REST API Layer (PHP)                      │
│                facebook_post_api.php                         │
│  - action=extract      → Python extractor                   │
│  - action=analyze      → Python analyzer                    │
│  - action=get_post     → Database query                     │
│  - action=delete_post  → Cascade delete                     │
└─────────────────────────────────────────────────────────────┘
                            │
            ┌───────────────┼───────────────┐
            │               │               │
            ▼               ▼               ▼
    ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
    │ Python       │  │ Python       │  │ MySQL        │
    │ Extractor    │  │ Analyzer     │  │ Database     │
    │              │  │              │  │              │
    │ - Chrome     │  │ - NLP Model  │  │ facebook_posts
    │ - Playwright │  │ - Transformers
    │ - DOM Parse  │  │ - Scoring    │  │ ai_analysis
    │              │  │              │  │              │
    └──────────────┘  └──────────────┘  └──────────────┘
            │               │               │
            └───────────────┴───────────────┘
                            │
                            ▼
                  ┌──────────────────┐
                  │  Facebook.com    │
                  │  (Public Posts)  │
                  └──────────────────┘
```

---

## 📊 Data Flow

### 1️⃣ User Input → Extraction

```
User URL Input
      │
      ├─→ Validation (facebook.com check)
      │
      ├─→ POST /api/facebook_post_api.php?action=extract
      │
      ├─→ shell_exec("python facebook_post_extractor.py --url ... --json")
      │
      ├─→ Python Process:
      │   ├─ launch_persistent_context (Chrome real browser)
      │   ├─ page.goto(url)
      │   ├─ Wait for content load
      │   └─ Extract: author, text, image, date
      │
      ├─→ JSON Output
      │   {
      │     "fb_post_url": "...",
      │     "author_name": "...",
      │     "content": "...",
      │     "image_url": "...",
      │     "published_at": "2026-05-26T10:00:00",
      │     "extracted_at": "2026-05-26T15:41:00",
      │     "status": "success"
      │   }
      │
      ├─→ MySQL INSERT/UPDATE
      │   INSERT INTO facebook_posts
      │     (fb_post_url, author_name, content, image_url, 
      │      published_at, extracted_at, is_analyzed)
      │
      ├─→ Response to Frontend
      │   { "success": true, "post": {...} }
      │
      └─→ Frontend: Page Reload → New post appears in grid
```

### 2️⃣ Analysis Pipeline

```
Click "Analyser" button
      │
      ├─→ POST /api/facebook_post_api.php?action=analyze
      │   { "post_id": 1 }
      │
      ├─→ Fetch post content from DB
      │
      ├─→ shell_exec("python python-ai/analyze.py --post-id 1")
      │
      ├─→ Python NLP Pipeline:
      │   ├─ Load Transformers model
      │   ├─ Tokenize content
      │   ├─ Run inference
      │   └─ Classify: {category, confidence, risk_level}
      │
      ├─→ JSON Output
      │   {
      │     "post_id": 1,
      │     "category": "fake_news",
      │     "confidence": 0.95,
      │     "risk_level": "high"
      │   }
      │
      ├─→ MySQL INSERT
      │   INSERT INTO ai_analysis
      │     (post_id, category, confidence_score, risk_level)
      │
      ├─→ UPDATE facebook_posts SET is_analyzed = 1
      │
      ├─→ Response to Frontend
      │   { "success": true, "analysis": {...} }
      │
      └─→ Frontend: Badge appears (risk_level color)
           🔴 HIGH  |  🟡 MEDIUM  |  🟢 LOW
```

---

## 🗂️ Directory Structure (Final)

```
fake-news-platform-b/
│
├── api/
│   ├── facebook_post_api.php          ← 5 endpoints (extract, analyze, get_post, get_recent_posts, delete_post)
│   └── ai_analyze.php                 ← Legacy (optional)
│
├── pages/
│   ├── publications.php               ← Main OSINT Dashboard (modal, grid, filters, pagination)
│   ├── detail.php                     ← Post detail view (LEFT JOIN ai_analysis)
│   ├── analyse.php                    ← Statistics page
│   └── [others]
│
├── python-ai/
│   ├── facebook_post_extractor.py     ← 480 lines (async Playwright, DOM parsing, MySQL)
│   ├── analyze.py                     ← NLP analysis module
│   ├── requirements.txt               ← Playwright, transformers, mysql-connector
│   └── chrome_session/                ← Persistent browser profile (auto-created)
│
├── database/
│   ├── init_facebook_posts.php        ← Execute once: creates 4 tables + indexes
│   ├── schema.sql                     ← SQL schema reference
│   └── [others]
│
├── includes/
│   ├── config.php                     ← DB connection, constants
│   ├── auth.php                       ← Session management
│   ├── functions.php                  ← Utility functions
│   └── sidebar.php                    ← Navigation component
│
├── assets/
│   ├── css/main.css                   ← Global styles
│   └── js/
│
├── uploads/                           ← Post images (cached)
│
├── README.md                          ← Full documentation (NEW)
├── QUICKSTART.md                      ← 5-minute setup guide (NEW)
├── ARCHITECTURE.md                    ← This file (NEW)
│
└── index.php, login.php, logout.php   ← Entry points

Deleted Files (Scraper v1):
❌ facebook_scraper.py (old version)
❌ facebook_scraper_api.php (old)
❌ config_scraper.py
❌ test_setup.py
❌ SCRAPER_QUICKSTART.md
❌ chrome_session/ (old)
❌ session/
❌ scraper.php
❌ facebook_api.php (old)
❌ INSTALL_SCRAPER.bat
❌ EXAMPLES.md
❌ MODULE_*.md
```

---

## 💾 Database Schema

### Table: `facebook_posts`
```sql
id              INT PRIMARY KEY AUTO_INCREMENT
fb_post_url     VARCHAR(2000) UNIQUE            ← Prevents duplicates
author_name     VARCHAR(255)
content         LONGTEXT                         ← Can be very long
image_url       VARCHAR(2000) NULLABLE
published_at    DATETIME
extracted_at    DATETIME DEFAULT CURRENT_TIMESTAMP
is_analyzed     BOOLEAN DEFAULT 0
created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP

INDEX idx_extracted_at (extracted_at)
INDEX idx_is_analyzed (is_analyzed)
```

### Table: `ai_analysis`
```sql
id              INT PRIMARY KEY AUTO_INCREMENT
post_id         INT UNIQUE NOT NULL
category        VARCHAR(50)                      ← fake_news|disinformation|hate_speech|reliable
confidence_score DECIMAL(3,2)                    ← 0.00-1.00
risk_level      VARCHAR(10)                      ← low|medium|high
analyzed_at     DATETIME DEFAULT CURRENT_TIMESTAMP
reasoning       LONGTEXT NULLABLE

FOREIGN KEY (post_id) REFERENCES facebook_posts(id) ON DELETE CASCADE

INDEX idx_category (category)
INDEX idx_risk_level (risk_level)
```

### Table: `osint_reports` (optional)
```sql
id              INT PRIMARY KEY AUTO_INCREMENT
title           VARCHAR(255)
description     LONGTEXT
created_by      INT                              ← User ID
created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
status          VARCHAR(20)                      ← draft|published|archived
```

### Table: `report_posts` (M:N liaison)
```sql
report_id       INT NOT NULL
post_id         INT NOT NULL
UNIQUE(report_id, post_id)

FOREIGN KEY (report_id) REFERENCES osint_reports(id) ON DELETE CASCADE
FOREIGN KEY (post_id) REFERENCES facebook_posts(id) ON DELETE CASCADE
```

---

## 🔄 Component Details

### Component: facebook_post_extractor.py

**Purpose:** Extract individual Facebook post data using real Chrome browser

**Key Classes:**
```python
class FacebookPostExtractor:
    async def init_browser()
        ├─ channel="chrome"                    # Real Chrome executable
        ├─ launch_persistent_context()        # Persistent session
        ├─ user_data_dir="chrome_session"     # Local isolated profile
        ├─ add_init_script()                  # Disable navigator.webdriver
        └─ headless=True/False (configurable)

    async def extract_post(url)
        ├─ Validate URL (contains "facebook.com")
        ├─ page.goto(url)
        ├─ Wait for selectors: [data-testid="post_message"]
        ├─ Extract: author_name, content, image_url, published_at
        └─ Return: JSON object

    async def save_post(post_data)
        ├─ INSERT OR UPDATE (UNIQUE fb_post_url)
        └─ Return: {id, affected_rows}

    def main() [CLI]
        ├─ --url <url>           # URL to extract
        ├─ --json                # Output JSON
        ├─ --save-db             # Save to database
        └─ --test                # Test DB connection
```

**Browser Launch Args:**
```python
args = [
    "--disable-blink-features=AutomationControlled",
    "--disable-extensions",
    "--disable-gpu",              # Windows optimization
    "--disable-popup-blocking",
    "--disable-images",           # Speed up
]
```

---

### Component: facebook_post_api.php

**Purpose:** REST API layer bridging frontend ↔ Python tools ↔ Database

**Endpoints:**

```php
POST /api/facebook_post_api.php

// 1. Extract post
{
  "action": "extract",
  "url": "https://facebook.com/..."
}
→ Calls: python facebook_post_extractor.py --url ... --json --save-db
→ Returns: { "success": true/false, "post": {...}, "error": "..." }

// 2. Analyze post
{
  "action": "analyze",
  "post_id": 1
}
→ Calls: python python-ai/analyze.py --post-id 1 --json
→ Returns: { "success": true/false, "analysis": {...} }

// 3. Get single post
GET /api/facebook_post_api.php?action=get_post&id=1
→ SELECT p.*, a.category, a.confidence_score, a.risk_level
    FROM facebook_posts p
    LEFT JOIN ai_analysis a ON p.id = a.post_id
    WHERE p.id = 1
→ Returns: Post + Analysis (if exists)

// 4. Get paginated posts
GET /api/facebook_post_api.php?action=get_recent_posts&page=1&limit=20&filter=all|analyzed|unanalyzed
→ SELECT ... WITH LIMIT OFFSET
→ Returns: { "posts": [...], "total": N, "pages": M }

// 5. Delete post
{
  "action": "delete_post",
  "id": 1
}
→ DELETE FROM facebook_posts WHERE id = 1
→ Cascades: DELETE FROM ai_analysis WHERE post_id = 1
→ Returns: { "success": true/false }
```

---

### Component: publications.php (Dashboard)

**HTML Structure:**
```html
<!-- Modal: Add URL -->
<div id="modal-add">
  <form>
    <input type="text" placeholder="https://facebook.com/...">
    <button>Extraire et analyser</button>
  </form>
</div>

<!-- Filter Tabs -->
<div class="filters">
  <button class="tab active">Tous (N)</button>
  <button class="tab">À analyser (N)</button>
  <button class="tab">Analysées (N)</button>
</div>

<!-- Posts Grid -->
<div class="posts-grid">
  <div class="post-card">
    <div class="avatar">J</div>  <!-- First letter -->
    <div class="author">Jean Dupont</div>
    <div class="date">26 mai 2026</div>
    <div class="content">Texte du post (150 chars)...</div>
    <img class="image-thumbnail" src="...">
    <div class="badge danger">FAKE NEWS</div>
    <div class="actions">
      <button onclick="analyzePost(1)">🔄 Analyser</button>
      <button onclick="viewPost(1)">👁️ Voir</button>
      <button onclick="deletePost(1)">🗑️</button>
    </div>
  </div>
  ...more cards
</div>

<!-- Pagination -->
<div class="pagination">
  <button>◄ Précédent</button>
  <button class="active">1</button>
  <button>2</button>
  <button>3</button>
  <button>Suivant ►</button>
</div>
```

**CSS:**
- Gradient background: #667eea → #764ba2
- Responsive grid: 3 columns (desktop), 1 (mobile)
- Badges: danger (red), warning (yellow), success (green)
- Hover effects: box-shadow, scale

**JavaScript:**
```javascript
// Submit URL form
submitAddForm()
  ├─ GET url from input
  ├─ Validate (contains "facebook.com")
  ├─ POST /api/facebook_post_api.php?action=extract
  ├─ Show loading spinner
  ├─ On success: location.reload()
  └─ On error: showAlert(error, "danger")

// Analyze post
analyzePost(postId)
  ├─ POST /api/facebook_post_api.php?action=analyze
  ├─ location.reload()

// View post
viewPost(postId)
  ├─ window.location = "detail.php?id=" + postId

// Delete post
deletePost(postId)
  ├─ If confirm():
  │   ├─ POST /api/facebook_post_api.php?action=delete_post
  │   └─ location.reload()

// Filter posts
filterPosts(filter)
  └─ location = "?filter=" + filter
```

---

## 🔐 Security Model

### Input Validation
```php
// URL validation
if (!filter_var($url, FILTER_VALIDATE_URL)) throw error;
if (strpos($url, 'facebook.com') === false) throw error;

// Post ID validation
if (!ctype_digit($post_id)) throw error;

// Filter validation
if (!in_array($filter, ['all', 'analyzed', 'unanalyzed'])) throw error;
```

### SQL Injection Prevention
```php
// Use prepared statements (PDO)
$stmt = $pdo->prepare("SELECT * FROM facebook_posts WHERE id = ?");
$stmt->execute([$id]);

// Or named parameters
$stmt = $pdo->prepare("SELECT * FROM facebook_posts WHERE fb_post_url = :url");
$stmt->execute(['url' => $url]);
```

### XSS Prevention
```php
// HTML escape output
echo htmlspecialchars($post['author_name'], ENT_QUOTES, 'UTF-8');

// URL escape
echo urlencode($post['fb_post_url']);
```

### CSRF Protection
```php
// Check referer (optional)
if ($_SERVER['HTTP_REFERER'] !== BASE_URL) throw error;

// Session tokens (implement if needed)
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) throw error;
```

---

## 📈 Performance Considerations

### Database
- ✅ Index on `extracted_at` for sorting recent posts
- ✅ Index on `is_analyzed` for filtering
- ✅ UNIQUE constraint on `fb_post_url` prevents duplicates
- ✅ CASCADE delete for referential integrity

### Python
- ✅ Async Playwright for concurrent page loads
- ✅ Persistent browser context (no launch/close overhead)
- ✅ Image disabled during extraction (faster DOM)
- ✅ Headless mode (faster rendering)

### Frontend
- ✅ Pagination (20 posts/page default)
- ✅ Lazy loading of images
- ✅ Client-side filtering (no page reload for tabs)
- ✅ AJAX for actions (modal input, analyze, delete)

---

## 🚨 Error Handling

### Python Errors
```python
try:
    browser = await init_browser()
except Exception as e:
    logger.error(f"Browser init failed: {e}")
    return {"status": "error", "message": str(e)}
```

### PHP Errors
```php
try {
    $stmt = $pdo->prepare($sql);
} catch (PDOException $e) {
    http_response_code(500);
    return json_encode(["error" => $e->getMessage()]);
}
```

### Frontend Errors
```javascript
fetch('/api/...')
  .then(r => r.json())
  .then(data => {
    if (!data.success) showAlert(data.error, "danger");
    else showAlert("✅ Success!", "success");
  })
  .catch(err => showAlert(err, "danger"));
```

---

## 🔄 Version History

| Version | Date | Changes |
|---------|------|---------|
| **1.0** | Old | Mass Facebook scraper (DEPRECATED) |
| **2.0** | 2026-05-26 | Individual URL extraction + AI analysis (CURRENT) |

---

**Last Updated:** 26 May 2026
