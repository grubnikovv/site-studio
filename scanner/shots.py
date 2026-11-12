"""Снимает скриншоты страниц (ПК и телефон) и выгружает текст и цвета. Результат шифруется."""
import base64, json, os, sys
from playwright.sync_api import sync_playwright
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.fernet import Fernet

def main(job_path, out_path):
    job = json.load(open(job_path)); out = []
    with sync_playwright() as p:
        br = p.chromium.launch()
        for url in job["shots"]:
            for w, h in [(1440, 900), (390, 844)]:
                pg = br.new_page(viewport={"width": w, "height": h}, locale="ru-RU")
                r = {"url": url, "w": w}
                try:
                    pg.goto(url, timeout=60000, wait_until="networkidle"); pg.wait_for_timeout(2500)
                    r["png"] = base64.b64encode(pg.screenshot(full_page=True, type="jpeg", quality=60)).decode()
                    if w == 1440:
                        r["text"] = pg.inner_text("body")[:30000]
                        r["style"] = pg.evaluate("""() => { const s = getComputedStyle(document.body); const h = document.querySelector('h1,h2');
                          const fonts = [...new Set([...document.querySelectorAll('h1,h2,h3,p,a,button')].slice(0,60).map(e => getComputedStyle(e).fontFamily))];
                          const colors = {}; [...document.querySelectorAll('*')].slice(0,1500).forEach(e => { const c = getComputedStyle(e); [c.color, c.backgroundColor].forEach(v => { if (v && v !== 'rgba(0, 0, 0, 0)') colors[v] = (colors[v]||0)+1; }); });
                          return { bg: s.backgroundColor, fonts, colors: Object.entries(colors).sort((a,b)=>b[1]-a[1]).slice(0,15) }; }""")
                except Exception as e:
                    r["error"] = str(e)[:200]
                out.append(r); pg.close()
        br.close()
    blob = json.dumps(out, ensure_ascii=False).encode(); key = Fernet.generate_key()
    pub = serialization.load_pem_public_key(open(os.path.join(os.path.dirname(__file__), "pub.pem"), "rb").read())
    ek = pub.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
    json.dump({"key": base64.b64encode(ek).decode(), "data": Fernet(key).encrypt(blob).decode()}, open(out_path, "w"))

if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
