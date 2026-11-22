"""Отладка: сохраняет сырой HTML страниц (requests) и пробует ИИ GitHub Models. Результат шифруется."""
import base64, json, os, sys, time
import requests
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.fernet import Fernet

UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"


def main(job_path, out_path):
    job = json.load(open(job_path)); out = []
    s = requests.Session(); s.headers.update({"User-Agent": UA, "Accept-Language": "ru-RU,ru;q=0.9"})
    for url in job["raw"]:
        r = {"url": url}
        try:
            resp = s.get(url, timeout=30)
            r.update(status=resp.status_code, final=resp.url, html=resp.text[:1500000])
        except Exception as e:
            r["error"] = str(e)[:300]
        print(url, r.get("status"), len(r.get("html", "")), r.get("error", ""), flush=True)
        out.append(r); time.sleep(job.get("pause", 3))
    # Токен уходит только на models.github.ai.
    for method, url, body in job.get("http", []):
        try:
            assert url.startswith("https://models.github.ai/"), "только models.github.ai"
            resp = requests.request(method, url, json=body, timeout=60, headers={"Authorization": "Bearer " + os.environ.get("GITHUB_TOKEN", "")})
            out.append({"http": url, "status": resp.status_code, "body": resp.text[:3000], "ctype": resp.headers.get("Content-Type")})
        except Exception as e:
            out.append({"http": url, "error": str(e)[:300]})
        print("http", method, url, out[-1].get("status"), flush=True)
    blob = json.dumps(out, ensure_ascii=False).encode()
    key = Fernet.generate_key()
    pub = serialization.load_pem_public_key(job["pubkey"].encode())
    ek = pub.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
    json.dump({"key": base64.b64encode(ek).decode(), "data": Fernet(key).encrypt(blob).decode()}, open(out_path, "w"))


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
