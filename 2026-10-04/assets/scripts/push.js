(() => {
  'use strict';

  // 「新しいスレッドが立ったら知らせる」プッシュ通知の、この端末ぶんの登録。
  // 実際の購読処理は共通ヘルパー(pusyuusystem/scripts/push-subscribe.js の
  // window.PusyuuPush)に任せます。
  //
  // 【中身をJSが丸ごと組み立てる理由】購読も通知の許可も、操作そのものがJS前提です。
  // no-JS環境向けに「非表示のプレースホルダーをJSが表示する」形にすると、JSが無い人に
  // 押せないボタンを見せることになります。JSが無ければ最初から何も無い、が正しい姿です。
  // (RSSでの受け取りはJS無しで成立するので、そちらはPHPが常に描画しています)
  //
  // 【見出しもここで作ります】隣にPHPが描く「📰 RSSで受け取る」があり、この2つは
  // 「どちらで受け取るか」を選ぶ対の選択肢です。こちらだけ見出しが無いと、状態文と
  // ボタンがその上の説明にぶら下がっているように見え、選択肢に見えません。
  //
  // 【読み込む場所】index.phpの</body>直前から読んでいます。<head>から読むと
  // 下のquerySelector()が必ずnullになり、何も描かないまま終わります(画面には
  // RSSの案内だけが残り、エラーも出ません)。移動させないでください。
  //
  // 【許可を求めるのは必ずクリックの中から】ブラウザは、利用者の操作を伴わない
  // 通知許可の要求を自動的に拒否します。しかも拒否された記録だけが残り、以後は
  // 正しい手順を踏んでも求められなくなります。ページ読み込み時に呼ぶ形へ
  // 「親切に」変えないでください。(読み込み時に走る resync は許可を求めません。
  // 既に購読がある時だけ、その購読をサーバへ登録し直すものです)
  //
  // 【状態は2か所にあり、両方を見る】「この端末のブラウザが購読しているか」
  // (isSubscribed)と「通知APIにこの人の端末が登録されているか」
  // (config.serverSubscriptions)は別物です。ブラウザ側だけを見ていると、
  // サーバ側の登録が消えていても「受け取れる状態です」と表示し続けます。

  const config = window.PipsNotifyConfig;
  if (!config) { return; }

  const mount = document.querySelector(config.mountSelector);
  if (!mount) { return; }

  const LOG = '[PIPS push]';

  function line(text, isError) {
    const p = document.createElement('p');
    p.textContent = text;
    if (isError) { p.style.color = '#ff5555'; }
    return p;
  }

  // 対応していない端末でも見出しと理由は出します。「何も無い」より
  // 「この端末では使えない、だからRSSがある」と分かる方が親切なためです。
  function heading() {
    const h = document.createElement('h4');
    h.textContent = '🔔 プッシュ通知で受け取る';
    mount.appendChild(h);
  }

  function fail(message) {
    mount.innerHTML = '';
    heading();
    mount.appendChild(line(message, true));
    console.error(LOG, message);
  }

  heading();
  mount.appendChild(line('この端末のブラウザへ直接お知らせを届けます。ページを開いていなくても届き、端末ごとの設定です。'));

  if (!window.PusyuuPush) {
    fail('プッシュ通知の共通スクリプトを読み込めませんでした。ネットワークや広告ブロッカーの影響の可能性があります。RSSでの受け取りは下から設定できます。');
    return;
  }
  if (!window.PusyuuPush.isSupported()) {
    const d = window.PusyuuPush.supportDetails ? window.PusyuuPush.supportDetails() : {};
    const missing = [];
    if (d.secureContext === false) { missing.push('安全な接続(HTTPS)ではありません'); }
    if (d.serviceWorker === false) { missing.push('Service Worker 未対応'); }
    if (d.pushManager === false) { missing.push('Push API 未対応'); }
    if (d.notification === false) { missing.push('通知API 未対応'); }
    const detail = missing.length > 0 ? '(' + missing.join('・') + ')' : '(原因不明)';
    fail('このブラウザ/表示方法ではプッシュ通知に対応していません' + detail
      + '。LINEやX(旧Twitter)などのアプリ内ブラウザで開くと、見た目はChromeと同じでも使えないことがよくあります。'
      + 'この環境でも受け取りたい場合は、下の「RSSで受け取る」が使えます。');
    return;
  }
  if (!config.vapidKey) {
    fail('プッシュ通知の設定を取得できませんでした(サーバー側の一時的な問題の可能性があります)。');
    return;
  }

  const status = line('');
  const progress = line('');
  progress.style.opacity = '0.8';
  progress.hidden = true;
  const btn = document.createElement('button');
  btn.type = 'button';
  mount.appendChild(status);
  mount.appendChild(progress);
  mount.appendChild(btn);

  function setStatus(text, isError) {
    status.textContent = text;
    status.style.color = isError ? '#ff5555' : '';
    if (!text) {
      // 何も出さない
    } else if (isError) {
      console.error(LOG, text);
    } else {
      console.log(LOG, text);
    }
  }

  // 共通ヘルパーの onStage から呼ばれ、今どの段階かを画面に出します。
  // 詳しい経過は console に [PusyuuPush] 付きで出ています。
  function showStage(s) {
    progress.hidden = false;
    progress.textContent = '(' + s.index + '/' + s.total + ') ' + s.text + '...';
  }

  function hideStage() {
    progress.hidden = true;
    progress.textContent = '';
  }

  function blockedMessage() {
    return 'この端末では通知がブロックされています。ブラウザのアドレスバー左の鍵アイコン'
      + '(またはサイト設定)から、このサイトの通知を「許可」または「確認する」に戻してください。';
  }

  function channelDeniedMessage() {
    return 'このアカウントはプッシュ通知を使わない設定になっています。'
      + '下の「RSSの設定を開く」からプッシュ通知を「使う」に戻すと、ここから登録できます。';
  }

  /**
   * 共通ヘルパーの getLastResult() を、利用者に見せる1文にする。
   * kind ごとに1つずつ書き分けています。まとめて「失敗しました」にすると、
   * 押し直せば直るのか、設定を変えないと直らないのかが利用者に分かりません。
   */
  function describeFailure(r) {
    let text;
    if (!r) {
      text = '処理に失敗しました(理由を取得できませんでした)。しばらくしてからもう一度お試しください。';
    } else if (r.kind === 'permission_denied') {
      text = blockedMessage();
    } else if (r.kind === 'permission_dismissed') {
      text = '通知の許可が選ばれませんでした。もう一度ボタンを押し、表示される確認で「許可」を選んでください。';
    } else if (r.kind === 'unsupported') {
      text = 'このブラウザはプッシュ通知の登録に対応していませんでした。下の「RSSで受け取る」をお使いください。';
    } else if (r.kind === 'sw_register') {
      text = '通知の受け口(Service Worker)を準備できませんでした。ページを再読み込みしてもう一度お試しください。';
    } else if (r.kind === 'push_service') {
      text = 'ブラウザのプッシュサービスに接続できませんでした。ネットワークを確認するか、しばらくしてからもう一度お試しください。';
    } else if (r.kind === 'network') {
      text = 'サーバーに接続できませんでした。ネットワークを確認してもう一度お試しください。';
    } else if (r.kind === 'bad_response') {
      text = 'サーバーから想定外の応答が返りました。ログインが切れている可能性があります。ページを再読み込みしてください。';
    } else if (r.kind === 'http') {
      text = 'サーバーでエラーが起きました(HTTP ' + r.status + ')。しばらくしてからもう一度お試しください。';
    } else if (r.kind === 'server') {
      // サーバ(index.php の PipsPush::replyToBrowser 等)が理由の文を付けていればそれを使います。
      text = r.serverMessage || ('サーバーが処理を受け付けませんでした(' + (r.code || 'unknown') + ')。');
    } else {
      text = '予期しないエラーが発生しました(' + r.stage + ': ' + r.message + ')。';
    }
    return text;
  }

  let subscribed = false;

  function render() {
    hideStage();
    btn.disabled = false;

    if (typeof Notification !== 'undefined' && Notification.permission === 'denied') {
      setStatus(blockedMessage(), true);
      btn.hidden = true;
    } else if (config.pushChannel === 'deny' && !subscribed) {
      // 押しても通知APIが channel_denied で断るのが分かっているので、ボタンは出しません。
      setStatus(channelDeniedMessage(), true);
      btn.hidden = true;
    } else if (config.pushChannel === 'deny' && subscribed) {
      // 解除はできるようにしておきます(端末に購読だけ残っていても何も届かないため)。
      setStatus('この端末は登録されていますが、アカウントがプッシュ通知を使わない設定のため届きません。', true);
      btn.hidden = false;
      btn.textContent = 'この端末で受け取るのをやめる';
    } else if (subscribed) {
      setStatus('この端末は新しいスレッドの通知を受け取れる状態です。');
      btn.hidden = false;
      btn.textContent = 'この端末で受け取るのをやめる';
    } else {
      setStatus('この端末はまだ登録されていません。');
      btn.hidden = false;
      btn.textContent = 'この端末で受け取る';
    }
  }

  btn.addEventListener('click', () => {
    btn.disabled = true;
    const wasSubscribed = subscribed;
    setStatus(wasSubscribed ? '登録を解除しています...' : '登録しています...');

    const action = wasSubscribed
      ? window.PusyuuPush.unsubscribe({
          unsubscribeUrl: config.postUrl,
          extraFields: { push_unsubscribe: '1', server_token: config.token },
          onStage: showStage,
        })
      : window.PusyuuPush.subscribe({
          swUrl: config.swUrl,
          scope: config.swScope,
          publicKey: config.vapidKey,
          subscribeUrl: config.postUrl,
          extraFields: { push_subscribe: '1', server_token: config.token },
          onStage: showStage,
        });

    action.then((ok) => {
      const r = window.PusyuuPush.getLastResult ? window.PusyuuPush.getLastResult() : null;
      if (ok) {
        subscribed = !wasSubscribed;
        render();
        setStatus(subscribed
          ? 'この端末を登録しました。次に新しいスレッドが立った時に届きます。'
          : 'この端末の登録を解除しました。');
      } else if (r && r.kind === 'permission_denied') {
        render(); // ブロック中の表示に切り替わり、ボタンも隠れます。
      } else if (r && r.kind === 'server' && r.code === 'channel_denied') {
        // ページを開いた後に別の画面で「使わない」にした場合など。以後は押しても同じなので隠します。
        config.pushChannel = 'deny';
        render();
      } else {
        // 失敗しても購読状態はヘルパーが元のままに保っています(subscribed は変えない)。
        hideStage();
        setStatus(describeFailure(r), true);
        btn.disabled = false;
      }
    }).catch((e) => {
      // ヘルパーは失敗を false で返すので、ここに来るのは上の then の中の不具合だけです。
      hideStage();
      setStatus('予期しないエラーが発生しました: ' + (e && e.message ? e.message : e), true);
      btn.disabled = false;
    });
  });

  // 読み込み時: ブラウザ側の購読状態を読み、サーバ側の見え方と食い違っていれば直します。
  window.PusyuuPush.isSubscribed().then((yes) => {
    subscribed = yes;
    render();

    if (!yes) {
      // 購読していない。サーバ側を気にする必要はありません。
    } else if (config.pushChannel === 'deny') {
      // 登録し直しても断られるだけなので、何もしません(render が理由を出しています)。
    } else if (config.serverSubscriptions === 0) {
      // ブラウザは購読しているのに、通知APIにはこの人の端末が1台も無い。
      // 放っておくと「受け取れる状態です」と表示されたまま何も届きません。
      // 許可は既に得ているので、利用者に押させずにその場で登録し直します。
      console.warn(LOG, 'ブラウザは購読済みですが、サーバーに登録がありません。登録し直します。');
      btn.disabled = true;
      window.PusyuuPush.resync({
        subscribeUrl: config.postUrl,
        extraFields: { push_subscribe: '1', server_token: config.token },
        onStage: showStage,
      }).then((ok) => {
        const r = window.PusyuuPush.getLastResult ? window.PusyuuPush.getLastResult() : null;
        if (ok) {
          config.serverSubscriptions = 1;
          render();
          setStatus('サーバー側の登録が外れていたため、この端末を登録し直しました。');
        } else if (r && r.kind === 'server' && r.code === 'channel_denied') {
          config.pushChannel = 'deny';
          render();
        } else {
          render();
          setStatus('この端末の登録がサーバー側で外れています。' + describeFailure(r), true);
        }
      });
    } else {
      // serverSubscriptions が1以上(=どれかの端末は登録済み)か、null(=取れなかった)。
      // null の時に登録し直さないのは、APIが落ちている間に毎回失敗表示を出さないためです。
    }
  }).catch((e) => {
    console.error(LOG, '購読状態の確認に失敗しました', e);
    render();
  });
})();
