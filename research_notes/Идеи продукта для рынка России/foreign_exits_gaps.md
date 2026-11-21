# Зарубежные цифровые сервисы, которые ушли, заблокированы или перестали принимать российские платежи (2022–2026), и насколько их закрывают российские замены

> Состояние на 21.11.2026. **Ограничения метода (важно для автора отчёта):**
> 1. Полные тексты страниц получить не удалось. WebFetch к meduza.io, 3dnews.ru, istories.media, comnews.ru и ru.themoscowtimes.com вернул ошибку DNS: эти хосты не входят в разрешённую сеть сессии. Чтобы дочитать первоисточники, их нужно разрешить в сетевых настройках.
> 2. После ~37 поисковых запросов этого исследователя закончился общий лимит веб-поиска на ход (200 запросов на всех агентов). Поэтому **все факты ниже взяты из сводок поисковой выдачи, а не из полного текста статей**.
> 3. Метки у фактов: «данные вендора»; «статья посредника» (продаёт услуги оплаты зарубежных сервисов, т. е. заинтересован); «только заголовок» (известен лишь заголовок, содержание не прочитано); «устарело» (данные 2022–2023 гг.).
> 4. Категории «инструменты разработчика» и «ИИ-инструменты» исследовать не успели. Фоновые знания модели по ним вынесены в «Пробелы» с пометкой «не проверено».

---

## 1. Какие зарубежные сервисы ушли, перестали принимать российские платежи или были заблокированы РКН в 2022–2026 гг. (по категориям)

### Takeaway
Уходы шли тремя волнами:
- **2022.** 10 марта перестали работать Visa/Mastercard и Apple Pay, после чего последовал массовый уход и прекращение продаж: Adobe, Microsoft, Atlassian, GitLab, Docker, Canva, Mailchimp, Serpstat, Shopify, Strava, приложения Nike, Netflix, Spotify.
- **2024.** В силу вступил санкционный запрет США на IT-услуги (12.09.2024). Notion закрыл российские аккаунты 09.09.2024, корпоративные подписки Microsoft 365 отключились 02.09.2024, ушёл Slack, неопределённость возникла у Miro и Figma. В тот же год Adidas Running удалил аккаунты россиян (31.03.2024), РКН заблокировал Discord (октябрь 2024), а YouTube был фактически заблокирован.
- **2025–2026.** Волна РКН ударила по средствам связи: звонки в WhatsApp и Telegram (с августа 2025), Snapchat (10.10.2025), FaceTime и Roblox (декабрь 2025), попытка полной блокировки WhatsApp (февраль 2026), поэтапное ограничение Telegram (с января–февраля 2026).

Большинство SaaS-сервисов в 2025–2026 гг. не заблокированы, но **оплатить их невозможно**. Именно на этом держится рынок VPN и платёжных посредников.

### Cited Findings

#### Сквозные события: платежи, санкции, общие списки
- С 10.03.2022 Visa и Mastercard прекратили обслуживать российские карты, Apple Pay перестал с ними работать. Тот же живой список Habr 2022 г. перечисляет ушедшие и ограничившие доступ сервисы: Netflix, Spotify, TikTok, Booking.com, Adobe, Microsoft, Docker, GitLab, Atlassian и другие. Это исторический ориентир, а не текущее состояние (устарело: 2022) — [Habr, март 2022](https://habr.com/ru/post/654911).
- В сентябре 2024 г. из-за санкций США с российского рынка начали уходить зарубежные IT-сервисы — [3DNews, сентябрь 2024 (только заголовок)](https://3dnews.ru/1110896/izza-sanktsiy-ssha-s-rossiyskogo-rinka-uhodyat-zarubegnie-itservisi/print); [Hi-Tech Mail «Зарубежные сервисы массово перестали работать в России» (только заголовок)](https://hi-tech.mail.ru/news/114775-zarubezhnye-servisy-massovo-perestali-rabotat-v-rossii/).
- С января 2025 г. Казахстан ужесточил выпуск карт нерезидентам: теперь нужно лично прийти в отделение. Это был один из основных способов оплачивать зарубежные сервисы из России — [DTF, 2025](https://dtf.ru/howto/4415547-oplatit-zarubezhnye-servisy-iz-rossii).
- Обзор Meduza «2025-й — очередной год запретов в российском интернете» и итог года о мобильных шатдаунах, «белых списках», ограничениях WhatsApp/Telegram и MAX — [Meduza, 25.08.2025 (только заголовок)](https://meduza.io/feature/2025/08/25/2025-y-ocherednoy-god-zapretov-v-rossiyskom-internete); [Meduza, 23.12.2025 (только заголовок)](https://meduza.io/feature/2025/12/23/mobilnye-shatdauny-belye-spiski-ogranicheniya-votsapa-i-telegrama-messendzher-max-novye-blokirovki-vse-eto-sluchilos-s-runetom-v-2025-m).

#### Коммуникации
- **YouTube.** «Почти 40% россиян перешли на VPN после блокировки YouTube» — [The Moscow Times, 22.04.2025](https://ru.themoscowtimes.com/2025/04/22/pochti-40-rossiyan-pereshli-na-vpn-posle-blokirovki-youtube-a161703). В феврале 2026 г. домены YouTube, WhatsApp, BBC и других пропали из национальной системы DNS — [Медиазона, 11.02.2026](https://zona.media/news/2026/02/11/domeny).
- **Discord** заблокирован РКН в октябре 2024 г. за отказ удалить запрещённый контент. Через VPN соединение тормозит, качество стрима падает — [Habr](https://habr.com/ru/post/958952); [vc.ru](https://vc.ru/services/1560930-analogi-discord); [Спорт-Экспресс, 08.10.2024](https://www.sport-express.ru/cybersport/gadgets/reviews/10-luchshih-alternativ-discord-teamspeak-telegram-vk-zvonki-i-drugie-2261017/).
- **Revolt**, открытый клон Discord, по данным Mentoday, тоже оказался заблокирован после роста популярности — [Mentoday, 12.10.2024](https://www.mentoday.ru/life/news/12-10-2024/alternativa-discord-okazalas-zablokirovana-dlya-rossiyan-posle-togo-kak-nachala-nabirat-populyarnost-u-polzovatelej/); противоречит [Cybersport.ru (дата не установлена)](https://www.cybersport.ru/tags/other/analogi-discord-gde-obshchatsia-posle-blokirovki-samoi-populiarnoi-platformy), где сказано, что Revolt «пока не заблокирован».
- **Slack** «в 2024 году официально прекратил работу с российскими компаниями». Источник — материал конкурента, Битрикс24 — [Битрикс24, 2026](https://www.bitrix24.ru/journal/chem-zamenit-slack/).
- **Звонки WhatsApp и Telegram** РКН блокирует с августа 2025 г. — [Meduza, 14.02.2026](https://meduza.io/feature/2026/02/14/rossiyskie-vlasti-prodolzhayut-lomat-telegram-i-votsap); о частичной блокировке обоих мессенджеров — [E1, 22.10.2025](https://www.e1.ru/text/world/2025/10/22/76086298/).
- **Snapchat.** Меры по блокировке приняты 10.10.2025, официальное подтверждение пришло 04.12.2025. Обоснование — «используется для организации терактов» — [Новая газета Европа, 04.12.2025](https://novayagazeta.eu/articles/2025/12/04/roskomnadzor-zablokiroval-snapchat-v-rossii-news).
- **FaceTime.** Ограничение подтверждено 04.12.2025, перебои начались с середины октября 2025 г. — [Медиазона, 04.12.2025](https://zona.media/news/2025/12/04/facetime); [Вёрстка, 04.12.2025](https://verstka.media/roskomnadzor-zablokiroval-snapchat-i-facetime-v-rossii); [PBS](https://www.pbs.org/newshour/world/russia-restricts-facetime-in-latest-step-to-control-online-communications).
- **Roblox** заблокирован 03.12.2025 с формулировкой «пропаганда экстремизма» — [Вфокусе Mail](https://vfokuse.mail.ru/news/68986596-blokirovka-roblox-snapchat-i-facetime-v-rossii-prichiny-posledstviya-i-chto-dalshe/); [News.ru, 08.12.2025](https://news.ru/society/roblox-snapchat-facetime-zablokirovany-chto-proizoshlo-i-kak-ih-zamenit); [TechRadar](https://www.techradar.com/vpn/vpn-privacy-security/roblox-facetime-become-the-last-targets-of-russias-censorship).
- **Telegram.**
  - Частичная блокировка началась в январе 2026 г. — [The Moscow Times, 16.01.2026](https://ru.themoscowtimes.com/2026/01/16/roskomnadzor-nachal-chastichno-blokirovat-telegram-a184705).
  - 10.02.2026 РКН подтвердил «усиление блокировки» и сослался на несоблюдение закона и незащищённость персональных данных. Сбои шли с 09.02.2026 — [Медиазона, 10.02.2026](https://zona.media/news/2026/02/10/telega); [Новая газета Европа, 10.02.2026](https://novayagazeta.eu/articles/2026/02/10/rbk-roskomnadzor-nachal-zamedliat-telegram-v-rossii-news).
  - СМИ писали о полной блокировке с 01.04.2026, РКН дату не подтвердил — [Hi-Tech Mail](https://hi-tech.mail.ru/news/142805-smi-telegram-zablokiruyut-v-rossii-1-aprelya/); [РИАМО](https://riamo.ru/articles/aktsenty/ostavit-nelzja-blokirovat-chto-budet-s-telegram-v-rossii-i-kogda-eto-proizojdet/).
- **WhatsApp** заявил, что в России попытались полностью заблокировать мессенджер — [Meduza, 12.02.2026](https://meduza.io/news/2026/02/12/eto-shag-nazad-kotoryy-mozhet-privesti-tolko-k-snizheniyu-bezopasnosti-lyudey-whatsapp-zayavil-chto-v-rossii-popytalis-polnostyu-zablokirovat-messendzher).

#### Продуктивность и совместная работа
- **Notion** перестал обслуживать российских пользователей с 09.09.2024, сославшись на ограничения правительства США. Выгрузить данные можно было до 08.09.2024, после этого рабочие пространства удалялись — [Habr, 2024](https://habr.com/ru/articles/841864); [Infostart, август 2024](https://infostart.ru/journal/news/biznes/notion-ukhodit-iz-rossii-dostup-zakroyut-9-sentyabrya_2176253/); [Прайм, 26.08.2024](https://1prime.ru/20240826/sanktsii-851137530.html); [iChip](https://ichip.ru/novosti/notion-prekratit-rabotat-v-rossii-u-udalit-dannye-vseh-polzovatelej-882832).
- **Microsoft 365.** По данным Infostart, 02.09.2024 перестали действовать корпоративные облачные подписки Microsoft Office 365 — [Infostart, 2024](https://infostart.ru/journal/news/biznes/notion-ukhodit-iz-rossii-dostup-zakroyut-9-sentyabrya_2176253/).
- **Miro.** Источники противоречат друг другу:
  - Битрикс24 пишет, что Miro закрыла доступ россиянам и что «с 15 октября» ограничения распространились на бесплатные аккаунты. Год в сводке не указан — [Битрикс24](https://www.bitrix24.ru/journal/miro-zakryla-dostup-rossiyanam/).
  - Meduza предупреждала о рисках для пользователей Notion и Miro в сентябре 2024 г. — [Meduza, 01.09.2024](https://meduza.io/feature/2024/09/01/v-sentyabre-zhizn-rossiyskih-polzovateley-notion-i-miro-stanet-esche-slozhnee-vplot-do-riska-poteryat-vse-dannye).
  - На Habr стоит пометка «UPD: miro больше не ограничивает доступ» — [Habr, 2024](https://habr.com/en/articles/836832).
  - Опрос «МТС Линк» в июле 2025 г. описывает ситуацию «спустя год после ухода Miro» — [ComNews, 12.08.2025](https://www.comnews.ru/content/240642/2025-08-12/2025-w33/1008/spros-rossiyskie-onlayn-doski-vyrastet-k-koncu-2025-g).
- **Atlassian (Jira/Confluence)** есть в списке ушедших в 2022 г. — [Habr, 2022](https://habr.com/ru/post/654911). Тема замены обсуждается до сих пор — [Т-Бизнес секреты «Жизнь после Jira» (только заголовок)](https://secrets.tbank.ru/blogi-kompanij/zhizn-posle-jira/).

#### Дизайн и креатив
- **Canva** недоступна жителям России с 1 июня (2022) — [Kokoc](https://kokoc.com/blog/analogi-canva/); [Лайфхакер](https://lifehacker.ru/analogi-canva/).
- **Adobe** с 2022 г. не продаёт подписки в РФ. Stripe отклоняет карты с российским BIN, при этом сами программы на территории РФ работают. По одному источнику, подписки прежних клиентов аннулированы. С августа 2025 г. план «Все приложения» называется Creative Cloud Pro и стоит около $60 в месяц — [vc.ru, 2026 (статья посредника)](https://vc.ru/services/2862193-kak-oplatit-adobe-creative-cloud-iz-rossii); [DTF, 2026 (статья посредника)](https://dtf.ru/howto/5165715-kak-oplatit-adobe-creative-cloud-iz-rossii).
- **Figma.**
  - После санкций США от 12.09.2024 обсуждалось, сохранится ли доступ — [Skillbox Media](https://skillbox.ru/media/design/figma-and-us12sept/); [vc.ru «Figma – всё?», 2024](https://vc.ru/1422257-figma-vse); [форум Figma](https://forum.figma.com/t/us-sanctions-for-design-services-in-russia/85509).
  - Гайды 2025 г. предупреждают, что Figma может отключить россиян без предупреждения, а подписку Pro оплачивают через посредников — [vc.ru, 2025 (статья посредника)](https://vc.ru/services/2253824-kak-oplatit-figma-iz-rossii-v-2025).
  - По данным Quasa, Figma доступна в России «для некоммерческих аккаунтов» — [Quasa](https://quasa.io/ru/media/analogi-canva-dlya-raboty-s-izobrazheniyami).
  - VGTimes сообщал о массовых жалобах на недоступность. Дата не установлена — [VGTimes](https://vgtimes.ru/tech-and-hardware/157066-figma-pohozhe-zablokirovali-v-rossii-polzovateli-massovo-zhaluyutsya-na-nedostupnost-servisa.html).

#### Маркетинг и email
- **Mailchimp**, входящий в Intuit, сообщил российским клиентам, что больше не сможет их обслуживать (устарело: 2022) — [vc.ru](https://vc.ru/1029774-mailchimp-zablokirovali-kuda-podatsya-za-rassylkami).
- **Serpstat** перестал обслуживать клиентов из РФ 02.03.2022 — [Startpack](https://startpack.ru/application/serpstat).
- Kokoc собрал подборку «Топ-100 российских сервисов для интернет-маркетинга, которые заменят уходящие или ушедшие зарубежные аналоги» — [Kokoc](https://kokoc.com/blog/top-100-rossijskih-analogov/).

#### E-commerce и конструкторы сайтов
- **Shopify** ушёл с российского рынка в 2022 г., отечественной альтернативой стал inSales — [Dealroom, профиль inSales](https://app.dealroom.co/companies/insales).
- **Ecwid.** Данных об уходе нет. Бесплатный тариф сворачивается: одному пользователю пришло уведомление, что его бесплатный план закончится 20.11.2025 — [Ecwid KB](https://support.ecwid.com/hc/en-us/articles/18247837438492-Ecwid-updates-2025).

#### Запись и формы
- Подтверждения блокировки Calendly, Typeform или SurveyMonkey не найдено. В пользовательском отзыве на CRMindex сказано, что «некоторые сервисы не принимают ответы от респондентов из РФ», без названий сервисов — [CRMindex](https://crmindex.ru/services/surveymonkey/analog).

#### Образование и языки
- **Duolingo** оштрафован на 2 млн руб. за отказ локализовать данные. После этого пошли слухи о блокировке, но РКН заявил, что решений об ограничении не получал — [Фонтанка, 26.03.2026](https://www.fontanka.ru/2026/03/26/76333062/).
- Приложение Duolingo работает, бесплатные уроки доступны. Платные функции через App Store для российского региона недоступны — [РБК Компании, 2026](https://companies.rbc.ru/news/YemvApCS6D/kak-skachat-duolingo-i-oplatit-podpisku-iz-rossii-v-2026-godu/).
- По данным статьи посредника, «с апреля 2026 года Apple отключила оплату подписок» для российских аккаунтов внутри App Store — [vc.ru, 2026 (статья посредника)](https://vc.ru/services/3012698-sposoby-oplaty-podpiski-duolingo-iz-rossii). Какой именно способ оплаты отключён, см. «Пробелы».

#### Здоровье, фитнес, медитация
- **Strava** в марте 2022 г. прекратила работу в России, пользоваться ею можно только через VPN — [Т—Ж](https://t-j.ru/rossiiskoe-prilozhenie-dlia-bega/).
- **Nike** удалил Run Club из российских каталогов Google Play и App Store в 2022 г.; «в конце 2022 года Nike ушел из России». Атрибуция по сводке поиска — [Mentoday](https://www.mentoday.ru/fitness/training/adidas-running-uhodit-iz-rossii-6-udobnyh-prilojenii-dlya-bega-na-android-i-iphone/); [Т—Ж](https://t-j.ru/short/fitness-apps/).
- **Adidas Running** удаляет аккаунты россиян, ограничения вступили в силу 31.03.2024 — [Rozetked](https://rozetked.me/news/32318-adidas-udalit-akkaunty-rossiyan-v-fitnes-prilozhenii-running); [iChip](https://ichip.ru/novosti/adidas-running-udalit-dannye-rossiyan-cherez-mesyac-860142).
- **Headspace** по-прежнему виден в российских магазинах: в App Store с ценами в рублях (по сводке поиска) и в RuStore — [RuStore](https://www.rustore.ru/catalog/app/com.getsomeheadspace.android). Статус Calm не установлен.

#### Личные финансы
- Apple Pay перестал работать с российскими картами в марте 2022 г. — [Habr, 2022](https://habr.com/ru/post/654911).
- Пример: пользователь несколько лет вёл бюджет в **YNAB**, но после 2022 г. не смог ни обновить версию, ни оплатить доступ. Статья Т—Ж о Дзен-мани, атрибуция по сводке — [Т—Ж](https://t-j.ru/apps-zen-money-2/).

#### Экономика авторов и платные сообщества
- Т—Ж выпустил материал «Friendship ended with Patreon» (только заголовок) — [Т—Ж](https://t-j.ru/friendship-ended-with-patreon/).
- Ограничивают и российскую площадку: с сентября 2025 г. Boosty запретил «иностранным агентам» публиковать посты — [Медиазона, 25.08.2025](https://zona.media/news/2025/08/25/boosty).
- Discord как площадка для сообществ заблокирован с октября 2024 г. (см. «Коммуникации»).

#### Инструменты разработчика
- Docker, GitLab, Atlassian и Microsoft есть в списке ушедших и ограничивших доступ в 2022 г. (устарело) — [Habr, 2022](https://habr.com/ru/post/654911). Прочее см. в «Пробелах».

#### ИИ-инструменты
- Данные в этой сессии собрать не удалось, см. «Пробелы».

### Inferences
- **Волны сменили характер.** До 2024 г. сервисы уходили по собственной инициативе: отключали платежи и аккаунты, соблюдая санкции. В 2024–2026 гг. блокирует уже государство, и почти исключительно **средства связи и молодёжные платформы**: Discord, Snapchat, FaceTime, Roblox, WhatsApp, Telegram. Для B2B/B2C SaaS основной барьер сейчас — **оплата**, а не блокировка. Adobe, Figma, Duolingo и Headspace технически доступны, но легально не оплачиваются.
- **Платформенный риск для соло-основателя.** Ограничение Telegram в 2026 г. делает рискованной ставку только на Telegram-бота или мини-приложение. Продукт на ботах/мини-приложениях стоит изначально строить мультиплатформенным: Telegram + MAX + веб.
- **Монетизация через App Store** для российских пользователей фактически закрыта. Платная функциональность Duolingo через App Store недоступна, а с апреля 2026 г. отключён ещё какой-то способ оплаты. Отсюда следует веб-оплата: через лицензированного платёжного агрегатора, RuStore для Android или мини-приложения.

### Gaps
- **Статус Telegram и WhatsApp на ноябрь 2026 г. не установлен.** Свежие подтверждённые данные заканчиваются февралём–мартом 2026 г. Неизвестно, введена ли полная блокировка Telegram (обсуждались «осень 2026 / выборы»).
- **Что Apple отключила «с апреля 2026»** (vc.ru) — нужно проверить. Российские карты в App Store не работали и раньше, поэтому речь, вероятно, о другом способе: оплате со счёта мобильного телефона. Это не подтверждено.
- **Не проверено в этой сессии** (фоновые знания модели, могут быть неточны):
  - Wix, Semrush, Ahrefs, HubSpot, Zoom (прекращение продаж в 2022 г.), Calendly, Typeform, Patreon (выплаты на российские карты) и OnlyFans.
  - Полный перечень сервисов, отключившихся из-за запрета США на IT-услуги с 12.09.2024. Статья 3DNews 1110896 недоступна.
- **Инструменты разработчика.** Фоновые знания, **не проверено, требует проверки**:
  - GitHub в 2022 г. ограничивал аккаунты подсанкционных организаций, в целом продолжает работать.
  - GitLab и JetBrains прекратили продажи в РФ в 2022 г.
  - Docker Hub в 2024 г. ограничивал доступ из РФ.
  - AWS, Azure и GCP не принимают новых российских клиентов.
  - Российские замены: GitVerse (Сбер), SourceCraft (Яндекс), GitFlic; облака Yandex Cloud, VK Cloud, Selectel, Cloud.ru, Timeweb Cloud; IDE GigaIDE и OpenIDE.
- **ИИ-инструменты.** Фоновые знания, **не проверено**:
  - ChatGPT/OpenAI, Claude и Gemini официально недоступны из РФ.
  - Подписки ChatGPT Plus и Midjourney не оплачиваются российскими картами.
  - Российские замены: GigaChat (Сбер), YandexGPT/«Алиса AI», «Шедеврум», Kandinsky.
  - Массово распространены Telegram-боты, перепродающие доступ к зарубежным моделям. **Это исключённая модель**, учитывать её только как сигнал спроса.
  - MAU GigaChat и Алисы и доля россиян, пользующихся ChatGPT через VPN, в этой сессии не найдены.

---

## 2. Кто заменяет ушедшие сервисы в каждой категории: лидеры, трекшн, цены, жалобы

### Takeaway
В B2B-коммуникациях и совместной работе замены зрелые и многочисленные. Российские системы видеосвязи держат около 90% корпоративного рынка. Таск-трекеров и онлайн-досок десятки, многие с бесплатными тарифами на 10–15 человек. Сайты почти целиком закрывает Tilda, онлайн-запись в сфере услуг — YCLIENTS (VK) и DIKIDI (Яндекс).

Слабые места другие:
- **Качество для конечного пользователя.** У аналогов Notion нет нативных и офлайн-клиентов, в Pixso нет переменных, у Supa урезан бесплатный тариф.
- **Зависимость от китайских продуктов** (Buildin, Pixso).
- **Потребительские ниши, где замены мелкие или отсутствуют:** медитация, домашние тренировки, бюджетирование в стиле YNAB, сервисы встреч в духе Calendly для экспертов.

Ценовые ориентиры: продуктивность — 150–600 ₽ за пользователя в месяц, дизайн — 400–990 ₽ в месяц, email — от 950 ₽ в месяц, площадки для авторов — около 10% комиссии.

### Cited Findings

#### Рынок в целом
- **iKS-Consulting.** Облачный рынок в 2025 г. — 416,5 млрд руб. (+32,8%), из них SaaS — 190,7 млрд, IaaS — 183,1 млрд, PaaS — 42,7 млрд. Прогноз на 2030 г. — 1,2 трлн руб. при среднегодовом росте 24,4% — [ComNews, 12.11.2025](https://www.comnews.ru/content/242275/2025-11-12/2025-w46/1008/obem-rossiyskogo-oblachnogo-rynka-k-2030-g-pereydet-za-1-trln-rub); [ComNews, 12.01.2026](https://www.comnews.ru/content/243065/2026-01-12/2026-w03/1008/rynok-oblachnykh-servisov-2026-g-prodolzhit-rost).
- **SaaS Rating.** Рынок B2B-сервисов по подписке в 2025 г. — около 91,1 млрд руб. Рост замедлился до 24% против 60% в 2024 г.; методика не сопоставима с iKS — [ComNews «SaaS-рынок достиг зрелости», 24.04.2026](https://www.comnews.ru/content/244947/2026-04-24/2026-w17/1008/saas-rynok-dostig-zrelosti). Прогноз роста SaaS на 2026 г. — 15% — [Рамблер/Финансы, 2026 (только заголовок)](https://finance.rambler.ru/business/56609606-saas-v-rossii-vyrastet-v-2026-g-na-15/).
- В первом полугодии 2026 г. выручка облачных провайдеров выросла на 13,6% год к году, до 123 млрд руб. Атрибуция по сводке поиска неточна — [Inc. Russia «Рынок облачных услуг замедлился после перехода бизнеса на отечественные решения»](https://incrussia.ru/news/rynok-oblachnyh-uslug-zamedlilsya-posle-perehoda-biznesa-na-otechestvennye-resheniya/).
- **Рейтинг no-code Nocodecircle 2025.** Tilda на 1-м месте. Суммарная выручка 65 российских no-code-платформ в 2025 г. — более 8,2 млрд руб. — [vc.ru, 2025](https://vc.ru/services/2259259-reyting-rossiyskih-no-code-platform-2025).

#### Коммуникации (замены WhatsApp, Telegram, Discord, Zoom, Slack)
- **MAX (VK, государственная поддержка), данные Mediascope:**

  | Период | Показатель | MAX | Telegram | WhatsApp | Источник |
  |---|---|---|---|---|---|
  | Середина декабря 2025 | Суточная аудитория | 46,4 млн | — | — | [Hi-Tech Mail](https://hi-tech.mail.ru/news/139928-ezhednevnaya-auditoriya-max-v-seredine-dekabrya-sostavila-464-mln-polzovatelej/) |
  | Апрель 2026 | Суточная аудитория | ≈68 млн (впервые 1-е место) | 50,3 млн | 29,8 млн | [Ведомости, 19.05.2026](https://vedomosti.ru/media/articles/2026/05/19/1198292-max-v-aprele-stal) |
  | Апрель 2026 | Месячная аудитория | 85,4 млн | 88 млн (ещё лидер) | — | там же |
  | Июнь 2026 | Месячная аудитория | 86,3 млн (впервые выше Telegram) | 75,7 млн | — | [Вёрстка](https://verstka.media/messendzher-max-vpervye-operedil-telegram-po-mesyachnoj-auditorii-v-rossii-mediascope); [Rozetked](https://rozetked.me/news/47309-mediascope-max-stal-liderom-sredi-messendzherov-v-rossii-po-mesyachnomu-ohvatu) |
  | Август 2026 | Суточная аудитория | — | 33,8 млн | — | [Эксперт](https://expert.ru/tekhnologii/kak-max-obognal-telegram) (единственный источник) |

  - В апреле 2026 г. суточная аудитория MAX выросла за месяц на 10,6%, а Telegram потерял около 30% — [vc.ru](https://vc.ru/whereiszhenya/2943404-max-oboshel-telegram-po-dnevnoy-auditorii).
  - Сам MAX в начале мая заявлял 85 млн ежедневных пользователей с учётом иностранцев и детей младше 12 лет — [Ведомости, 19.05.2026](https://vedomosti.ru/media/articles/2026/05/19/1198292-max-v-aprele-stal).
  - 16.07.2026 MAX удалили из Google Play — [Rozetked](https://rozetked.me/news/47309-mediascope-max-stal-liderom-sredi-messendzherov-v-rossii-po-mesyachnomu-ohvatu).
  - «Мессенджер MAX разросся каналами, но три четверти из них государственные и почти без активности» — [3DNews, июль 2026 (только заголовок)](https://3dnews.ru/1133879/messendger-max-razrossya-kanalami-no-tri-chetverti-iz-nih-gosudarstvennie-i-pochti-bez-aktivnosti).
- **Корпоративные мессенджеры.**
  - Доля компаний, которые пользуются только общедоступными мессенджерами, снизилась с 52% в 2022 г. до 42% в 2025 г. — [ComNews, рейтинг корпоративных мессенджеров, 06.11.2025](https://www.comnews.ru/content/242163/2025-11-06/2025-w45/1180/reyting-rossiyskikh-korporativnykh-messendzherov-noyabr-2025).
  - «Пачка» — российский корпоративный мессенджер с 9-летней историей. Среди клиентов называет Pravo Tech, МФТИ, Intourist, «Умназию», «Сноб» — [vc.ru/pachca](https://vc.ru/pachca). Выручка не найдена.
  - Кейс «Переезд из Discord за 3 дня» (только заголовок) — [Т-Бизнес секреты](https://secrets.tbank.ru/blogi-kompanij/pereezd-iz-discord-za-3-dnya/).
- **Видеосвязь.** Опрос апреля–мая 2025 г., более 1,7 тыс. респондентов, в основном собственники и руководители компаний от 1 тыс. сотрудников:

  | Сервис | Доля ответов |
  |---|---|
  | IVA MCU | 33% |
  | TrueConf | 21% |
  | VK Звонки | 16% |
  | Яндекс Телемост | 9% |
  | Dion | 5% |
  | МТС Линк | 5% |
  | Зарубежные (Zoom, Skype, Teams, Webex, Meet) | 7% |

  - Российские сервисы видеосвязи заняли около 90% корпоративного рынка (Forbes со ссылкой на TelecomDaily) — [3DNews (заголовок «…более 90% корпоративного рынка»)](https://3dnews.ru/1125152/otechestvennie-servisi-videosvyazi-zahvatili-bolee-90-korporativnogo-rinka-v-rossii/print); [Anti-Malware, обзор 2025](https://www.anti-malware.ru/analytics/Market_Analysis/Russian-video-conferencing-2025).
  - Для сравнения, в 2024 г. по другой методике Zoom набирал 68%, Skype — 44%, Телемост — 19%. Атрибуция по сводке — [ДП, 26.06.2024](https://www.dp.ru/a/2024/06/26/rabochij-sozvon-na-rinke-videokonferencsvjazi).
  - «Импортозамещение на рынке ВКС ещё не завершилось» — [ComNews, 01.07.2025 (только заголовок)](https://www.comnews.ru/content/239928/2025-07-01/2025-w27/1008/importozameschenie-rynke-vks-esche-ne-zavershilos).
  - Рынок цифрового рабочего места: 94 млрд руб. в 2023 г., ожидалось 109 млрд в 2024 г. и 128 млрд в 2025 г. — [Inc. Russia «Кто заменит Microsoft Teams»](https://incrussia.ru/news/kto-zamenit-microsoft-teams-kak-v-rossii-delyat-rynok-tsifrovogo-rabochego-mesta/).
- **Замены Discord.** Названы TeamSpeak (сервер создать сложнее, чем в Discord), Mumble (бесплатный, низкий пинг), Revolt (ближе всего по функциям, но нестабилен из-за наплыва пользователей и, по одному источнику, заблокирован), Guilded (требует аккаунт Roblox, который тоже заблокирован), Telegram (для текстовых сообществ и ботов), Lenza, Volt.im, VK Звонки — [Cybersport.ru](https://www.cybersport.ru/tags/other/analogi-discord-gde-obshchatsia-posle-blokirovki-samoi-populiarnoi-platformy); [Habr](https://habr.com/ru/post/958952); [Т—Ж](https://t-j.ru/list/discord-alternatives/); [Tproger](https://tproger.ru/articles/proshhaj--discord--gde-teper-obshhatsya-gejmeram-i-ne-tolko).

#### Продуктивность и совместная работа (замены Notion, Jira/Trello/Asana, Miro, Microsoft 365)
- **Замены Notion:**

  | Сервис | Происхождение | Цена | Плюсы | Жалобы |
  |---|---|---|---|---|
  | **Buildin.AI** | Китай, основан в 2023 г. | Бесплатный тариф; платный от $6,5 за пользователя | «Идентичен Notion», импорт из Notion по API сохраняет структуру и ссылки | Только веб-версия без PWA: «исключает использование контекстного меню и нормальную офлайн работу» |
  | **Yonote** | Россия | Бесплатно для групп до 5 человек; платные тарифы от 149 ₽ в месяц | Облако или установка на свои серверы | Нет отдельного приложения, «функционала баз не хватает» |

  - Источники по Buildin — [Т—Ж](https://t-j.ru/apps-buildin/); [Startpack, сравнение Notion и Buildin](https://startpack.ru/compare/notion-vs-buildin-ai); [Habr](https://habr.com/en/articles/845382).
  - Источники по Yonote — [Лайфхакер](https://lifehacker.ru/notion-alternatives/); [TechInsider](https://www.techinsider.ru/news/news-1657297-ekspert-goldberg-perechislil-alternativy-notion-v-rossii/).
  - Экосистемы тоже заходят в нишу: «Заметки Mail» (VK) добавили перенос данных из Notion — [РБК, сентябрь 2024](https://www.rbc.ru/life/news/66d834029a79477afc8a54e7).
  - Подборка Startpack «Аналоги Notion в России в 2026 году: 12 сервисов» — [Startpack, 13.07.2026](https://startpack.ru/article/20260713-analogi-notion-v-rossii-v-2026-godu-12-servisov-dlya-zametok-bazy-znaniy-i-zadach).
  - Число пользователей Buildin и Yonote не найдено.
- **Таск-трекеры:**

  | Сервис | Цена | Бесплатно | Особенности и жалобы | Источник |
  |---|---|---|---|---|
  | **YouGile** | Около 569 ₽ за пользователя в месяц | Без ограничений для команд до 10 человек | «Специфичный интерфейс», только облако | [vc.ru, 2025](https://vc.ru/services/2246991-task-menedzhery-v-rossii-kaiten-yougile-yandeks-treker-i-weeek) |
  | **Kaiten** | — | Тариф «Старт» для команд до 15 человек | Ориентирован на Agile | [Т-Бизнес секреты](https://secrets.tbank.ru/novosti/kaiten-dlya-msp/) |
  | **Weeek** | Личный тариф 559 ₽ в месяц при оплате за год | — | Таск-трекер, CRM и база знаний в одном | [Т—Ж, 2026](https://t-j.ru/top-10-sistem-upravleniia-proektami/) |

  - В обзорах также фигурирует **Яндекс Трекер** — [vc.ru](https://vc.ru/services/2246991-task-menedzhery-v-rossii-kaiten-yougile-yandeks-treker-i-weeek); [Workspace, топ-10 на 2026 г.](https://workspace.ru/blog/top-10-rossiyskih-task-menedzherov-v-2026-godu/).
  - Выручка и число пользователей Kaiten, YouGile и Weeek в выдаче не найдены.
- **Онлайн-доски.**
  - По опросу «МТС Линк» (июль 2025 г.), на отечественные доски перешла треть компаний. В первом полугодии 2025 г. на платформе создали на 55% больше досок, чем годом ранее (данные вендора) — [ComNews, 12.08.2025](https://www.comnews.ru/content/240642/2025-08-12/2025-w33/1008/spros-rossiyskie-onlayn-doski-vyrastet-k-koncu-2025-g).
  - Альтернативы: Holst; Flip (есть кнопка «Импорт из Miro»); «МТС Линк Доски» (перенос в один клик); Pruffme; sBoard; Microboard; VK Доски (бета, импорт ещё в разработке) — [Callibri](https://callibri.ru/blog/analogi-miro-v-rossii); [DTF](https://dtf.ru/howto/4548601-analogi-miro-luchshie-onlajn-doski). Ещё один вариант — Unidraw — [Т-Бизнес секреты (только заголовок)](https://secrets.tbank.ru/novosti/unidraw/).

#### Дизайн и креатив (замены Canva, Figma, Adobe)
- **Pixso.** Китайский редактор (Шэньчжэнь), по логике похож на Figma. Есть русский интерфейс и импорт .fig, но нет переменных — [Unisender](https://www.unisender.com/ru/blog/obzor-servisa-pixso/).
- **Supa.**
  - Русский язык, бесплатный тариф, платный — от 990 ₽ в месяц (590 ₽ при оплате за год). Несколько тысяч шаблонов, стоковые фото, иконки, музыка — [Лайфхакер](https://lifehacker.ru/analogi-canva/).
  - Минусы: в бесплатном тарифе ограничен монтаж видео, инструментов для редактирования изображений мало — [Timeweb](https://timeweb.com/ru/community/articles/10-luchshih-analogov-canva-dlya-raboty-s-graficheskim-dizaynom).
- **Flyvi.**
  - «Интерфейс почти такой же, как у Canva», разработан в РФ, оплата российской картой. Есть шаблоны для маркетплейсов и планировщик публикаций — [Habr, 2022 (устарело)](https://habr.com/ru/post/676956/); [Unisender, гайд по Flyvi](https://www.unisender.com/ru/blog/gajd-po-graficheskomu-redaktoru-flyvi/).
  - Читатель Лайфхакера считает цену около 400 ₽ оправданной (единичное мнение) — [Лайфхакер](https://lifehacker.ru/analogi-canva/).
- **Wilda.** Для документов и полиграфии: буклеты, визитки, КП — [Tproger](https://tproger.ru/articles/analogi-canva-top-10-alternativ-dlya-dizajna-i-prezentacij).
- Подборки «чем заменить Canva в 2025» выходят до сих пор — [LPmotor, 2025](https://lpmotor.ru/articles/chem-zamenit-canva-v-rossii-2025-2512).

#### Маркетинг и email (замены Mailchimp, Semrush/Serpstat, Zapier/Make)
- **Unisender.** Серверы и бизнес в РФ. По словам компании, после 24.02.2022 загрузка новых клиентских баз выросла на 40% (данные вендора, устарело: 2022) — [vc.ru, 2022](https://vc.ru/382809-unisender-na-rossiiskom-rynke).
- **DashaMail.** Позиционируется как полностью российский и соответствующий 152-ФЗ. По одной из подборок, стоит от 950 ₽ в месяц — [Т—Ж, подборка email-сервисов](https://t-j.ru/list/email-services).
- «Российские компании массово переходят на отечественные сервисы email-рассылок» (только заголовок) — [Oborot.ru](https://oborot.ru/news/rossijskie-kompanii-massovo-perehodyat-na-otechestvennye-servisy-email-rassylok-i154772.html).
- **Albato.**
  - Позиционируется как бюджетная альтернатива Zapier и Make. Каталог — около 1000 приложений против тысяч у Zapier и Make — [Albato blog, 2025](https://www.albato.com/blog/publications/zapier-vs-make-vs-albato-comparison).
  - Toolfox указывает 500+ интеграций у Albato и 3000+ у Make; Make стоит от $10,59 в месяц — [Toolfox](https://toolfox.ru/services/s/make-integromat/alternatives).
  - Habr называет Albato «отечественным аналогом Zapier», основанным в 2015 г. — [Habr](https://habr.com/ru/post/978986). Это расходится с данными о базе на Кипре (англоязычные профили в выдаче).
  - Выручка не найдена.
- **SEO.**
  - Вместо Serpstat предлагают Rush Analytics и SeoPult — [Startpack](https://startpack.ru/application/serpstat).
  - Keys.so — от 990 ₽ в месяц (цена может быть устаревшей). Альтернативы: Topvisor, SpyWords — [CRMindex](https://crmindex.ru/services/keys/analog); [Toolfox](https://toolfox.ru/services/s/keys-so/alternatives).

#### E-commerce и конструкторы сайтов (замены Wix, Shopify, Ecwid)
- **Tilda.** Выручка АО «Тильда Паблишинг» в 2025 г. — около 3,47 млрд руб. (+45%), в 2024 г. — около 2,4 млрд. Источник единственный, нужно сверить с отчётностью — [Techora, 09.07.2026](https://techora.ru/news/tilda-publishing-narastila-chistuyu-pribyl-do-2026-07-09).
- Сигналы недовольства Tilda (только заголовки):
  - «Я делал сайты на Tilda четыре года. После одного звонка я больше не открыл её ни разу» — [vc.ru, 2026](https://vc.ru/marketing/2954100-perekhod-s-tilda-na-low-code).
  - Кейс переезда RetailCRM с Tilda на Taptop — [Sostav](https://www.sostav.ru/blogs/287569/93950).
- **inSales.**
  - GMV продавцов на платформе в 2025 г. — 407 млрд руб. (+96%), число заказов +131% (данные вендора; это не выручка компании) — [Sostav, блог inSales](https://www.sostav.ru/blogs/277495/78206).
  - Обсуждение на Oborot: выручка inSales выросла на 115% за год за счёт продавцов маркетплейсов и служб доставки (только заголовок) — [Oborot.ru, апрель 2025](https://oborot.ru/forum/za-schet-chego-vyruchka-insales-vyrosla-na-115-za-god-sellery-marketplejsov-i-sluzhby-dostavki-zaplatili-kompanii-707-millionov-obsuzhdenie-i52490.html).
- В мае 2025 г. Яндекс объявил о запуске конструктора интернет-магазинов, который сам называет аналогом Shopify. Атрибуция по сводке — [Oborot.ru, тег «конструктор интернет-магазинов»](https://oborot.ru/tag/konstruktor-internet-magazinov/).

#### Запись и формы (замены Calendly, Typeform, SurveyMonkey)
- **YCLIENTS (VK):**
  - По одному источнику, достался VK в конце 2023 г.
  - Выручка в 2025 г. — около 2 млрд руб., чистая прибыль — 302 млн руб. (сравнение с DIKIDI) — [Techora, 08.09.2026](https://techora.ru/news/yandeks-servis-dikidi-pokazal-rentabelnost-45-2026-09-08).
  - GMV в 2025 г. — 442 млрд руб.; выручка клиентов в 2024 г. — 357 млрд руб. — [Т-Бизнес секреты «Как развивалась платформа YCLIENTS»](https://secrets.tbank.ru/trendy/kak-razvivalas-platforma-yclients/).
  - Сама компания пишет о «свыше 16 млн записей в месяц» — [Хабр Карьера](https://career.habr.com/companies/yclients); в другом источнике — 22 млн записей в месяц и 55 тыс. компаний-клиентов.
  - Цифры расходятся и требуют проверки по отчётности VK.
- **DIKIDI (Яндекс):**
  - О покупке Яндекс объявил 23.12.2025, сервис интегрирует «Алису AI» — [ДП, 23.12.2025](https://www.dp.ru/a/2025/12/23/jandeks-kupit-platformu-dikidi); [Sostav](https://www.sostav.ru/publication/yandeks-pokupaet-platformu-dikidi-dlya-obsluzhivaniya-malogo-i-srednego-biznesa-80619.html); [Т—Ж](https://t-j.ru/news/yandex-dikidi/).
  - Выручка в 2025 г. — 632 млн руб., рентабельность по чистой прибыли — около 45% — [Techora, 08.09.2026](https://techora.ru/news/yandeks-servis-dikidi-pokazal-rentabelnost-45-2026-09-08).
  - Около 120 тыс. бизнес-пользователей и более 150 млн записей в год (по сводке поиска).
- **Аналоги Calendly** почти не встречаются. Каталоги называют Cal.com, YCLIENTS (запись клиентов в сфере услуг, 7 дней пробного периода) и ПланФикс — [Toolfox](https://toolfox.ru/services/s/calendly/alternatives); а также «Планёрку» (запись и встречи с интеграцией календарей и ВКС, есть бесплатный тариф) — [Startpack](https://startpack.ru/application/planerka).
- **Формы и опросы.** Каталоги называют Яндекс Формы, Google Forms и «Анкетолог» («полностью русскоязычный сервис») — [Toolfox](https://toolfox.ru/services/s/surveymonkey/alternatives); [Startpack](https://startpack.ru/application/yandex-forms/alternatives). Подборка «Чем заменить Google Forms: 8 альтернатив» (только заголовок) — [vc.ru](https://vc.ru/services/2182024-alternativy-google-forms-8-servisov).
- Бьюти-сфера в 2025 г.: «выручка растёт, проблемы с управлением остаются» (только заголовок) — [РБК Радио, 12.09.2025](https://www.rbc.ru/radio/12/09/2025/68c3ee5b9a79476b5ae20a1b).

#### Образование и языки
- **Smart Ranking, итоги 2025 г.:**
  - 100 ведущих компаний онлайн-образования заработали около 154 млрд руб. (+12%). Основательница агентства Дарья Рыжкова считает, что рынок «фактически перешёл к стагнации».
  - Лидеры: «Синергия», Skillbox, Skyeng.
  - Рост дополнительного профессионального образования (ДПО) замедлился до 6% против 16,1% годом ранее.
  - Детский сегмент во 2-м квартале 2025 г. вырос на 22%, до 14 млрд руб.
  - Источники — [Skillbox Media, 2026](https://skillbox.ru/media/edtech/v-2025-godu-rossiyskiy-edtech-vyros-vsego-na-12/); [Sostav](https://www.sostav.ru/publication/tempy-rosta-vyruchki-krupnejshikh-rossijskikh-edtech-kompanij-prodolzhayut-zamedlyatsya-81431.html); [Коммерсантъ](https://www.kommersant.ru/doc/8860952).
- «Российский рынок онлайн-образования в первом квартале составил 35,5 млрд рублей». Только заголовок, год не установлен — [Sostav](https://www.sostav.ru/publication/rossijskij-rynok-onlajn-obrazovaniya-v-pervom-kvartale-sostavil-35-5-mlrd-rublej-83635.html).
- Конкретных сравнений приложений-аналогов Duolingo в выдаче не найдено (см. «Пробелы»).

#### Здоровье, фитнес, медитация
- **Медитация (замены Headspace и Calm):**
  - «Мо: Медитация и Сон» — «качественный, адаптированный под российские реалии клон Meditopia и Headspace». Отзыв в App Store: «как calm или headspace, только на русском» — [vc.ru, 2026](https://vc.ru/services/3034866-obzor-prilozheniy-dlya-sna-i-meditacii); [App Store](https://apps.apple.com/ru/app/%D0%BC%D0%BE-%D0%BC%D0%B5%D0%B4%D0%B8%D1%82%D0%B0%D1%86%D0%B8%D1%8F-%D0%B8-%D1%81%D0%BE%D0%BD/id1460803131).
  - Zazn — бесплатный упрощённый аналог Headspace — [Лайфхакер](https://lifehacker.ru/zazn-meditation-ios/).
  - «Серотонин» — бесплатный, на русском (по отзыву в App Store, атрибуция по сводке).
  - Softly — нет кнопки SOS, как в Headspace — [Teletype](https://teletype.in/@elenalomova/top-free-wellness-apps).
  - Цифр по аудитории нет.
- **Бег и тренировки.**
  - Т—Ж описывает российский сервис для бега, по функциям похожий на Strava — [Т—Ж](https://t-j.ru/rossiiskoe-prilozhenie-dlia-bega/).
  - Отечественного аналога для домашних и силовых тренировок в источниках нет. Читатель в комментарии пишет, что без него «ситуация грустная», другой упоминает, что «ВКонтакте что-то своё запилил». Атрибуция комментариев по сводке — [Т—Ж](https://t-j.ru/short/fitness-apps/).

#### Личные финансы (замены YNAB, Apple Pay)
- **Дзен-мани.**
  - Интеграций 3300+ по одному каталогу и 200+ по другому (данные расходятся). Цена от 99 ₽ или от 149 ₽ в месяц, по разным источникам.
  - Частный разработчик сделал браузерное расширение для бюджета «по принципу YNAB» поверх Дзен-мани.
  - Источники — [Toolfox](https://toolfox.ru/services/s/zenmoney/alternatives); [Startpack, сравнение Дзен-мани и CoinKeeper](https://startpack.ru/compare/zenmoney-vs-coinkeeper); [CRMindex](https://crmindex.ru/services/zenmoney/analog).
- **CoinKeeper.**
  - Приложения для iOS и Android: ИИ-ассистент, ввод операций голосом и по фото, семейный учёт, распознавание SMS любых банков.
  - Веб-версии нет, расширенные функции — в тарифе Platinum. Цена от 149 ₽ или от 199 ₽ в месяц, по разным источникам.
  - Источники — [Toolfox](https://toolfox.ru/services/s/coinkeeper/alternatives); [Startpack](https://startpack.ru/compare/zenmoney-vs-coinkeeper).
- Число пользователей обоих сервисов в 2025 г. не найдено.

#### Экономика авторов и платные сообщества (замены Patreon, OnlyFans, Discord-сообществ)
- **Boosty:**
  - Владелец — CEBC B.V. (Амстердам), зарегистрировано более 650 тыс. авторов — [vc.ru, 2025 (статья посредника)](https://vc.ru/services/2131143-oplata-podpiski-boosty-v-rossii-2025).
  - Для страниц, созданных после 04.08.2025, комиссия — 10% плюс комиссии платёжных систем — [Getly (сторонний агрегатор)](https://www.getly.store/ru/alternatives/boosty).
  - Нет ленты рекомендаций, органического охвата и внутреннего продвижения: авторы приходят со своей аудиторией — [Octo Browser blog, 2026](https://blog.octobrowser.net/ru/kak-zarabotat-na-boosty).
  - С сентября 2025 г. иностранным агентам запрещено публиковать посты — [Медиазона, 25.08.2025](https://zona.media/news/2025/08/25/boosty).
  - Выручка и сумма выплат авторам не найдены.
- **VK Donut** против Boosty: сравнение плюсов и минусов (только заголовок) — [vc.ru](https://vc.ru/promonetization/2217161-sravnenie-vk-donut-i-boosty).
- **Tribute (платные подписки в Telegram), исследования самой платформы:**
  - Выборка — более 50 тыс. авторов, январь–август 2025 г.
  - 74% блогеров зарабатывают на платных подписках на закрытые каналы.
  - Число пользователей с платными подписками выросло вдвое, с 280 тыс. до 586 тыс.
  - Средний месячный доход авторов в 2025 г. вырос на 44%.
  - Источники — [Sostav](https://www.sostav.ru/publication/dokhod-blogerov-v-telegram-vyros-na-44-v-2025-godu-78514.html); [PPC.world](https://ppc.world/news/dohody-blogerov-ot-monetizacii-v-telegram-vyrosli-za-god-na-44/).
  - С апреля по сентябрь 2025 г. число авторов с аудиторией до 500 подписчиков, которые зарабатывают на монетизации, выросло на 46%, с 5,7 тыс. до 8,3 тыс. Микроблогеры заработали более 990 млн руб. — [Sostav](https://www.sostav.ru/publication/mikroblogery-zarabotali-bolee-990-mln-rublej-na-platnykh-telegram-kanalakh-79517.html).
  - Совокупный доход авторов от рекламы за девять месяцев 2025 г. снизился почти на 20%, со 144 до 119 млн руб. Это данные по авторам Tribute, атрибуция по сводке — [Sostav «Трансформация рынка Telegram: от рекламы к подписной экономике»](https://www.sostav.ru/publication/transformatsiya-rynka-telegram-ot-reklamy-k-podpisnoj-ekonomike-79642.html).
- **Объём рынка монетизации в Telegram.** По оценке Ассоциации блогеров и агентств (АБА), рынок в 2025 г. превысил 8,5 млрд руб. в год. Атрибуция по сводке — [Т-Бизнес секреты](https://secrets.tbank.ru/novosti/dokhody-blogerov-v-telegrame/). Противоречие: заголовок Финуслуг звучит как «Блогеры заработали в Telegram 8,5 млрд за полгода» — [Финуслуги](https://finuslugi.ru/navigator/news/novosti_bankovskoj_otrasli/blogery_zarabotali_v_telegram_85_mlrd_za_polgoda).

### Inferences
- **В B2B закрыто то, что важно корпоративным закупкам:** установка на свои серверы, 152-ФЗ, реестр ПО, ВКС. Отстаёт «потребительский лоск»: нативные и офлайн-клиенты (Buildin, Yonote), глубина функций (Pixso без переменных), щедрость бесплатных тарифов (Supa). Конкурировать здесь проще не горизонтально, а через UX и узкий сценарий.
- **Часть пробелов заполнили китайские продукты** (Buildin, Pixso). Для пользователя это частично повторяет риск оригинала: зарубежная юрисдикция и возможные санкционные отключения. Это аргумент для продукта с хостингом в РФ, но не самостоятельное УТП.
- **Экосистемы активно скупают и встраивают нишевые продукты:** Яндекс купил DIKIDI, VK владеет YCLIENTS, «Заметки Mail» импортируют из Notion, MAX растёт за счёт административной поддержки. Где экосистема уже купила лидера, соло-основателю заходить не стоит.
- **Экономика авторов в Telegram растёт** (подписчики Tribute удвоились, доход авторов +44%), но рекламные доходы падают, а платформа с 2026 г. под ограничениями. Растущему спросу на платные подписки нужен канал, не зависящий от одной платформы.

### Gaps
- Выручка и MAU Kaiten, YouGile, Weeek, Buildin, Yonote, «Пачки», Holst, Supa, Flyvi, Albato, Boosty, Дзен-мани и CoinKeeper не найдены. Нужны СПАРК, Rusprofile и интервью основателей.
- Не исследованы Яндекс 360, Контур.Толк (доли и выручка), МойОфис, Р7-Офис, Bitrix24, Pyrus, Teamly, EvaTeam, VK WorkSpace.
- Не исследованы замены Duolingo (Skyeng, Lingualeo, Puzzle English и др.), онлайн-школы английского и их цены.
- Не исследованы российские замены Calm, MyFitnessPal (подсчёт калорий), Flo, Garmin Pay.
- Не исследованы ИИ-генераторы карточек для маркетплейсов и ИИ-дизайн-сервисы на российских моделях.
- Не исследованы российские сервисы пожертвований для стримеров (DonationAlerts и др.) и судьба Twitch и YouTube-монетизации.
- Нет отзывов из RuStore, App Store, Otzovik и irecommend: в этой сессии их не открывали.

---

## 3. Что говорит о том, что россияне продолжают пользоваться оригиналами через VPN, зарубежные карты и посредников

### Takeaway
VPN стал массовой нормой. По «Леваде», им пользуются 36% в марте 2025 г. и 39% в начале 2026 г. К маю 2026 г. VPN стоит на каждом втором смартфоне, доля платящих за VPN выросла с 15% до 48%. Вокруг этого сложилась целая индустрия гайдов «как оплатить X из России в 2025/2026»: Adobe, Figma, Duolingo, Boosty.

Сильнее всего держатся за оригиналы:
- **Креативные инструменты** (Adobe, Figma): оригинал работает, но не оплачивается.
- **Изучение языков** (Duolingo): бесплатная часть работает.
- **Связь** (Discord через VPN с тормозами; WhatsApp и Telegram).
- **Онлайн-доски:** к июлю 2025 г. на российские перешла лишь треть компаний.

Обратный эффект: российские сервисы, которые не работают под VPN, теряют пользователей.

### Cited Findings
- **«Левада-Центр».**
  - В марте 2025 г. VPN пользовались 36% россиян, на 13 п. п. больше, чем в марте 2024 г.; в 2023–2024 гг. — около четверти — [The Moscow Times, 22.04.2025](https://ru.themoscowtimes.com/2025/04/22/pochti-40-rossiyan-pereshli-na-vpn-posle-blokirovki-youtube-a161703); [Левада-Центр](https://www.levada.ru/?p=35584).
  - В начале 2026 г. — 39%. Атрибуция по сводке — [«Важные истории», 25.02.2026](https://istories.media/stories/2026/02/25/rkn-vs-vpn/).
- **Институт социального маркетинга (2025 г.):** 46% хотя бы раз пользовались VPN. Это метрика «хотя бы раз», с текущим использованием по «Леваде» её не сравнить. Атрибуция по сводке неоднозначна — [The Moscow Times (англ.), 06.08.2026](https://www.themoscowtimes.com/2026/08/06/as-kremlin-cuts-off-the-internet-vpns-become-a-way-of-life).
- **Платный VPN:** в августе 2024 г. — 15% россиян, «сейчас» — 48% (точная дата исследования в сводке не указана) — [RTVI, 2026](https://rtvi.com/news/v-kazhdom-vtorom-smartfone-rossiyane-massovo-pereshli-na-platnye-vpn/).
- **VPN на каждом втором смартфоне** в России — [The Moscow Times, 08.05.2026](https://ru.themoscowtimes.com/2026/05/08/na-kazhdom-vtorom-smartfone-v-rossii-okazalsya-ustanovlen-vpn-a194880); [Mentoday, 07.05.2026](https://www.mentoday.ru/life/news/07-05-2026/vpn-est-u-kajdogo-vtorogo-rossiyanina-kak-obhody-blokirovok-stali-chastyu-povsednevnoi-jizni/); «Каждый второй россиянин активно использует VPN в повседневной жизни» (только заголовок) — [SecurityLab](https://www.securitylab.ru/news/572416.php).
- **Интерес к VPN** вырос в 33 раза за год по статистике поиска (только заголовок) — [www1.ru, 29.03.2026](https://www1.ru/news/2026/03/29/interes-k-vpn-v-rossii-vyros-v-33-raza-za-god-statistika-poiska.amp.html). Политическое измерение использования VPN — [Carnegie, май 2026 (только заголовок)](https://carnegieendowment.org/russia-eurasia/politika/2026/05/russia-vpn-usage-political).
- **Обратный эффект:** «Россияне стали реже пользоваться сервисами, не работающими под VPN» — [RTVI (только заголовок)](https://rtvi.com/news/rossiyane-stali-rezhe-polzovatsya-servisami-ne-rabotayushhimi-pod-vpn/); «Похоже, вы используете VPN» — [Новая газета Европа, 15.04.2026 (только заголовок)](https://novayagazeta.eu/amp/articles/2026/04/15/pokhozhe-vy-ispolzuete-vpn).
- **Discord.** Пользователи обходят блокировку через VPN, хотя соединение тормозит и качество стрима падает — [Habr](https://habr.com/ru/post/958952).
- **Strava** доступна через VPN — [Т—Ж](https://t-j.ru/rossiiskoe-prilozhenie-dlia-bega/).
- **Гайды посредников 2025–2026 гг. как сигнал спроса.** Бизнес-модель посредников исключена; учитывать их только как признак спроса:
  - Adobe — [vc.ru, 2026](https://vc.ru/services/2862193-kak-oplatit-adobe-creative-cloud-iz-rossii); [vc.ru, 2026](https://vc.ru/services/2748219-oplata-adobe-creative-cloud-iz-rossii); [DTF, 2026](https://dtf.ru/howto/5165715-kak-oplatit-adobe-creative-cloud-iz-rossii).
  - Figma — [vc.ru, 2025](https://vc.ru/services/2253824-kak-oplatit-figma-iz-rossii-v-2025).
  - Duolingo — [vc.ru, 2026](https://vc.ru/services/3012698-sposoby-oplaty-podpiski-duolingo-iz-rossii); [DTF, 2026](https://dtf.ru/howto/5000836-kak-oplatit-duolingo-iz-rossii); [Pikabu, 2026](https://pikabu.ru/story/_14196137).
  - Boosty — [vc.ru, 2025](https://vc.ru/services/2131143-oplata-podpiski-boosty-v-rossii-2025).
  - Способы, которые описывают гайды: виртуальные зарубежные карты, посредники с оплатой в рублях, подарочные карты App Store и Google Play других стран, веб-версии сервисов.
- **Figma** используют через некоммерческие аккаунты — [Quasa](https://quasa.io/ru/media/analogi-canva-dlya-raboty-s-izobrazheniyami).
- **Онлайн-доски.** Через год после ухода Miro на отечественные доски перешла только треть компаний (опрос «МТС Линк», июль 2025 г.) — [ComNews, 12.08.2025](https://www.comnews.ru/content/240642/2025-08-12/2025-w33/1008/spros-rossiyskie-onlayn-doski-vyrastet-k-koncu-2025-g).
- **Видеосвязь.** Даже среди крупных компаний зарубежные ВКС в 2025 г. набирают 7% ответов — [Anti-Malware](https://www.anti-malware.ru/analytics/Market_Analysis/Russian-video-conferencing-2025).
- **Корпоративная связь.** 42% компаний в 2025 г. общаются только через общедоступные мессенджеры (Telegram, WhatsApp и др.) — [ComNews, 06.11.2025](https://www.comnews.ru/content/242163/2025-11-06/2025-w45/1180/reyting-rossiyskikh-korporativnykh-messendzherov-noyabr-2025).
- **Обходные пути дорожают:** с января 2025 г. казахстанские карты нерезидентам выдают только при личном визите — [DTF](https://dtf.ru/howto/4415547-oplatit-zarubezhnye-servisy-iz-rossii).

### Inferences
- **Российская замена соревнуется не с пустотой, а с бесплатной версией оригинала через VPN.** Это видно по Figma для некоммерческих аккаунтов, бесплатной части Duolingo, программам Adobe и Strava. Переключение массово происходит только там, где оригинал удаляет данные (Notion, Adidas Running) или жёстко заблокирован (Discord, FaceTime, WhatsApp). Но и тогда выигрывают в основном экосистемы: в связи это MAX и VK Звонки.
- **Самый сильный сигнал спроса там, где оригинал не оплачивается, а российский аналог заметно хуже:** профессиональный дизайн (Adobe, Figma) и платные функции Duolingo. Но сделать замену Adobe или Figma соло-основателю нереально. Реалистичнее узкие сценарии, где «Canva + ИИ» решает конкретную задачу.
- **Высокое проникновение VPN делает российские сервисы, блокирующие VPN-трафик, менее удобными** (заголовок RTVI). Если продукт нормально работает при включённом VPN (без антифрод-блокировок по IP), это может стать мелким, но реальным конкурентным преимуществом. Вывод по заголовку, требует проверки.

### Gaps
- Нет разбивки, ради каких сервисов россияне включают VPN (YouTube, Instagram, ChatGPT, Discord и т. д.): статьи «Важных историй» и The Moscow Times не удалось открыть.
- Нет данных об использовании ChatGPT, Claude и Midjourney из РФ и о выручке ботов-перепродавцов (исключённая модель — только как сигнал).
- Не собраны обсуждения «чем заменить X» с vc.ru и Habr с конкретными жалобами: страницы не открывались. Есть только заголовки и сводки.

---

## 4. Какие ниши уже насыщены экосистемами (Яндекс, VK, Сбер, МТС, Т-Банк) или множеством клонов

### Takeaway
Насыщены:
- **Связь и ВКС:** MAX с административной поддержкой, около 90% корпоративной видеосвязи у полудюжины российских вендоров.
- **Таск-трекеры и онлайн-доски:** десятки клонов с бесплатными тарифами.
- **Конструкторы сайтов и магазинов:** Tilda с выручкой около 3,47 млрд руб., inSales с GMV 407 млрд руб., вход Яндекса.
- **Онлайн-запись в сфере услуг:** YCLIENTS у VK, DIKIDI у Яндекса.
- **Email-рассылки и интеграторы.**
- **Широкий EdTech** в стагнации.

Общие ИИ-ассистенты, вероятно, тоже насыщены экосистемами (Сбер, Яндекс), но в этой сессии это не проверено. Рост рынка SaaS в целом замедляется: 60% в 2024 г., 24% в 2025 г., прогноз 15% на 2026 г.

### Cited Findings
- **Мессенджеры.** MAX — №1 по суточной (около 68 млн, апрель 2026 г.) и месячной (86,3 млн, июнь 2026 г.) аудитории — [Ведомости](https://vedomosti.ru/media/articles/2026/05/19/1198292-max-v-aprele-stal); [Вёрстка](https://verstka.media/messendzher-max-vpervye-operedil-telegram-po-mesyachnoj-auditorii-v-rossii-mediascope).
- **Видеосвязь.** IVA, TrueConf, VK Звонки, Телемост, Dion и МТС Линк делят около 90% корпоративного рынка — [3DNews](https://3dnews.ru/1125152/otechestvennie-servisi-videosvyazi-zahvatili-bolee-90-korporativnogo-rinka-v-rossii/print); [Anti-Malware](https://www.anti-malware.ru/analytics/Market_Analysis/Russian-video-conferencing-2025).
- **Онлайн-доски.** Не меньше 8 российских продуктов (Holst, Flip, «МТС Линк Доски», Pruffme, sBoard, Microboard, VK Доски, Unidraw), включая продукты МТС и VK — [Callibri](https://callibri.ru/blog/analogi-miro-v-rossii); [Т-Бизнес секреты](https://secrets.tbank.ru/novosti/unidraw/).
- **Таск-трекеры.** Бесплатно работают для команд до 10 человек (YouGile) и до 15 человек (Kaiten «Старт»), плюс Яндекс Трекер и десятки других в подборках «топ-10 на 2026 г.» — [vc.ru](https://vc.ru/services/2246991-task-menedzhery-v-rossii-kaiten-yougile-yandeks-treker-i-weeek); [Т-Бизнес секреты](https://secrets.tbank.ru/novosti/kaiten-dlya-msp/); [Workspace](https://workspace.ru/blog/top-10-rossiyskih-task-menedzherov-v-2026-godu/).
- **Аналоги Notion.** Только в подборке Startpack на 2026 г. их 12 — [Startpack, 13.07.2026](https://startpack.ru/article/20260713-analogi-notion-v-rossii-v-2026-godu-12-servisov-dlya-zametok-bazy-znaniy-i-zadach). VK встроил импорт из Notion в «Заметки Mail» — [РБК](https://www.rbc.ru/life/news/66d834029a79477afc8a54e7).
- **Конструкторы сайтов и магазинов.**
  - Tilda: около 3,47 млрд руб. выручки, +45% — [Techora](https://techora.ru/news/tilda-publishing-narastila-chistuyu-pribyl-do-2026-07-09).
  - 65 no-code-платформ суммарно — более 8,2 млрд руб. — [vc.ru](https://vc.ru/services/2259259-reyting-rossiyskih-no-code-platform-2025).
  - inSales: GMV 407 млрд руб. — [Sostav](https://www.sostav.ru/blogs/277495/78206).
  - Яндекс запускает конструктор магазинов (май 2025 г.) — [Oborot.ru](https://oborot.ru/tag/konstruktor-internet-magazinov/).
- **Онлайн-запись в сфере услуг.** YCLIENTS (VK): около 2 млрд руб. выручки и 442 млрд руб. GMV. DIKIDI (Яндекс): 632 млн руб. выручки и около 120 тыс. бизнесов — [Techora](https://techora.ru/news/yandeks-servis-dikidi-pokazal-rentabelnost-45-2026-09-08); [Т-Бизнес секреты](https://secrets.tbank.ru/trendy/kak-razvivalas-platforma-yclients/); [ДП](https://www.dp.ru/a/2025/12/23/jandeks-kupit-platformu-dikidi).
- **Email-рассылки.** Unisender, DashaMail и многие другие. Kokoc насчитывает около 100 российских маркетинговых сервисов-замен — [Kokoc](https://kokoc.com/blog/top-100-rossijskih-analogov/); [vc.ru, топ-10 на 2026 г.](https://vc.ru/top-raiting/2332428-luchshie-servisy-dlya-email-rassylki).
- **Интеграторы.** У Albato около 1000 интеграций, позиционируется как дешёвая замена Zapier и Make — [Albato](https://www.albato.com/blog/publications/zapier-vs-make-vs-albato-comparison).
- **EdTech.** Топ-100 компаний: 154 млрд руб. (+12% номинально), «стагнация». ДПО растёт всего на 6% — [Skillbox Media](https://skillbox.ru/media/edtech/v-2025-godu-rossiyskiy-edtech-vyros-vsego-na-12/).
- **Монетизация авторов в Telegram.** Tribute работает с более чем 50 тыс. авторов. Альтернативы — Boosty (более 650 тыс. зарегистрированных авторов) и VK Donut — [Sostav](https://www.sostav.ru/publication/dokhod-blogerov-v-telegram-vyros-na-44-v-2025-godu-78514.html); [vc.ru](https://vc.ru/services/2131143-oplata-podpiski-boosty-v-rossii-2025); [vc.ru](https://vc.ru/promonetization/2217161-sravnenie-vk-donut-i-boosty).
- **Рынок в целом.** Рост SaaS замедлился до 24% в 2025 г. после 60% в 2024 г. — [ComNews, 24.04.2026](https://www.comnews.ru/content/244947/2026-04-24/2026-w17/1008/saas-rynok-dostig-zrelosti).

### Inferences
- **Для соло-основателя плохи горизонтальные B2B-категории** (мессенджеры, ВКС, трекеры, доски, конструкторы, ESP, интеграторы, CRM для услуг). Там нужно бесплатно давать столько же, сколько дают финансируемые игроки и экосистемы, а корпоративные продажи требуют отдела продаж, внесения в реестр ПО и сертификации. Рынок SaaS «созрел», рост падает: дальше конкуренция пойдёт за переманивание чужих клиентов.
- **Связь, стриминг и ИИ-ассистенты общего назначения** — зона экосистем и государства. Здесь нужна тяжёлая инфраструктура, а для мессенджеров ещё и регуляторные обязанности. Медиастриминг как замена Netflix и Spotify в этой сессии цифрами не проверен, но по составу игроков это очевидно экосистемная ниша.

### Gaps
- Не проверены цифры по Яндекс 360, «Контур.Толку», СберДжазу, МТС Линку, GigaChat, «Алисе AI», «Шедевруму», Kandinsky, VK Видео, Rutube, Яндекс Музыке, «Кинопоиску», Okko.
- Не найдено количественных оценок «числа клонов» по нишам: сколько активных таск-трекеров, ESP и т. п. Есть только подборки.

---

## 5. Итоговые списки: недообслуженные ниши (рейтинг) и насыщенные ниши, которых соло-основателю лучше избегать

### Takeaway
Самые перспективные пробелы для одного человека с ИИ-кодингом — потребительские и «просьюмерские» сценарии, где оригинал ушёл или не оплачивается, а российская замена мелкая или неудобная:
- запись на встречи в духе Calendly для экспертов и B2B;
- инструменты монетизации авторов, не привязанные к одному мессенджеру (Telegram, MAX, веб), на фоне ограничений Telegram;
- лёгкая база знаний с нормальными мобильными и офлайн-клиентами;
- домашние тренировки и беговые планы;
- медитация и сон на русском;
- вертикальный быстрый дизайн с ИИ;
- формы и квизы с оплатой;
- бюджетирование «по конвертам».

Уверенность низкая–средняя. Это гипотезы, а не доказанный спрос: доказательная база неполная (ограничения метода в шапке).

### Cited Findings
Ключевые доказательства по нишам рейтинга. Подробности и все ссылки — в разделах 1–3.
- **Calendly для экспертов.** Прямых российских клонов почти нет: каталоги вместо них называют YCLIENTS (CRM для услуг) и ПланФикс, а из небольших — «Планёрку» — [Toolfox](https://toolfox.ru/services/s/calendly/alternatives); [Startpack](https://startpack.ru/application/planerka). Лидеры записи заточены под сферу услуг и принадлежат экосистемам — [Techora](https://techora.ru/news/yandeks-servis-dikidi-pokazal-rentabelnost-45-2026-09-08); [ДП](https://www.dp.ru/a/2025/12/23/jandeks-kupit-platformu-dikidi).
- **Монетизация авторов.**
  - Платных подписчиков Tribute стало вдвое больше (с 280 тыс. до 586 тыс.), 74% авторов зарабатывают на подписках — [Sostav](https://www.sostav.ru/publication/dokhod-blogerov-v-telegram-vyros-na-44-v-2025-godu-78514.html).
  - Telegram ограничивают с января–февраля 2026 г. — [Медиазона](https://zona.media/news/2026/02/10/telega). Его суточная аудитория упала до 50,3 млн (апрель 2026 г.) и, по одному источнику, до 33,8 млн (август 2026 г.) — [Ведомости](https://vedomosti.ru/media/articles/2026/05/19/1198292-max-v-aprele-stal); [Эксперт](https://expert.ru/tekhnologii/kak-max-obognal-telegram).
  - Каналы в MAX в основном государственные и неактивные — [3DNews (заголовок)](https://3dnews.ru/1133879/messendger-max-razrossya-kanalami-no-tri-chetverti-iz-nih-gosudarstvennie-i-pochti-bez-aktivnosti).
  - У Boosty нет рекомендаций и органического охвата — [Octo Browser blog](https://blog.octobrowser.net/ru/kak-zarabotat-na-boosty).
- **База знаний.** Notion удалил российские пространства — [Habr](https://habr.com/ru/articles/841864). Buildin — китайский, только веб без PWA — [Habr](https://habr.com/en/articles/845382). У Yonote нет приложения и слабые базы — [Лайфхакер](https://lifehacker.ru/notion-alternatives/).
- **Фитнес.** Приложения Nike, Strava и Adidas Running ушли — [Т—Ж](https://t-j.ru/rossiiskoe-prilozhenie-dlia-bega/); [Rozetked](https://rozetked.me/news/32318-adidas-udalit-akkaunty-rossiyan-v-fitnes-prilozhenii-running). Читатели пишут, что отечественного аналога для домашних тренировок нет — [Т—Ж](https://t-j.ru/short/fitness-apps/).
- **Медитация.** Замены мелкие: «Мо», Zazn, «Серотонин», Softly — [vc.ru](https://vc.ru/services/3034866-obzor-prilozheniy-dlya-sna-i-meditacii); [Лайфхакер](https://lifehacker.ru/zazn-meditation-ios/).
- **Дизайн.** Canva нет с июня 2022 г. — [Kokoc](https://kokoc.com/blog/analogi-canva/). У Supa урезан бесплатный тариф и мало инструментов — [Timeweb](https://timeweb.com/ru/community/articles/10-luchshih-analogov-canva-dlya-raboty-s-graficheskim-dizaynom). Adobe и Figma оплачивают только через посредников — [vc.ru](https://vc.ru/services/2862193-kak-oplatit-adobe-creative-cloud-iz-rossii).
- **Формы.** Часть зарубежных сервисов не принимает ответы российских респондентов — [CRMindex](https://crmindex.ru/services/surveymonkey/analog).
- **Бюджетирование.** YNAB нельзя оплатить — [Т—Ж](https://t-j.ru/apps-zen-money-2/). Энтузиаст сам написал расширение «по принципу YNAB» для Дзен-мани — [Toolfox](https://toolfox.ru/services/s/zenmoney/alternatives).

### Inferences

#### Список 1. Недообслуженные ниши (5–8), по убыванию привлекательности для соло-основателя
Оценки «сила пробела» и «уверенность» — суждения исследователя на основе доказательств выше, а не измерения.

1. **Запись на встречи и консультации для экспертов, репетиторов, консультантов и B2B-продаж (аналог Calendly и Cal.com).**
   - Возможный продукт: ссылка для записи, синхронизация с Яндекс Календарём, автоматические ссылки на Телемост, «Контур.Толк» или VK Звонки, предоплата через лицензированного агрегатора (ЮKassa, CloudPayments и т. п.), напоминания в MAX, Telegram и по email.
   - Почему недообслужено: в выдаче нет прямых российских клонов. Лидеры (YCLIENTS у VK, DIKIDI у Яндекса) заточены под салоны и сферу услуг и превратились в тяжёлые CRM.
   - Формат: веб-SaaS, лицензии не нужны, инфраструктура лёгкая.
   - Риски: Яндекс может добавить страницы записи в Яндекс Календарь, средний чек низкий.
   - Сила пробела: средняя. Уверенность: средняя (отсутствие клонов в выдаче ещё не доказывает отсутствие спроса).
2. **Мультиплатформенные инструменты для платного контента и сообществ (Telegram, MAX, веб).**
   - Возможный продукт: платный доступ и подписки, перенос и «страховка» аудитории между платформами, витрина цифровых товаров, чеки по 54-ФЗ через агрегатора.
   - Почему сейчас: спрос растёт (подписчики Tribute ×2, доход авторов +44%, рынок 8,5 млрд руб. по оценке АБА), а платформенный риск Telegram с 2026 г. резко вырос. MAX набирает аудиторию, но авторская экосистема там почти пустая. У Boosty нет органического охвата.
   - Формат: боты, мини-приложения и веб.
   - Флаги: платежи только через лицензированного провайдера; правила MAX для сторонних ботов и платежей не проверены (см. «Пробелы»); есть ограничения для иностранных агентов; сильные игроки — Tribute, Boosty, VK Donut.
   - Сила пробела: средняя–высокая по моменту. Уверенность: средняя.
3. **Лёгкая база знаний и заметки с таблицами-базами и нормальными нативными или офлайн-клиентами (Notion для частных лиц и маленьких команд).**
   - Почему недообслужено: Notion удалил данные россиян, что создало запрос на хранение в РФ. Лучший клон (Buildin) китайский и работает только в браузере без PWA, российский Yonote без приложений и со слабыми базами.
   - Флаги: продукт технически тяжёлый (редактор, синхронизация, офлайн), игроков много (12 в подборке Startpack 2026 г.), в нишу зашла экосистема VK. Соло-основателю лучше входить узко: личная база, трекер, шаблоны под профессию.
   - Сила пробела: средняя. Уверенность: средняя–низкая.
4. **Домашние тренировки и беговые планы (замена Nike Training Club, Nike Run Club, Adidas Running, Strava).**
   - Почему недообслужено: западные приложения ушли или удалили аккаунты (2022–2024). Читатели прямо пишут об отсутствии отечественного аналога для домашних тренировок; есть один российский «аналог Strava».
   - Формат: веб или мини-приложение с ИИ-генерацией программ.
   - Флаги: видеоконтент дорог; через App Store не монетизировать, нужны веб-оплата и RuStore; конкурентов (FitStars, продукты Сбера и VK) в этой сессии не проверяли.
   - Сила пробела: средняя. Уверенность: низкая–средняя.
5. **Медитация, сон и саморегуляция на русском (замена Headspace и Calm).**
   - Почему недообслужено: оригиналы трудно оплатить, локальные приложения мелкие («Мо», Zazn, «Серотонин», Softly), цифр по ним нет.
   - Флаги: никаких медицинских или психотерапевтических обещаний, иначе возникают вопросы лицензирования и медизделий. Конкуренция с бесплатным контентом. Headspace остаётся в RuStore.
   - Сила пробела: средняя. Уверенность: низкая.
6. **Быстрый дизайн под узкие форматы с ИИ (Canva для одного сценария).**
   - Примеры форматов: обложки для VK Видео и Rutube, посты для MAX и VK, презентации КП, баннеры.
   - Почему есть пробел: Canva ушла, Adobe и Figma оплачивают через посредников, у Supa урезан бесплатный тариф и мало инструментов, Pixso без переменных.
   - Флаги: Flyvi и Supa уже делают шаблоны для маркетплейсов. Генерация ИИ на российских моделях (Шедеврум, Kandinsky) у экосистем бесплатна (не проверено). Ниша конкурентная, выигрывать придётся вертикальностью.
   - Сила пробела: низкая–средняя. Уверенность: низкая.
7. **Формы, квизы и опросы уровня Typeform** (логика, оплата, красивый UX, ответы из РФ без проблем).
   - Флаги: бесплатные Яндекс Формы — сильный конкурент; есть «Анкетолог».
   - Сила пробела: низкая–средняя. Уверенность: низкая.
8. **Бюджетирование «по конвертам» в стиле YNAB** (поверх выгрузок или Дзен-мани).
   - Почему пробел: YNAB нельзя оплатить, а энтузиаст сам написал расширение под этот метод.
   - Флаги: автоматическая интеграция с банками сложна (нет открытого банкинга, банки не дают API). Базовый учёт уже закрыт аналитикой банков, Дзен-мани и CoinKeeper.
   - Сила пробела: низкая. Уверенность: низкая.

**Сигналы спроса, которые соло-основателю не подходят** (в рейтинг не включены):
- **Замена Discord** с голосовыми комнатами и сообществами. Спрос сильный, VPN тормозит, Revolt, по одному источнику, тоже заблокирован. Но нужны тяжёлая медиаинфраструктура и модерация, а регуляторные обязанности для мессенджеров создают риск блокировки.
- **Замена Duolingo.** Пока оригинал бесплатно работает. Если его заблокируют, спрос всплеснёт (штраф 2 млн руб., слухи о блокировке в марте 2026 г.). Ниша требует много контента; для образовательной деятельности с выдачей документов нужна лицензия.

#### Список 2. Насыщенные ниши — соло-основателю избегать

| Ниша | Кто занимает | Почему избегать |
|---|---|---|
| Мессенджеры и звонки, в том числе корпоративные | MAX (государство и VK), VK, «Пачка» и др. | Регуляторика, тяжёлая инфраструктура |
| Видеоконференции | IVA, TrueConf, VK Звонки, Телемост, Dion, МТС Линк, «Контур.Толк» — около 90% рынка | Инфраструктура, корпоративные продажи |
| Таск-трекеры и управление проектами | Kaiten, YouGile, Weeek, Яндекс Трекер, Bitrix24 и десятки других | Бесплатные тарифы на 10–15 пользователей |
| Онлайн-доски | Не меньше 8 игроков, включая продукты МТС и VK | Много игроков при участии экосистем |
| Конструкторы сайтов и интернет-магазинов | Tilda (около 3,47 млрд руб.), inSales (GMV 407 млрд руб.), Taptop, Яндекс | Сильный лидер и вход экосистемы |
| Онлайн-запись в сфере услуг и бьюти | YCLIENTS (VK), DIKIDI (Яндекс) | Обе платформы в экосистемах |
| Email-рассылки и ESP | Unisender, DashaMail и около 100 маркетинговых замен | Множество клонов |
| Интеграторы и no-code-автоматизация | Albato (около 1000 интеграций) и другие | Сетевой эффект каталога интеграций |
| Аналоги Notion для команд (горизонтально) | 12 сервисов в подборке на 2026 г. плюс VK | Вход только узким вертикальным сценарием |
| Широкий EdTech и курсы | «Синергия», Skillbox, Skyeng | Стагнация рынка, лицензии |
| Монетизация только в Telegram | Tribute | Лидер и платформенный риск |
| ИИ-ассистенты и генерация изображений общего назначения | GigaChat, «Алиса AI», «Шедеврум», Kandinsky (не проверено) | Экосистемы; перепродажа доступа к зарубежным моделям исключена |
| Медиастриминг (замены Netflix, Spotify) | Экосистемы | Нужны лицензии на контент (цифрами в сессии не проверено) |

#### Исключённые модели — только как сигнал спроса
- Платёжные посредники для Adobe, Figma, Duolingo и др.: гайды 2025–2026 гг. исчисляются десятками.
- VPN: 48% платят за VPN.
- Перепродажа доступа к ChatGPT, Claude, Midjourney и Gemini.
- Всё это — сильнейшие сигналы неудовлетворённого спроса на оригиналы, но по условиям задачи такие модели исключены.

### Gaps
- Рейтинг построен на неполных данных: поиск был ограничен, первоисточники не открывались. Перед решением по нишам 1, 2 и 4 стоит:
  - проверить российских конкурентов Calendly (Cal.com-подобные сервисы, «Планёрка», функции записи в Яндекс 360 и Bitrix24) и их трафик;
  - изучить правила MAX для ботов, мини-приложений и платежей сторонних разработчиков, а также долю авторов, переехавших из Telegram в MAX или VK к ноябрю 2026 г.;
  - проверить российские фитнес-приложения (FitStars и др.) и их выручку и загрузки в RuStore;
  - выяснить, введена ли полная блокировка Telegram к ноябрю 2026 г.: это критично для ниши 2.
- В рейтинг не попали инструменты разработчика и ИИ-инструменты: по ним нет проверенных данных (см. раздел 1, «Пробелы»). Возможная ниша — вертикальные ИИ-продукты на API российских моделей (GigaChat API, YandexGPT API). Это гипотеза, не проверена.
- Нет цен и тарифов конкурентов по нишам 1, 4 и 5 в рублях: нужны сайты сервисов.
