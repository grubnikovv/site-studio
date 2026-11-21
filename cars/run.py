"""Один проход: свежие объявления Дрома → кандидаты → разбор → уведомление.

python cars/run.py <state.json> [<out.enc.json> <pubkey.pem>]
Настройки — переменные окружения: PAGES, MIN_DISCOUNT, MIN_PROFIT, MIN_PRICE, MAX_PRICE.
"""
import base64, json, os, sys, time
sys.path.insert(0, os.path.dirname(__file__))
import drom, judge, ai, notify

PAGES = int(os.environ.get("PAGES", 5))
MIN_DISCOUNT = float(os.environ.get("MIN_DISCOUNT", 10))
MIN_PROFIT = int(os.environ.get("MIN_PROFIT", 40000))
MIN_PRICE = int(os.environ.get("MIN_PRICE", 150000))
MAX_PRICE = int(os.environ.get("MAX_PRICE", 0)) or 10 ** 10
MAX_DETAILS = int(os.environ.get("MAX_DETAILS", 25))


def main(state_path, out_path=None, pub_path=None):
    seen = set(json.load(open(state_path))) if os.path.exists(state_path) else set()
    log = {"feed": 0, "fresh": 0, "candidates": 0, "deals": 0, "sent": 0, "ai": ai.available(), "errors": []}
    fresh = []
    for page in range(1, PAGES + 1):
        try:
            items = drom.feed(page)
        except Exception as e:
            log["errors"].append(f"feed {page}: {e}"[:200]); break
        log["feed"] += len(items)
        new = [b for b in items if b["id"] not in seen]
        fresh += new
        if len(new) < len(items) / 2:  # дошли до уже просмотренного
            break
        time.sleep(2)
    log["fresh"] = len(fresh)
    cands = [b for b in fresh if not b["new"] and b["mark"] in ("отличная цена", "хорошая цена")
             and MIN_PRICE <= b["price"] <= MAX_PRICE]
    uniq = {}
    for b in cands:  # салоны часто дублируют одну машину
        uniq.setdefault((b["title"], b["price"], b["km"], b["city"]), b)
    cands = list(uniq.values())
    log["candidates"] = len(cands)
    report = []
    for b in cands[:MAX_DETAILS]:
        x = {"url": b["url"], "feed": b}
        try:
            d = drom.details(b["url"]); time.sleep(2)
        except Exception as e:
            x["error"] = str(e)[:200]; report.append(x); continue
        if not d:
            continue
        x["d"] = d
        x["red"], x["good"] = judge.flags(d)
        x["p"] = judge.profit(d)
        ok = x["p"] and x["p"]["discount"] >= MIN_DISCOUNT and x["p"]["profit"] >= MIN_PROFIT
        if ok and ai.available():
            x["ai"] = ai.analyze(d) or {}
            rep = x["ai"].get("repair_rub")
            if isinstance(rep, (int, float)) and rep > 0:
                x["p"] = judge.profit(d, int(rep))
            ok = x["p"]["profit"] >= MIN_PROFIT and x["ai"].get("verdict") != "мимо"
        x["deal"] = bool(ok)
        if ok:
            log["deals"] += 1
            if notify.send(x):
                log["sent"] += 1
        report.append(x)
    seen.update(b["id"] for b in fresh)
    json.dump(sorted(seen)[-20000:], open(state_path, "w"))
    print(json.dumps(log, ensure_ascii=False), flush=True)
    if out_path and pub_path:
        from cryptography.hazmat.primitives import hashes, serialization
        from cryptography.hazmat.primitives.asymmetric import padding
        from cryptography.fernet import Fernet
        blob = json.dumps({"log": log, "report": report, "fresh": fresh}, ensure_ascii=False).encode()
        key = Fernet.generate_key()
        pub = serialization.load_pem_public_key(open(pub_path, "rb").read())
        ek = pub.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
        json.dump({"key": base64.b64encode(ek).decode(), "data": Fernet(key).encrypt(blob).decode()}, open(out_path, "w"))


if __name__ == "__main__":
    main(*sys.argv[1:])
