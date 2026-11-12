"""Разбирает расшифрованный результат boards.py: заказы Kwork с бюджетом, числом откликов и заказчиком.

python3 scanner/parse_kwork.py results.json state/seen_kwork.json
Печатает новые подходящие заказы (JSON) и обновляет список уже показанных.
"""
import json, re, sys

BAD = re.compile(r"звон|лидоген|привлеч|подписчик|рассылк|продвиж|раскрут|директ|seo|сео|реклам|инвайт|менеджер|ватсап|отзыв|1с|битрикс|wordpress|вордпресс|opencart|rustore|монетизац|чертеж|генплан|corel|autocad|smm|таргет|ютуб|youtube|android|ios|kotlin|ставк|крипт|казино|арбитраж|3ds|ландшафт|вакансия", re.I)

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

if __name__ == "__main__":
    data = json.load(open(sys.argv[1])); seen_path = sys.argv[2]
    seen = set(json.load(open(seen_path)))
    allp = parse(data)
    fresh = [v for k, v in allp.items() if k not in seen and v["budget"] >= 1500 and v["offers"] <= 25 and not BAD.search(v["title"] + " " + v["desc"])]
    fresh.sort(key=lambda v: (v["offers"], -v["budget"]))
    json.dump(sorted(seen | set(allp)), open(seen_path, "w"))
    print(json.dumps(fresh, ensure_ascii=False, indent=1))
