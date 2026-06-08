#!/usr/bin/env python3
"""
====================================================================
DETECTION DU FAKE NEWS — Module d'analyse IA
====================================================================
Utilise un modèle AraBERT / multilingue HuggingFace pour classifier
les publications Facebook en :
  - fake_news
  - disinformation
  - hate_speech
  - reliable

Usage :
  python analyze.py --all                   # Analyser tous les posts non traités
  python analyze.py --post-id 5             # Analyser un post spécifique
  python analyze.py --text "votre texte"    # Tester un texte directement
====================================================================
"""

import argparse
import json
import re
import sys
import os
import mysql.connector
import logging
from datetime import datetime

# Charger .env du projet (même config que PHP)
try:
    from dotenv import load_dotenv
    _env_path = os.path.join(os.path.dirname(__file__), "..", ".env")
    load_dotenv(_env_path)
except ImportError:
    pass

# ── LOGGING - Force logs to stderr to avoid corrupting JSON output ──
logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)-8s | %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
    stream=sys.stderr  # CRITICAL: Log to stderr, not stdout
)
logger = logging.getLogger(__name__)

# ── Dépendances IA ──────────────────────────────────────────────────
try:
    from transformers import pipeline, AutoTokenizer, AutoModelForSequenceClassification
    import torch
    HAS_TRANSFORMERS = True
except ImportError:
    HAS_TRANSFORMERS = False
    logger.warning("transformers non installé — utilisation du classifieur de secours")

# ── Config base de données ──────────────────────────────────────────
DB_CONFIG = {
    "host":     os.getenv("DB_HOST",   "localhost"),
    "user":     os.getenv("DB_USER",   "root"),
    "password": os.getenv("DB_PASS",   ""),
    "database": os.getenv("DB_NAME",   "fake_news_platform"),
    "charset":  "utf8mb4",
}

# ── Mots-clés hassaniya/arabe (secours si modèle GBERT indisponible) ──
# Pas de mots-clés français — analyse orientée Hassaniya / arabe uniquement.
HASSANIYA_URGENCY_MARKERS = ["!!!", "عاجل", "خبر عاجل", "الحين", "دابا"]


def clean_text(text: str) -> str:
    """Nettoyer et normaliser le texte."""
    text = text.lower().strip()
    text = re.sub(r"http\S+|www\S+", "", text)        # Supprimer URLs
    text = re.sub(r"@\w+", "", text)                   # Supprimer mentions
    text = re.sub(r"#\w+", "", text)                   # Supprimer hashtags
    text = re.sub(r"\s+", " ", text)                   # Normaliser espaces
    text = re.sub(r"[!]{2,}", "!", text)               # Normaliser exclamations
    return text.strip()


# ── Cache des règles de détection chargées depuis la base ─────────────
_rules_cache = None
_rules_cache_timestamp = None
RULES_CACHE_TTL = 3600  # 1 heure


def load_detection_rules():
    """
    Charger les règles de détection depuis la base de données.
    Cache pour 1 heure pour éviter trop de requêtes.
    """
    global _rules_cache, _rules_cache_timestamp
    from datetime import datetime, timedelta
    
    now = datetime.now()
    
    # Utiliser le cache s'il est valide
    if _rules_cache is not None and _rules_cache_timestamp is not None:
        age = (now - _rules_cache_timestamp).total_seconds()
        if age < RULES_CACHE_TTL:
            return _rules_cache
    
    try:
        conn = get_connection()
        cur = conn.cursor()
        
        cur.execute("""
            SELECT category, keyword, weight, rule_type, priority
            FROM ai_detection_rules
            WHERE is_active = 1
            ORDER BY category, priority DESC, weight DESC
        """)
        
        rows = cur.fetchall()
        rules = {}
        
        for category, keyword, weight, rule_type, priority in rows:
            if category not in rules:
                rules[category] = []
            rules[category].append({
                'keyword': keyword.lower(),
                'weight': float(weight),
                'type': rule_type,
                'priority': priority
            })
        
        cur.close()
        conn.close()
        
        _rules_cache = rules
        _rules_cache_timestamp = now
        
        logger.info(f"{sum(len(v) for v in rules.values())} règles chargées depuis la base de données")
        return rules
        
    except Exception as e:
        logger.warning(f"Erreur lors du chargement des règles DB: {e}")
        logger.warning("Utilisation des règles par défaut...")
        return get_default_rules()


def get_default_rules():
    """Règles par défaut hassaniya/arabe (fallback si DB indisponible)."""
    return {
        'fake_news': [
            {'keyword': 'كذبة', 'weight': 0.22, 'type': 'keyword', 'priority': 2},
            {'keyword': 'كيدب', 'weight': 0.22, 'type': 'keyword', 'priority': 2},
            {'keyword': 'شائعة', 'weight': 0.20, 'type': 'keyword', 'priority': 2},
            {'keyword': 'كلام فارغ', 'weight': 0.21, 'type': 'phrase', 'priority': 2},
            {'keyword': 'مش صحيح', 'weight': 0.19, 'type': 'phrase', 'priority': 2},
            {'keyword': 'ما صح', 'weight': 0.18, 'type': 'phrase', 'priority': 2},
            {'keyword': 'انشرو قبل ما', 'weight': 0.20, 'type': 'phrase', 'priority': 2},
        ],
        'disinformation': [
            {'keyword': 'قالو بلي', 'weight': 0.18, 'type': 'phrase', 'priority': 2},
            {'keyword': 'سمعت بلي', 'weight': 0.17, 'type': 'phrase', 'priority': 2},
            {'keyword': 'حسب ما وصل', 'weight': 0.16, 'type': 'phrase', 'priority': 1},
            {'keyword': 'خبار ما مثبتة', 'weight': 0.19, 'type': 'phrase', 'priority': 2},
            {'keyword': 'معلومة غلط', 'weight': 0.18, 'type': 'phrase', 'priority': 2},
        ],
        'hate_speech': [
            {'keyword': 'خرج من البلاد', 'weight': 0.24, 'type': 'phrase', 'priority': 3},
            {'keyword': 'الاجانب', 'weight': 0.16, 'type': 'keyword', 'priority': 1},
            {'keyword': 'موريتانيا لمرتان', 'weight': 0.20, 'type': 'phrase', 'priority': 2},
            {'keyword': 'كره', 'weight': 0.15, 'type': 'keyword', 'priority': 1},
        ],
        'cyberbullying': [
            {'keyword': 'سخف', 'weight': 0.18, 'type': 'keyword', 'priority': 2},
            {'keyword': 'اهين', 'weight': 0.20, 'type': 'keyword', 'priority': 2},
            {'keyword': 'سب', 'weight': 0.16, 'type': 'keyword', 'priority': 1},
        ],
        'misinformation': [
            {'keyword': 'دواء يشفي', 'weight': 0.22, 'type': 'phrase', 'priority': 2},
            {'keyword': 'علاج سري', 'weight': 0.21, 'type': 'phrase', 'priority': 2},
            {'keyword': '100%', 'weight': 0.14, 'type': 'keyword', 'priority': 1},
        ],
    }


def rule_based_classify(text: str, use_db_rules: bool = True) -> dict:
    """
    Classifieur basé sur les règles (fallback sans transformers).
    Charge les règles depuis la base de données si disponible.
    Retourne category + confidence + keywords détectés.
    """
    text_lower = text.lower()
    scores = {"fake_news": 0.0, "disinformation": 0.0, "hate_speech": 0.0, "misinformation": 0.0, "propaganda": 0.0, "violence": 0.0, "cyberbullying": 0.0, "reliable": 0.0}
    detected_keywords = []

    # Charger les règles
    if use_db_rules:
        rules = load_detection_rules()
    else:
        rules = get_default_rules()

    # Appliquer les règles pour chaque catégorie
    for category in ['fake_news', 'disinformation', 'hate_speech', 'misinformation', 'propaganda', 'violence', 'cyberbullying']:
        if category not in rules:
            continue
        
        for rule in rules[category]:
            keyword = rule['keyword']
            weight = rule['weight']
            rule_type = rule['type']
            
            matched = False
            
            if rule_type == 'keyword':
                # Correspondance de mot-clé simple
                if keyword in text_lower:
                    matched = True
            elif rule_type == 'phrase':
                # Correspondance de phrase
                if keyword in text_lower:
                    matched = True
            elif rule_type == 'regex':
                # Correspondance par expression régulière
                try:
                    if re.search(keyword, text_lower):
                        matched = True
                except re.error:
                    pass
            
            if matched:
                scores[category] += weight
                detected_keywords.append((keyword, min(scores[category], 0.95), category))

    # Urgence hassaniya/arabe (pas de marqueurs français)
    urgency = sum(1 for s in HASSANIYA_URGENCY_MARKERS if s in text_lower)
    if urgency >= 2:
        scores["fake_news"] += 0.15

    # Déterminer la catégorie finale
    max_score = max(
        scores["fake_news"], scores["disinformation"], scores["hate_speech"],
        scores["misinformation"], scores.get("propaganda", 0), scores.get("violence", 0),
        scores.get("cyberbullying", 0),
    )
    if max_score < 0.30:
        scores["reliable"] = 0.85
        category = "reliable"
        confidence = 85.0
    else:
        category = max(
            (k for k in ['fake_news', 'disinformation', 'hate_speech', 'misinformation', 'propaganda', 'violence', 'cyberbullying']),
            key=lambda k: scores[k],
        )
        confidence = min(scores[category] * 100, 97.0)
        confidence = max(confidence, 60.0)

    # Niveau de risque
    if confidence >= 90 or category == "hate_speech":
        risk = "critical"
    elif confidence >= 75:
        risk = "high"
    elif confidence >= 60:
        risk = "medium"
    else:
        risk = "low"

    return {
        "category":   category,
        "confidence": round(confidence, 2),
        "risk_level": risk,
        "keywords":   detected_keywords[:8],
        "model":      "rule-based-dynamic",
    }



# ── Classifieur HuggingFace ─────────────────────────────────────────
_classifier = None

def _uses_gbert() -> bool:
    model_name = os.getenv("AI_MODEL", "cardiffnlp/twitter-xlm-roberta-base-sentiment")
    return model_name.lower() in ("gbert-hassaniya", "gbert", "gbert_hassaniya")


def gbert_classify(text: str, rule_result: dict) -> dict:
    """Classifier GBERT (Hassaniya) + règles multi-catégories."""
    try:
        from gbert_model import predict
    except ImportError as e:
        logger.warning("Module gbert_model indisponible: %s", e)
        return rule_result

    try:
        pred, conf, _probs = predict(text)
    except Exception as e:
        logger.warning("Erreur GBERT: %s — fallback règles", e)
        return rule_result

    category = "fake_news" if pred == 1 else "reliable"
    rule_cat = rule_result.get("category", "reliable")
    rule_conf = float(rule_result.get("confidence", 0))

    priority_rules = (
        "hate_speech", "cyberbullying", "violence", "misinformation",
        "disinformation", "propaganda",
    )
    if rule_cat in priority_rules and rule_conf >= 55:
        category = rule_cat
        conf = max(conf, rule_conf)
    elif pred == 1 and rule_cat == "disinformation" and rule_conf >= 50:
        category = "disinformation"
        conf = max(conf, rule_conf)

    if conf >= 90 or category in ("hate_speech", "violence"):
        risk = "critical"
    elif conf >= 75:
        risk = "high"
    elif conf >= 60:
        risk = "medium"
    else:
        risk = "low"

    return {
        "category": category,
        "confidence": round(conf, 2),
        "risk_level": risk,
        "keywords": rule_result["keywords"],
        "model": "gbert-hassaniya (AraBERT+AraGPT2)",
    }


def load_model():
    """Charger le modèle HuggingFace (lazy loading)."""
    global _classifier
    if _classifier is not None:
        return _classifier
    if not HAS_TRANSFORMERS:
        return None

    if _uses_gbert():
        return "gbert"

    # Modèles recommandés (par ordre de préférence) :
    # 1. "aubmindlab/bert-base-arabertv02"     — AraBERT v2 (arabe)
    # 2. "microsoft/Multilingual-MiniLM-L12-H384" — multilingue
    # 3. "cardiffnlp/twitter-xlm-roberta-base-sentiment" — multilingue sentiment
    model_name = os.getenv("AI_MODEL", "cardiffnlp/twitter-xlm-roberta-base-sentiment")

    logger.info(f"Chargement du modèle : {model_name}")
    try:
        _classifier = pipeline(
            "text-classification",
            model=model_name,
            tokenizer=model_name,
            max_length=512,
            truncation=True,
            device=0 if torch.cuda.is_available() else -1,
        )
        logger.info("Modèle chargé avec succès")
    except Exception as e:
        logger.warning(f"Impossible de charger {model_name}: {e}")
        _classifier = None

    return _classifier


def ai_classify(text: str) -> dict:
    """
    Classifier avec HuggingFace + enrichissement par règles depuis la base.
    Si le modèle n'est pas disponible, utilise le classifieur par règles dynamiques.
    """
    cleaned = clean_text(text)
    # Charger les règles depuis la base de données
    rule_result = rule_based_classify(cleaned, use_db_rules=True)

    clf = load_model()
    if clf is None:
        return rule_result

    if clf == "gbert":
        return gbert_classify(text.strip() or cleaned, rule_result)

    try:
        result = clf(cleaned[:512])[0]
        label  = result["label"].lower()
        score  = result["score"]

        # Mapper les labels du modèle vers nos catégories
        label_map = {
            "label_0": "reliable",     # NEGATIVE sentiment → souvent fiable
            "label_1": "disinformation",
            "label_2": "fake_news",    # POSITIVE sentiment → peut être trompeur
            "positive": "reliable",
            "negative": "fake_news",
            "neutral":  "disinformation",
        }
        ai_cat = label_map.get(label, rule_result["category"])

        # Fusion règles + modèle (pondération 60/40)
        final_cat = ai_cat if score > 0.70 else rule_result["category"]
        final_conf = round(score * 60 + rule_result["confidence"] * 0.40, 2)
        final_conf = min(max(final_conf, 55.0), 98.0)

        # Risk level
        if final_conf >= 90 or final_cat == "hate_speech":
            risk = "critical"
        elif final_conf >= 75:
            risk = "high"
        elif final_conf >= 60:
            risk = "medium"
        else:
            risk = "low"

        return {
            "category":   final_cat,
            "confidence": final_conf,
            "risk_level": risk,
            "keywords":   rule_result["keywords"],
            "model":      "arabert-multilingual+rule-based-dynamic",
        }

    except Exception as e:
        logger.warning(f"Erreur IA: {e} — utilisation du classifieur par règles")
        return rule_result


# ── Base de données ─────────────────────────────────────────────────
def get_connection():
    return mysql.connector.connect(**DB_CONFIG)


def save_analysis(post_id: int, result: dict) -> int:
    """Sauvegarder le résultat d'analyse en base."""
    conn = get_connection()
    cur  = conn.cursor()

    # Upsert analysis
    cur.execute("""
        INSERT INTO ai_analysis (post_id, category, confidence_score, risk_level, model_used)
        VALUES (%s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            category         = VALUES(category),
            confidence_score = VALUES(confidence_score),
            risk_level       = VALUES(risk_level),
            model_used       = VALUES(model_used)
    """, (post_id, result["category"], result["confidence"], result["risk_level"], result["model"]))

    # lastrowid is 0 when ON DUPLICATE KEY UPDATE triggers, so always re-select
    cur.execute("SELECT id FROM ai_analysis WHERE post_id = %s LIMIT 1", (post_id,))
    row = cur.fetchone()
    analysis_id = int(row[0]) if row else 0

    # Supprimer anciens keywords
    cur.execute("DELETE FROM detected_keywords WHERE analysis_id = %s", (analysis_id,))

    # Insérer nouveaux keywords
    for kw, weight, cat in result.get("keywords", []):
        cur.execute("""
            INSERT INTO detected_keywords (analysis_id, keyword, weight, category)
            VALUES (%s, %s, %s, %s)
        """, (analysis_id, kw, round(weight, 3), cat))

    # Marquer le post comme analysé
    cur.execute("UPDATE facebook_posts SET is_analyzed = 1 WHERE id = %s", (post_id,))

    # Notifications: alerte si haut risque (admins)
    try:
        if result.get("risk_level") in ("high", "critical"):
            cur.execute("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")
            admin_ids = [r[0] for r in cur.fetchall()]
            title = "Alerte : contenu à haut risque détecté"
            msg = f"Publication #{post_id} classifiée {result.get('category')} ({result.get('confidence')}% confiance) — risque {result.get('risk_level')}."
            notif_type = "alert" if result.get("risk_level") == "critical" else "warning"
            for uid in admin_ids:
                cur.execute(
                    """
                    INSERT INTO notifications (user_id, title, message, type, is_read, post_id)
                    VALUES (%s, %s, %s, %s, 0, %s)
                    """,
                    (uid, title, msg, notif_type, post_id),
                )
    except Exception:
        pass

    conn.commit()
    cur.close()
    conn.close()
    return analysis_id


def _ensure_comments_table(cur) -> None:
    try:
        cur.execute("""
            CREATE TABLE IF NOT EXISTS post_comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                author_name VARCHAR(255) NULL,
                content TEXT NOT NULL,
                category VARCHAR(50) NULL,
                confidence_score DECIMAL(5,2) NULL,
                risk_level ENUM('low','medium','high','critical') DEFAULT 'low',
                model_used VARCHAR(100) NULL,
                is_analyzed TINYINT(1) DEFAULT 0,
                sort_order INT DEFAULT 0,
                analyzed_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_post_id (post_id),
                INDEX idx_is_analyzed (is_analyzed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
    except Exception:
        pass


def analyze_comments(post_id: int, reanalyze: bool = False) -> list:
    """Analyser les commentaires texte d'une publication avec GBERT."""
    conn = get_connection()
    cur = conn.cursor(dictionary=True)
    _ensure_comments_table(cur)

    if reanalyze:
        cur.execute(
            "SELECT id, content, author_name FROM post_comments WHERE post_id = %s ORDER BY sort_order, id",
            (post_id,),
        )
    else:
        cur.execute(
            "SELECT id, content, author_name FROM post_comments WHERE post_id = %s AND is_analyzed = 0 ORDER BY sort_order, id",
            (post_id,),
        )
    rows = cur.fetchall()
    results = []

    for row in rows:
        text = (row.get("content") or "").strip()
        if len(text) < 3:
            continue
        result = ai_classify(text)
        cur.execute(
            """
            UPDATE post_comments SET
                is_analyzed = 1,
                category = %s,
                confidence_score = %s,
                risk_level = %s,
                model_used = %s,
                analyzed_at = NOW()
            WHERE id = %s
            """,
            (
                result["category"],
                result["confidence"],
                result["risk_level"],
                result["model"],
                row["id"],
            ),
        )
        results.append({
            "comment_id": row["id"],
            "author_name": row.get("author_name"),
            "content": text[:300],
            "category": result["category"],
            "confidence": result["confidence"],
            "risk_level": result["risk_level"],
            "model": result["model"],
        })

    conn.commit()
    cur.close()
    conn.close()
    return results


def _comments_summary(comment_results: list) -> dict:
    if not comment_results:
        return {"total": 0, "flagged": 0, "worst_category": None, "worst_confidence": None}
    flagged = [
        c for c in comment_results
        if c.get("category") not in ("reliable", None)
    ]
    worst = max(comment_results, key=lambda c: float(c.get("confidence") or 0))
    return {
        "total": len(comment_results),
        "flagged": len(flagged),
        "worst_category": worst.get("category"),
        "worst_confidence": worst.get("confidence"),
    }


def analyze_post(post_id: int) -> dict:
    """Analyser un post par son ID (+ commentaires texte associés)."""
    conn = get_connection()
    cur  = conn.cursor(dictionary=True)
    cur.execute("SELECT id, content FROM facebook_posts WHERE id = %s", (post_id,))
    post = cur.fetchone()
    cur.close()
    conn.close()

    if not post:
        return {"error": f"Post {post_id} introuvable"}

    result = ai_classify(post["content"] or "")
    analysis_id = save_analysis(post_id, result)
    comment_results = analyze_comments(post_id)
    summary = _comments_summary(comment_results)

    return {
        "post_id":     post_id,
        "analysis_id": analysis_id,
        "category":    result["category"],
        "confidence":  result["confidence"],
        "risk_level":  result["risk_level"],
        "keywords":    [k[0] for k in result["keywords"]],
        "model":       result["model"],
        "analyzed_at": datetime.now().isoformat(),
        "comments_analyzed": len(comment_results),
        "comments": comment_results,
        "comments_summary": summary,
    }


def analyze_all_pending() -> list:
    """Analyser tous les posts non encore traités."""
    conn = get_connection()
    cur  = conn.cursor(dictionary=True)
    cur.execute("SELECT id, content FROM facebook_posts WHERE is_analyzed = 0")
    posts = cur.fetchall()
    cur.close()
    conn.close()

    if not posts:
        logger.info("Aucun post en attente d'analyse.")
        return []

    logger.info(f"{len(posts)} post(s) à analyser…")
    results = []
    for post in posts:
        logger.info(f"Post #{post['id']}: {post['content'][:60]}…")
        r = ai_classify(post["content"] or "")
        save_analysis(post["id"], r)
        results.append({"post_id": post["id"], **r})
        logger.info(f"Analyse: {r['category']} ({r['confidence']}%)")

    logger.info(f"Analyse terminée : {len(results)} post(s) traité(s)")
    return results


# ── CLI ─────────────────────────────────────────────────────────────
if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Module IA — Détection du Fake News")
    parser.add_argument("--all",     action="store_true", help="Analyser tous les posts non traités")
    parser.add_argument("--post-id", type=int,            help="Analyser un post spécifique par ID")
    parser.add_argument("--text",    type=str,            help="Tester un texte directement (sans DB)")
    parser.add_argument("--json",    action="store_true", help="Sortie JSON")
    args = parser.parse_args()

    if args.text:
        result = ai_classify(args.text)
        if args.json:
            print(json.dumps(result, ensure_ascii=False, indent=2))
        else:
            print(f"\n📊 Résultat d'analyse")
            print(f"   Catégorie   : {result['category']}")
            print(f"   Confiance   : {result['confidence']}%")
            print(f"   Risque      : {result['risk_level']}")
            print(f"   Mots-clés   : {[k[0] for k in result['keywords']]}")
            print(f"   Modèle      : {result['model']}\n")

    elif args.post_id:
        result = analyze_post(args.post_id)
        print(json.dumps(result, ensure_ascii=False, indent=2) if args.json else result)

    elif args.all:
        results = analyze_all_pending()
        if args.json:
            print(json.dumps(results, ensure_ascii=False, indent=2))

    else:
        parser.print_help()
