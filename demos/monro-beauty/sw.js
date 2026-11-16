// Уведомления кабинета мастера. Сервер присылает «пустой» пуш, мы спрашиваем у него, что нового, и показываем.
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (e) {
  e.waitUntil(self.registration.pushManager.getSubscription().then(function (sub) {
    return fetch('server/api.php', { method: 'POST', body: JSON.stringify({ type: 'push_peek', endpoint: sub && sub.endpoint }) })
      .then(function (r) { return r.json(); }).catch(function () { return {}; });
  }).then(function (j) {
    var notes = (j && j.notes && j.notes.length) ? j.notes : [{ title: 'Студия «М.А»', body: 'Новое в кабинете', tag: 'ma' }];
    return Promise.all(notes.map(function (n) {
      return self.registration.showNotification(n.title, { body: n.body, tag: n.tag || 'ma', icon: 'img/app-192.png', badge: 'img/app-192.png', data: { url: './#admin' } });
    }));
  }));
});

// Нажали на уведомление — открыть кабинет (или переключиться на уже открытый)
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = new URL('./#admin', self.registration.scope).href;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) if ('focus' in list[i]) { if (list[i].navigate) list[i].navigate(url); return list[i].focus(); }
    return self.clients.openWindow(url);
  }));
});
