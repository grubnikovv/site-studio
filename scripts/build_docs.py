"""Собирает публичные версии демо в docs/ для GitHub Pages."""
import os, re
PAIRS = {'okna': 'demos/okna-chelyabinsk/index.html', 'krovlya': 'demos/krovlya/index.html', 'avtomatika': 'demos/avtomatika/index.html', 'vizitka': 'demos/vizitka-chip/index.html', 'sto': 'demos/sto/index.html', 'detailing': 'templates/detailing/index.html',
         'coffee': 'templates/coffee/index.html', 'dental': 'templates/dental/index.html',
         'kitchen': 'templates/kitchen/index.html', 'fitness': 'templates/fitness/index.html'}
for k, src in PAIRS.items():
    s = open(src).read()
    m = re.search(r'<title>(.*?)</title>', s); t = m.group(1) if m else k
    if m: s = s.replace(m.group(0), '', 1)
    s = s.replace('<meta name="claude:viewport-fit-sides" content="cover">', '').replace('<meta name="robots" content="noindex">', '')
    head = ('<!doctype html>\n<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
            '<meta name="robots" content="noindex,nofollow"><title>' + t + '</title>\n<style>:root{padding-top:env(safe-area-inset-top,0px);'
            'padding-bottom:env(safe-area-inset-bottom,0px)}body{margin:0}img{max-width:100%}[hidden]{display:none!important}</style>\n')
    os.makedirs('docs/' + k, exist_ok=True)
    open('docs/' + k + '/index.html', 'w').write(head + '</head><body>\n' + s + '\n</body></html>\n')
print('docs built')
