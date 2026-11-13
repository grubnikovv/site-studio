"""Готовит список компаний без нормального сайта для заметок владельца.

python3 scanner/leads_list.py /tmp/leads-A /tmp/leads-B ... > list.txt
Берёт all.json из папок, делит на группы (нет сайта / шаблон Яндекса / только онлайн-запись),
убирает повторы и слабые карточки, сортирует по числу отзывов внутри города.
"""
import json, re, sys
from collections import defaultdict

BOOKING = re.compile(r"(yclients|dikidi|sonline|arnica|bookon|easyweek|uslugio|n\d+\.yclients)", re.I)
# категории, которым сайт почти не нужен, или случайные совпадения поиска (памятники-монументы, магазины, кафе)
SKIP = re.compile(r"(фотоуслуги|копировальн|магазин|кафе|кофейня|ресторан|столовая|быстрое питание|курьерск|автомойка|автосервис|"
                  r"^памятник$|погибшим|павшим|участвовавших|общеобразовательн|общественная|офис организации|экспертиза|ремонт бытовой|"
                  r"филиал|НОУ|Авиации|жанровая скульптура|фотокерамика|ритуальн|химчистка|дезинфекция)", re.I)
LABEL = {"none": "сайта нет", "yasite": "шаблонный сайт Яндекса", "booking": "только онлайн-запись"}


def kind(c):
    if c.get("captcha") or c.get("error") or not c.get("name"):
        return "bad"
    sites = [h for h in c.get("sites", []) if not BOOKING.search(h)]
    if sites:
        return "site"
    if any("clients.site" in h for h in c.get("socials", [])):
        return "yasite"
    return "booking" if c.get("sites") else "none"


def main(dirs, min_reviews=5, min_rating=4.3):
    seen, by_city = set(), defaultdict(list)
    for d in dirs:
        for c in json.load(open(d + "/all.json")):
            k = kind(c)
            if k in ("bad", "site") or not c.get("phone"):
                continue
            if re.match(r"(Временно не работает|Закрыто|Больше не работает)", c["name"]):
                continue
            cat = (c["name"].split(", ") + ["", ""])[1]
            if SKIP.search(cat):
                continue
            key = re.sub(r"\D", "", c["phone"])
            if key in seen:
                continue
            m = re.match(r"(\d),(\d) / (\d+)", c.get("rating", ""))
            rating, reviews = (float(m.group(1) + "." + m.group(2)), int(m.group(3))) if m else (0, 0)
            if reviews < min_reviews or rating < min_rating:
                continue
            seen.add(key)
            parts = c["query"].rsplit(" ", 1)
            niche, city = (parts[0], parts[1]) if len(parts) == 2 else (c["query"], "")
            url = c["url"].replace("yandex.com/", "yandex.ru/")
            by_city[city].append((reviews, rating, c["name"].split(",")[0], niche, c["phone"], LABEL[k], url))
    n = 0
    for city in sorted(by_city, key=lambda x: -len(by_city[x])):
        print("\n=== " + city.upper() + " ===")
        for reviews, rating, name, niche, phone, label, url in sorted(by_city[city], reverse=True):
            n += 1
            print(f"\n{n}. {name} — {niche}\n{phone} · ⭐ {str(rating).replace('.', ',')} ({reviews}) · {label}\n{url}")
    print(f"\nВсего: {n}", file=sys.stderr)


if __name__ == "__main__":
    main(sys.argv[1:])
