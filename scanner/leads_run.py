"""Поиск компаний без сайта в Яндекс Картах по городам и нишам России.

python3 scanner/leads_run.py "Волгоград,Камышин" "салон красоты,автосервис" [на_запрос=8]
Пушит задание jobs/leads-<время>.json, GitHub Actions выполняет scanner/ymaps.py,
скрипт ждёт результат, расшифровывает и кладёт в /tmp/leads-<время>/:
  all.json — все карточки, nosite.json — без сайта, с телефоном и рейтингом.
"""
import datetime, json, os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from watch_run import ROOT, push, wait, decrypt, sh, BRANCH
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric import rsa


def main():
    cities = [c.strip() for c in sys.argv[1].split(",") if c.strip()]
    niches = [n.strip() for n in sys.argv[2].split(",") if n.strip()]
    per = int(sys.argv[3]) if len(sys.argv) > 3 else 8
    now = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=3)))
    name = "leads-" + now.strftime("%Y%m%d-%H%M") + "-" + os.urandom(2).hex()
    work = "/tmp/" + name; os.makedirs(work, exist_ok=True)
    sh("git", "checkout", BRANCH); sh("git", "pull", "--rebase", "origin", BRANCH)
    priv = rsa.generate_private_key(public_exponent=65537, key_size=3072)
    open(work + "/k.pem", "wb").write(priv.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption()))
    pub = priv.public_key().public_bytes(serialization.Encoding.PEM, serialization.PublicFormat.SubjectPublicKeyInfo).decode()
    # чередуем города, чтобы не долбить один и тот же район подряд
    queries = [n + " " + c for n in niches for c in cities]
    json.dump({"name": name, "anywhere": True, "pause": 3, "scrolls": 3, "per_query": per, "pubkey": pub, "queries": queries},
              open(f"{ROOT}/jobs/{name}.json", "w"), ensure_ascii=False, indent=1)
    push("Поиск компаний без сайта: " + ", ".join(cities), f"jobs/{name}.json")
    print("pushed", name, len(queries), "queries", flush=True)
    if wait([name], limit=110 * 60):
        raise SystemExit("ERROR: результат не пришёл")
    data = decrypt(f"{ROOT}/results/{name}.enc.json", priv)
    json.dump(data, open(work + "/all.json", "w"), ensure_ascii=False, indent=1)
    nosite = [c for c in data if not c.get("has_site") and c.get("phone") and not c.get("captcha") and not c.get("error")]
    json.dump(nosite, open(work + "/nosite.json", "w"), ensure_ascii=False, indent=1)
    ok = [c for c in data if c.get("name") and not c.get("captcha")]
    print("cards", len(data), "ok", len(ok), "captcha", sum(1 for c in data if c.get("captcha")), "nosite", len(nosite), flush=True)
    print("DONE", work, flush=True)


if __name__ == "__main__":
    main()
