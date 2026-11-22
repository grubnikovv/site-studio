"""Отправка находок в Telegram (токен бота — только в секретах GitHub, не во фронтенде)."""
import html, os
import requests


def rub(x):
    return f"{x:,}".replace(",", " ") + " ₽" if isinstance(x, (int, float)) else "—"


def card(x):
    d, p, a = x["d"], x["p"], x.get("ai") or {}
    e = html.escape
    head = f"{d['brand']} {d['model']} {d['year']}" + (f", {d['complect']}" if d.get("complect") else "")
    lines = [f"<b>{'🔥' if a.get('verdict') == 'брать' else '🚗'} {e(head)}</b>",
             f"{d['km']:,} км · {e(d.get('city') or '')} · {'частник' if d.get('private') else 'салон/дилер'}".replace(",", " ") if d.get("km") else e(d.get("city") or ""),
             "",
             f"Цена: <b>{rub(p['buy'])}</b> · рынок {rub(d['market'])} (−{p['discount']}%)",
             f"💰 Прибыль ≈ <b>{rub(p['profit'])}</b> (продажа {rub(p['sell'])}, расходы {rub(p['costs'])}"
             + (f", из них ремонт {rub(p['repair'])}" if p.get("repair") else "") + ")"]
    if a.get("verdict"):
        lines += ["", f"🤖 <b>{e(a['verdict'])}</b> · {a.get('score', '?')}/10" + (f" · продать за ~{a['sell_days']} дн." if a.get("sell_days") else ""),
                  e(a.get("summary") or "")]
    risks = (a.get("risks") or []) + x["red"]
    if risks: lines += ["", "⚠️ " + e("; ".join(dict.fromkeys(risks)))]
    plus = (a.get("plus") or []) + x["good"]
    if plus: lines += ["✅ " + e("; ".join(dict.fromkeys(plus)))]
    if a.get("check"): lines += ["🔎 Проверить: " + e("; ".join(a["check"]))]
    lines += ["", f"Опубликовано: {e(d.get('published') or '')}"]
    return "\n".join(lines)[:4000]


def send(x):
    tok, chat = os.environ.get("TG_TOKEN"), os.environ.get("TG_CHAT")
    if not tok or not chat:
        return False
    api = f"https://api.telegram.org/bot{tok}/"
    kb = {"inline_keyboard": [[{"text": "Открыть объявление", "url": x["url"]}]]}
    if x["d"].get("photos"):
        requests.post(api + "sendPhoto", timeout=30, json={"chat_id": chat, "photo": x["d"]["photos"][0], "disable_notification": True})
    r = requests.post(api + "sendMessage", timeout=30, json={"chat_id": chat, "text": card(x), "parse_mode": "HTML",
                                                              "reply_markup": kb, "disable_web_page_preview": True})
    return r.ok
