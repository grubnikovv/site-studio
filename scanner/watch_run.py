"""Один плановый запуск поиска заказов (см. scanner/kwork_watch.md).

python3 scanner/watch_run.py
Создаёт временный ключ, пушит задание jobs/watch-<дата-время>.json (по понедельникам ещё
jobs/warm-<дата-время>.json для Яндекс Карт), ждёт результат GitHub Actions, расшифровывает,
прогоняет parse_kwork.py, пушит state/seen_kwork.json.
Итог: /tmp/watch-<stamp>/new.json (новые заказы) и /tmp/watch-<stamp>/warm.json (тёплые клиенты).
"""
import base64, datetime, json, os, subprocess, sys, time
from urllib.parse import quote
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.fernet import Fernet

BRANCH = "claude/low-code-business-ideas-0dr8l2"
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
WORDS = ["лендинг", "сайт", "редизайн", "дизайн сайта", "telegram бот", "бот", "чат-бот", "верстка", "квиз",
         "калькулятор", "mini app", "автоматизация", "презентация", "инфографика", "баннер"]
OTHER = ["https://www.fl.ru/projects/", "https://www.fl.ru/projects/category/razrabotka-sajtov/",
         "https://www.fl.ru/projects/category/razrabotka-sajtov/landingi/", "https://www.fl.ru/projects/category/messendzhery/",
         "https://www.fl.ru/projects/category/dizajn/", "https://www.fl.ru/projects/category/teksty/",
         "https://youdo.com/tasks-all-opened-all"] + ["https://t.me/s/" + c for c in
         ("job_for_bots", "digitaltender", "freelancetaverna", "mirfri", "FrWork3", "job_webdev")]
WARM = ["грузовой автосервис", "грузовой шиномонтаж", "запчасти для грузовиков", "эвакуатор", "грузоперевозки"]
# Новые районы Москвы и области — по одному на неделю, по кругу.
DISTRICTS = ["Коммунарка", "Новая Москва", "Видное", "Подольск", "Домодедово", "Балашиха", "Мытищи",
             "Химки", "Люберцы", "Одинцово", "Красногорск", "Щёлково", "Пушкино", "Зеленоград"]


def sh(*cmd, check=True):
    return subprocess.run(cmd, cwd=ROOT, check=check, capture_output=True, text=True).stdout


def push(msg, *files):
    sh("git", "add", *files)
    sh("git", "commit", "-m", msg + "\n\nCo-Authored-By: Claude <noreply@anthropic.com>")
    for i in range(5):
        try:
            sh("git", "pull", "--rebase", "origin", BRANCH); sh("git", "push", "-u", "origin", BRANCH); return
        except subprocess.CalledProcessError as e:
            print("push retry", i, e.stderr[-200:], flush=True); time.sleep(2 ** (i + 1))
    raise SystemExit("push failed")


def decrypt(path, key):
    blob = json.load(open(path))
    k = key.decrypt(base64.b64decode(blob["key"]), padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
    return json.loads(Fernet(k).decrypt(blob["data"].encode()))


def wait(names, limit=30 * 60):
    t0 = time.time(); left = set(names)
    while left and time.time() - t0 < limit:
        time.sleep(20)
        try: sh("git", "pull", "--rebase", "origin", BRANCH)
        except subprocess.CalledProcessError: continue
        for n in list(left):
            if os.path.exists(os.path.join(ROOT, "results", n + ".enc.json")):
                print("ready", n, int(time.time() - t0), "s", flush=True); left.discard(n)
    return left


def main():
    now = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=3)))
    stamp = now.strftime("%Y%m%d-%H%M"); work = "/tmp/watch-" + stamp; os.makedirs(work, exist_ok=True)
    sh("git", "checkout", BRANCH); sh("git", "pull", "--rebase", "origin", BRANCH)
    priv = __import__("cryptography.hazmat.primitives.asymmetric.rsa", fromlist=["x"]).generate_private_key(public_exponent=65537, key_size=3072)
    open(work + "/k.pem", "wb").write(priv.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption()))
    pub = priv.public_key().public_bytes(serialization.Encoding.PEM, serialization.PublicFormat.SubjectPublicKeyInfo).decode()

    names = ["watch-" + stamp]
    urls = ["https://kwork.ru/projects?keyword=" + quote(w) for w in WORDS] + OTHER
    json.dump({"name": names[0], "pause": 8, "pubkey": pub, "urls": urls}, open(f"{ROOT}/jobs/{names[0]}.json", "w"), ensure_ascii=False, indent=1)
    push("Плановая проверка заказов", f"jobs/{names[0]}.json")
    if now.weekday() == 0:
        d = DISTRICTS[now.isocalendar()[1] % len(DISTRICTS)]
        names.append("warm-" + stamp)
        json.dump({"name": names[1], "pause": 4, "per_query": 12, "pubkey": pub, "queries": [q + " " + d for q in WARM]},
                  open(f"{ROOT}/jobs/{names[1]}.json", "w"), ensure_ascii=False, indent=1)
        push("Поиск тёплых клиентов: " + d, f"jobs/{names[1]}.json")  # отдельный пуш — workflow смотрит только последний коммит
    print("pushed", names, flush=True)

    missing = wait(names)
    if names[0] in missing:
        raise SystemExit("ERROR: результат " + names[0] + " не пришёл за 30 минут")
    data = decrypt(f"{ROOT}/results/{names[0]}.enc.json", priv)
    json.dump(data, open(work + "/raw.json", "w"), ensure_ascii=False)
    for r in data:
        print("src", r["url"][:70], len(r.get("text", "")), r.get("error", "")[:80], flush=True)
    out = sh("python3", "scanner/parse_kwork.py", work + "/raw.json", "state/seen_kwork.json")
    open(work + "/new.json", "w").write(out)
    if sh("git", "status", "--porcelain", "state/seen_kwork.json").strip():
        push("Учёт показанных заказов", "state/seen_kwork.json")
    print("new", len(json.loads(out)), flush=True)

    if len(names) > 1:
        if names[1] in missing:
            print("warm: не успел", flush=True)
        else:
            warm = [c for c in decrypt(f"{ROOT}/results/{names[1]}.enc.json", priv) if not c.get("has_site") and c.get("phone")]
            json.dump(warm, open(work + "/warm.json", "w"), ensure_ascii=False, indent=1)
            print("warm", len(warm), flush=True)
    print("DONE", work, flush=True)


if __name__ == "__main__":
    main()
