# 🧪 Testing Guide — OSINT Platform v2.0

## Pre-Flight Checklist

- [ ] MySQL Server running (WAMP green icon)
- [ ] PHP CLI accessible
- [ ] Python 3.9+ installed
- [ ] Playwright installed (`playwright install`)
- [ ] Database `fake_news_platform` exists
- [ ] All `python-ai/` dependencies installed

**Verify Setup:**
```bash
# Check Python
python --version

# Check Playwright
python -c "from playwright.async_api import async_playwright; print('OK')"

# Check MySQL
mysql -u root -p -e "SELECT VERSION();"
```

---

## Phase 1: Database Initialization

### Test 1.1: Create Tables

```bash
# Method 1: Browser
# Navigate to: http://localhost/fake-news-platform-b/database/init_facebook_posts.php
# Expected: Green checkmark + "✓ Initialisation complètée avec succès!"

# Method 2: Direct PHP CLI
cd c:\wamp64\www\fake-news-platform-b
php database/init_facebook_posts.php
# Expected: Same success message
```

**Verify:**
```bash
# Check tables exist
mysql -u root -p fake_news_platform -e "SHOW TABLES;"

# Output should show:
# facebook_posts
# ai_analysis
# osint_reports
# report_posts
```

**Verify Columns:**
```sql
DESCRIBE facebook_posts;
-- Should show: id, fb_post_url (UNIQUE), author_name, content, image_url, published_at, extracted_at, is_analyzed

DESCRIBE ai_analysis;
-- Should show: id, post_id (UNIQUE FK), category, confidence_score, risk_level, analyzed_at
```

---

## Phase 2: Python Extractor Testing

### Test 2.1: Test Database Connection

```bash
cd c:\wamp64\www\fake-news-platform-b

python python-ai/facebook_post_extractor.py --test

# Expected Output:
# ✓ Database connection successful!
# ✓ facebook_posts table exists
```

### Test 2.2: Manual Post Extraction

**Get a real Facebook post URL:**

Find a public post:
1. Go to Facebook.com
2. Find any public post
3. Click the timestamp → Copy post link
4. Format: `https://facebook.com/...` or `https://www.facebook.com/...`

**Test Extraction:**
```bash
python python-ai/facebook_post_extractor.py \
  --url "https://facebook.com/share/p/1CPEP2NmNW/" \
  --json

# Expected Output (JSON):
# {
#   "fb_post_url": "https://facebook.com/...",
#   "author_name": "Name of Author",
#   "content": "Post text here...",
#   "image_url": "https://...",
#   "published_at": "2026-05-26T10:00:00",
#   "extracted_at": "2026-05-26T15:41:00",
#   "status": "success"
# }
```

### Test 2.3: Extract + Save to DB

```bash
python python-ai/facebook_post_extractor.py \
  --url "https://facebook.com/share/p/1CPEP2NmNW/" \
  --json \
  --save-db

# Expected: JSON output + successful DB save
```

**Verify in Database:**
```sql
SELECT id, fb_post_url, author_name, is_analyzed FROM facebook_posts;

-- Should show the new post with is_analyzed=0
```

---

## Phase 3: REST API Testing

### Test 3.1: Extract via API

**Using cURL:**
```bash
curl -X POST "http://localhost/fake-news-platform-b/api/facebook_post_api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "extract",
    "url": "https://facebook.com/share/p/1CPEP2NmNW/"
  }'

# Expected Response:
# {
#   "success": true,
#   "post": {
#     "id": 1,
#     "fb_post_url": "...",
#     "author_name": "...",
#     "content": "...",
#     "image_url": "...",
#     "published_at": "2026-05-26T10:00:00",
#     "extracted_at": "2026-05-26T15:41:00",
#     "is_analyzed": false
#   }
# }
```

**Using JavaScript (Postman/Browser):**
```javascript
fetch("http://localhost/fake-news-platform-b/api/facebook_post_api.php", {
  method: "POST",
  headers: {"Content-Type": "application/json"},
  body: JSON.stringify({
    action: "extract",
    url: "https://facebook.com/share/p/1CPEP2NmNW/"
  })
})
.then(r => r.json())
.then(data => console.log(data))
```

### Test 3.2: Get Recent Posts

```bash
curl "http://localhost/fake-news-platform-b/api/facebook_post_api.php?action=get_recent_posts&page=1&filter=all"

# Expected:
# {
#   "success": true,
#   "posts": [
#     {
#       "id": 1,
#       "fb_post_url": "...",
#       "author_name": "...",
#       "category": null,              ← null before analysis
#       "confidence_score": null,
#       "risk_level": null
#     }
#   ],
#   "total": 1,
#   "pages": 1
# }
```

### Test 3.3: Filter by Analysis Status

```bash
# Get posts that need analysis
curl "http://localhost/fake-news-platform-b/api/facebook_post_api.php?action=get_recent_posts&filter=unanalyzed"

# Get already analyzed posts
curl "http://localhost/fake-news-platform-b/api/facebook_post_api.php?action=get_recent_posts&filter=analyzed"
```

---

## Phase 4: AI Analysis Testing

### Test 4.1: Analyze Single Post

```bash
# Extract a post first (from Phase 3.1), note the ID

# Then analyze it
curl -X POST "http://localhost/fake-news-platform-b/api/facebook_post_api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "analyze",
    "post_id": 1
  }'

# Expected (takes 10-30 seconds):
# {
#   "success": true,
#   "analysis": {
#     "post_id": 1,
#     "category": "fake_news",           ← Detected category
#     "confidence": 0.95,                ← 0.00 to 1.00
#     "risk_level": "high"               ← low|medium|high
#   }
# }
```

### Test 4.2: Verify Analysis in DB

```sql
SELECT * FROM ai_analysis WHERE post_id = 1;

-- Should show:
-- id: 1
-- post_id: 1
-- category: fake_news
-- confidence_score: 0.95
-- risk_level: high
-- analyzed_at: 2026-05-26 15:41:00
```

### Test 4.3: Get Analyzed Post

```bash
curl "http://localhost/fake-news-platform-b/api/facebook_post_api.php?action=get_post&id=1"

# Expected (should now include analysis):
# {
#   "success": true,
#   "post": {
#     ...all post fields...,
#     "category": "fake_news",
#     "confidence_score": 0.95,
#     "risk_level": "high"
#   }
# }
```

---

## Phase 5: Frontend Dashboard Testing

### Test 5.1: Load Publications Page

```
Navigate to: http://localhost/fake-news-platform-b/pages/publications.php
```

**Expected:**
- Page loads without errors
- Header: "📝 Publications"
- Filter tabs visible: "Tous (1) | À analyser (1) | Analysées (0)"
- Post card displayed:
  - Avatar with first letter "J"
  - Author name
  - Date
  - Content preview
  - (No badge yet, not analyzed)
- "➕ Ajouter une publication" button visible

### Test 5.2: Add New Post via Modal

**Steps:**
1. Click "➕ Ajouter une publication"
2. Modal appears with URL input field
3. Enter new Facebook URL
4. Click "Extraire et analyser"
5. Wait for extraction...
6. Page refreshes
7. New post appears in grid

**Expected:**
- Post appears in "Tous" tab
- Appears in "À analyser" tab
- NOT in "Analysées" tab
- Loading indicator visible during extraction

### Test 5.3: Analyze Post from Dashboard

**Steps:**
1. Find a post card (not yet analyzed)
2. Click "🔄 Analyser" button
3. Wait for AI analysis...
4. Page refreshes
5. Badge appears showing analysis

**Expected:**
- Badge appears with category (FAKE NEWS / DISINFORMATION / etc.)
- Badge color: 🔴 HIGH | 🟡 MEDIUM | 🟢 LOW
- Post moves to "Analysées" tab
- Button changes to "🔄 Réanalyser"

### Test 5.4: View Post Details

**Steps:**
1. Click author name or "👁️" button
2. Redirects to `detail.php?id=1`

**Expected:**
- Full post content displayed
- Author information
- Extraction date
- Analysis results (if analyzed)
- Category + confidence score + risk level

### Test 5.5: Filter by Analysis Status

**Steps:**
1. Click "À analyser" tab
2. See only unanalyzed posts

**Steps:**
3. Click "Analysées" tab
4. See only analyzed posts

**Steps:**
5. Click "Tous" tab
6. See all posts

### Test 5.6: Pagination

**Steps:**
1. Add 25+ posts
2. Only 20 posts show per page (default)
3. "Suivant ►" button appears
4. Click to see page 2
5. Navigation buttons: First | ◄ Prev | Page Numbers | Next ► | Last

### Test 5.7: Delete Post

**Steps:**
1. Click "🗑️" button on any post card
2. Confirm delete in browser alert
3. Post disappears
4. Tab counts update

**Expected:**
- Post removed from DB
- Frontend refreshes
- Counts recalculate

---

## Phase 6: Error Handling

### Test 6.1: Invalid URL

```bash
curl -X POST "http://localhost/fake-news-platform-b/api/facebook_post_api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "extract",
    "url": "https://google.com"
  }'

# Expected Error:
# {
#   "success": false,
#   "error": "URL must contain facebook.com"
# }
```

### Test 6.2: Non-existent Post

```bash
curl "http://localhost/fake-news-platform-b/api/facebook_post_api.php?action=get_post&id=99999"

# Expected:
# {
#   "success": false,
#   "error": "Post not found"
# }
```

### Test 6.3: Invalid Filter

```bash
curl "http://localhost/fake-news-platform-b/api/facebook_post_api.php?action=get_recent_posts&filter=invalid"

# Expected Error or filtered to default
```

### Test 6.4: Missing Required Parameters

```bash
curl -X POST "http://localhost/fake-news-platform-b/api/facebook_post_api.php" \
  -H "Content-Type: application/json" \
  -d '{"action": "extract"}'

# Expected (missing url):
# {
#   "success": false,
#   "error": "URL parameter required"
# }
```

---

## Phase 7: Log Analysis

### Check Python Extractor Logs

```bash
# View extraction logs
cat api/extractor_log.txt

# Expected entries:
# 2026-05-26 15:40:00 — Extracting: https://facebook.com/...
# 2026-05-26 15:40:15 — Success: Author extracted = "Jean Dupont"
# 2026-05-26 15:40:20 — Saved to DB: post_id = 1
```

### Check API Execution Logs

```bash
# View PHP API logs (if logging enabled)
tail api/extractor_log.txt
```

### Windows Event Viewer (Optional)

```bash
# Check PowerShell execution logs
Get-EventLog -LogName Application | Where-Object {$_.Source -like "*Python*"}
```

---

## Test Case Summary

| Phase | Test | Expected Result | Status |
|-------|------|-----------------|--------|
| 1 | DB init | ✅ Tables created | ⏳ TO DO |
| 2.1 | Python test | ✅ Connection OK | ⏳ TO DO |
| 2.2 | Manual extract | ✅ JSON output | ⏳ TO DO |
| 2.3 | Extract + save | ✅ Post in DB | ⏳ TO DO |
| 3.1 | Extract API | ✅ POST success | ⏳ TO DO |
| 3.2 | Get posts | ✅ List retrieved | ⏳ TO DO |
| 3.3 | Filter | ✅ Filtering works | ⏳ TO DO |
| 4.1 | Analyze | ✅ AI result | ⏳ TO DO |
| 4.2 | Verify DB | ✅ Analysis saved | ⏳ TO DO |
| 4.3 | Get analyzed | ✅ Full post | ⏳ TO DO |
| 5.1 | Load page | ✅ Page renders | ⏳ TO DO |
| 5.2 | Modal add | ✅ Post added | ⏳ TO DO |
| 5.3 | Analyze | ✅ Badge appears | ⏳ TO DO |
| 5.4 | View details | ✅ Detail page | ⏳ TO DO |
| 5.5 | Filtering | ✅ Tabs work | ⏳ TO DO |
| 5.6 | Pagination | ✅ Pages navigate | ⏳ TO DO |
| 5.7 | Delete | ✅ Post deleted | ⏳ TO DO |
| 6.1 | Invalid URL | ❌ Error returned | ⏳ TO DO |
| 6.2 | Not found | ❌ 404 response | ⏳ TO DO |
| 6.3 | Invalid filter | ❌ Error handling | ⏳ TO DO |
| 6.4 | Missing params | ❌ Validation | ⏳ TO DO |

---

## Debugging Tips

### 🔴 Common Issues

**Issue: "Chrome not found"**
```
Solution: 
  1. Check C:\Program Files\Google\Chrome\Application\chrome.exe exists
  2. Or use: python facebook_post_extractor.py --browser firefox
```

**Issue: "MySQL connection error"**
```
Solution:
  1. Check MySQL is running (WAMP green)
  2. Verify config.php DB credentials
  3. Test: mysql -u root -p
```

**Issue: "Page never loads" (in extraction)**
```
Solution:
  1. Facebook detected automation
  2. Try headless=False to see what's happening
  3. Add delay: await page.wait_for_timeout(3000)
  4. Try different URL format or older post
```

**Issue: "Analysis returns null"**
```
Solution:
  1. Check python-ai/analyze.py is working
  2. Test: python python-ai/analyze.py --text "test"
  3. Check requirements.txt installed: pip install -r
  4. Model may be downloading (first run takes time)
```

### 📊 Monitoring

**Watch logs in real-time:**
```bash
# PowerShell
Get-Content api/extractor_log.txt -Wait

# Bash (Git Bash)
tail -f api/extractor_log.txt
```

**Monitor database:**
```bash
# Terminal 1: Watch for new posts
mysql -u root -p -e "SELECT COUNT(*) FROM facebook_posts;" | watch -n 1

# Terminal 2: Watch for analyses
mysql -u root -p -e "SELECT COUNT(*) FROM ai_analysis;" | watch -n 1
```

---

## Performance Benchmarks

**Expected Timings:**

| Operation | Time | Notes |
|-----------|------|-------|
| DB init | 2-3 sec | One-time |
| Extract post | 15-30 sec | Depends on network |
| Analyze post | 5-15 sec | Model inference |
| Load dashboard | 1-2 sec | 20 posts/page |
| Filter posts | <1 sec | Client-side |
| Pagination | <1 sec | No reload |

---

**Last Updated:** 26 May 2026
**Status:** Ready for testing
