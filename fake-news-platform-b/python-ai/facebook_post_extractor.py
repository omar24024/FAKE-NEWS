#!/usr/bin/env python3
"""
====================================================================
FACEBOOK POST EXTRACTOR — OSINT Intelligence Tool
====================================================================

Extraction intelligente de données de publications Facebook publiques.
Analyse et stockage des contenus visibles légalement.

Fonctionnalités:
- Extraction de URL de publications individuelles
- Récupération du texte, auteur, image, date
- Sauvegarde en MySQL
- API JSON pour intégration frontend
- Gestion des erreurs et timeouts

Usage:
    python facebook_post_extractor.py --url "https://facebook.com/..." --json

Author: OSINT Intelligence Platform
Version: 2.0 (Post-based OSINT)
Last Updated: 2026-05-26
====================================================================
"""

import argparse
import asyncio
import json
import logging
import os
import re
import sys
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple
from urllib.parse import urlparse, parse_qs

import mysql.connector
from mysql.connector import Error as MySQLError
from playwright.async_api import async_playwright, Page

# Force UTF-8 output on Windows consoles
os.environ["PYTHONIOENCODING"] = "utf-8"


def _resolve_playwright_browsers_path() -> str:
    """Chemin Chromium valide (ignore PLAYWRIGHT_BROWSERS_PATH invalide / sandbox)."""
    candidates = [
        os.getenv("PLAYWRIGHT_BROWSERS_PATH"),
        os.path.expanduser("~/gbert_project/playwright-browsers"),
        str(Path(__file__).parent / "playwright-browsers"),
    ]
    for path in candidates:
        if not path:
            continue
        base = Path(path)
        shell = base / "chromium_headless_shell-1223" / "chrome-headless-shell-linux64" / "chrome-headless-shell"
        chrome = base / "chromium-1223" / "chrome-linux" / "chrome"
        if shell.is_file() or chrome.is_file():
            return str(base)
    return candidates[1] or candidates[2]


os.environ["PLAYWRIGHT_BROWSERS_PATH"] = _resolve_playwright_browsers_path()
try:
    sys.stdout.reconfigure(encoding='utf-8')
    sys.stderr.reconfigure(encoding='utf-8')
except Exception:
    # Older Python or non-reconfigurable streams may fail silently
    pass
# ────────────────────────────────────────────────────────────────────
# LOGGING - Force logs to stderr to avoid corrupting JSON output
# ────────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)-8s | %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
    stream=sys.stderr  # CRITICAL: Log to stderr, not stdout
)
logger = logging.getLogger(__name__)

# ────────────────────────────────────────────────────────────────────
# DATABASE CONFIG
# ────────────────────────────────────────────────────────────────────
DB_CONFIG = {
    "host": os.getenv("DB_HOST", "localhost"),
    "user": os.getenv("DB_USER", "root"),
    "password": os.getenv("DB_PASS", ""),
    "database": os.getenv("DB_NAME", "fake_news_platform"),
    "charset": "utf8mb4",
    "autocommit": True,
}

# ────────────────────────────────────────────────────────────────────
# CONSTANTS
# ────────────────────────────────────────────────────────────────────
SCRIPT_DIR = Path(__file__).parent
TIMEOUT_MS = 15000
USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36"
)

# Nombres d'engagement (likes, commentaires, partages, abonnés)
_METRIC_NUMBER_RE = re.compile(
    r"(?P<num>\d+(?:[.,]\d+)?)\s*(?P<suffix>[kKmM])?",
    re.UNICODE,
)


def parse_metric_number(text: str) -> Optional[int]:
    """Parse '1,2 K', '15', '2.5M', '1 234' en entier."""
    if not text:
        return None
    blob = text.replace("\xa0", " ").strip().lower()
    m = _METRIC_NUMBER_RE.search(blob)
    if not m:
        return None
    raw = m.group("num").replace(" ", "")
    if raw.count(",") == 1 and raw.count(".") == 0:
        raw = raw.replace(",", ".")
    elif raw.count(",") > 1 or ("," in raw and "." in raw):
        raw = raw.replace(",", "").replace(".", "")
    try:
        num = float(raw)
    except ValueError:
        return None
    suffix = (m.group("suffix") or "").lower()
    if suffix == "k":
        num *= 1000
    elif suffix == "m":
        num *= 1_000_000
    return max(0, int(num))


# Libellés UI / faux positifs à rejeter pour le nom d'auteur
_AUTHOR_REJECT_RE = re.compile(
    r"^(publication de|post de|posted by|partagé par|shared by|voir plus|see more|"
    r"auteur inconnu|inconnu|facebook|meta|sponsored|sponsorisé)\b",
    re.I,
)

# ────────────────────────────────────────────────────────────────────
# EXTRACTEUR
# ────────────────────────────────────────────────────────────────────
class FacebookPostExtractor:
    """
    Outil OSINT pour extraction de données Facebook publiques.
    
    Traite uniquement les contenus publics et visibles légalement.
    """

    def __init__(self):
        self.playwright_instance = None
        self.browser = None
        self.context = None
        self.page: Optional[Page] = None

    async def init_browser(self) -> bool:
        """Initialise Playwright (Chrome systeme ou Chromium Playwright)."""
        launch_args = {
            "user_data_dir": str(SCRIPT_DIR / "chrome_session"),
            "headless": os.getenv("CHROME_HEADLESS", "true").lower() != "false",
            "args": [
                "--disable-blink-features=AutomationControlled",
                "--disable-extensions",
                "--disable-dev-shm-usage",
                "--disable-gpu",
                "--no-first-run",
            ],
            "viewport": {"width": 1920, "height": 1080},
            "user_agent": USER_AGENT,
            "locale": "fr-FR",
            "timezone_id": "Europe/Paris",
        }

        try:
            logger.info("Initialisation du navigateur...")
            self.playwright_instance = await async_playwright().start()

            # Essayer Chrome installe, sinon Chromium Playwright (Linux/Kali)
            try:
                self.context = await self.playwright_instance.chromium.launch_persistent_context(
                    channel="chrome",
                    **launch_args,
                )
                logger.info("Navigateur: Google Chrome")
            except Exception as chrome_err:
                logger.warning("Chrome introuvable (%s) — fallback Chromium Playwright", chrome_err)
                self.context = await self.playwright_instance.chromium.launch_persistent_context(
                    **launch_args,
                )
                logger.info("Navigateur: Chromium Playwright")

            self.page = await self.context.new_page()

            await self.page.add_init_script("""
                Object.defineProperty(navigator, 'webdriver', {
                    get: () => false,
                });
            """)

            logger.info("✓ Navigateur prêt")
            return True

        except Exception as e:
            logger.error(f"✗ Erreur navigateur: {e}")
            return False

    def _is_login_or_blocked(self, content: str, title: str, author: str, author_url: Optional[str]) -> bool:
        """Détecte page login Facebook ou contenu non accessible."""
        blob = f"{content} {title} {author} {author_url or ''}".lower()
        login_markers = [
            "se connecter", "mot de passe", "informations de compte oubli",
            "vous devez vous connecter", "log in", "sign up", "créer un compte",
            "create a page", "créer une page", "bootstrapwebsession",
        ]
        if author_url and "pages/create" in author_url.lower():
            return True
        if any(m in blob for m in login_markers):
            return True
        if content and content.strip().startswith("requireLazy"):
            return True
        return False

    async def close_browser(self) -> None:
        """Ferme le navigateur."""
        try:
            if self.page:
                await self.page.close()
            if self.context:
                await self.context.close()
            if self.playwright_instance:
                await self.playwright_instance.stop()
        except:
            pass

    @staticmethod
    def _clean_author_name(name: Optional[str]) -> Optional[str]:
        if not name:
            return None
        cleaned = re.sub(r"\s+", " ", name).strip()
        cleaned = re.sub(r"^(publication de|post de|posted by|partagé par)\s+", "", cleaned, flags=re.I).strip()
        if not cleaned or len(cleaned) < 2 or len(cleaned) > 80:
            return None
        if _AUTHOR_REJECT_RE.search(cleaned):
            return None
        if re.fullmatch(r"[\d\s:./\-h]+", cleaned, flags=re.I):
            return None
        if cleaned.lower() in {"auteur inconnu", "inconnu", "unknown", "facebook"}:
            return None
        return cleaned[:255]

    @staticmethod
    def _is_valid_author(name: Optional[str]) -> bool:
        return FacebookPostExtractor._clean_author_name(name) is not None

    async def _extract_author(self) -> Tuple[Optional[str], Optional[str]]:
        """
        Extrait le nom d'auteur visible via sélecteurs multiples + évaluation DOM.
        Gère publications, commentaires, pages et profils.
        Retourne (author_name, author_profile_url).
        """
        candidates: List[Tuple[str, int, Optional[str]]] = []

        def add_candidate(raw: Optional[str], score: int, href: Optional[str] = None) -> None:
            clean = self._clean_author_name(raw)
            if clean:
                candidates.append((clean, score, href))

        # ── Stratégie 1: JavaScript — en-tête de publication/commentaire ──
        try:
            js_result = await self.page.evaluate(
                """() => {
                    // Chercher dans article ou main
                    const root = document.querySelector('div[role="article"]')
                        || document.querySelector('[role="main"] article')
                        || document.querySelector('[role="main"]')
                        || document.querySelector('div[data-pagelet]');
                    if (!root) return null;

                    const pickText = (el) => {
                        if (!el) return '';
                        const t = (el.innerText || el.textContent || '').trim();
                        return t.split('\\n').map(s => s.trim()).filter(Boolean)[0] || '';
                    };

                    const profilePatterns = [
                        '/user/', '/profile.php', '/people/', '/pages/', '/groups/',
                        'facebook.com/', 'fb.com/'
                    ];

                    const isProfileHref = (href) => {
                        if (!href) return false;
                        const h = href.toLowerCase();
                        if (h.includes('/posts/') || h.includes('/videos/') || h.includes('/photo')) return false;
                        return profilePatterns.some(p => h.includes(p))
                            || (h.includes('facebook.com') && !h.includes('/share'));
                    };

                    const out = [];

                    // h2 (nom principal sur posts récents)
                    root.querySelectorAll('h2').forEach(h2 => {
                        const link = h2.querySelector('a[href]') || h2.closest('a[href]');
                        const name = pickText(h2);
                        if (name) out.push({ name, href: link?.href || null, score: 95 });
                    });

                    // h3 (pour commentaires et variantes)
                    root.querySelectorAll('h3').forEach(h3 => {
                        const link = h3.querySelector('a[href]') || h3.closest('a[href]');
                        const name = pickText(h3);
                        if (name && name.length < 80) out.push({ name, href: link?.href || null, score: 88 });
                    });

                    // strong + span / liens
                    root.querySelectorAll('strong span, strong a, span[dir="auto"] strong').forEach(el => {
                        const link = el.closest('a[href]') || el.querySelector('a[href]');
                        const name = pickText(el);
                        if (name && name.length < 80) out.push({ name, href: link?.href || null, score: 85 });
                    });

                    // liens profil / role=link
                    root.querySelectorAll('a[role="link"][href], a[href][aria-label]').forEach(a => {
                        const href = a.href || '';
                        if (!isProfileHref(href)) return;
                        let name = pickText(a);
                        const aria = (a.getAttribute('aria-label') || '').trim();
                        if ((!name || name.length < 2) && aria && aria.length < 80) name = aria;
                        if (name) out.push({ name, href, score: 80 });
                    });

                    // data-visualcompletion (blocs nom)
                    root.querySelectorAll('[data-visualcompletion] a[href], [data-visualcompletion] span[dir="auto"]').forEach(el => {
                        const link = el.tagName === 'A' ? el : el.closest('a[href]');
                        const name = pickText(el);
                        const href = link?.href || null;
                        if (name && name.length < 80) out.push({ name, href, score: 70 });
                    });

                    // div[dir=auto] en haut de l'article (premiers éléments courts)
                    const autos = root.querySelectorAll('div[dir="auto"]');
                    for (let i = 0; i < Math.min(autos.length, 10); i++) {
                        const name = pickText(autos[i]);
                        if (name && name.length >= 2 && name.length <= 60) {
                            const link = autos[i].closest('a[href]');
                            out.push({ name, href: link?.href || null, score: 55 - i });
                        }
                    }

                    // Spécifique aux commentaires: chercher dans les structures de commentaire
                    const commentBlocks = root.querySelectorAll('[role="comment"], [data-comment-id]');
                    commentBlocks.forEach(block => {
                        const authorLink = block.querySelector('a[href*="/profile.php"], a[href*="/user/"], a[href*="/people/"]');
                        if (authorLink) {
                            const name = pickText(authorLink);
                            if (name && name.length < 80) {
                                out.push({ name, href: authorLink.href, score: 93 });
                            }
                        }
                    });

                    return out.length ? out : null;
                }"""
            )
            if js_result:
                for item in js_result:
                    add_candidate(item.get("name"), int(item.get("score", 50)), item.get("href"))
        except Exception as e:
            logger.debug(f"JS auteur échoué: {e}")

        # ── Stratégie 2: sélecteurs Playwright améliorés ──
        author_selectors = [
            # Publications principales
            ("div[role='article'] h2 span[dir='auto']", 92, True),
            ("div[role='article'] h2 a", 91, True),
            ("h2 span", 90, True),
            ("h2 >> a", 89, True),
            # Commentaires
            ("div[role='comment'] a[href*='/profile.php']", 94, True),
            ("div[role='comment'] a[href*='/user/']", 93, True),
            ("div[role='comment'] span[dir='auto']", 92, False),
            ("[data-comment-id] a[href*='/profile.php']", 94, True),
            # Strong elements
            ("div[role='article'] strong span", 88, False),
            ("div[role='article'] strong a", 87, True),
            ("article strong span", 86, False),
            ("article strong a", 85, True),
            ("div[role='main'] strong >> a", 84, True),
            # Links with role
            ("div[role='article'] a[role='link']", 82, True),
            ("div[role='main'] a[role='link']", 81, True),
            ("div[role='comment'] a[role='link']", 83, True),
            # Profile URLs
            ("a[href*='/user/']", 80, True),
            ("a[href*='profile.php']", 79, True),
            ("a[href*='/pages/']", 78, True),
            ("a[href*='/people/']", 77, True),
            # Data attributes
            ("[data-visualcompletion] a[role='link']", 76, True),
            ("[data-visualcompletion] span[dir='auto']", 75, False),
            # Fallback
            ("div[dir='auto'] >> xpath=ancestor::div[@role='article']//a[@role='link']", 74, True),
            ("span[dir='auto'] >> xpath=ancestor::div[@role='comment']//a", 73, True),
        ]

        for selector, score, want_href in author_selectors:
            try:
                loc = self.page.locator(selector).first
                if await loc.count() == 0:
                    continue
                text = (await loc.text_content() or "").strip()
                href = None
                if want_href:
                    try:
                        href = await loc.get_attribute("href")
                    except Exception:
                        href = None
                if not href:
                    try:
                        href = await loc.evaluate(
                            "el => el.closest('a[href]')?.href || el.href || null"
                        )
                    except Exception:
                        pass
                add_candidate(text, score, href)
            except Exception:
                continue

        # ── Stratégie 3: aria-label sur liens ──
        try:
            links = await self.page.locator("div[role='article'] a[aria-label], [role='main'] a[aria-label], div[role='comment'] a[aria-label]").all()
            for link in links[:15]:
                label = await link.get_attribute("aria-label")
                href = await link.get_attribute("href")
                add_candidate(label, 72, href)
        except Exception:
            pass

        # ── Stratégie 4: og:title / document title ──
        try:
            og_title = await self.page.locator("meta[property='og:title']").get_attribute("content")
            if og_title:
                # "Jean Dupont - Publication | Facebook" ou "Commentaire de..."
                part = og_title.split(" - ")[0].split(" | ")[0].strip()
                # Nettoyer les préfixes comme "Commentaire de", "Publication de"
                part = re.sub(r'^(commentaire de|publication de|post de)\s*', '', part, flags=re.I)
                add_candidate(part, 45, None)
        except Exception:
            pass

        # ── Stratégie 5: document.title comme dernier recours ──
        try:
            doc_title = await self.page.title()
            if doc_title:
                # Nettoyer le titre de la page
                part = doc_title.split(" - ")[0].split(" | ")[0].strip()
                part = re.sub(r'^(commentaire de|publication de|post de)\s*', '', part, flags=re.I)
                if len(part) > 2 and len(part) < 80:
                    add_candidate(part, 40, None)
        except Exception:
            pass

        if not candidates:
            logger.warning("Aucun candidat auteur valide")
            return None, None

        name, score, href = max(candidates, key=lambda c: c[1])
        logger.info(f"Auteur retenu: {name} (score {score})")
        if href and href.startswith("/"):
            href = "https://www.facebook.com" + href
        return name, href

    def validate_facebook_url(self, url: str) -> bool:
        """Valide que l'URL est une publication Facebook publique."""
        if not url:
            return False

        # Accepter les URLs Facebook standard (various domains)
        facebook_domains = ['facebook.com', 'fb.com', 'm.facebook.com', 'www.facebook.com', 'mbasic.facebook.com']
        url_lower = url.lower()
        has_facebook_domain = any(domain in url_lower for domain in facebook_domains)

        if not has_facebook_domain:
            return False

        # Éviter les URLs privées/auth
        private_paths = ['facebook.com/login', 'facebook.com/messages', 'facebook.com/settings',
                        'facebook.com/messages/read', 'facebook.com/groups/addmember']
        if any(path in url_lower for path in private_paths):
            return False

        # Accepter les URLs de publications, commentaires, pages, profils
        # Patterns valides: /posts/, /photos/, /videos/, /permalink.php, /comments/, /groups/, /pages/, /profile.php
        valid_patterns = ['/posts/', '/photos/', '/videos/', '/permalink.php', '/comments/',
                         '/groups/', '/pages/', '/profile.php', '/people/', '/notes/']
        has_valid_pattern = any(pattern in url_lower for pattern in valid_patterns)

        # Si pas de pattern spécifique, vérifier que c'est une URL Facebook publique basique
        if not has_valid_pattern:
            # Accepter les URLs simples comme facebook.com/username ou facebook.com/page.id
            # Mais rejeter les URLs de login/settings
            return True

        return True

    async def extract_post(self, url: str, debug: bool = False) -> Optional[Dict]:
        """
        Extrait les données d'une publication Facebook.
        
        Retourne:
        {
            'fb_post_url': URL,
            'author_name': 'Nom',
            'content': 'Texte du post',
            'image_url': 'URL image ou null',
            'published_at': 'DateTime',
            'extracted_at': 'DateTime',
            'status': 'success'/'error',
            'message': 'Détails'
        }
        """
        if not self.validate_facebook_url(url):
            return {
                'status': 'error',
                'message': 'URL Facebook invalide',
                'url': url
            }

        try:
            logger.info(f"Extraction de: {url}")
            
            # Navigation
            try:
                await self.page.goto(url, wait_until="domcontentloaded", timeout=TIMEOUT_MS)
                logger.info(f"Page title: {await self.page.title()}")
                logger.info(f"URL actuelle: {self.page.url}")
            except Exception as e:
                logger.warning(f"Navigation échouée: {e}")
                return {
                    'status': 'error',
                    'message': 'Publication restreinte ou supprimée',
                    'fb_post_url': url,
                    'link_status': 'inaccessible',
                    'extracted_at': datetime.now().isoformat()
                }

            # Attendre le chargement complet + en-tête auteur
            logger.info("Attente du chargement de la page...")
            try:
                await self.page.wait_for_selector(
                    "div[role='article'], [role='main'] h2, div[role='main']",
                    timeout=12000,
                )
            except Exception:
                pass
            await self.page.wait_for_timeout(4000)

            # Scroll réaliste pour charger le contenu
            logger.info("Scroll pour charger le contenu...")
            await self.page.evaluate("window.scrollBy(0, 3000)")
            await self.page.wait_for_timeout(2000)
            await self.page.evaluate("window.scrollBy(0, -3000)")
            await self.page.wait_for_timeout(1000)

            # Extraire les données
            post_data = await self._extract_post_data(url, debug=debug)
            
            if post_data:
                logger.info(f"Extraction réussie: {post_data.get('author_name', 'Inconnu')}")
                return post_data
            else:
                logger.warning("Données insuffisantes extraites")
                if debug:
                    await self._save_debug_files("debug_failed")
                return {
                    'status': 'error',
                    'message': 'Impossible d\'extraire les données',
                    'fb_post_url': url,
                    'extracted_at': datetime.now().isoformat()
                }

        except Exception as e:
            logger.error(f"Erreur extraction: {e}")
            if debug:
                await self._save_debug_files("debug_error")
            return {
                'status': 'error',
                'message': str(e),
                'fb_post_url': url,
                'extracted_at': datetime.now().isoformat()
            }

    @staticmethod
    def _clean_post_content(content: str) -> str:
        """Retire le bruit UI Facebook (commentaires, réponses) du texte du post."""
        if not content:
            return ""
        text = re.sub(r"\s+", " ", content).strip()
        cut_markers = [
            r"\d+[\s\u00a0]*(commentaires?|partages?|réactions?)\b",
            r"\bVoir \d+ réponse",
            r"\ba répondu\b",
            r"\b\d+[\s\u00a0]*réponses?\b",
            r"\b(Commenter|Partager|J'aime|Like|Reply|Répondre)\b",
        ]
        for marker in cut_markers:
            m = re.search(marker, text, flags=re.I)
            if m and m.start() > 15:
                text = text[: m.start()].strip()
                break
        return text[:5000].strip()

    async def _extract_post_message_only(self) -> str:
        """Extrait uniquement le texte de la publication (sans commentaires)."""
        try:
            raw = await self.page.evaluate(
                """() => {
                    const root = document.querySelector('div[role="article"]')
                        || document.querySelector('[role="main"] article')
                        || document.querySelector('[role="main"]');
                    if (!root) return '';

                    const msg = root.querySelector("div[data-ad-preview='message']");
                    if (msg) {
                        const t = (msg.innerText || msg.textContent || '').trim();
                        if (t.length > 5) return t;
                    }

                    const legacy = root.querySelector('[data-testid="post_message"]');
                    if (legacy) {
                        const t = (legacy.innerText || '').trim();
                        if (t.length > 5) return t;
                    }

                    const noise = /^(j'aime|commenter|partager|commentaires?|partages?|voir plus|se connecter|répondre|like|share|reply|\\d+[\\s\\u00a0]*(commentaires?|partages?|réactions?))$/i;
                    let best = '';
                    root.querySelectorAll("span[dir='auto'], div[dir='auto']").forEach((el) => {
                        if (el.closest('[role="comment"]')) return;
                        if (el.closest('div[role="article"] div[role="article"]')) return;
                        const t = (el.innerText || el.textContent || '').trim();
                        if (!t || t.length < 8 || noise.test(t)) return;
                        if (/voir \\d+ réponse/i.test(t)) return;
                        if (/a répondu/i.test(t)) return;
                        if (t.length > best.length && t.length < 4000) best = t;
                    });
                    return best;
                }"""
            )
            return self._clean_post_content((raw or "").strip())
        except Exception as e:
            logger.debug("Extraction message post échouée: %s", e)
            return ""

    async def _extract_post_data(self, url: str, debug: bool = False) -> Optional[Dict]:
        """
        Extrait les données du post du DOM avec sélecteurs robustes et multiples.
        
        Stratégie:
        1. Cibler uniquement le message de la publication (pas les commentaires)
        2. Fallback ciblé si le sélecteur principal échoue
        3. Valider que le contenu est réel (> 10 caractères)
        """
        try:
            logger.info("Début extraction du contenu...")
            
            # ────────────────────────────────────────────────────────
            # ÉTAPE 1: EXTRAIRE LE TEXTE DU POST (sans commentaires)
            # ────────────────────────────────────────────────────────
            content = await self._extract_post_message_only()
            logger.info("Message publication (sans commentaires): %s caractères", len(content))

            if len(content) < 10:
                for selector in (
                    "div[data-ad-preview='message']",
                    "[data-testid='post_message']",
                ):
                    try:
                        elem = await self.page.query_selector(selector)
                        if not elem:
                            continue
                        text = self._clean_post_content((await elem.text_content() or "").strip())
                        if len(text) > len(content):
                            content = text
                            logger.info("Fallback sélecteur %s: %s chars", selector, len(content))
                            break
                    except Exception:
                        continue
            
            logger.info(f"📝 Contenu final: {len(content)} caractères")
            
            if len(content) < 10:
                logger.warning("Contenu insuffisant (< 10 chars)")
                if debug:
                    all_text = await self.page.locator("body").text_content()
                    logger.info(f"📋 Texte brut visible ({len(all_text)} chars):\n{all_text[:500]}...")
                return None
            
            # ────────────────────────────────────────────────────────
            # ÉTAPE 2: EXTRAIRE L'AUTEUR
            # ────────────────────────────────────────────────────────
            logger.info("Extraction de l'auteur...")
            author, author_url = await self._extract_author()
            if not author:
                logger.warning("Auteur non extrait, tentative de fallback...")
                # Fallback: essayer d'extraire depuis l'URL ou le titre
                try:
                    url_parts = url.split('/')
                    for i, part in enumerate(url_parts):
                        if part in ['posts', 'photos', 'videos'] and i > 0:
                            potential_author = url_parts[i-1]
                            if len(potential_author) > 2 and not potential_author.isdigit():
                                author = potential_author.replace('-', ' ').title()
                                logger.info(f"Auteur extrait depuis URL: {author}")
                                break
                except:
                    pass

            if not author:
                author = None
                author_url = None

            # ────────────────────────────────────────────────────────
            # ÉTAPE 3: EXTRAIRE L'IMAGE
            # ────────────────────────────────────────────────────────
            image_url = None
            image_selectors = [
                "div[role='article'] img[src*='scontent']",
                "article img[src*='scontent']",
                "img[data-testid='post_image']",
                "div[role='main'] img[src*='fbcdn']",
                "article img[alt]",
                "div[role='article'] img",
            ]
            
            logger.info("Extraction de l'image...")
            for selector in image_selectors:
                try:
                    img_elem = await self.page.query_selector(selector)
                    if img_elem:
                        src = await img_elem.get_attribute("src")
                        if src and ("fbcdn" in src or "scontent" in src):
                            image_url = src
                            logger.info(f"Image trouvée: {src[:50]}...")
                            break
                except:
                    pass
            
            # ────────────────────────────────────────────────────────
            # ÉTAPE 4: EXTRAIRE LA DATE
            # ────────────────────────────────────────────────────────
            published_at = datetime.now().isoformat()
            timestamp_selectors = [
                "time",
                "div[role='article'] time",
                "article time",
                "a[role='link'] >> span",
            ]
            
            logger.info("Extraction de la date...")
            for selector in timestamp_selectors:
                try:
                    if ">>" in selector:
                        time_elem = await self.page.locator(selector).first.element_handle()
                    else:
                        time_elem = await self.page.query_selector(selector)
                    
                    if time_elem:
                        timestamp = await time_elem.get_attribute("datetime")
                        if timestamp:
                            published_at = timestamp
                            logger.info(f"Date trouvée: {timestamp}")
                            break
                        time_text = (await time_elem.text_content() or "").strip()
                        bad_date = {"se connecter", "log in", "sign up", "mot de passe", "créer un compte"}
                        if (
                            time_text
                            and len(time_text) < 80
                            and time_text.lower() not in bad_date
                            and "connecter" not in time_text.lower()
                        ):
                            published_at = time_text
                            logger.info(f"Date texte trouvée: {time_text}")
                            break
                except:
                    pass

            # ────────────────────────────────────────────────────────
            # ÉTAPE 5: COMMENTAIRES (texte)
            # ────────────────────────────────────────────────────────
            logger.info("Extraction des commentaires texte...")
            comments = await self._extract_comments(content)

            # ────────────────────────────────────────────────────────
            # ÉTAPE 6: ENGAGEMENT (likes, partages, commentaires, abonnés)
            # ────────────────────────────────────────────────────────
            logger.info("Extraction des métriques d'engagement...")
            engagement = await self._extract_engagement_metrics()
            
            logger.info(f"📋 Résumé extraction:")
            logger.info(f"   • Auteur: {author}")
            logger.info(f"   • Contenu: {len(content)} caractères")
            logger.info(f"   • Image: {'Oui' if image_url else 'Non'}")
            logger.info(f"   • Date: {published_at}")
            logger.info(f"   • J'aime: {engagement['likes_count']}")
            logger.info(f"   • Partages: {engagement['shares_count']}")
            logger.info(f"   • Commentaires (texte): {len(comments)}")
            logger.info(f"   • Commentaires (compteur): {engagement['comments_count']}")
            logger.info(f"   • Abonnés: {engagement['followers_count']}")

            page_title = await self.page.title()
            if self._is_login_or_blocked(content, page_title, author, author_url):
                return {
                    'status': 'error',
                    'message': 'Publication privée ou connexion Facebook requise — URL publique nécessaire',
                    'fb_post_url': url,
                    'link_status': 'inaccessible',
                    'extracted_at': datetime.now().isoformat(),
                }
            
            has_text = bool(content and content.strip())
            has_image = bool(image_url)
            if has_text and has_image:
                content_type = "text_image"
            elif has_image:
                content_type = "image"
            else:
                content_type = "text"

            return {
                'fb_post_url': url,
                'author_name': author,
                'author_url': author_url,
                'content': content,
                'image_url': image_url,
                'content_type': content_type,
                'link_status': 'active',
                'published_at': published_at,
                'likes_count': engagement['likes_count'],
                'shares_count': engagement['shares_count'],
                'comments_count': engagement['comments_count'],
                'followers_count': engagement['followers_count'],
                'comments': comments,
                'extracted_at': datetime.now().isoformat(),
                'status': 'success'
            }

        except Exception as e:
            logger.error(f"Erreur extraction données: {e}")
            if debug:
                await self._save_debug_files("debug_exception")
            return None

    async def _extract_comments(self, post_content: str = "") -> List[Dict]:
        """Extrait le texte des commentaires visibles sous la publication."""
        comments: List[Dict] = []
        seen_keys: set = set()
        post_key = (post_content or "").strip()[:120].lower()

        def _add(item: Dict) -> None:
            content = (item.get("content") or "").strip()
            if len(content) < 3:
                return
            key = content[:100].lower()
            if key in seen_keys:
                return
            if post_key and content.lower()[:120] == post_key:
                return
            seen_keys.add(key)
            comments.append({
                "author_name": self._clean_author_name(item.get("author")),
                "content": content[:2000],
                "position": int(item.get("position", len(comments))),
            })

        try:
            await self.page.evaluate("window.scrollTo(0, document.body.scrollHeight * 0.55)")
            await asyncio.sleep(0.8)

            # Stratégie 1 : blocs role=comment
            raw_role = await self.page.evaluate(
                """() => {
                    const out = [];
                    document.querySelectorAll('div[role="comment"]').forEach((block, idx) => {
                        const spans = block.querySelectorAll('span[dir="auto"], div[dir="auto"]');
                        let text = '';
                        spans.forEach(s => {
                            const t = (s.innerText || '').trim();
                            if (t.length > text.length) text = t;
                        });
                        if (!text || text.length < 3) return;
                        let author = '';
                        block.querySelectorAll('a[role="link"]').forEach(a => {
                            const t = (a.innerText || '').trim();
                            if (!t || t.length > 80) return;
                            if (/commentaire|répondre|reply|j'aime|like|partager|share/i.test(t)) return;
                            author = t;
                        });
                        out.push({ author, content: text, position: idx });
                    });
                    return out;
                }"""
            )
            for item in raw_role or []:
                _add(item)

            # Stratégie 2 : articles imbriqués (Facebook public share pages)
            if len(comments) < 3:
                raw_articles = await self.page.evaluate(
                    """() => {
                        const noise = /^(j'aime|commenter|partager|commentaires?|partages?|voir plus|se connecter|répondre|like|share|reply|informations de compte)$/i;
                        const out = [];
                        const arts = Array.from(document.querySelectorAll('div[role="article"]'));
                        arts.forEach((art, idx) => {
                            if (idx === 0) return;
                            const texts = Array.from(art.querySelectorAll('span[dir="auto"], div[dir="auto"]'))
                                .map(e => (e.innerText || '').trim())
                                .filter(t => t.length > 2 && !noise.test(t) && !/^\\d+[\\s\\u00a0]*(commentaires?|partages?)$/i.test(t));
                            if (!texts.length) return;
                            const author = texts[0].length <= 80 ? texts[0] : '';
                            let content = '';
                            texts.forEach(t => {
                                if (t === author) return;
                                if (t.length > content.length) content = t;
                            });
                            if (content.length < 3) return;
                            out.push({ author, content, position: idx });
                        });
                        return out.slice(0, 30);
                    }"""
                )
                for item in raw_articles or []:
                    _add(item)

            logger.info("Commentaires texte extraits: %d", len(comments))
        except Exception as e:
            logger.debug("Commentaires texte non extraits: %s", e)

        return comments

    async def _extract_engagement_metrics(self) -> Dict[str, int]:
        """Extrait likes, commentaires, partages et abonnés depuis le DOM Facebook."""
        metrics = {"likes_count": 0, "shares_count": 0, "comments_count": 0, "followers_count": 0}

        like_keys = ("réaction", "reaction", "j'aime", "jaime", "like", "personnes ont réagi", "people reacted")
        comment_keys = ("commentaire", "comment", "comments")
        share_keys = ("partage", "share", "shares", "publication partagée")
        follower_keys = ("abonné", "abonne", "follower", "followers", "personnes suivent", "people follow")

        try:
            scope = self.page.locator("div[role='article'], article").first
            if await scope.count() == 0:
                scope = self.page.locator("div[role='main']").first

            labels = await scope.locator("[aria-label]").all()
            for el in labels[:80]:
                try:
                    label = (await el.get_attribute("aria-label")) or ""
                    label_l = label.lower()
                    count = parse_metric_number(label)
                    if count is None:
                        continue
                    if any(k in label_l for k in like_keys):
                        metrics["likes_count"] = max(metrics["likes_count"], count)
                    elif any(k in label_l for k in comment_keys):
                        metrics["comments_count"] = max(metrics["comments_count"], count)
                    elif any(k in label_l for k in share_keys):
                        metrics["shares_count"] = max(metrics["shares_count"], count)
                    elif any(k in label_l for k in follower_keys):
                        metrics["followers_count"] = max(metrics["followers_count"], count)
                except Exception:
                    continue

            blob = ""
            try:
                blob = (await scope.inner_text()) or ""
            except Exception:
                blob = (await self.page.locator("body").inner_text()) or ""
            blob_l = blob.lower()

            patterns = [
                (r"(\d[\d\s.,]*[km]?)\s*(?:réactions?|reactions?|j['']aime|likes?)", "likes_count"),
                (r"(\d[\d\s.,]*[km]?)\s*(?:commentaires?|comments?)", "comments_count"),
                (r"(\d[\d\s.,]*[km]?)\s*(?:partages?|shares?)", "shares_count"),
                (r"(\d[\d\s.,]*[km]?)\s*(?:abonnés?|abonnes?|followers?)", "followers_count"),
            ]
            for pattern, key in patterns:
                for m in re.finditer(pattern, blob_l, re.IGNORECASE):
                    count = parse_metric_number(m.group(1))
                    if count is not None:
                        metrics[key] = max(metrics[key], count)

            logger.info(
                "Engagement: likes=%s, commentaires=%s, partages=%s, abonnés=%s",
                metrics["likes_count"],
                metrics["comments_count"],
                metrics["shares_count"],
                metrics["followers_count"],
            )
        except Exception as e:
            logger.debug("Engagement non extrait: %s", e)

        return metrics

    async def _save_debug_files(self, prefix: str = "debug") -> None:
        """Sauvegarde les fichiers de debug (HTML et screenshot)."""
        try:
            debug_dir = SCRIPT_DIR / "debug_output"
            debug_dir.mkdir(exist_ok=True)
            
            # Sauvegarder le HTML
            html_file = debug_dir / f"{prefix}.html"
            html_content = await self.page.content()
            with open(html_file, "w", encoding="utf-8") as f:
                f.write(html_content)
            logger.info(f"💾 HTML sauvegardé: {html_file}")
            
            # Sauvegarder le screenshot
            png_file = debug_dir / f"{prefix}.png"
            await self.page.screenshot(path=png_file)
            logger.info(f"📸 Screenshot sauvegardé: {png_file}")
            
            # Sauvegarder le texte brut visible
            txt_file = debug_dir / f"{prefix}_visible.txt"
            visible_text = await self.page.locator("body").text_content()
            with open(txt_file, "w", encoding="utf-8") as f:
                f.write(visible_text)
            logger.info(f"📝 Texte brut sauvegardé: {txt_file}")
            
        except Exception as e:
            logger.warning(f"⚠️ Erreur sauvegarde debug: {e}")

    # ────────────────────────────────────────────────────────────────
    # DATABASE
    # ────────────────────────────────────────────────────────────────

    def init_database(self) -> bool:
        """
        Vérifie la connexion DB et l'existence des tables cibles.

        IMPORTANT: la plateforme utilise la base canonical définie dans database/schema.sql :
        - facebook_accounts
        - facebook_posts
        """
        try:
            conn = mysql.connector.connect(**DB_CONFIG)
            cursor = conn.cursor(buffered=True)  # Use buffered cursor to avoid unread result errors
            cursor.execute("SELECT 1")
            cursor.fetchone()  # Consume result
            # quick sanity check: required tables exist
            cursor.execute("SHOW TABLES LIKE 'facebook_posts'")
            if cursor.fetchone() is None:
                raise MySQLError("Table manquante: facebook_posts (importer database/schema.sql)")
            cursor.execute("SHOW TABLES LIKE 'facebook_accounts'")
            if cursor.fetchone() is None:
                raise MySQLError("Table manquante: facebook_accounts (importer database/schema.sql)")
            logger.info("✓ Schéma DB OSINT détecté (facebook_posts, facebook_accounts)")

            # Colonne author_name sur facebook_posts (migration légère)
            try:
                cursor.execute(
                    "ALTER TABLE facebook_posts ADD COLUMN author_name VARCHAR(255) NULL AFTER account_id"
                )
                conn.commit()
                logger.info("✓ Colonne author_name ajoutée à facebook_posts")
            except MySQLError as e:
                if "1060" not in str(e) and "Duplicate column" not in str(e):
                    logger.debug(f"author_name: {e}")
            try:
                cursor.execute(
                    "CREATE INDEX idx_author_name ON facebook_posts (author_name)"
                )
                conn.commit()
            except MySQLError:
                pass

            try:
                cursor.execute("""
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
                conn.commit()
            except MySQLError:
                pass
            
            cursor.close()
            conn.close()
            return True

        except MySQLError as e:
            logger.error(f"✗ Erreur MySQL: {e}")
            return False

    def _parse_datetime_for_mysql(self, iso: Optional[str]) -> Optional[str]:
        """Convertit une date ISO-ish en DATETIME MySQL."""
        if not iso:
            return None
        try:
            s = iso.strip()
            # Handle Zulu
            if s.endswith("Z"):
                s = s[:-1] + "+00:00"
            dt = datetime.fromisoformat(s)
            return dt.strftime("%Y-%m-%d %H:%M:%S")
        except Exception:
            return None

    def _stable_id_from_url(self, url: str) -> str:
        """Génère un identifiant stable (hash) à partir de l'URL."""
        import hashlib
        return hashlib.sha1(url.encode("utf-8")).hexdigest()[:32]

    @staticmethod
    def _account_type_from_url(author_url: Optional[str]) -> str:
        u = (author_url or "").lower()
        if "/groups/" in u:
            return "group"
        if "/pages/" in u or "/page/" in u:
            return "page"
        return "profile" if u else "page"

    def _ensure_account(self, cursor, author_name: str, author_url: Optional[str], followers_count: int = 0) -> int:
        """
        Crée/retourne un compte Facebook (facebook_accounts).
        fb_id est un identifiant stable dérivé de l'URL auteur (si dispo) ou du nom.
        """
        import hashlib
        key = (author_url or author_name or "").strip()
        fb_id = hashlib.sha1(key.encode("utf-8")).hexdigest()[:20] if key else hashlib.sha1(b"unknown").hexdigest()[:20]

        clean_name = self._clean_author_name(author_name) or "Auteur non identifié"

        cursor.execute("SELECT id, name FROM facebook_accounts WHERE fb_id = %s LIMIT 1", (fb_id,))
        row = cursor.fetchone()
        fb_url = (author_url or "").strip()[:500] or None

        acc_type = self._account_type_from_url(author_url)

        if row:
            acc_id = int(row[0])
            old_name = (row[1] or "").strip().lower()
            if clean_name and clean_name.lower() not in {"auteur non identifié", "auteur inconnu", "inconnu"}:
                if old_name in {"", "auteur inconnu", "auteur non identifié", "inconnu"} or old_name != clean_name.lower():
                    cursor.execute(
                        "UPDATE facebook_accounts SET name = %s, fb_url = COALESCE(%s, fb_url), type = %s WHERE id = %s",
                        (clean_name, fb_url, acc_type, acc_id),
                    )
            if followers_count > 0:
                cursor.execute(
                    "UPDATE facebook_accounts SET followers_count = %s WHERE id = %s",
                    (followers_count, acc_id),
                )
            return acc_id

        cursor.execute(
            """
            INSERT INTO facebook_accounts (fb_id, name, type, category, followers_count, profile_picture, fb_url, is_monitored, risk_level, added_by)
            VALUES (%s, %s, %s, NULL, %s, NULL, %s, 1, 'low', NULL)
            """,
            (fb_id, clean_name, acc_type, max(0, followers_count), fb_url),
        )
        return int(cursor.lastrowid)

    def _save_comments(self, cursor, post_pk: int, comments: List[Dict]) -> int:
        """Remplace les commentaires extraits pour une publication."""
        if not post_pk:
            return 0
        cursor.execute("DELETE FROM post_comments WHERE post_id = %s", (post_pk,))
        saved = 0
        for c in comments or []:
            content = (c.get("content") or "").strip()
            if len(content) < 3:
                continue
            cursor.execute(
                """
                INSERT INTO post_comments (post_id, author_name, content, sort_order, is_analyzed)
                VALUES (%s, %s, %s, %s, 0)
                """,
                (
                    post_pk,
                    c.get("author_name"),
                    content[:2000],
                    int(c.get("position", saved)),
                ),
            )
            saved += 1
        return saved

    def save_post(self, post_data: Dict) -> bool:
        """Sauvegarde le post dans facebook_posts (schéma canonical)."""
        if post_data.get('status') == 'error':
            return False

        content = post_data.get("content", "") or ""
        if self._is_login_or_blocked(
            content,
            "",
            post_data.get("author_name", "") or "",
            post_data.get("author_url"),
        ):
            logger.warning("Sauvegarde refusée — contenu login/privé détecté")
            return False

        try:
            conn = mysql.connector.connect(**DB_CONFIG)
            cursor = conn.cursor()
            raw_author = post_data.get("author_name")
            clean_author = self._clean_author_name(raw_author)

            # Use account name if author extraction failed
            if not clean_author:
                logger.warning("Author name not extracted, will use account name")
                clean_author = raw_author  # Fallback to raw author name

            followers_count = int(post_data.get("followers_count") or 0)
            likes_count = int(post_data.get("likes_count") or 0)
            shares_count = int(post_data.get("shares_count") or 0)
            comments_count = int(post_data.get("comments_count") or 0)

            account_id = self._ensure_account(
                cursor,
                clean_author or "Auteur non identifié",
                post_data.get("author_url"),
                followers_count,
            )

            fb_post_url = post_data["fb_post_url"].strip()
            fb_post_id = self._stable_id_from_url(fb_post_url)
            published_at = self._parse_datetime_for_mysql(post_data.get("published_at"))
            author_db = clean_author

            cursor.execute(
                """
                INSERT INTO facebook_posts
                  (fb_post_id, account_id, author_name, content, image_url, content_type, fb_post_url, link_status, likes_count, shares_count, comments_count, published_at, is_analyzed)
                VALUES
                  (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 0)
                ON DUPLICATE KEY UPDATE
                  account_id = VALUES(account_id),
                  author_name = COALESCE(VALUES(author_name), author_name),
                  content = VALUES(content),
                  image_url = VALUES(image_url),
                  content_type = VALUES(content_type),
                  link_status = VALUES(link_status),
                  likes_count = VALUES(likes_count),
                  shares_count = VALUES(shares_count),
                  comments_count = VALUES(comments_count),
                  published_at = COALESCE(VALUES(published_at), published_at),
                  fetched_at = CURRENT_TIMESTAMP
                """,
                (
                    fb_post_id,
                    account_id,
                    author_db,
                    post_data.get("content", "")[:5000],
                    post_data.get("image_url"),
                    post_data.get("content_type", "text"),
                    fb_post_url,
                    post_data.get("link_status", "active"),
                    likes_count,
                    shares_count,
                    comments_count,
                    published_at,
                ),
            )

            conn.commit()

            cursor.execute("SELECT id FROM facebook_posts WHERE fb_post_id = %s LIMIT 1", (fb_post_id,))
            row = cursor.fetchone()
            post_pk = int(row[0]) if row else None

            comments_saved = 0
            if post_pk:
                comments_saved = self._save_comments(
                    cursor, post_pk, post_data.get("comments") or []
                )
            conn.commit()

            cursor.close()
            conn.close()

            logger.info(
                "✅ Post sauvegardé (ID: %s, author: %s, commentaires: %s)",
                post_pk, author_db, comments_saved,
            )
            return True

        except MySQLError as e:
            logger.warning(f"⚠️ Erreur sauvegarde: {e}")
            return False

    @staticmethod
    def page_url_from_id(page_id: str) -> str:
        """Construit l'URL Facebook d'une Page ou d'un profil public."""
        raw = (page_id or "").strip().strip("/")
        if not raw:
            raise ValueError("page_id vide")
        if raw.startswith("http"):
            return raw
        if raw.isdigit():
            return f"https://www.facebook.com/profile.php?id={raw}"
        return f"https://www.facebook.com/{raw}"

    async def _collect_post_urls_on_page(self, limit: int = 10) -> List[str]:
        """Collecte les liens de publications visibles sur une Page/profil."""
        limit = max(1, min(limit, 25))
        seen: List[str] = []

        async def harvest() -> None:
            hrefs = await self.page.evaluate(
                """() => {
                    const out = [];
                    const seen = new Set();
                    const ok = (h) => {
                        if (!h || !h.includes('facebook.com')) return false;
                        const x = h.toLowerCase();
                        if (x.includes('/login') || x.includes('/messages')) return false;
                        return x.includes('/posts/') || x.includes('/share/p/')
                            || x.includes('pfbid') || x.includes('/photos/')
                            || x.includes('/videos/') || x.includes('/permalink.php');
                    };
                    document.querySelectorAll('a[href]').forEach((a) => {
                        let h = a.href || '';
                        h = h.split('?')[0].split('#')[0];
                        if (!ok(h) || seen.has(h)) return;
                        seen.add(h);
                        out.push(h);
                    });
                    return out;
                }"""
            )
            for href in hrefs or []:
                if href not in seen:
                    seen.append(href)

        for _ in range(4):
            await harvest()
            if len(seen) >= limit:
                break
            await self.page.evaluate("window.scrollBy(0, 2200)")
            await self.page.wait_for_timeout(1800)

        return seen[:limit]

    async def extract_page_posts(
        self, page_id: str, limit: int = 10, save_to_db: bool = True, debug: bool = False
    ) -> Dict:
        """Importe plusieurs publications d'une Page/profil public (fallback OSINT)."""
        limit = max(1, min(limit, 25))
        page_url = self.page_url_from_id(page_id)
        result: Dict = {
            "status": "error",
            "source": "osint_scraper",
            "page_id": page_id,
            "page_url": page_url,
            "imported_count": 0,
            "posts": [],
            "errors": [],
        }

        if not await self.init_browser():
            result["message"] = "Navigateur indisponible — playwright install chromium"
            return result

        try:
            logger.info("Import Page OSINT: %s", page_url)
            await self.page.goto(page_url, wait_until="domcontentloaded", timeout=TIMEOUT_MS)
            await self.page.wait_for_timeout(3500)
            try:
                await self.page.wait_for_selector(
                    "div[role='article'], [role='main']", timeout=12000
                )
            except Exception:
                pass

            post_urls = await self._collect_post_urls_on_page(limit)
            logger.info("Liens publications trouvés: %s", len(post_urls))

            if not post_urls:
                result["message"] = (
                    "Aucune publication trouvée sur cette Page. "
                    "Vérifiez l'ID/username ou essayez une URL de publication directe."
                )
                return result

            if save_to_db:
                self.init_database()

            imported = []
            for i, post_url in enumerate(post_urls, 1):
                logger.info("Post %s/%s: %s", i, len(post_urls), post_url)
                try:
                    await self.page.goto(post_url, wait_until="domcontentloaded", timeout=TIMEOUT_MS)
                    await self.page.wait_for_timeout(2500)
                    post_data = await self._extract_post_data(post_url, debug=debug)
                    if not post_data:
                        result["errors"].append({"url": post_url, "error": "données insuffisantes"})
                        continue
                    post_data["status"] = "success"
                    post_data["fb_post_url"] = post_data.get("fb_post_url") or post_url
                    post_data["extracted_at"] = datetime.now().isoformat()
                    if save_to_db:
                        self.save_post(post_data)
                    imported.append(post_data)
                except Exception as exc:
                    logger.warning("Échec post %s: %s", post_url, exc)
                    result["errors"].append({"url": post_url, "error": str(exc)})

            result["imported_count"] = len(imported)
            result["posts"] = imported
            if imported:
                result["status"] = "success"
                result["message"] = f"{len(imported)} publication(s) importée(s) via Scraper OSINT"
            else:
                result["message"] = "Publications détectées mais extraction échouée pour toutes"
            return result
        except Exception as exc:
            logger.error("Erreur import Page: %s", exc)
            result["message"] = str(exc)
            return result
        finally:
            await self.close_browser()

    # ────────────────────────────────────────────────────────────────
    # ORCHESTRATION
    # ────────────────────────────────────────────────────────────────

    async def process_url(self, url: str, save_to_db: bool = True, debug: bool = False) -> Dict:
        """Traite une URL complètement."""
        if not await self.init_browser():
            return {
                'status': 'error',
                'message': 'Navigateur indisponible — exécutez: playwright install chromium',
                'fb_post_url': url,
                'link_status': 'inaccessible',
                'extracted_at': datetime.now().isoformat(),
            }

        try:
            # Extraire
            post_data = await self.extract_post(url, debug=debug)
            
            # Sauvegarder si succès
            if post_data and post_data.get('status') == 'success' and save_to_db:
                self.init_database()
                self.save_post(post_data)
            
            return post_data or {
                'status': 'error',
                'message': 'Extraction échouée',
                'fb_post_url': url,
                'extracted_at': datetime.now().isoformat()
            }
        finally:
            await self.close_browser()


# ────────────────────────────────────────────────────────────────────
# CLI
# ────────────────────────────────────────────────────────────────────

async def main():
    parser = argparse.ArgumentParser(
        description="🔵 Facebook Post Extractor — OSINT Intelligence Tool"
    )
    
    parser.add_argument("--url", type=str, help="URL de publication Facebook")
    parser.add_argument("--page", type=str, help="ID ou username de Page/profil public (import multiple)")
    parser.add_argument("--limit", type=int, default=10, help="Nombre max de posts (--page)")
    parser.add_argument("--json", action="store_true", help="Sortie JSON")
    parser.add_argument("--save-db", action="store_true", default=True, help="Sauvegarder en BD")
    parser.add_argument("--test", action="store_true", help="Tester la connexion BD")
    parser.add_argument("--debug", action="store_true", help="Mode debug: save HTML/screenshot on failure")
    
    args = parser.parse_args()

    if args.test:
        extractor = FacebookPostExtractor()
        if extractor.init_database():
            logger.info("✓ Connexion BD OK")
            sys.exit(0)
        else:
            logger.error("✗ Erreur BD")
            sys.exit(1)

    if not args.url and not args.page:
        parser.print_help()
        sys.exit(1)

    extractor = FacebookPostExtractor()
    if args.page:
        result = await extractor.extract_page_posts(
            args.page, limit=args.limit, save_to_db=args.save_db, debug=args.debug
        )
    else:
        result = await extractor.process_url(args.url, save_to_db=args.save_db, debug=args.debug)

    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        logger.info(json.dumps(result, ensure_ascii=False, indent=2))

    sys.exit(0 if result.get('status') == 'success' else 1)


if __name__ == "__main__":
    """
    Entry point avec gestion complète de l'event loop asyncio.
    Fixes pour Windows Python 3.10 et problèmes Playwright.
    """
    if sys.platform == "win32":
        asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())
    
    loop = None
    try:
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        loop.run_until_complete(main())
    except KeyboardInterrupt:
        logger.info("\n✗ Arrêt par l'utilisateur")
        sys.exit(130)
    except Exception as e:
        logger.error(f"✗ Erreur: {e}", exc_info=True)
        sys.exit(1)
    finally:
        if loop:
            try:
                # Cancel pending tasks
                pending = asyncio.all_tasks(loop)
                for task in pending:
                    task.cancel()
                
                # Run loop one more time to process cancellations
                if pending:
                    loop.run_until_complete(asyncio.gather(*pending, return_exceptions=True))
                
                # Close event loop properly
                loop.close()
            except Exception as cleanup_err:
                logger.debug(f"Cleanup warning: {cleanup_err}")
