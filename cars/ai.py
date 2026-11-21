"""Разбор объявления нейросетью: текст + коллаж из фото → риски, ремонт, вердикт.

Провайдер выбирается переменными окружения:
- GIGACHAT_KEY (ключ авторизации из личного кабинета GigaChat API, физлица) — GigaChat;
- AI_URL + AI_KEY + AI_MODEL — любой сервис с OpenAI-совместимым API (например, Yandex AI Studio);
- OLLAMA_MODEL — своя открытая нейросеть, запущенная прямо на сервере через Ollama (бесплатно, без ключей).
Без ключей возвращает None — сервис работает на правилах из judge.py.
"""
import base64, io, json, os, re, time, uuid
import requests

PROMPT = """Ты — опытный перекупщик автомобилей и автоэксперт в России. Оцени объявление с точки зрения перепродажи.
Данные объявления (JSON):
{data}

На изображении — коллаж из фотографий автомобиля из объявления (если приложен).
Найди всё, что может быть подвохом: следы ДТП и перекраса (разные оттенки, зазоры, непрокрас), ржавчина, состояние салона
и руля против заявленного пробега (скрутка), такси/каршеринг, несоответствие фото описанию, подозрительно низкая цена (мошенничество),
залог/кредит/ограничения, дубликат ПТС, проблемы мотора/коробки, перекуп под видом собственника.
Оцени стоимость необходимого ремонта в рублях (0, если не видно проблем) и насколько быстро машина продастся.

Ответь СТРОГО JSON без пояснений:
{{"verdict": "брать" | "смотреть вживую" | "мимо",
 "score": 1-10 (насколько выгодно и безопасно для перепродажи),
 "risks": ["коротко, по-русски", ...],
 "plus": ["коротко", ...],
 "repair_rub": число,
 "sell_days": число (за сколько дней реально продать),
 "check": ["что проверить при осмотре / спросить у продавца", ...],
 "summary": "2-3 предложения: почему выгодно или нет"}}"""


def collage(urls, n=6):
    """Склеивает до n фото в одну картинку 3×2: одно изображение на запрос дешевле и проще для API."""
    from PIL import Image
    imgs = []
    for u in urls[:n]:
        try:
            imgs.append(Image.open(io.BytesIO(requests.get(u, timeout=20).content)).convert("RGB"))
        except Exception:
            pass
    if not imgs:
        return None
    w, h, cols = 600, 450, 3
    rows = (len(imgs) + cols - 1) // cols
    sheet = Image.new("RGB", (w * cols, h * rows), "white")
    for i, im in enumerate(imgs):
        im.thumbnail((w, h)); sheet.paste(im, ((i % cols) * w, (i // cols) * h))
    buf = io.BytesIO(); sheet.save(buf, "JPEG", quality=80)
    return buf.getvalue()


def parse(text):
    m = re.search(r"\{.*\}", text or "", re.S)
    return json.loads(m.group(0)) if m else None


def _gigachat(prompt, img):
    ca = os.environ.get("GIGACHAT_CA") or True
    tok = requests.post("https://ngw.devices.sberbank.ru:9443/api/v2/oauth", verify=ca, timeout=30,
                        headers={"Authorization": "Basic " + os.environ["GIGACHAT_KEY"], "RqUID": str(uuid.uuid4()),
                                 "Content-Type": "application/x-www-form-urlencoded"},
                        data={"scope": os.environ.get("GIGACHAT_SCOPE", "GIGACHAT_API_PERS")}).json()["access_token"]
    base = "https://gigachat.devices.sberbank.ru/api/v1"
    h = {"Authorization": "Bearer " + tok}
    msg = {"role": "user", "content": prompt}
    if img:
        f = requests.post(base + "/files", headers=h, verify=ca, timeout=60,
                          files={"file": ("car.jpg", img, "image/jpeg")}, data={"purpose": "general"}).json()
        msg["attachments"] = [f["id"]]
    r = requests.post(base + "/chat/completions", headers=h, verify=ca, timeout=120,
                      json={"model": os.environ.get("GIGACHAT_MODEL", "GigaChat-2-Max"), "messages": [msg], "temperature": 0.2})
    return r.json()["choices"][0]["message"]["content"]


def _openai(prompt, img):
    content = [{"type": "text", "text": prompt}]
    if img:
        content.append({"type": "image_url", "image_url": {"url": "data:image/jpeg;base64," + base64.b64encode(img).decode()}})
    r = requests.post(os.environ["AI_URL"].rstrip("/") + "/chat/completions", timeout=120,
                      headers={"Authorization": "Bearer " + os.environ["AI_KEY"]},
                      json={"model": os.environ["AI_MODEL"], "messages": [{"role": "user", "content": content}], "temperature": 0.2})
    return r.json()["choices"][0]["message"]["content"]


def _ollama(prompt, img):
    msg = {"role": "user", "content": prompt}
    if img:
        msg["images"] = [base64.b64encode(img).decode()]
    r = requests.post("http://127.0.0.1:11434/api/chat", timeout=900,
                      json={"model": os.environ["OLLAMA_MODEL"], "messages": [msg], "stream": False, "format": "json",
                            "options": {"temperature": 0.2, "num_ctx": 8192}})
    return r.json()["message"]["content"]


def available():
    return bool(os.environ.get("GIGACHAT_KEY") or os.environ.get("AI_KEY") or os.environ.get("OLLAMA_MODEL"))


def analyze(d, img=None):
    if not available():
        return None
    data = {k: d.get(k) for k in ("brand", "model", "year", "price", "market", "km", "owners", "generation", "complect",
                                  "engine", "hp", "city", "damaged", "docs", "vin_report", "private", "seller_age", "text")}
    prompt = PROMPT.format(data=json.dumps(data, ensure_ascii=False))
    img = img or collage(d.get("photos") or [])
    t0 = time.time()
    call = _gigachat if os.environ.get("GIGACHAT_KEY") else _openai if os.environ.get("AI_KEY") else _ollama
    for i in range(2):
        try:
            res = parse(call(prompt, img)) or {}
            res["_sec"] = round(time.time() - t0)
            return res
        except Exception as e:
            err = str(e)[:200]; time.sleep(3)
    return {"error": err}
