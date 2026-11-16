// Уведомления на телефон: кабинет мастера и личные кабинеты клиенток. Сервер присылает «пустой» пуш, мы спрашиваем у него, что нового, и показываем.
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (e) {
  // Обычно текст уже внутри пуша (зашифрован, браузер расшифровал сам). Старые подписки — спрашиваем у сайта.
  var inside = null; try { inside = e.data && e.data.json(); } catch (x) {}
  e.waitUntil((inside && inside.notes ? Promise.resolve(inside) : self.registration.pushManager.getSubscription().then(function (sub) {
    return fetch('server/api.php', { method: 'POST', body: JSON.stringify({ type: 'push_peek', endpoint: sub && sub.endpoint }) })
      .then(function (r) { return r.json(); }).catch(function () { return {}; });
  })).then(function (j) {
    var notes = (j && j.notes && j.notes.length) ? j.notes : [{ title: 'Студия «М.А»', body: 'Новое в кабинете', tag: 'ma' }];
    return Promise.all(notes.map(function (n) {
      return self.registration.showNotification(n.title, { body: n.body, tag: n.tag || 'ma', icon: 'img/app-192.png', badge: 'img/app-192.png', data: { url: n.url || './#admin' } });
    }));
  }));
});

// Нажали на уведомление — открыть нужный кабинет: мастера или клиентки (или переключиться на уже открытый)
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = new URL((e.notification.data && e.notification.data.url) || './#admin', self.registration.scope).href;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) if ('focus' in list[i]) { if (list[i].navigate) list[i].navigate(url); return list[i].focus(); }
    return self.clients.openWindow(url);
  }));
});
