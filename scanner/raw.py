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
    if job.get("llm"):
        for ep, model in job["llm"]:
            try:
                resp = requests.post(ep, timeout=60,
                    headers={"Authorization": "Bearer " + os.environ.get("GITHUB_TOKEN", ""), "Accept": "application/vnd.github+json",
                             "X-GitHub-Api-Version": "2022-11-28", "Content-Type": "application/json"},
                    json={"model": model, "messages": [{"role": "user", "content": [
                        {"type": "text", "text": "Что на фото? Ответь одной фразой по-русски."},
                        {"type": "image_url", "image_url": {"url": job.get("img", "")}}]}]})
                out.append({"llm": model, "ep": ep, "hist": [(h.status_code, h.url) for h in resp.history], "status": resp.status_code, "body": resp.text[:3000], "headers": dict(resp.headers)})
            except Exception as e:
                out.append({"llm": model, "error": str(e)[:300]})
            print("llm", ep, model, out[-1].get("status"), out[-1].get("hist"), str(out[-1].get("body"))[:200], flush=True)
    blob = json.dumps(out, ensure_ascii=False).encode()
    key = Fernet.generate_key()
    pub = serialization.load_pem_public_key(job["pubkey"].encode())
    ek = pub.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
    json.dump({"key": base64.b64encode(ek).decode(), "data": Fernet(key).encrypt(blob).decode()}, open(out_path, "w"))


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
