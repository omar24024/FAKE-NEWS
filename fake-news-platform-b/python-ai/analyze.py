#!/usr/bin/env python3
import argparse
import json
import re
import sys
import mysql.connector
import logging

# ── LOGGING ──
logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)-8s | %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
    stream=sys.stderr
)
logger = logging.getLogger(__name__)

# ── المسار الثابت للنموذج (تم التعديل لإجبار النظام على استخدامه) ──
LOCAL_MODEL_PATH = "/home/abass/Desktop/PiS4/FAKE-NEWS/fake-news-platform-b/python-ai/modelAi/mon_gbert_hassaniya_5classes"

# ── Dépendances IA ──
try:
    from transformers import pipeline, AutoTokenizer, AutoModelForSequenceClassification
    import torch
    HAS_TRANSFORMERS = True
except ImportError:
    HAS_TRANSFORMERS = False
    logger.warning("transformers غير مثبت.")

# ── Config base de données ──
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "fake_news_platform",
    "charset": "utf8mb4",
}

def clean_text(text: str) -> str:
    text = text.lower().strip()
    text = re.sub(r"http\S+|www\S+", "", text)
    text = re.sub(r"@\w+", "", text)
    text = re.sub(r"#\w+", "", text)
    text = re.sub(r"\s+", " ", text)
    return text.strip()

# ── Classifieur GBERT Fine-Tuned ──
_classifier = None

def load_model():
    global _classifier
    if _classifier is not None: return _classifier
    if not HAS_TRANSFORMERS: return None

    logger.info(f"جاري تحميل النموذج المحلي من : {LOCAL_MODEL_PATH}")
    
    try:
        tokenizer = AutoTokenizer.from_pretrained(LOCAL_MODEL_PATH)
        model = AutoModelForSequenceClassification.from_pretrained(LOCAL_MODEL_PATH)
        device_id = 0 if torch.cuda.is_available() else -1
        _classifier = pipeline("text-classification", model=model, tokenizer=tokenizer, device=device_id)
        logger.info("✅ تم تحميل نموذج GBERT الحسّاني بنجاح!")
    except Exception as e:
        logger.error(f"❌ فشل تحميل النموذج من {LOCAL_MODEL_PATH}: {e}")
        _classifier = None
    return _classifier

def ai_classify(text: str) -> dict:
    cleaned = clean_text(text)
    clf = load_model()
    
    # محرك القواعد الاحتياطي (في حال فشل النموذج)
    rule_result = {"category": "reliable", "confidence": 50.0, "keywords": []}

    if clf is None: 
        return rule_result

    try:
        # التنبؤ الذكي بالنموذج
        result = clf(cleaned[:512])[0]
        ai_cat = result["label"].lower().strip()
        score  = result["score"]

        valid_categories = ["fake_news", "hate_speech", "misinformation", "cyberbullying", "reliable"]
        if ai_cat not in valid_categories: 
            ai_cat = "reliable"

        final_cat = ai_cat
        final_conf = round(score * 100, 2)

        # تحديد مستوى الخطورة
        if final_conf >= 85 or final_cat in ("hate_speech", "cyberbullying"):
            risk = "critical"
        elif final_conf >= 70:
            risk = "high"
        elif final_conf >= 55:
            risk = "medium"
        else:
            risk = "low"

        return {
            "category": final_cat, 
            "confidence": final_conf, 
            "risk_level": risk,
            "keywords": [], 
            "model": "Fine-Tuned GBERT (5-Class)"
        }
    except Exception as e:
        logger.warning(f"Erreur IA inference: {e}")
        return rule_result

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--text", type=str)
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args()

    if args.text:
        res = ai_classify(args.text)
        if args.json:
            print(json.dumps(res, ensure_ascii=False, indent=2))
        else:
            print(res)