# Плановая проверка Kwork

Инструкция для автоматической сессии. Шаги 1–5 выполняет одной командой `python3 scanner/watch_run.py` (запускать фоном, итог — `/tmp/watch-<stamp>/new.json` и по понедельникам `warm.json`). Каждый запуск:

1. Создать временный ключ: `openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out /tmp/k.pem && openssl pkey -in /tmp/k.pem -pubout -out /tmp/k.pub`.
2. Записать `jobs/watch-<YYYYMMDD-HHMM>.json`: `{"name": "...", "pause": 14, "pubkey": "<содержимое /tmp/k.pub>", "urls": [...]}` — ссылки:
   - Kwork по словам: лендинг, сайт, редизайн, дизайн сайта, telegram бот, бот, чат-бот, верстка, квиз, калькулятор, mini app, автоматизация, презентация, инфографика, баннер (`https://kwork.ru/projects?keyword=<слово>`);
   - FL.ru: `https://www.fl.ru/projects/`, `https://www.fl.ru/projects/category/razrabotka-sajtov/`, `https://www.fl.ru/projects/category/avtomatizaciya-biznesa/`, `https://www.fl.ru/projects/category/ai-iskusstvenniy-intellekt/`, `https://www.fl.ru/projects/category/dizajn/`, `https://www.fl.ru/projects/category/teksty/`;
   - YouDo: `https://youdo.com/tasks-all-opened-all`;
   - Telegram-каналы с заказами: `https://t.me/s/job_for_bots`, `https://t.me/s/digitaltender`, `https://t.me/s/freelancetaverna`, `https://t.me/s/mirfri`, `https://t.me/s/FrWork3`, `https://t.me/s/job_webdev`.
   Раз в неделю (по понедельникам) дополнительно — тёплые клиенты: задание с полем `"queries"` (см. `scanner/ymaps.py`) по запросам «грузовой автосервис», «грузовой шиномонтаж», «запчасти для грузовиков», «эвакуатор», «грузоперевозки» в новых районах Москвы и области; показать компании без собственного сайта с рейтингом от 4,5.
3. Закоммитить и запушить в ветку `claude/low-code-business-ideas-0dr8l2`. GitHub Actions выполнит `scanner/boards.py` и положит `results/watch-....enc.json`.
4. Дождаться файла (опрашивать `git fetch` раз в 20 секунд, до 20 минут), расшифровать приватным ключом `/tmp/k.pem` (см. `~/.leadkeys/dec.py` как пример: RSA-OAEP SHA256 ключ + Fernet).
5. `python3 scanner/parse_kwork.py <расшифрованный.json> state/seen_kwork.json` — новые заказы со всех площадок (Kwork: откликов ≤ 25, бюджет ≥ 1 500 ₽; FL.ru: откликов ≤ 30; Telegram и YouDo — по ключевым словам). Посты Telegram проверить вручную: только реальные заказы, не вакансии и не реклама. Закоммитить обновлённый `state/seen_kwork.json`.
6. Из новых выбрать до 5 лучших: можно сделать с ИИ (сайты, лендинги, редизайн, боты, дизайн, тексты, таблицы), заказчик нанимает часто (Нанято ≥ 40% или новый), бюджет адекватен объёму.
7. Ответ владельцу — по-русски, коротко: для каждого заказа ссылка, бюджет, число откликов, суть в одну строку, почему подходит, и готовый текст отклика (цена, срок, 5–8 строк). Если подходящих нет — одна строка «новых подходящих заказов нет».
