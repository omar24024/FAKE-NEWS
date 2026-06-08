# ⚡ QUICKSTART — Platform OSINT

## 🎯 في 5 دقائق

### الخطوة 1: إعداد قاعدة البيانات
```bash
# افتح المتصفح وذهب إلى:
http://localhost/fake-news-platform-b/database/init_facebook_posts.php
```
✅ ستري: **"✓ Initialisation complètée avec succès!"**

### الخطوة 2: تثبيت المتطلبات
```bash
cd c:\wamp64\www\fake-news-platform-b
pip install -r python-ai/requirements.txt
```

### الخطوة 3: تشغيل Playwright
```bash
playwright install
```

### الخطوة 4: فتح Dashboard
```
http://localhost/fake-news-platform-b/pages/publications.php
```

### الخطوة 5: إضافة منشور Facebook
1. انقر على **➕ Ajouter une publication**
2. الصق رابط Facebook (مثل `https://facebook.com/...`)
3. انقر **Extraire et analyser**
4. انتظر النتيجة ✅

---

## 📝 الملفات الرئيسية

| الملف | الوظيفة |
|------|---------|
| `python-ai/facebook_post_extractor.py` | استخراج منشورات Facebook |
| `api/facebook_post_api.php` | API REST للعمليات |
| `pages/publications.php` | لوحة التحكم الرئيسية |
| `database/init_facebook_posts.php` | إنشاء الجداول |

---

## 🔧 استكشاف الأخطاء

### ❌ الخطأ: "موعد الوصول غير موجود"
```bash
# تحقق من Python:
python --version  # يجب أن يكون 3.9+

# تحقق من Playwright:
pip show playwright
```

### ❌ الخطأ: "لا يمكن الاتصال بـ Facebook"
✅ هذا طبيعي - Facebook يكتشف الأتمتة
✅ استخدم رابطًا عام (ليس خاصًا)

### ❌ الخطأ: "خطأ قاعدة البيانات"
```bash
# تحقق من MySQL:
http://localhost/phpmyadmin
# تأكد من إنشاء قاعدة البيانات "fake_news_platform"
```

---

## 💡 نصائح سريعة

✅ **استخدم روابط عام عالية:**
```
https://facebook.com/share/p/1CPEP2NmNW/
https://www.facebook.com/stories/...
```

✅ **عرض السجل:**
```
api/extractor_log.txt
```

✅ **اختبار الاتصال:**
```bash
python python-ai/facebook_post_extractor.py --test
```

---

## 🚀 التالي؟

بعد الاستخراج الأول الناجح:

1. **تحليل تلقائي**: انقر على "Analyser" في البطاقة
2. **عرض التفاصيل**: انقر على اسم المؤلف
3. **حذف**: استخدم رمز سلة المهملات
4. **تصفية**: استخدم علامات التبويب (الكل/للتحليل/محلل)

---

**احتاج الى مساعدة؟** اقرأ `README.md`
