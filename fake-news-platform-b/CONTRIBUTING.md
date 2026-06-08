# 🤝 Contributing to OSINT Platform

Thank you for considering contributing to Fake News Platform! We welcome bug reports, feature requests, and code contributions.

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Report security issues privately (security@company.com)
- Follow ethical OSINT principles
- Respect privacy and legal requirements

---

## How to Contribute

### 1. Reporting Bugs

**Before reporting, check:**
- [ ] Issue doesn't already exist
- [ ] You're using latest version
- [ ] You can reproduce the issue
- [ ] You have logs/screenshots

**Bug Report Template:**
```markdown
**Describe the bug**
Brief description of the issue

**To Reproduce**
Steps to reproduce the behavior:
1. Extract post from URL...
2. Click analyze...
3. See error...

**Expected behavior**
What should happen instead

**Actual behavior**
What actually happens

**Environment**
- OS: [Windows 10/Ubuntu 20.04]
- Python: [3.9.5]
- PHP: [8.1.2]
- Browser: [Chrome 96.0]

**Logs**
Paste relevant error logs from:
- api/extractor_log.txt
- /var/log/osint/php_error.log
```

### 2. Suggesting Enhancements

**Enhancement Proposal Template:**
```markdown
**Is your feature request related to a problem?**
Describe the problem this would solve

**Describe the solution you'd like**
How should this feature work?

**Describe alternatives you've considered**
Other approaches?

**Additional context**
Any other information?

**Implementation difficulty**
[ ] Easy  [ ] Medium  [ ] Hard  [ ] Unknown
```

### 3. Code Contributions

#### Setup Development Environment

```bash
# 1. Clone repository
git clone https://github.com/yourorg/fake-news-platform.git
cd fake-news-platform-b

# 2. Create feature branch
git checkout -b feature/amazing-feature

# 3. Setup Python environment
python -m venv venv
source venv/bin/activate  # Linux/Mac
# or
.\venv\Scripts\Activate  # Windows

# 4. Install dependencies
pip install -r python-ai/requirements.txt
pip install pylint black pytest

# 5. Setup local database
mysql -u root -p -e "CREATE DATABASE fake_news_platform_dev;"
php database/init_facebook_posts.php

# 6. Run tests
pytest tests/

# 7. Start development server
php -S localhost:8000 -t .
```

#### Code Style Guidelines

**Python:**
```python
# Use black for formatting
black python-ai/*.py

# Use pylint for linting
pylint python-ai/facebook_post_extractor.py

# Type hints recommended
async def extract_post(url: str) -> Dict[str, Any]:
    """Extract Facebook post data from URL."""
    pass

# Docstrings for functions
def save_post(post_data: Dict) -> int:
    """
    Save extracted post to database.
    
    Args:
        post_data: Dictionary containing post fields
        
    Returns:
        int: Post ID if successful, -1 if duplicate
        
    Raises:
        DatabaseError: If database connection fails
    """
    pass
```

**PHP:**
```php
<?php
// PSR-12 code style
// https://www.php-fig.org/psr/psr-12/

function extractPost(string $url): array {
    // Descriptive variable names
    $isValidUrl = filter_var($url, FILTER_VALIDATE_URL);
    
    // Document functions
    /**
     * Extract post from Facebook URL
     * 
     * @param string $url Facebook post URL
     * @return array Post data or error
     */
}
?>
```

**SQL:**
```sql
-- Descriptive table/column names
-- Use snake_case for identifiers
-- Comment complex queries

SELECT 
    p.id,
    p.author_name,
    a.category,
    a.risk_level
FROM facebook_posts p
LEFT JOIN ai_analysis a ON p.id = a.post_id
WHERE p.extracted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY p.extracted_at DESC;
```

#### Commit Guidelines

```bash
# Use clear, descriptive commit messages
# Format: type(scope): description

# Examples:
git commit -m "feat(extractor): add timeout handling"
git commit -m "fix(api): resolve PDO error in delete endpoint"
git commit -m "docs(readme): update installation steps"
git commit -m "refactor(dashboard): optimize pagination query"
git commit -m "test(extractor): add unit tests for URL validation"

# Types:
# - feat: New feature
# - fix: Bug fix
# - docs: Documentation only
# - style: Code style (no logic change)
# - refactor: Code reorganization
# - perf: Performance improvement
# - test: Tests added/modified
# - chore: Build/dependencies

# Scope: Optional, specific component
# Description: Imperative mood, present tense
```

#### Testing Requirements

**Write tests for:**
- [ ] New functions/methods
- [ ] Bug fixes (add regression test)
- [ ] Edge cases
- [ ] Error conditions

**Test locations:**
```
tests/
├── unit/
│   ├── test_extractor.py
│   ├── test_api.py
│   └── test_db.py
├── integration/
│   ├── test_extraction_workflow.py
│   └── test_api_endpoints.py
└── fixtures/
    └── sample_posts.json
```

**Example Python test:**
```python
import pytest
from facebook_post_extractor import FacebookPostExtractor

class TestExtractor:
    @pytest.fixture
    def extractor(self):
        return FacebookPostExtractor()
    
    def test_url_validation_valid(self, extractor):
        """Valid Facebook URL passes validation"""
        url = "https://facebook.com/share/p/1CPEP2NmNW/"
        assert extractor.validate_url(url) is True
    
    def test_url_validation_invalid_domain(self, extractor):
        """Non-Facebook URL fails validation"""
        url = "https://google.com"
        assert extractor.validate_url(url) is False
    
    def test_url_validation_empty(self, extractor):
        """Empty URL fails validation"""
        assert extractor.validate_url("") is False
```

**Example PHP test:**
```php
<?php
// tests/unit/TestAPI.php
use PHPUnit\Framework\TestCase;

class TestFacebookPostAPI extends TestCase {
    public function testExtractEndpointMissingUrl() {
        // Simulate POST request without URL
        $_POST = ["action" => "extract"];
        
        ob_start();
        include "api/facebook_post_api.php";
        $response = json_decode(ob_get_clean(), true);
        
        $this->assertFalse($response["success"]);
        $this->assertStringContainsString("required", $response["error"]);
    }
}
?>
```

#### Pull Request Process

1. **Before submitting:**
   - [ ] Code follows style guidelines (black, pylint, PSR-12)
   - [ ] All tests pass (`pytest tests/`)
   - [ ] No hardcoded credentials or API keys
   - [ ] Documentation updated (README, CHANGELOG)
   - [ ] Commit messages clear and descriptive

2. **Submit PR:**
   - [ ] Title is clear and descriptive
   - [ ] Description explains changes and why
   - [ ] References related issues (#123)
   - [ ] Screenshots for UI changes
   - [ ] Mentions breaking changes (if any)

3. **PR Template:**
```markdown
## Description
Brief overview of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Breaking change

## Related Issues
Closes #123

## Changes Made
- Change 1
- Change 2
- Change 3

## Testing
How to test these changes:
1. ...
2. ...

## Screenshots (if applicable)
[Paste screenshots here]

## Checklist
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] No console errors
- [ ] Code follows style guide
```

4. **Code Review:**
   - At least 1 approval required
   - All tests must pass
   - No merge conflicts
   - CI/CD pipeline successful

---

## Documentation Guidelines

### Adding Documentation

**For new features:**
1. Update `README.md` (feature overview)
2. Update `ARCHITECTURE.md` (technical details)
3. Update `API_REFERENCE.md` (if API change)
4. Add example in `TESTING.md`
5. Update `CHANGELOG.md` (under Unreleased)

**Documentation style:**
```markdown
# Clear Title (use H1 for main topics)

## Brief description
One paragraph explaining what this does

### Technical Details
- Bullet point 1
- Bullet point 2

### Example
\`\`\`bash
# Code example
python script.py --flag value
\`\`\`

### See Also
- Link to related documentation
```

### Documentation Review

- [ ] Grammar and spelling correct
- [ ] Code examples tested and accurate
- [ ] Links work (internal and external)
- [ ] Screenshots up-to-date
- [ ] Instructions are complete and clear

---

## Development Workflow

### From Issue to Merged PR

```
1. Issue reported/created (GitHub Issues)
   ↓
2. Assigned to developer
   ↓
3. Developer creates feature branch
   git checkout -b feature/issue-123
   ↓
4. Code changes made, tests written
   ↓
5. Commits pushed to branch
   git push origin feature/issue-123
   ↓
6. Pull Request created against main/develop
   ↓
7. Code review by maintainers
   ↓
8. Requested changes (if any)
   ↓
9. All tests passing, reviews approved
   ↓
10. PR merged to main
    ↓
11. Issue closed
    ↓
12. Release in next version (if applicable)
```

---

## Help Needed

**Areas we need help with:**

- [ ] **Bug Fixes** — Look for issues tagged `bug`
- [ ] **Documentation** — Help improve guides and examples
- [ ] **Testing** — Add test coverage for untested code
- [ ] **Performance** — Optimize slow operations
- [ ] **Security** — Review code for vulnerabilities
- [ ] **Translations** — Help localize UI (Arabic, French, etc.)

**Difficulty Levels:**
- `good first issue` — Perfect for first-time contributors
- `help wanted` — Assistance appreciated
- `expert only` — Requires deep knowledge

---

## Questions?

- 📖 Check the [README.md](README.md)
- 📚 Read [ARCHITECTURE.md](ARCHITECTURE.md)
- 💬 Ask in GitHub Discussions
- 📧 Email: contribute@company.com

---

## Recognition

Contributors will be recognized in:
- Release notes
- Contributors list in README
- Annual acknowledgments

Thank you for contributing! 🙏

---

**Last Updated**: 26 May 2026
**Version**: 2.0
