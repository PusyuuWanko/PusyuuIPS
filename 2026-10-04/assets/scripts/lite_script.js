 
/*****************************************
  *----------------------------------
  |  ThisScriptVersion: 1.0.4     |
  |  © 2021-2026 ISAMI ABE        |
  |  LastUpdate: 2026-07-27       |
  |  License: MIT License         |
  |  PusyuuIPS Lite version JS    |
----------------------------------*
******************************************/

document.addEventListener('DOMContentLoaded', function () {

  /* ============================================================
    センシティブ投稿の目隠し（JS単体 / 実質IE9+、中央寄せの飾りはIE10+）
    検知 → オーバーレイ生成 → クリックで解除、まで全部JSで完結
    ============================================================ */

  function pipsSensitiveMessage() {
    return '⚠ このポストはセンシティブな内容です。クリックで表示 / Sensitive. Click to view.';
  }

  // オーバーレイを1枚貼る
  function pipsAddOverlay(el) {
    el.style.position = 'relative';
    el.style.minHeight = '4em';

    var overlay = document.createElement('div');
    overlay.className = 'blur-overlay';
    overlay.appendChild(document.createTextNode(pipsSensitiveMessage()));

    var s = overlay.style;
    s.position = 'absolute';
    s.top = '0'; s.right = '0'; s.bottom = '0'; s.left = '0'; // inset shorthand は使わず個別指定
    s.padding = '10px';
    s.boxSizing = 'border-box';
    s.background = '#ccc';    // blur は IE全滅。隠す本体はべた塗りにする
    s.color = '#000';
    s.textAlign = 'center';
    s.cursor = 'pointer';
    s.zIndex = '1';
    s.display = 'flex';       // 中央寄せは IE10+。効かなくても「覆う」機能自体は死なない
    s.alignItems = 'center';
    s.justifyContent = 'center';

    el.appendChild(overlay);

    // クリックで解除
    var reveal = function (e) {
      e = e || window.event;
      if (e && e.stopPropagation) e.stopPropagation();
      else if (e) e.cancelBubble = true;    // IE8
      if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
      el.style.minHeight = 'auto';
      // data-overlaid は消さない → 再ポーリングで貼り直されない
    };
    if (overlay.addEventListener) overlay.addEventListener('click', reveal, false);
    else if (overlay.attachEvent) overlay.attachEvent('onclick', reveal); // IE8
  }

  // 検知して未処理のものだけに貼る。対象が1つでもあれば true
  function sensitiveOverlay() {
    var list = document.querySelectorAll('.content[data-sensitivity="true"]');
    var found = false;
    for (var i = 0; i < list.length; i++) {   // NodeList.forEach は IE非対応なので for
      var el = list[i];
      found = true;
      if (el.getAttribute('data-overlaid') === 'true') continue; // 二重貼り＆解除後の再貼り防止
      el.setAttribute('data-overlaid', 'true');
      pipsAddOverlay(el);
    }
    return found;
  }

  sensitiveOverlay();

  /* ============================================================
    pips-lite-loading.js  —— 置くだけでOK
    ・POSTフォーム送信時にロード中オーバーレイを表示
    ・GET遷移（通常リンク/フォーム外遷移）でも表示
    ・JSが動かない環境では何も起きない（素のPOST/GETがそのまま生きる）
    ・CSS/他JS非依存。全てinline styleで自己完結
    ============================================================ */
  (function () {
    // addEventListener が無い環境（IE8以下等）は、何もせず素の挙動に任せる
    if (!document.addEventListener && !document.attachEvent) return;

    function on(target, evt, fn) {
      if (target.addEventListener) target.addEventListener(evt, fn, false);
      else if (target.attachEvent) target.attachEvent('on' + evt, fn);
    }

    var shown = false;

    function showLoading(msg) {
      if (shown) return;          // 二重表示防止
      shown = true;

      var overlay = document.createElement('div');
      var s = overlay.style;
      s.position = 'fixed';
      s.top = '0'; s.right = '0'; s.bottom = '0'; s.left = '0';
      s.zIndex = '2147483647';    // 最前面
      s.background = 'rgba(0,0,0,0.55)';
      s.color = '#fff';
      s.textAlign = 'center';
      // flexは古い環境で効かないので、paddingTopで大まかに中央へ寄せる（フォールバック）
      s.paddingTop = '40vh';
      s.font = '16px sans-serif';

      var box = document.createElement('div');
      box.style.display = 'inline-block';
      box.style.padding = '16px 20px';
      box.style.background = 'rgba(0,0,0,0.6)';
      box.appendChild(document.createTextNode(msg || '送信中... / Sending...'));

      overlay.appendChild(box);
      (document.body || document.documentElement).appendChild(overlay);

      // 保険：万一送信が始まらず画面が固まったままにならないよう、一定時間で自動的に消す
      setTimeout(function () {
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        shown = false;
      }, 15000);
    }

    // --- POST フォームの送信を検知 ---
    on(document, 'submit', function (e) {
      e = e || window.event;
      var form = e.target || e.srcElement;
      if (!form || String(form.tagName).toLowerCase() !== 'form') return;

      var method = (form.getAttribute('method') || 'get').toLowerCase();
      showLoading(method === 'post' ? '送信中... / Sending...' : '読み込み中... / Loading...');
      // ここでは preventDefault しない → ブラウザが普通に送信/遷移する
    });

    // --- 通常のリンク遷移も拾う（任意） ---
    on(document, 'click', function (e) {
      e = e || window.event;
      var a = e.target || e.srcElement;
      // 親をたどって <a> を探す（closest はIE非対応なので手動）
      while (a && String(a.tagName).toLowerCase() !== 'a') a = a.parentNode;
      if (!a) return;

      var href = a.getAttribute('href');
      if (!href) return;
      if (href.charAt(0) === '#') return;                 // ページ内アンカー/モーダルは除外
      if (/^javascript:/i.test(href)) return;             // javascript: は除外
      if ((a.getAttribute('target') || '') === '_blank') return; // 別タブは除外
      if (a.hostname && a.hostname !== window.location.hostname) return; // 外部リンクは除外

      showLoading('読み込み中... / Loading...');
    });

    // --- bfcache等で戻ってきたら消す ---
    on(window, 'pageshow', function () {
      var kids = document.body ? document.body.childNodes : [];
      // 自前オーバーレイだけ消す用途では、shownフラグ運用で十分なのでここは簡易に
      shown = false;
    });
  })();

  // CSSが無くても、読める最低限の骨格だけは保証する
  (function () {
    if (!document.body) return;
    var b = document.body.style;
    b.margin = '0';
    b.padding = '16px';
    b.background = '#fff';   // 何色か分からない不安をなくす
    b.color = '#222';
    b.lineHeight = '1.7';
    b.font = '16px/1.7 sans-serif';
  })();

  // いつでも安全地帯に帰れる非常口を常設
  (function () {
    if (!document.body) return;
    var home = document.createElement('a');
    home.href = './';
    home.appendChild(document.createTextNode('↩ トップへ / Home'));
    var s = home.style;
    s.position = 'fixed';
    s.left = '8px'; s.bottom = '8px';
    s.zIndex = '2147483646';
    s.padding = '8px 12px';
    s.background = 'rgba(0,0,0,0.6)'; s.color = '#fff';
    s.textDecoration = 'none';
    s.font = '14px sans-serif';
    document.body.appendChild(home);
  })();

  // 何かが壊れても、沈黙させない。静かに生存報告を出す
  (function () {
    function onError(msg) {
      if (!document.body) return;
      var bar = document.createElement('div');
      bar.appendChild(document.createTextNode(
        '一部の機能が読み込めませんでしたが、ページは使えます。 / Some features failed, but the page still works.'
      ));
      var s = bar.style;
      s.position = 'fixed';
      s.top = '0'; s.left = '0'; s.right = '0';
      s.zIndex = '2147483647';
      s.padding = '8px';
      s.background = '#553300'; s.color = '#fff';
      s.textAlign = 'center';
      s.font = '13px sans-serif';
      document.body.appendChild(bar);
      setTimeout(function () { if (bar.parentNode) bar.parentNode.removeChild(bar); }, 6000);
    }

    if (window.addEventListener) {
      window.addEventListener('error', function () { onError(); }, false);
    } else if (window.attachEvent) {
      window.attachEvent('onerror', function () { onError(); });
    }
  })();
});