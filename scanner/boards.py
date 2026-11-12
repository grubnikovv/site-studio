"""Снимает текст страниц с заказами на фриланс-биржах. Результат шифруется."""
import base64, json, os, sys, time
from playwright.sync_api import sync_playwright
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.fernet import Fernet

def main(job_path, out_path):
    job = json.load(open(job_path))
    out = []
    with sync_playwright() as p:
        br = p.chromium.launch()
        ctx = br.new_context(locale="ru-RU", timezone_id="Europe/Moscow", viewport={"width": 1366, "height": 900},
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36")
        page = ctx.new_page()
        for url in job["urls"]:
            r = {"url": url}
            try:
                page.goto(url, timeout=45000, wait_until="domcontentloaded")
                page.wait_for_timeout(6000)
                for _ in range(3):
                    page.mouse.wheel(0, 4000); page.wait_for_timeout(1200)
                r["final"] = page.url; r["title"] = page.title()
                r["text"] = page.inner_text("body", timeout=15000)[:60000]
                r["links"] = page.eval_on_selector_all("a[href]", "els => els.map(e => [e.innerText.trim().slice(0,150), e.href]).filter(x => x[0].length > 15)")[:400]
            except Exception as e:
                r["error"] = str(e)[:300]
            print(url, len(r.get("text", "")), r.get("error", ""), flush=True)
            out.append(r); time.sleep(job.get("pause", 2))
        br.close()
    blob = json.dumps(out, ensure_ascii=False).encode()
    key = Fernet.generate_key()
    pem = job.get("pubkey", "").encode() or open(os.path.join(os.path.dirname(__file__), "pub.pem"), "rb").read()
    pub = serialization.load_pem_public_key(pem)
    ek = pub.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
    json.dump({"key": base64.b64encode(ek).decode(), "data": Fernet(key).encrypt(blob).decode()}, open(out_path, "w"))

if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
