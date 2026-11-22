"""Собирает публичные версии демо в docs/ для GitHub Pages."""
import os, re, shutil
PAIRS = {'okna': 'demos/okna-chelyabinsk/index.html', 'krovlya': 'demos/krovlya/index.html', 'avtomatika': 'demos/avtomatika/index.html', 'vizitka': 'demos/vizitka-chip/index.html', 'sto': 'demos/sto/index.html', 'delo': 'demos/delo-dnya/index.html', 'porcia': 'demos/porcia/index.html', 'lit-energy': 'demos/lit-energy/index.html', 'detailing': 'templates/detailing/index.html',
         'coffee': 'templates/coffee/index.html', 'dental': 'templates/dental/index.html',
         'kitchen': 'templates/kitchen/index.html', 'fitness': 'templates/fitness/index.html'}
for k, src in PAIRS.items():
    s = open(src).read()
    if s.lstrip().lower().startswith('<!doctype'):
        os.makedirs('docs/' + k, exist_ok=True)
        open('docs/' + k + '/index.html', 'w').write(s)
        for sub in ('img', 'video', 'fonts'):
            d = os.path.join(os.path.dirname(src), sub)
            if os.path.isdir(d):
                shutil.copytree(d, os.path.join('docs', k, sub), dirs_exist_ok=True)
        continue
    m = re.search(r'<title>(.*?)</title>', s); t = m.group(1) if m else k
    if m: s = s.replace(m.group(0), '', 1)
    s = s.replace('<meta name="claude:viewport-fit-sides" content="cover">', '').replace('<meta name="robots" content="noindex">', '')
    head = ('<!doctype html>\n<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
            '<meta name="robots" content="noindex,nofollow"><title>' + t + '</title>\n<style>:root{padding-top:env(safe-area-inset-top,0px);'
            'padding-bottom:env(safe-area-inset-bottom,0px)}body{margin:0}img{max-width:100%}[hidden]{display:none!important}</style>\n')
    # Всё до первого </style> (мета-теги, шрифты, стили) уходит в <head>: так браузер раньше начинает грузить шрифты,
    # а поисковики видят описание страницы.
    i = s.find('</style>')
    if i >= 0 and s[:i].lstrip().startswith(('<', '\n')) and '<body' not in s[:i]:
        top, s = s[:i + 8], s[i + 8:]
        head += top + '\n'
    if 'rel="icon"' not in head:
        head += '<link rel="icon" href="data:,">\n'
    os.makedirs('docs/' + k, exist_ok=True)
    open('docs/' + k + '/index.html', 'w').write(head + '</head><body>\n' + s.lstrip() + '\n</body></html>\n')
    # картинки, видео и шрифты демо (папки img/, video/, fonts/ рядом с index.html) копируем как есть
    for sub in ('img', 'video', 'fonts'):
        d = os.path.join(os.path.dirname(src), sub)
        if os.path.isdir(d):
            shutil.copytree(d, os.path.join('docs', k, sub), dirs_exist_ok=True)
    # файлы приложения: манифест для экрана «Домой» и service worker для уведомлений
    for f in ('manifest.json', 'sw.js'):
        fp = os.path.join(os.path.dirname(src), f)
        if os.path.isfile(fp):
            shutil.copy(fp, os.path.join('docs', k, f))
print('docs built')
