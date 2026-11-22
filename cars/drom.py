"""Сбор объявлений с Дрома: свежая лента и страница объявления (данные берём из встроенного JSON)."""
import html as htmllib, json, re, time
import requests

UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
S = requests.Session()
S.headers.update({"User-Agent": UA, "Accept-Language": "ru-RU,ru;q=0.9"})
MARKS = ("отличная цена", "хорошая цена", "нормальная цена", "высокая цена")


def get(url):
    r = S.get(url, timeout=30)
    r.raise_for_status()
    return r.text


def module(html, name):
    m = re.search(r'data-drom-module="%s"[^>]*>(.*?)</script>' % name, html, re.S)
    return json.loads(m.group(1)) if m else None


def num(text):
    d = re.sub(r"\D", "", text or "")
    return int(d) if d else None


def feed(page=1):
    """Свежие объявления по всей России (сначала новые)."""
    url = "https://auto.drom.ru/all/" + (f"page{page}/" if page > 1 else "") + "?order=date&unsold=1"
    html = get(url)
    data = module(html, "bulls-list-auto")
    bulls = data["bullList"]["bullsData"][0]["bulls"] if data else []
    # Оценка цены Дромом есть только в HTML карточки.
    marks = {}
    for block in html.split('data-ftid="bulls-list_bull"')[1:]:
        u = re.search(r'href="(https://auto\.drom\.ru/[^"]+?/(\d+)\.html)', block)
        mk = re.search("|".join(MARKS), block)
        if u:
            marks[int(u.group(2))] = mk.group(0) if mk else None
    out = []
    for b in bulls:
        attrs = [a.get("payload") for a in b.get("attributes", []) if isinstance(a.get("payload"), str)]
        km = next((num(a) for a in attrs if a.endswith("км")), None)
        out.append({"id": b["bullId"], "url": b["url"], "title": b["title"], "subtitle": b.get("subtitle"),
                    "price": b["price"], "city": b.get("location"), "dealer": b.get("dealerName"),
                    "attrs": attrs, "km": km, "date": b.get("date"), "mark": marks.get(b["bullId"]),
                    "new": "новый" in " ".join(attrs).lower() or b.get("dealType") == 1})
    return out


OWNERS = {"one": 1, "two": 2, "three": 3, "four_or_more": 4}


def details(url):
    html = get(url)
    d = module(html, "bull-page")
    if not d:
        return None
    f = {x["type"]: x.get("payload") for x in d["bullDescription"]["fields"]}
    c = d.get("constants", {})
    deal = (d.get("priceWidgetData") or {}).get("goodDealConfig") or {}
    rep = [x for x in (d.get("report") or {}).get("fields", []) if x.get("type") == "report_item"]
    info = d.get("additionalInfo") or {}
    text = (info.get("visible") or "") + (info.get("hidden") or "")
    text = re.sub(r"<br\s*/?>", "\n", text); text = htmllib.unescape(re.sub(r"<[^>]+>", "", text)).replace("\u2028", "")
    text = re.sub(r"\n\s*\n+", "\n", re.sub(r"[ \t]+", " ", text)).strip()
    photos = [i["image"]["sources"].get("gen600") or i["image"]["src"] for i in d["gallery"]["photos"]["images"]]
    special = f.get("specialDescription") or {}
    mil = f.get("mileage") or {}
    eng = f.get("engine") or {}
    return {
        "brand": c.get("firmName"), "model": c.get("modelName"), "year": c.get("year"), "price": c.get("price"),
        "market": deal.get("marketPrice"), "city": c.get("cityName"), "published": c.get("publicationDateText"),
        "km": mil.get("mileage"), "no_rf_km": mil.get("withoutMileageInRussia"),
        "owners": OWNERS.get(f.get("numberOfOwners")), "damaged": special.get("hasDamage"), "docs": special.get("hasDocs"),
        "engine": eng.get("volume"), "hp": (f.get("power") or {}).get("power"),
        "generation": (f.get("generation") or {}).get("generationName"),
        "complect": (f.get("complectation") or {}).get("complectationName"),
        "vin_report": [{"status": x.get("status"), "value": x.get("value"), "data": x.get("data")} for x in rep],
        "private": d.get("privatePersonCard") is not None, "seller_age": (d.get("privatePersonCard") or {}).get("age"),
        "dealer": (d.get("dealerCard") or {}).get("name") if isinstance(d.get("dealerCard"), dict) else None,
        "views": (d.get("bullCounters") or {}).get("totalViews"),
        "text": text[:4000], "photos": photos,
    }


if __name__ == "__main__":
    for b in feed(1)[:5]:
        print(b)
        time.sleep(1)
