# 📚 API Reference — OSINT Platform v2.0

## Base URL

**Development:**
```
http://localhost/fake-news-platform-b/api/facebook_post_api.php
```

**Production:**
```
https://osint.company.com/api/facebook_post_api.php
```

---

## Authentication

Currently, no API authentication is required. In production, add:

```php
// In facebook_post_api.php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized"]));
}
```

---

## Endpoints

### 1. Extract Facebook Post

**Endpoint:** `POST /api/facebook_post_api.php`

**Action:** `extract`

**Description:** Extract data from a Facebook post URL and save to database

**Request Body:**
```json
{
    "action": "extract",
    "url": "https://facebook.com/share/p/1CPEP2NmNW/"
}
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `action` | string | Yes | Must be `"extract"` |
| `url` | string | Yes | Valid Facebook post URL containing `facebook.com` |

**Request Example (cURL):**
```bash
curl -X POST "http://localhost/fake-news-platform-b/api/facebook_post_api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "extract",
    "url": "https://facebook.com/share/p/1CPEP2NmNW/"
  }'
```

**Request Example (JavaScript):**
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

**Response (Success):**
```json
{
  "success": true,
  "post": {
    "id": 1,
    "fb_post_url": "https://facebook.com/share/p/1CPEP2NmNW/",
    "author_name": "Jean Dupont",
    "content": "Ceci est un texte du post Facebook avec du contenu...",
    "image_url": "https://scontent.xx.fbcdn.net/...",
    "published_at": "2026-05-26T10:00:00",
    "extracted_at": "2026-05-26T15:41:23",
    "is_analyzed": 0
  }
}
```

**Response (Error - Invalid URL):**
```json
{
  "success": false,
  "error": "URL must contain facebook.com"
}
```

**Response (Error - Not Found):**
```json
{
  "success": false,
  "error": "Could not extract post data from URL"
}
```

**Response (Error - Server Error):**
```json
{
  "success": false,
  "error": "Database error: SQLSTATE[HY000]: General error: 1030 Got error..."
}
```

**HTTP Status Codes:**
- `200` - Success (check `success` field in JSON)
- `400` - Bad request (invalid parameters)
- `404` - URL not found
- `500` - Server error

**Timing:** 15-30 seconds (includes Chrome launch + page load)

---

### 2. Analyze Facebook Post

**Endpoint:** `POST /api/facebook_post_api.php`

**Action:** `analyze`

**Description:** Run AI analysis on an extracted post

**Request Body:**
```json
{
    "action": "analyze",
    "post_id": 1
}
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `action` | string | Yes | Must be `"analyze"` |
| `post_id` | integer | Yes | ID of extracted post from `facebook_posts` table |

**Request Example (cURL):**
```bash
curl -X POST "http://localhost/fake-news-platform-b/api/facebook_post_api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "analyze",
    "post_id": 1
  }'
```

**Response (Success):**
```json
{
  "success": true,
  "analysis": {
    "post_id": 1,
    "category": "fake_news",
    "confidence": 0.95,
    "risk_level": "high"
  }
}
```

**Response (Error - Post Not Found):**
```json
{
  "success": false,
  "error": "Post not found"
}
```

**Response (Error - Already Analyzed):**
```json
{
  "success": false,
  "error": "Post is already analyzed. Use re-analysis to update."
}
```

**HTTP Status Codes:**
- `200` - Success
- `400` - Bad request
- `404` - Post not found
- `500` - Server error

**Timing:** 5-15 seconds (NLP model inference)

**Category Values:**
- `fake_news` - Content contains factually false information
- `disinformation` - Deliberately misleading content
- `hate_speech` - Content inciting hatred against groups
- `reliable` - Trustworthy content

**Confidence:**
- Number between `0.0` and `1.0`
- `0.95` = 95% confidence in classification

**Risk Levels:**
- `low` - Confidence ≤ 0.6
- `medium` - Confidence 0.6 - 0.85
- `high` - Confidence > 0.85

---

### 3. Get Single Post

**Endpoint:** `GET /api/facebook_post_api.php`

**Action:** `get_post`

**Description:** Retrieve a single post with analysis results (if analyzed)

**Request:**
```
GET /api/facebook_post_api.php?action=get_post&id=1
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `action` | string | Yes | Must be `"get_post"` |
| `id` | integer | Yes | Post ID from `facebook_posts` table |

**Request Example:**
```bash
curl "http://localhost/fake-news-platform-b/api/facebook_post_api.php?action=get_post&id=1"
```

**Response (Success - Not Analyzed):**
```json
{
  "success": true,
  "post": {
    "id": 1,
    "fb_post_url": "https://facebook.com/share/p/1CPEP2NmNW/",
    "author_name": "Jean Dupont",
    "content": "Texte du post...",
    "image_url": "https://...",
    "published_at": "2026-05-26T10:00:00",
    "extracted_at": "2026-05-26T15:41:23",
    "is_analyzed": 0,
    "category": null,
    "confidence_score": null,
    "risk_level": null
  }
}
```

**Response (Success - Analyzed):**
```json
{
  "success": true,
  "post": {
    "id": 1,
    "fb_post_url": "https://facebook.com/share/p/1CPEP2NmNW/",
    "author_name": "Jean Dupont",
    "content": "Texte du post...",
    "image_url": "https://...",
    "published_at": "2026-05-26T10:00:00",
    "extracted_at": "2026-05-26T15:41:23",
    "is_analyzed": 1,
    "category": "fake_news",
    "confidence_score": 0.95,
    "risk_level": "high"
  }
}
```

**Response (Error - Not Found):**
```json
{
  "success": false,
  "error": "Post not found"
}
```

**HTTP Status Codes:**
- `200` - Success
- `400` - Bad request
- `404` - Post not found
- `500` - Server error

---

### 4. Get Recent Posts (Paginated)

**Endpoint:** `GET /api/facebook_post_api.php`

**Action:** `get_recent_posts`

**Description:** List posts with pagination and filtering

**Request:**
```
GET /api/facebook_post_api.php?action=get_recent_posts&page=1&limit=20&filter=all
```

**Parameters:**

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `action` | string | Yes | - | Must be `"get_recent_posts"` |
| `page` | integer | No | `1` | Page number (1-indexed) |
| `limit` | integer | No | `20` | Posts per page (1-100) |
| `filter` | string | No | `all` | `all`, `analyzed`, or `unanalyzed` |

**Request Examples:**

```bash
# Get first 20 posts
curl "http://localhost/.../api/facebook_post_api.php?action=get_recent_posts"

# Get page 2 with 50 posts per page
curl "http://localhost/.../api/facebook_post_api.php?action=get_recent_posts&page=2&limit=50"

# Get only analyzed posts
curl "http://localhost/.../api/facebook_post_api.php?action=get_recent_posts&filter=analyzed"

# Get only unanalyzed posts
curl "http://localhost/.../api/facebook_post_api.php?action=get_recent_posts&filter=unanalyzed"
```

**Response (Success):**
```json
{
  "success": true,
  "posts": [
    {
      "id": 1,
      "fb_post_url": "https://facebook.com/share/p/1CPEP2NmNW/",
      "author_name": "Jean Dupont",
      "content": "Texte du post (preview)...",
      "image_url": "https://...",
      "published_at": "2026-05-26T10:00:00",
      "extracted_at": "2026-05-26T15:41:23",
      "is_analyzed": 1,
      "category": "fake_news",
      "confidence_score": 0.95,
      "risk_level": "high"
    },
    {
      "id": 2,
      "fb_post_url": "https://facebook.com/share/p/2CPEP2NmNW/",
      "author_name": "Marie Martin",
      "content": "Autre texte...",
      "image_url": null,
      "published_at": "2026-05-25T14:00:00",
      "extracted_at": "2026-05-26T14:30:00",
      "is_analyzed": 0,
      "category": null,
      "confidence_score": null,
      "risk_level": null
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_posts": 42,
    "total_pages": 3,
    "per_page": 20
  }
}
```

**Response (Error - Invalid Filter):**
```json
{
  "success": false,
  "error": "Invalid filter. Use: all, analyzed, or unanalyzed"
}
```

**HTTP Status Codes:**
- `200` - Success
- `400` - Bad request
- `500` - Server error

---

### 5. Delete Post

**Endpoint:** `POST /api/facebook_post_api.php`

**Action:** `delete_post`

**Description:** Delete a post and cascade-delete its analysis

**Request Body:**
```json
{
    "action": "delete_post",
    "id": 1
}
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `action` | string | Yes | Must be `"delete_post"` |
| `id` | integer | Yes | Post ID to delete |

**Request Example (cURL):**
```bash
curl -X POST "http://localhost/fake-news-platform-b/api/facebook_post_api.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete_post",
    "id": 1
  }'
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Post and related analyses deleted successfully"
}
```

**Response (Error - Not Found):**
```json
{
  "success": false,
  "error": "Post not found"
}
```

**HTTP Status Codes:**
- `200` - Success
- `400` - Bad request
- `404` - Post not found
- `500` - Server error

**Cascade Behavior:**
- Deletes post from `facebook_posts`
- Deletes analysis from `ai_analysis` (FOREIGN KEY CASCADE)
- Deletes associations from `report_posts` if any (FOREIGN KEY CASCADE)

---

## Error Handling

### HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| `200` | OK | Successful extraction/analysis |
| `400` | Bad Request | Invalid parameters, missing required fields |
| `404` | Not Found | Post doesn't exist |
| `429` | Too Many Requests | Rate limit exceeded |
| `500` | Server Error | Database error, Python crash, etc. |

### Error Response Format

All errors follow this format:

```json
{
  "success": false,
  "error": "Human-readable error message"
}
```

### Common Errors

**Missing Required Parameter:**
```json
{
  "success": false,
  "error": "URL parameter required"
}
```

**Invalid URL:**
```json
{
  "success": false,
  "error": "URL must contain facebook.com"
}
```

**Database Connection Error:**
```json
{
  "success": false,
  "error": "Database error: PDOException: SQLSTATE[HY000] [1045] Access denied for user 'osint_user'@'localhost'"
}
```

**Python Extraction Failed:**
```json
{
  "success": false,
  "error": "Extraction failed: Chrome did not launch. Error: Failed to launch browser"
}
```

**AI Analysis Error:**
```json
{
  "success": false,
  "error": "Analysis failed: Model not found. Run: pip install transformers"
}
```

---

## Rate Limiting

### Implementation

Default: **10 requests per minute per IP**

```
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 9
X-RateLimit-Reset: 1653576083
```

### Exceeding Limit

```json
HTTP/1.1 429 Too Many Requests

{
  "success": false,
  "error": "Rate limit exceeded. Max 10 requests per minute."
}
```

---

## Batch Operations (Future Enhancement)

Currently not supported. Planned for v2.1:

```json
POST /api/facebook_post_api.php

{
  "action": "batch",
  "operations": [
    {"action": "extract", "url": "https://facebook.com/..."},
    {"action": "extract", "url": "https://facebook.com/..."},
    {"action": "analyze", "post_id": 1}
  ]
}
```

---

## Examples

### Example 1: Complete Workflow

```bash
#!/bin/bash

API="http://localhost/fake-news-platform-b/api/facebook_post_api.php"

# 1. Extract post
echo "1. Extracting post..."
POST_RESPONSE=$(curl -s -X POST "$API" \
  -H "Content-Type: application/json" \
  -d '{"action":"extract","url":"https://facebook.com/share/p/1CPEP2NmNW/"}')

echo "Response: $POST_RESPONSE"

# Parse post ID
POST_ID=$(echo "$POST_RESPONSE" | grep -o '"id":[0-9]*' | cut -d: -f2)
echo "Post ID: $POST_ID"

# 2. Wait a moment
sleep 5

# 3. Analyze post
echo "2. Analyzing post..."
ANALYZE_RESPONSE=$(curl -s -X POST "$API" \
  -H "Content-Type: application/json" \
  -d "{\"action\":\"analyze\",\"post_id\":$POST_ID}")

echo "Analysis: $ANALYZE_RESPONSE"

# 4. Get final result
echo "3. Getting final post data..."
FINAL_RESPONSE=$(curl -s "$API?action=get_post&id=$POST_ID")
echo "Final: $FINAL_RESPONSE"
```

### Example 2: Get Analytics

```python
import requests
import json

API_URL = "http://localhost/fake-news-platform-b/api/facebook_post_api.php"

# Get all posts with pagination
all_posts = []
page = 1

while True:
    response = requests.get(API_URL, params={
        "action": "get_recent_posts",
        "page": page,
        "limit": 100
    })
    
    data = response.json()
    if not data["success"]:
        break
    
    all_posts.extend(data["posts"])
    
    if page >= data["pagination"]["total_pages"]:
        break
    
    page += 1

# Calculate statistics
total = len(all_posts)
analyzed = sum(1 for p in all_posts if p["is_analyzed"])
fake_news = sum(1 for p in all_posts if p["category"] == "fake_news")
high_risk = sum(1 for p in all_posts if p["risk_level"] == "high")

print(f"Total posts: {total}")
print(f"Analyzed: {analyzed} ({100*analyzed//total}%)")
print(f"Fake news detected: {fake_news}")
print(f"High risk: {high_risk}")
```

---

## Webhooks (Future Enhancement)

Planned for v2.2: Real-time notifications

```json
POST https://your-server.com/webhook/osint-alert

{
  "event": "analysis_complete",
  "post_id": 1,
  "category": "fake_news",
  "risk_level": "high",
  "timestamp": "2026-05-26T15:41:23Z"
}
```

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| **2.0** | 2026-05-26 | Initial release with 5 endpoints |
| 2.1 | TBA | Batch operations, webhooks |
| 2.2 | TBA | Advanced filtering, export formats |

---

**Last Updated**: 26 May 2026
**API Version**: 2.0
