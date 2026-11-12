"""Ищет организации в Яндекс Картах по запросам и проверяет, указан ли у них сайт.

Для каждого запроса открывает выдачу, собирает ссылки на карточки организаций,
открывает каждую карточку и вытаскивает название, адрес, телефон, рейтинг и сайт.
Результат шифруется открытым ключом scanner/pub.pem, потому что репозиторий публичный.
"""
import base64, json, os, re, sys, time
from playwright.sync_api import sync_playwright
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.fernet import Fernet

SOCIAL = re.compile(r"(vk\.com|t\.me|instagram|wa\.me|whatsapp|ok\.ru|youtube|taplink|clients\.site|avito|2gis|yandex|zoon|drive2)", re.I)

def card(page, url):
    r = {"url": url}
    page.goto(url, timeout=45000, wait_until="domcontentloaded")
    page.wait_for_timeout(3500)
    text = page.inner_text("body", timeout=10000)
    if re.search(r"(не робот|captcha|SmartCaptcha)", text[:2000], re.I):
        r["captcha"] = True
        return r
    r["name"] = (page.title() or "").split("—")[0].split(":")[0].strip()
    m = re.search(r"\+7[\s(\-]*\d{3}[\s)\-]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}", text); r["phone"] = m.group(0) if m else ""
    m = re.search(r"(\d,\d)\s*\n?\s*(\d+)\s*оцен", text); r["rating"] = (m.group(1) + " / " + m.group(2)) if m else ""
    hrefs = page.eval_on_selector_all("a[href]", "els => els.map(e => [e.innerText.trim(), e.href])")
    sites, socials = [], []
    for t, h in hrefs:
        if not h.startswith("http"): continue
        host = re.sub(r"^https?://(www\.)?", "", h).split("/")[0].lower()
        if "yandex" in host or "ya.ru" in host or "yastatic" in host: continue
        (socials if SOCIAL.search(host) else sites).append(host)
    r["sites"] = sorted(set(sites)); r["socials"] = sorted(set(socials))
    a = re.search(r"\n([^\n]*(?:ул\.|улица|шоссе|проспект|пр-т|переулок|проезд|км|МКАД)[^\n]*)\n", text); r["address"] = a.group(1).strip()[:150] if a else ""
    r["has_site"] = bool(r["sites"])
    return r

def main(job_path, out_path):
    job = json.load(open(job_path)); out = []
    with sync_playwright() as p:
        br = p.chromium.launch()
        ctx = br.new_context(locale="ru-RU", timezone_id="Europe/Moscow", viewport={"width": 1366, "height": 900},
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36")
        page = ctx.new_page()
        for q in job["queries"]:
            try:
                page.goto("https://yandex.ru/maps/213/moscow/search/" + q + "/", timeout=45000, wait_until="domcontentloaded")
                page.wait_for_timeout(5000)
                for _ in range(job.get("scrolls", 4)):
                    page.mouse.move(300, 500); page.mouse.wheel(0, 3000); page.wait_for_timeout(1500)
                links = page.eval_on_selector_all('a[href*="/maps/org/"]', "els => [...new Set(els.map(e => { const m = e.href.match(/(https:[^?#]*\\/maps\\/org\\/[^/]+\\/\\d+\\/)/); return m ? m[1] : null; }).filter(Boolean))]")
            except Exception as e:
                print(q, "ERR", str(e)[:120], flush=True); links = []
            print(q, len(links), flush=True)
            for u in links[: job.get("per_query", 15)]:
                try:
                    r = card(page, u); r["query"] = q; out.append(r)
                    print(" ", r.get("name", "?"), "| сайт:", ",".join(r.get("sites", [])) or "НЕТ", "| captcha" if r.get("captcha") else "", flush=True)
                    if r.get("captcha"): break
                except Exception as e:
                    out.append({"url": u, "error": str(e)[:200], "query": q})
                time.sleep(job.get("pause", 4))
        br.close()
    blob = json.dumps(out, ensure_ascii=False).encode(); key = Fernet.generate_key()
    pem = job.get("pubkey", "").encode() or open(os.path.join(os.path.dirname(__file__), "pub.pem"), "rb").read()
    pub = serialization.load_pem_public_key(pem)
    ek = pub.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
    json.dump({"key": base64.b64encode(ek).decode(), "data": Fernet(key).encrypt(blob).decode()}, open(out_path, "w"))

if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
