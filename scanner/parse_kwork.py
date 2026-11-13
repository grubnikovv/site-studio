"""Разбирает расшифрованный результат boards.py: заказы Kwork с бюджетом, числом откликов и заказчиком.

python3 scanner/parse_kwork.py results.json state/seen_kwork.json
Печатает новые подходящие заказы (JSON) и обновляет список уже показанных.
"""
import hashlib, json, re, sys

BAD = re.compile(r"звон|лидоген|привлеч|подписчик|рассылк|продвиж|раскрут|директ|seo|сео|реклам|инвайт|менеджер|ватсап|отзыв|1с\b|rustore|монетизац|чертеж|генплан|corel|autocad|smm|таргет|ютуб|youtube|android|ios|kotlin|ставк|крипт|казино|арбитраж|3ds|ландшафт|вакансия", re.I)

def parse(data):
    out = {}
    for r in data:
        t = r.get("text", "")
        links = {l[0].strip(): l[1] for l in r.get("links", []) if re.search(r"kwork\.ru/projects/\d+$", l[1])}
        blocks = re.split(r"(Предложений:\s*\d+)", t)
        for i in range(0, len(blocks) - 1, 2):
            b = blocks[i]; off = int(re.search(r"\d+", blocks[i + 1]).group())
            title = None
            for name, h in links.items():
                if name and name in b: title = (name, h)
            if not title: continue
            m = re.search(r"(?:Желаемый бюджет: до|Цена до:|Цена)\s*([\d\s]+)\s*₽", b); m2 = re.search(r"Допустимый: до\s*([\d\s]+)\s*₽", b)
            bud = int(re.sub(r"\s", "", m.group(1))) if m else 0; mx = int(re.sub(r"\s", "", m2.group(1))) if m2 else bud
            hired = re.search(r"Нанято:\s*(\d+)%", b); proj = re.search(r"Размещено проектов на бирже:\s*(\d+)", b); left = re.search(r"Осталось:\s*([^\n]+)", b)
            desc = b.split(title[0])[-1].strip().replace("\n", " ")
            for stop in ("Желаемый", "Цена"):
                if stop in desc: desc = desc[: desc.find(stop)]
            out[title[1].split("/")[-1]] = dict(id=title[1].split("/")[-1], url=title[1], title=title[0], budget=bud, max=mx, offers=off,
                hired=hired.group(1) if hired else None, projects=proj.group(1) if proj else None, left=left.group(1).strip() if left else "", desc=desc[:300].replace("Показать полностью", "").strip())
    return out



# ---------- Другие площадки ----------
KW = re.compile(r"сайт|лендинг|бот|telegram|телеграм|дизайн|верст|тильд|tilda|баннер|презентац|инфографик|текст|копирайт|таблиц|excel|парсер|автоматизац|нейросет|ии\b|ai\b|видео|логотип|карточк", re.I)

def parse_fl(r):
    out = []
    t = r.get("text", "")
    links = {l[0].strip(): l[1] for l in r.get("links", []) if re.search(r"fl\.ru/projects/\d+/", l[1])}
    for m in re.finditer(r"\n([^\n]{8,120})\n([^\n]*(?:₽|по договоренности|по результатам)[^\n]*)\n([^\n]{20,500})\nОткликнуться\n([^\n]+)\n[^\n]*\n\s*([^\n]*)", t):
        title = m.group(1).strip(); when = m.group(4).strip(); answers = m.group(5).strip()
        if "Вакансия" in when: continue
        n = re.search(r"(\d+)", answers); n = 0 if "Нет" in answers else (int(n.group(1)) if n else 99)
        out.append(dict(site="FL.ru", id="fl:" + (links.get(title, title)), url=links.get(title, r["url"]), title=title, budget_text=m.group(2).strip(), offers=n, when=when, desc=m.group(3)[:300]))
    return out

def parse_tg(r, days=2):
    out = []
    t = r.get("text", ""); ch = r["url"].rstrip("/").split("/")[-1]
    posts = re.split(r"\n[\d.,]+K?\s*\nviews\n", t)
    for p in posts[-25:]:
        p = p.strip()
        if len(p) < 80 or not KW.search(p) or BAD.search(p): continue
        if not re.search(r"(нужен|нужна|нужно|ищу|ищем|требуется|сделать|разработать)", p, re.I): continue
        if re.search(r"(вакансия|зарплата|в штат|оклад|резюме)", p, re.I): continue
        key = "tg:" + ch + ":" + hashlib.md5(p[:120].encode()).hexdigest()[:12]
        lines = [l.strip() for l in p.split("\n") if len(l.strip()) >= 15 and re.search(r"[А-Яа-яA-Za-z]{4}", l) and not re.match(r"^(edited|\d{1,2}:\d{2}|January|February|March|April|May|June|July|August|September|October|November|December)", l.strip())]
        out.append(dict(site="Telegram @" + ch, id=key, url="https://t.me/" + ch, title=(lines[1] if len(lines) > 1 and lines[0].startswith(("Работа", "Фриланс", "Вакансии")) else (lines[0] if lines else ch))[:120], offers=None, desc=re.sub(r"\s+", " ", p)[:400]))
    return out

def parse_all(data):
    items = {}
    kw = parse([r for r in data if "kwork.ru" in r["url"]])
    for k, v in kw.items(): v["site"] = "Kwork"; items[k] = v
    for r in data:
        if "fl.ru" in r["url"]:
            for v in parse_fl(r): items[v["id"]] = v
        if "t.me/s/" in r["url"]:
            for v in parse_tg(r): items[v["id"]] = v
        if "youdo.com" in r["url"]:
            t = r.get("text", "")
            for m in re.finditer(r"\n([^\n]{8,100})\nМожно выполнить удаленно\n[^\n]*\n(?:[^\n]*\n)?до ([\d\s]+) ₽", t):
                title = m.group(1).strip()
                if KW.search(title) and not BAD.search(title):
                    items["yd:" + title] = dict(site="YouDo", id="yd:" + title, url=r["url"], title=title, budget=int(re.sub(r"\s", "", m.group(2))), offers=None, desc="")
    return items


if __name__ == "__main__":
    data = json.load(open(sys.argv[1])); seen_path = sys.argv[2]
    seen = set(json.load(open(seen_path)))
    allp = parse_all(data)
    def ok(v):
        if BAD.search(v["title"] + " " + v.get("desc", "")): return False
        if v["site"] == "Kwork": return v["budget"] >= 1500 and v["offers"] <= 25
        if v["site"] == "FL.ru": return v["offers"] <= 30
        return True
    fresh = [v for k, v in allp.items() if k not in seen and ok(v)]
    fresh.sort(key=lambda v: (v["offers"] if v["offers"] is not None else 50, -v.get("budget", 0)))
    json.dump(sorted(seen | set(allp)), open(seen_path, "w"))
    print(json.dumps(fresh, ensure_ascii=False, indent=1))
