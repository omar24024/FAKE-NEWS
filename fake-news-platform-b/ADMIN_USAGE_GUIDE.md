# 📋 AI Detection Rules — Admin Usage Examples

## Real-World Scenarios

### Scenario 1: Detecting Fake News About COVID-19

**Goal**: Add health misinformation keywords to strengthen COVID detection

**Steps**:

1. **Access Settings** → "Règles de détection IA"

2. **Add these keywords:**

```
Keyword              Category        Weight   Type     Description
──────────────────────────────────────────────────────────────────
"vaccin danger"      misinformation  0.23     phrase   Vaccine fear-mongering
"efficacité nulle"   misinformation  0.22     phrase   False efficacy claim
"5G et covid"        fake_news       0.25     phrase   5G conspiracy link
"remède secret"      misinformation  0.21     phrase   Hidden cure claim
"gouvernement ment"  fake_news       0.20     phrase   Authority distrust
```

3. **Test the system**:
   ```bash
   python analyze.py --text "Les vaccins sont dangereux! Secret gouvernement caché!"
   # Output: confidence 45%, category: fake_news, risk: medium
   ```

4. **Monitor results** over next 24 hours

---

### Scenario 2: Detecting Hate Speech Against a Minority Group

**Goal**: Add targeted hate speech keywords for specific context

**Steps**:

1. **Access Settings** → "Règles de détection IA"

2. **Add these keywords:**

```
Keyword                 Category       Weight   Type     Description
─────────────────────────────────────────────────────────────────
"[GroupName] dehors"   hate_speech    0.26     phrase   Expulsion rhetoric
"[GroupName] prennent" hate_speech    0.24     phrase   Scapegoating
"ennemi [GroupName]"   hate_speech    0.25     phrase   Enemy framing
"nettoyer la ville"    hate_speech    0.27     phrase   Ethnic cleansing rhetoric
"traîtres à pays"      hate_speech    0.24     phrase   Accusations of disloyalty
```

⚠️ **Important**: Replace `[GroupName]` with actual group name or use phrase matching with pattern

3. **Set higher priority** (5-10) for immediate detection

4. **Test with specific text**

---

### Scenario 3: Contextual Business Rules for Your Platform

**Goal**: Add industry-specific misinformation keywords

**Example for Financial Context**:

```
Keyword                 Category         Weight   Type     Description
─────────────────────────────────────────────────────────────────
"manipulé par cartels" propaganda       0.20     phrase   Market manipulation claim
"prix explosera"       fake_news        0.18     phrase   False price prediction
"acheter avant"        disinformation   0.16     phrase   Pump & dump scheme signal
"officiel révélation"  fake_news        0.20     phrase   False authority claim
```

---

## Advanced Workflows

### Workflow 1: A/B Testing Rule Changes

**Goal**: Test if increasing a weight improves accuracy

**Steps**:

1. **Note current performance**:
   ```sql
   SELECT AVG(confidence_score) FROM ai_analysis WHERE category='fake_news';
   -- Example: 68.5% average confidence
   ```

2. **Increase weight** of a specific rule:
   - Edit rule: "urgent"
   - Change weight from 0.18 → 0.22

3. **Monitor for 1 week**:
   ```sql
   SELECT AVG(confidence_score) FROM ai_analysis 
   WHERE category='fake_news' AND created_at > DATE_SUB(NOW(), INTERVAL 1 WEEK);
   ```

4. **Compare results**:
   - If confidence ↑ and false positives ↓ → Keep change
   - If issues ↑ → Revert

---

### Workflow 2: Seasonal Rule Adjustments

**Goal**: Adapt rules for seasonal events

**January - Election Season**:
```
"voto roubado"         fake_news       0.24     phrase   Election theft claim
"fraude électorale"    fake_news       0.25     phrase   Electoral fraud
"candidat acusação"    propaganda      0.20     phrase   False accusations
```

**March - Disease Season**:
```
"epidemia falsa"       misinformation  0.22     phrase   False epidemic
"morte censuram"       fake_news       0.23     phrase   Death coverup claim
```

**July - Political Crisis**:
```
"golpe iminente"       propaganda      0.23     phrase   Coup rhetoric
"subversion Estado"    propaganda      0.22     phrase   State overthrow claim
```

---

### Workflow 3: Responding to New Viral Misinformation

**Situation**: New false claim spreading on social media

**Quick Response Steps**:

1. **Identify the claim**:
   - Example: "5G causes COVID" (if not already detected)

2. **Add keywords immediately**:
   - Keyword: "5G cause covid"
   - Category: misinformation
   - Weight: 0.24 (high, new trend)
   - Priority: 10 (evaluate first)

3. **Test with real posts**:
   ```bash
   python analyze.py --text "[viral_text_here]"
   ```

4. **Monitor detection rate**:
   - How many posts matched?
   - Is confidence appropriate?
   - False positive rate?

5. **Adjust if needed**:
   - If confidence too low → increase weight
   - If catching false positives → decrease weight
   - If too aggressive → lower priority

---

## Key Metrics & Optimization

### Metrics to Track

```sql
-- Detection Rate by Category
SELECT category, COUNT(*) as detected_count, AVG(confidence_score) as avg_confidence
FROM ai_analysis
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY category
ORDER BY detected_count DESC;

-- High-Weight Rules Performance
SELECT keyword, COUNT(*) as match_count, AVG(confidence_score) as avg_conf
FROM ai_analysis a
JOIN detected_keywords dk ON a.id = dk.analysis_id
JOIN ai_detection_rules adr ON dk.keyword = adr.keyword
WHERE adr.weight >= 0.25
GROUP BY keyword
ORDER BY match_count DESC;

-- Rules by Creation Date (Recent additions)
SELECT keyword, category, weight, created_at
FROM ai_detection_rules
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;
```

### Optimization Checklist

- [ ] Review low-performing rules (< 0.3 avg confidence)
- [ ] Identify frequently matched keywords
- [ ] Check false positive rate (manual review)
- [ ] Adjust weights based on effectiveness
- [ ] Remove redundant rules
- [ ] Add missing contextual keywords
- [ ] Test with recent viral posts

---

## Common Admin Tasks

### Task 1: Disable a Rule Without Deleting

**Why**: Keep audit trail, can re-enable later

**Steps**:
1. Find rule in UI
2. Click **"Éditer"**
3. Note current values (screenshot/notes)
4. Click **"Supprimer"**
5. If needed later, add new rule with same values

### Task 2: Bulk Update Weights

**For**: Increasing detection sensitivity across category

**Via Database**:
```sql
UPDATE ai_detection_rules 
SET weight = weight * 1.2
WHERE category = 'hate_speech' AND weight < 0.25
AND updated_by IS NOT NULL;
-- This increases weights by 20% for non-default rules
```

### Task 3: Export Current Rules

**Via Database**:
```sql
SELECT CONCAT_WS(',', category, keyword, weight, rule_type, priority, description)
INTO OUTFILE '/tmp/ai_rules_backup.csv'
FROM ai_detection_rules
WHERE is_active = 1
ORDER BY category, priority DESC;
```

Then download via:
```bash
scp user@server:/tmp/ai_rules_backup.csv ./backup/
```

### Task 4: Compare Performance Before/After Change

**Before Change**:
```sql
SELECT COUNT(*) as before_count, AVG(confidence_score) as before_avg
FROM ai_analysis
WHERE category='fake_news' AND created_at < '2026-05-20';
-- Example: 125 posts, 68.5% avg confidence
```

**Make changes**

**After Change** (7 days later):
```sql
SELECT COUNT(*) as after_count, AVG(confidence_score) as after_avg
FROM ai_analysis
WHERE category='fake_news' AND created_at >= '2026-05-20';
-- Example: 142 posts, 71.2% avg confidence (+2.7%)
```

---

## Troubleshooting for Admins

### Problem: "Rules not showing in settings page"

**Check**:
```bash
# 1. Are you logged in as admin?
# User icon → check "Role" should be "admin"

# 2. In browser console (F12):
# Try: fetch('/api/ai_rules.php?action=list').then(r => r.json()).then(d => console.log(d))
```

### Problem: "New rule not detecting anything"

**Diagnosis**:
```bash
# Test the rule manually
python analyze.py --text "Your exact keyword here"

# Check if keyword is exact match or if phrase matching issue
# Remember: phrases are case-insensitive and match substrings
```

### Problem: "Too many false positives"

**Solutions**:
1. Decrease weight (e.g., 0.20 → 0.15)
2. Change from keyword to phrase (more specific)
3. Use regex for complex matching
4. Increase priority of more reliable rules
5. Remove the rule entirely

### Problem: "Not enough detections"

**Solutions**:
1. Increase weight (e.g., 0.15 → 0.22)
2. Add related keywords/phrases
3. Lower priority (evaluate earlier)
4. Broaden regex patterns
5. Switch from phrase to keyword matching

---

## Best Practices

### ✅ DO:

- ✅ Test each new rule with real posts first
- ✅ Use specific phrases instead of single words when possible
- ✅ Document rule descriptions for future reference
- ✅ Review rules monthly for effectiveness
- ✅ Keep high-weight rules for obvious indicators
- ✅ Use priorities to control evaluation order
- ✅ Monitor false positive rate
- ✅ Archive old rules instead of deleting

### ❌ DON'T:

- ❌ Use weights > 0.30 for ambiguous keywords
- ❌ Create rules for every variation (use regex instead)
- ❌ Forget to document why rules exist
- ❌ Set too many rules as priority 10 (everything becomes urgent)
- ❌ Use regex without testing first
- ❌ Create overlapping rules for same content
- ❌ Change multiple weights at once (can't isolate results)
- ❌ Ignore performance metrics

---

## Example Rulesets by Region/Context

### Mauritania-Specific Rules

```
Keyword                    Category       Weight   Description
───────────────────────────────────────────────────────────
"caste inférieur"         hate_speech    0.26     Caste-based discrimination
"Noir expulsé"            hate_speech    0.27     Racial expulsion rhetoric
"amazigh vs arabe"        propaganda     0.22     Ethnic conflict incitement
"coup militaire"          propaganda     0.24     Frequent political claims
"blocus médias"           fake_news      0.21     Media blockade claims
"fraude législatives"     fake_news      0.23     Election fraud claims
```

### Middle East-Specific Rules

```
"infiltrés étrangers"     propaganda     0.23
"complot sioniste"        propaganda     0.25     (context-dependent)
"drones ciblés"           misinformation 0.20
"révolution imminente"    propaganda     0.21
```

---

## Monthly Review Checklist

**Last Review Date**: __________

- [ ] Review top-performing rules (highest match count)
- [ ] Review lowest-performing rules (fewest matches)
- [ ] Check false positive rates
- [ ] Review new viral misinformation trends
- [ ] Add 5-10 new context-specific keywords
- [ ] Archive underperforming rules
- [ ] Update priority order if needed
- [ ] Document changes in log
- [ ] Test with recent high-profile posts

**Notes**:
```
[Add notes from this month's review]
```

---

## Support Resources

- 📖 **Full Guide**: [AI_DETECTION_RULES_GUIDE.md](../AI_DETECTION_RULES_GUIDE.md)
- 🚀 **Quick Start**: [QUICKSTART_AI_RULES.md](../QUICKSTART_AI_RULES.md)
- 💻 **Technical Details**: [api/ai_rules.php](../api/ai_rules.php)
- 🐍 **Python Integration**: [python-ai/analyze.py](../python-ai/analyze.py)

---

**Document Version**: 1.0
**Last Updated**: 2026-05-26
**For Questions**: Contact platform administrator
