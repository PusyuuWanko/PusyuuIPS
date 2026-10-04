// PIPSのService Worker(プッシュ通知の受け口)。
//
// 【なぜ中身がプッシュに載ってこないのか】通知APIは「中身の無い(empty payload)
// プッシュ」だけを送ります。本文をサーバ→ブラウザ間で暗号化して運ぶには
// openssl_pkey_derive()(PHP 8.1以降)が要り、このサーバのPHPが8.0の可能性が
// あるためです。そこでSWはpushイベントで起こされたら、自分自身のオリジンへ
// fetch()して中身を取りに行きます(同一オリジンなのでセッションCookieがそのまま
// 乗り、ログイン中の本人ぶんだけが返ります)。
// 詳しくは main/pusyuusystem/apis/pusyuu_push/PUSH_INTEGRATION_SPEC.md 2節。
//
// 【このファイルを他のプロダクトと共有できない理由】別オリジンのService Workerは
// 登録できません。中身が同じでもサービスごとに1つ必要です。

self.addEventListener('push', (event) => {
  event.waitUntil(
    fetch('./?push_inbox=1', { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((data) => Promise.all((data.items || []).map((n) =>
        self.registration.showNotification(n.title, {
          body: n.body,
          tag: n.tag || undefined,
          icon: n.icon || undefined,
          data: { url: n.url || './' },
        })
      )))
      .catch(() => {})
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || './';
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then((list) => {
      // 既に開いているタブがあればそれを前に出します。同じサイトのタブを
      // 増やさないためで、開いていない時だけ新しく開きます。
      for (const c of list) { if ('focus' in c) return c.focus(); }
      return clients.openWindow(url);
    })
  );
});

// 購読はブラウザの都合で作り直されることがあります(端末の更新等)。その時に
// 黙って通知が止まらないよう、新しい購読をサーバへ登録し直します。
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil(
    self.registration.pushManager.getSubscription().then((sub) => {
      if (!sub) { return; }
      return fetch('./', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        // 通常の購読(push_subscribe)とは別の受け口へ送ります。SWはページでは
        // ないのでCSRFトークンを持っておらず、そちらは通れないためです。
        body: new URLSearchParams({
          push_resubscribe: '1',
          subscription: JSON.stringify(sub.toJSON()),
        }).toString(),
      });
    }).catch(() => {})
  );
});
