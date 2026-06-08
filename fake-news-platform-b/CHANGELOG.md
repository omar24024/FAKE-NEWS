# 📝 Changelog — OSINT Platform

All notable changes to Fake News Platform are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.0.0] — 2026-05-26

### 🎯 Major Release: Complete Strategy Pivot

**Key Theme:** From mass Facebook scraping to professional individual URL-based OSINT analysis

### ✅ Added

#### Core Features
- **Facebook Post Extractor** (`python-ai/facebook_post_extractor.py`)
  - 480 lines of async Playwright code
  - Real Chrome browser integration (not Chromium)
  - Persistent browser session with anti-webdriver detection
  - DOM parsing for post content extraction
  - MySQL database persistence
  - JSON CLI interface

- **OSINT REST API** (`api/facebook_post_api.php`)
  - 5 endpoints for extract/analyze/retrieve/list/delete operations
  - Clean JSON request/response format
  - Comprehensive error handling
  - Execution logging for debugging

- **OSINT Dashboard** (`pages/publications.php`)
  - Modern modal-based URL input interface
  - Responsive post card grid layout
  - Color-coded analysis badges (risk levels)
  - Filter tabs: Tous/À analyser/Analysées
  - Pagination with 20 posts/page
  - Real-time post counts in tabs
  - Delete and re-analyze functionality

- **Database Schema** (`database/init_facebook_posts.php`)
  - `facebook_posts` table (extracted posts)
  - `ai_analysis` table (analysis results with FOREIGN KEY)
  - `osint_reports` table (optional reporting)
  - `report_posts` table (M:N liaison)
  - Automatic table creation script

- **Documentation Suite** (NEW)
  - `README.md` — Project overview and features
  - `QUICKSTART.md` — 5-minute setup guide
  - `ARCHITECTURE.md` — System design and component details
  - `TESTING.md` — Comprehensive testing procedures
  - `DEPLOYMENT.md` — Production deployment guide
  - `API_REFERENCE.md` — Full API documentation
  - `CHANGELOG.md` — This file

#### Security Improvements
- Input validation on all API endpoints
- SQL injection prevention (prepared statements)
- XSS prevention (HTML escaping)
- Rate limiting framework (configurable)
- HTTPS/SSL ready configuration
- Secure session handling

#### Infrastructure
- Python async architecture with Playwright
- MySQL InnoDB database with proper indexes
- File-based logging for extraction debugging
- Chrome persistent context for stability
- Windows ProactorEventLoop policy for async on Windows

### 🔄 Changed

#### Architecture Changes
- **Complete pivot from** mass scraping API to individual URL extraction
- **Removed** old Scraper system entirely (v1 deprecation)
- **Refactored** API from scraper-focused to analyst-focused
- **Updated** dashboard from table layout to modern grid cards

#### File Structure
- Deleted: `facebook_scraper.py`, `config_scraper.py`, `test_setup.py`
- Deleted: `facebook_scraper_api.php`, `facebook_api.php`
- Deleted: `pages/scraper.php`, `SCRAPER_QUICKSTART.md`
- Deleted: Old documentation files (MODULE_*.md, EXAMPLES.md)
- Deleted: Old session management files

#### Database Changes
- Migrated from generic `facebook_posts` (v1) to specialized schema
- Added explicit `ai_analysis` table with FOREIGN KEY relationships
- Added analysis metadata: category, confidence_score, risk_level
- Implemented CASCADE delete for referential integrity

#### Python Integration
- **Updated** `facebook_scraper.py` to use real Chrome
- **Changed** browser initialization from launch() to launch_persistent_context()
- **Fixed** Chrome User Data path (local `chrome_session` instead of main profile)
- **Added** anti-webdriver JavaScript injection
- **Verified** exit code 0 for real Chrome launches

#### UI/UX Changes
- **Modal dialog** for URL input (replaced in-page form)
- **Grid layout** instead of table (responsive, modern)
- **Color badges** for risk levels (red/yellow/green)
- **Filter tabs** with dynamic counts
- **Pagination controls** with first/last buttons
- **Real-time updates** on analysis completion

#### API Changes
- New endpoint structure: `?action=extract|analyze|get_post|get_recent_posts|delete_post`
- Changed from form data to JSON request body
- Enhanced error responses with descriptive messages
- Added pagination support with limit/page parameters

### 🐛 Fixed

#### Bug Fixes
- **PDO Error**: Changed `$db->affected_rows` → `$stmt->rowCount()` in facebook_scraper_api.php
- **Chrome Exit Code 21**: Resolved by using local chrome_session folder instead of main User Data directory
- **Browser Lock Conflicts**: Fixed file locking issues with persistent context
- **Async Event Loop**: Applied Windows-specific ProactorEventLoop policy

#### Code Quality
- Removed MySQLi code remnants (now pure PDO)
- Fixed database connection pool handling
- Verified Python syntax across all 500+ lines
- Eliminated race conditions in extraction

### 📚 Documentation

- Added comprehensive API reference with cURL/JavaScript examples
- Created architecture diagrams and data flow documentation
- Provided step-by-step testing procedures
- Documented production deployment with security hardening
- Included troubleshooting guides and scaling recommendations

### 🔒 Security

- Implemented HTTPS/SSL configuration templates
- Added firewall rules documentation
- Secured database user permissions
- Configured rate limiting framework
- Documented session security best practices

### 🚀 Performance

- Async extraction with concurrent page loads
- Persistent Chrome context (no startup overhead)
- Headless mode for faster rendering
- Disabled images during extraction (speed boost)
- Database indexing on frequently-queried columns
- Pagination for large datasets

### 📦 Dependencies

#### Python
- `playwright` (1.40.0+) — Real browser automation
- `mysql-connector-python` — Database connectivity
- `transformers` — AI NLP models
- `torch` — Deep learning framework
- `asyncio` — Async Python

#### PHP
- PDO MySQL extension (built-in)
- PHP 8.0+ features

#### System
- Google Chrome (real browser, not Chromium)
- MySQL 5.7+ or MariaDB 10.3+
- Python 3.9+

### 🎨 UI Improvements

- Modern gradient background (#667eea → #764ba2)
- Responsive grid layout (3 cols desktop, 1 col mobile)
- Hover effects on cards (shadow, scale)
- Color-coded badges by risk level
- Smooth modal animations
- Auto-hiding alert messages

### 📊 Dashboard Metrics

New dashboard displays:
- Total posts in database
- Posts awaiting analysis (count)
- Analyzed posts (count)
- Category distribution
- Risk level breakdown

---

## [1.0.0] — 2026-04-15

### ❌ DEPRECATED

Initial release with mass Facebook scraping system.

**Features (now removed):**
- Facebook scraper module (Chromium-based)
- Bulk page/profile scraping
- Graph API integration
- Scraper control panel
- Import/export functionality

**Reason for Deprecation:**
- Unstable mass scraping implementation
- Inefficient resource usage (memory, CPU)
- Difficulty in maintaining Chrome processes
- Moved to professional OSINT analysis workflow

---

## Versioning Strategy

This project uses [Semantic Versioning](https://semver.org/):

- **MAJOR** version bump: Significant architecture changes (1.0 → 2.0)
- **MINOR** version bump: New features, backward compatible
- **PATCH** version bump: Bug fixes, security patches

---

## Future Roadmap

### v2.1.0 (Q3 2026)

- [ ] Batch operations API endpoint
- [ ] Webhook notifications for analysis results
- [ ] Advanced filtering (date range, author, content keywords)
- [ ] Export formats (PDF, Excel, JSON reports)
- [ ] User roles and permissions system
- [ ] Multi-language support

### v2.2.0 (Q4 2026)

- [ ] Real-time alert system (Slack/Teams integration)
- [ ] Duplicate content detection (clustering)
- [ ] Content propagation graphs
- [ ] Credibility scoring system
- [ ] Source tracking and traceability
- [ ] Automated report generation

### v3.0.0 (2027)

- [ ] Distributed extraction (multiple workers)
- [ ] Advanced NLP models (GPT integration)
- [ ] Social network analysis
- [ ] Video/image content analysis
- [ ] Fact-checking API integration
- [ ] Public API for third-party integrations

---

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### How to Contribute

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## Security Policy

### Reporting Vulnerabilities

Please email security@company.com with:
- Vulnerability description
- Steps to reproduce
- Potential impact
- Your contact information

**Do NOT** open public issues for security vulnerabilities.

### Supported Versions

| Version | Status | End of Support |
|---------|--------|-----------------|
| 2.0.x | Active Development | 2027-05-26 |
| 1.x.x | Deprecated | 2026-06-26 |

---

## Breaking Changes

### From v1.0 to v2.0

⚠️ **MAJOR BREAKING CHANGES:**

1. **API Endpoints**
   - Old: `api/facebook_scraper_api.php?action=startScraping`
   - New: `api/facebook_post_api.php` with 5 new actions

2. **Database Schema**
   - Old: Single `facebook_posts` table
   - New: `facebook_posts` + `ai_analysis` (linked)

3. **Extraction Method**
   - Old: Mass page/profile scraping
   - New: Individual URL extraction

4. **URL Format**
   - Old: Page IDs, profile names
   - New: Full Facebook post URLs only

5. **UI/UX**
   - Old: Scraper control panel
   - New: OSINT analyst dashboard

**Migration Path:**
- Backup v1 database
- Run `database/init_facebook_posts.php` for new schema
- Update any third-party integrations to use new API

---

## Known Issues

### Current (v2.0.0)

- [ ] Facebook may block extraction after ~10 consecutive requests (rate limiting)
- [ ] Private/restricted posts cannot be extracted (expected behavior)
- [ ] Chrome sometimes crashes on large posts (500+ KB content) — restart required
- [ ] Image extraction occasionally fails for cached/CDN images

### Workarounds

**Issue: "Chrome did not launch"**
```
Workaround: Restart Python process, check available disk space
```

**Issue: "Post not found"**
```
Workaround: Verify URL is public, wait 30 seconds, try again
```

**Issue: "AI analysis takes 30+ seconds"**
```
Workaround: First model load downloads ~500MB, subsequent analyses cached
```

---

## Acknowledgments

- **Playwright** team for robust browser automation
- **HuggingFace** for pre-trained NLP models
- **React community** for UI/UX inspiration
- **OSINT researchers** who provided workflow feedback

---

## License

This project is licensed under the MIT License - see [LICENSE](LICENSE) file for details.

---

## Support

- 📖 **Documentation**: See `README.md` and other .md files
- 🐛 **Issues**: Open an issue on GitHub
- 💬 **Discussions**: Forum at company.com/osint
- 📧 **Email**: support@company.com

---

**Last Updated**: 26 May 2026
**Current Version**: 2.0.0
**Next Review**: June 2026
