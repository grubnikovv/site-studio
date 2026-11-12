"""Проверка: есть ли у компании собственный сайт.

Для каждой компании из jobs/<job>.json открывает российские каталоги и поиск,
собирает все домены, которые там встречаются, отбрасывает каталоги и соцсети
и проверяет оставшиеся домены: упоминается ли на них компания.
Результат шифруется открытым ключом scanner/pub.pem, потому что репозиторий публичный.
"""
import base64, json, os, re, sys, time, urllib.parse
from playwright.sync_api import sync_playwright
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.fernet import Fernet
import requests

NOT_OWN = re.compile(r"(^|\.)(" + "|".join(map(re.escape, [
    "2gis.ru", "2gis.com", "2gis.kz", "yandex.ru", "yandex.com", "ya.ru", "yandex.net", "yastatic.net", "yandex-team.ru",
    "clients.site", "turbo.site", "zoon.ru", "yell.ru", "avito.ru", "avito.st", "vk.com", "vk.ru", "vkontakte.ru", "userapi.com",
    "ok.ru", "mail.ru", "t.me", "telegram.org", "telegram.me", "whatsapp.com", "wa.me", "instagram.com", "facebook.com",
    "youtube.com", "youtu.be", "rutube.ru", "dzen.ru", "drive2.ru", "drom.ru", "auto.ru", "taplink.cc", "taplink.ws", "taplink.ru",
    "flamp.ru", "profi.ru", "uslugi.yandex.ru", "orgpage.ru", "spr.ru", "list-org.com", "rusprofile.ru", "google.com", "google.ru",
    "gstatic.com", "googleapis.com", "bing.com", "microsoft.com", "duckduckgo.com", "apple.com", "tiktok.com", "pinterest.com",
    "cataloxy.ru", "moskva.bizly.ru", "bizly.ru", "msk.yp.ru", "yp.ru", "zoon.com", "otzovik.com", "irecommend.ru", "tripadvisor.ru",
    "hh.ru", "superjob.ru", "avtocod.ru", "carsclub.ru", "mapcard.pro", "spravker.ru", "moscow.cataloxy.ru", "kartaslov.ru",
    "pulscen.ru", "tiu.ru", "satu.kz", "wildberries.ru", "ozon.ru", "dixy.ru", "cloudflare.com", "jsdelivr.net", "w3.org",
    "schema.org", "openstreetmap.org", "sberbank.ru", "tinkoff.ru", "tbank.ru", "mos.ru", "gosuslugi.ru", "nalog.ru", "nalog.gov.ru",
    "checko.ru", "zachestnyibiznes.ru", "audit-it.ru", "vbankcenter.ru", "fsin.su", "sbis.ru", "kontur.ru", "egrul.ru",
    "pochta.ru", "detailing.su", "autotuning.ru", "tvoi-detailing.ru", "detailingclub.ru", "spravochnik.ru", "firmsonmap.ru",
    "infobel.com", "cylex.ru", "yelp.ru", "moskvamap.ru", "allbiz.ru", "uslugio.com", "youdo.com", "remontnik.ru",
    "tilda.ws", "tilda.cc", "wixsite.com", "ucoz.ru", "narod.ru", "github.com", "gitflic.ru",
])) + r")$")

DOMAIN_RE = re.compile(r"\b((?:[a-z0-9-]+\.)+(?:ru|рф|xn--p1ai|com|net|org|su|moscow|msk\.ru|pro|online|site|store|club|auto|car|cars|studio|shop|biz|info|me|io))\b", re.I)
CAPTCHA_RE = re.compile(r"(captcha|капча|я не робот|подтвердите, что запросы|showcaptcha|smartcaptcha|are you a robot|доступ ограничен|access denied)", re.I)


def own_domains(text, hrefs):
    found = set()
    for h in hrefs:
        try:
            u = urllib.parse.urlparse(h)
            host = (u.hostname or "").lower()
            # 2ГИС и Яндекс оборачивают внешние ссылки в редирект с адресом внутри
            for v in urllib.parse.parse_qs(u.query).values():
                for x in v:
                    if x.startswith("http"):
                        found.add((urllib.parse.urlparse(x).hostname or "").lower())
            if host:
                found.add(host)
        except Exception:
            pass
    for m in DOMAIN_RE.findall(text or ""):
        found.add(m.lower())
    out = set()
    for d in found:
        d = d.strip(".").removeprefix("www.")
        if not d or "." not in d or NOT_OWN.search(d):
            continue
        out.add(d)
    return sorted(out)


def grab(page, url, wait=4000, click=None):
    r = {"url": url}
    try:
        page.goto(url, timeout=45000, wait_until="domcontentloaded")
        page.wait_for_timeout(wait)
        if click:
            loc = page.locator(click).first
            if loc.count():
                href = loc.get_attribute("href")
                r["clicked"] = href
                loc.click(timeout=10000)
                page.wait_for_timeout(wait)
        r["final"] = page.url
        r["title"] = page.title()
        text = page.inner_text("body", timeout=10000)
        hrefs = page.eval_on_selector_all("a[href]", "els => els.map(e => e.href)")
        r["captcha"] = bool(CAPTCHA_RE.search(text[:3000] + " " + r["title"]))
        r["domains"] = own_domains(text, hrefs)
        r["len"] = len(text)
        r["excerpt"] = text[:1500]
        r["ok"] = not r["captcha"] and len(text) > 200
    except Exception as e:
        r["ok"] = False
        r["error"] = str(e)[:300]
    return r


def sources(q):
    qq = urllib.parse.quote(q)
    return {
        "2gis": ("https://2gis.ru/moscow/search/" + qq, 'a[href*="/firm/"]'),
        "yandex_maps": ("https://yandex.ru/maps/213/moscow/search/" + qq + "/", 'a[href*="/maps/org/"]'),
        "zoon": ("https://zoon.ru/search/?query=" + qq + "&city=msk", 'a[href*="zoon.ru/msk/"][href$="/"]'),
        "yell": ("https://www.yell.ru/moscow/top/?text=" + qq, 'a[href*="/moscow/com/"]'),
        "ddg": ("https://html.duckduckgo.com/html/?q=" + urllib.parse.quote(q + " Москва"), None),
        "bing": ("https://www.bing.com/search?setlang=ru&cc=RU&q=" + urllib.parse.quote(q + " Москва"), None),
        "yandex_search": ("https://yandex.ru/search/?text=" + urllib.parse.quote(q + " Москва"), None),
    }


def confirm(domain, lead):
    """Открывает домен и ищет на нём название или телефон компании."""
    digits = re.sub(r"\D", "", lead.get("phone", ""))[-10:]
    words = [w.lower() for w in re.findall(r"[\wА-Яа-яЁё]{4,}", lead["name"]) if w.lower() not in {"детейлинг", "detailing", "studio", "студия", "москва", "тонировка", "оклейка", "автомобилей", "автосервис", "шиномонтаж"}]
    for scheme in ("https://", "http://"):
        try:
            resp = requests.get(scheme + domain, timeout=15, headers={"User-Agent": "Mozilla/5.0"})
            html = resp.text.lower()
            hit_phone = bool(digits) and digits in re.sub(r"\D", "", html)
            hit_name = any(w in html for w in words)
            return {"status": resp.status_code, "phone": hit_phone, "name": hit_name, "title": (re.search(r"<title[^>]*>(.*?)</title>", html, re.S) or [None, ""])[1][:120]}
        except Exception as e:
            err = str(e)[:120]
    return {"error": err}


def main(job_path, out_path, debug):
    job = json.load(open(job_path))
    leads = job["leads"]
    results = []
    with sync_playwright() as p:
        br = p.chromium.launch()
        ctx = br.new_context(locale="ru-RU", timezone_id="Europe/Moscow", viewport={"width": 1366, "height": 900},
                             user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36")
        page = ctx.new_page()
        for lead in leads:
            q = lead["name"] + (" " + lead["address"].replace("Москва,", "").strip() if lead.get("address") else "")
            q = q[:120]
            per = {}
            for name, (url, click) in sources(q).items():
                per[name] = grab(page, url, click=click)
                time.sleep(2)
            doms = sorted({d for r in per.values() for d in r.get("domains", [])})
            checks = {d: confirm(d, lead) for d in doms[:15]}
            confirmed = [d for d, c in checks.items() if c.get("phone") or c.get("name")]
            ok_sources = [n for n, r in per.items() if r.get("ok")]
            verdict = "has_site" if confirmed else ("no_site" if len(ok_sources) >= 3 else "unclear")
            if not debug:
                for r in per.values():
                    r.pop("excerpt", None)
            results.append({"id": lead["id"], "name": lead["name"], "verdict": verdict, "confirmed": confirmed,
                            "ok_sources": ok_sources, "checks": checks, "sources": per})
            print(lead["id"], verdict, "ok:", ",".join(ok_sources), "| сайт:", ",".join(confirmed) or "-", flush=True)
        br.close()
    blob = json.dumps({"job": job.get("name"), "results": results}, ensure_ascii=False).encode()
    key = Fernet.generate_key()
    pub = serialization.load_pem_public_key(open(os.path.join(os.path.dirname(__file__), "pub.pem"), "rb").read())
    ek = pub.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
    json.dump({"key": base64.b64encode(ek).decode(), "data": Fernet(key).encrypt(blob).decode()}, open(out_path, "w"))


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2], os.environ.get("DEBUG") == "1")
