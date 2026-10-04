
/*****************************************
  *----------------------------------
  |  ThisScriptVersion: 4.19.11   |
  |  © 2021-2026 ISAMI ABE        |
  |  LastUpdate: 2026-08-10       |
  |  License: MIT License         |
  |  PusyuuIPS Normal JS          |
----------------------------------*
******************************************/

// ================================================================
// 投稿カードのボタンを押せる状態にします。
//
// PHPは共有ボタン(📨)を disabled で書き出します。JSが無い環境で「押しても何も起きない
// ボタン」を見せないためで、それを解除するのがここの役目です。
//
// 【タイマーで現れるのを待たないこと】以前ここは setInterval(fn) ——間隔を指定しない、
// つまり4msごとに回り続けるタイマー——でボタンが現れるのを待っていました。しかも
// clearInterval() が setInterval() の直後、コールバックの外に書かれていたため、
// その行に来た時点では stopIntervalFlug が必ず false で、一度も止まりませんでした。
// urlHandle() と DOMContentLoaded から二度呼ばれるうえ、呼ぶたびにタイマーを掴んでいた
// 変数が上書きされるので、後から止める手立てもありません。
//
// 投稿がサーバ描画になってボタンが最初から存在するようになり、1画面に70個ほど並ぶように
// なったため、そのすべてに4msごとに触り続ける形になっていました。画面が重い・入力が
// 引っかかる・スクロールがつっかえる、といった症状はここが原因です。
//
// 待つ必要はもうありません。呼ばれた時点にある物を解除するだけにして、一覧を差し替えた
// 時は差し替えた側(getDispPost)から呼び直します。
// ================================================================
function jsBtnDisabledUnlock() {
  var buttons = Array.prototype.slice.call(document.querySelectorAll('.bbs_content .buttons form button'));
  buttons.forEach(function (button) {
    button.disabled = false;
  });
}

function sensitiveOverlay() {
  // センシティブなカードの目隠し処理
  var contentElements = document.querySelectorAll('.content');
  contentElements.forEach(function(contentElement) {
    if (contentElement.querySelector('.blur-overlay')) return;
    var isSensitive = contentElement.getAttribute('data-sensitivity') === 'true';
    if (isSensitive) {
      contentElement.style.position = 'relative';
      contentElement.style.minHeight = '100%';
      var overlay = document.createElement('div');
      overlay.className = 'blur-overlay';
      overlay.textContent = '⚠このポストはセンシティブな内容です。解除方法を見るにはこのはポストをクリックしてください。/⚠ This post is sensitive. Click on this post to see how to undo it.';
      overlay.style.display = 'flex';
      overlay.style.justifyContent = 'center';
      overlay.style.alignItems = 'start';
      overlay.style.position = 'absolute';
      overlay.style.inset = '0';
      overlay.style.padding = '10px';
      overlay.style.boxSizing = 'border-box';
      overlay.style.backgroundColor = '#CCCCCC';
      overlay.style.color = '#000';
      contentElement.appendChild(overlay);
      // Check if the 'id' parameter is present in the URL
      var urlParams = new URLSearchParams(window.location.search);
      var idParam = urlParams.get('id');
      if (idParam) {
        overlay.textContent = '⚠このポストはセンシティブな内容です。クリックすると閲覧できます。/⚠ This post is sensitive. Click to view.';
        contentElement.addEventListener('click', function() {
          contentElement.style.position = 'none';
          contentElement.style.minHeight = 'auto';
          contentElement.removeChild(overlay);
        });
      }
    }
  });
}

// 【ここも待ちません】jsBtnDisabledUnlock()と同じ理由です。以前は0.5秒ごとに最大20回、
// 目隠しを付ける相手が現れるのを見張っていました。投稿がサーバ描画になった今は最初から
// 居るので、呼ばれた時点の物へ付けるだけで足ります。一覧を差し替えた時は差し替えた側
// (getDispPost)から呼び直してください。付け直しても、既に目隠しのある投稿は
// sensitiveOverlay()の先頭で弾かれるので二重にはなりません。
function sensitiveOverlayHandle() {
  sensitiveOverlay();
}

var getDispPostFlug = true;
function getDispPost() {
  var displayEle = document.querySelector(".bbs_content");//Array.prototype.slice.call();
  var param = window.location.search.replace("?", "&");
  param = param.replace("&getDispPost", "");
  if (displayEle) {
    // ================================================================
    // サーバが投稿を入れ終えて返してきた回は、取り直しません。
    //
    // 【なぜ要るか】この関数は下で displayEle.innerHTML = "" と、まず表示欄を空にしてから
    // 取りに行きます。PHPが入れてくれた投稿をわざわざ消し、同じ物をもう一度もらうだけの
    // 往復と、消えてから戻るまでのちらつきが起きます。
    //
    // 【一度きりである理由】印はここで外します。以後のurlHandle()（ページ送り、新着の
    // 取り直し、投稿後の再描画）は今までどおり普通に取りに行きます。外し忘れると、
    // ページ送りを押しても中身が変わらない画面になります。
    // ================================================================
    if (displayEle.getAttribute("data-prerendered") === "1") {
      displayEle.removeAttribute("data-prerendered");
      // 取りに行かない代わりに、新着判定の基準だけは作っておきます。これが無いと
      // PipsNewPosts.marker が null のままになり、「新しい投稿があります」が永久に出ません。
      // 一覧のHTMLを作らせる ?getDispPost ではなく、件数とidだけを返す軽い口を使います。
      PipsNewPosts.prime();
      return;
    }

    if (getDispPostFlug) {
      getDispPostFlug = false;
      displayEle.innerHTML = "";
      var stateMsg = document.createElement("div");
      stateMsg.style.background = "rgba(0,255,0,0.5)";
      stateMsg.style.backdropFilter = "blur(5px)";
      stateMsg.style.padding = "15px";
      stateMsg.style.borderRadius = "5px";
      displayEle.appendChild(stateMsg);
      stateMsg.innerHTML = "<p>頑張って組み立ててるのでほんの少し待ってね（多分数秒で終わる）！！</>";
      var xhr = new XMLHttpRequest();
      xhr.open('get', "./?getDispPost" + param, true);
      xhr.withCredentials = true;
      // 画面の裏で走る問い合わせであることを名乗ります。これが無いと、共有の
      // アカウントクライアントがこれを「画面遷移」とみなし、SSOの往路を始めてしまいます。
      xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 3) {
          stateMsg.innerHTML = '<div style="display: flex; gap: 10px; justify-content: center; align-items: center;"><span>サーバーへ通信中...</span><img style="width: 25px; height: 25px;" src="./assets/images/loading.svg" /></div>';
        } else if (xhr.readyState === 4) {
          getDispPostFlug = true;
          if (xhr.status >= 200 && xhr.status < 300) {
            try {
              var getPostData = JSON.parse(xhr.responseText);
              document.querySelector("title").textContent = getPostData.title;
              document.querySelector('meta[name="description"]').textContent.description;
              displayEle.innerHTML = getPostData.content; // 正常にレスポンスが返ってきた場合
              // 【差し替えた直後に必ず呼ぶこと】
              // ボタンの解除と目隠しは、今入れ替えたばかりの投稿に対しても要ります。
              // urlHandle()側にも同じ呼び出しがありますが、あちらはこの通信の**前**に走るので、
              // ここに無いと差し替え後の投稿だけボタンが押せず、目隠しも付きません。
              // 以前はそのズレをタイマーで見張って埋めていました(その弊害は
              // jsBtnDisabledUnlock()のコメント参照)。呼ぶ場所を正しくすれば見張りは要りません。
              jsBtnDisabledUnlock();
              sensitiveOverlayHandle();
              // 今表示した一覧の「新しさ」を覚え、出しっぱなしのお知らせを消します。
              PipsNewPosts.remember(getPostData);
            } catch (error) {
              console.error("JSON解析エラーが出ています。詳細：" + error);
              stateMsg.style.background = "rgba(255,100,0,0.5)";
              stateMsg.innerHTML = '<div style="display: flex; flex-direction: column; justify-content: center; align-items: center;"><p>サーバーとの通信に成功はしたのですが、投稿データが異常な為処理できませんした、この問題は開発者の設定ミスによる可能性も大きいです、少なくともあなたにできることはしばらくたってから再試行し回復できるか試みることぐらいです、もし回復できないようでしたらお手数をおかけしますが開発者に連絡をお願いいたします。エラーコード：' + error +'</p><button style="margin: 5px 0 5px;" onclick="window.location.hash = \'#modal-2\'">開発者に連絡</button><button style="margin: 5px 0 5px;" onclick="getDispPost()">再試行</button></div>';
            }
          } else {
            stateMsg.style.background = "rgba(255,255,0,0.5)";
            stateMsg.innerHTML = '<div style="display: flex; flex-direction: column; justify-content: center; align-items: center;"><p>サーバーとの通信に成功はしたのですが、サーバーソフトウェアに問題が発生しました。エラーコード：' + (xhr.status === 404 ? '404（詳細：アクセス先の投稿が削除されたかページそのものがそもそも存在しません。）</p></div>' : xhr.status + '</p><button style="margin: 5px 0 5px;" onclick="getDispPost()">再試行</button></div>');
          }
        }
      }
      xhr.onerror = function ()  {
        getDispPostFlug = true;
        stateMsg.style.background = "rgba(255,0,0,0.5)";
        stateMsg.innerHTML = '<div style="display: flex; flex-direction: column; justify-content: center; align-items: center;"><p>サーバーとの通信中に問題が発生しました。ネットワーク接続状態を確認し再試行してください。</p><button onclick="getDispPost()">再試行</button></div>';
      }
      xhr.send();
    } else {
      alert("現在処理中じゃ。。。待ってくんろ👵☕");
    }
  } else {
    console.error("表示する場所がないんですけど、開発者さん。。。");
  }
}

function urlHandle(url = null) {
  if (url) {
    if (history.pushState) {
      history.pushState(null, null, url);
      getDispPost();
      jsBtnDisabledUnlock();
      sensitiveOverlayHandle();
    } else {
      window.location.href = url;
    }
  } else {
    getDispPost();
    sensitiveOverlayHandle();
    jsBtnDisabledUnlock();
  }
}

document.addEventListener("DOMContentLoaded", function() {
  urlHandle();

  var dateNow = new Date();
  var dateString = dateNow.getFullYear() + "年" + ("0" + (dateNow.getMonth() + 1)).slice(-2) + "月" + ("0" + dateNow.getDate()).slice(-2) + "日";
  var timeString = ("0" + dateNow.getHours()).slice(-2) + ":" + ("0" + dateNow.getMinutes()).slice(-2) + ":" + ("0" + dateNow.getSeconds()).slice(-2);
  // 【1つ欠けただけで以降が全部死ぬ形にしないこと】
  // ここはDOMContentLoadedの中で、この下に下書きの復元・送信の見張り・新着の見張りが
  // 続きます。要素が1つ見つからないまま .value に触ると、そこで例外が出て残り全部が
  // 動かなくなります。しかも画面には何も出ないので「なんとなく調子が悪い」としか見えません。
  // 下の relButton / nameInput と同じように、有無を確かめてから触ります。
  var datetimeField = document.getElementById("datetime");
  if (datetimeField) {
    datetimeField.value = dateString + " " + timeString;
  }
  // 書きかけの保持はサーバが行います（PipsDraft参照）。ここはそれを自動で呼ぶだけです。
  // 【localStorageへ書き戻す処理をここに足さないこと】以前はここで
  //   subjectInput.value = localStorage.getItem("subjectsave");
  // と、サーバが入れた値を無条件に上書きしていました。保持の本体がサーバへ移った今、
  // これをやると「サーバに預けた下書き」と「ブラウザに残った古い打鍵」のどちらが
  // 出るのか読めなくなります。localStorageで埋めてよいのは、サーバが何も持っていない
  // 欄だけです（PipsDraft.restoreLocalIfEmpty）。
  PipsDraft.start();

  var nameInput = document.querySelector('[name="name"]');
  var textInput = document.querySelector('#modal-1 [name="text"]');
  var sendButton = document.querySelector('[name="postSend"]');
  if (nameInput) {
    // 名前だけは投稿ごとに変わる物ではないので、これまでどおりブラウザ側に覚えさせます
    // （サーバは匿名の名前を預かりません）。
    nameInput.addEventListener("input", function () {
      try { localStorage.setItem("namesave", nameInput.value); } catch (e) {}
    });
    if (nameInput.value.length < 1) {
      try { nameInput.value = localStorage.getItem("namesave") || ""; } catch (e) {}
    }
  }
  if (sendButton && textInput) {
    sendButton.addEventListener("click", function(event) {
      if (PipsComposer.pending) {
        event.preventDefault();
        alert("書き込み情報の状態を確認しています。\n少しだけお待ちください。\n\nChecking the writing status.\nPlease wait a moment.");
        return;
      }
      if (textInput.value.trim() === "") {
        event.preventDefault(); // フォーム送信をキャンセル
        alert("フォームに\nコメント\nを入力してから送信してください。\nPlease enter\nComment\nin the form before submitting.");
        location.href = "#modal-1";
      }
    });
  }

  // ボタンを押さない送信（文字欄でEnterを押した等）も止めます。
  // ボタンを無効にしただけでは、ブラウザによってはEnterでの送信が通ってしまい、
  // 前に開いていた投稿への返信として送られます。
  var composerForm = document.querySelector('#modal-1 form');
  if (composerForm) {
    // event.submitter は古いブラウザには無いので、どのボタンが押されたかを自前でも覚えます
    // （ここが取れないと、下書きの保存まで巻き添えで止まります）。
    var lastSubmitName = "";
    var submitters = Array.prototype.slice.call(composerForm.querySelectorAll('button, input[type="submit"]'));
    submitters.forEach(function (b) {
      b.addEventListener("click", function () { lastSubmitName = b.name || ""; });
    });

    composerForm.addEventListener("submit", function (event) {
      if (!PipsComposer.pending) { return; }
      // 下書きの保存は返信先を使わないので、取得中でも止めません。
      var pressed = (event.submitter && event.submitter.name) || lastSubmitName;
      if (pressed === "draft_save") { return; }
      event.preventDefault();
      alert("書き込み情報の状態を確認しています。\n少しだけお待ちください。\n\nChecking the writing status.\nPlease wait a moment.");
    });
  }

  setTimeout(function() {
    if (window.location.hash === "#sended") {
      // 投稿できた合図。サーバ側の下書きは既に捨てられているので、
      // ブラウザ側に残っている打鍵ぶんもここで揃えて捨てます。
      try {
        localStorage.setItem("subjectsave", "");
        localStorage.setItem("textsave", "");
      } catch (e) {}
      // 一度だけ使うのでハッシュを消す（リロードで再発しないように）
      history.replaceState(null, null, window.location.pathname + window.location.search);
    }
  }, 1000);

  // 再読込ボタン
  let relButton = document.getElementById("reyomikomi");
  if (relButton) {
    relButton.onclick = function() {
      location.reload();
    };
  } else {
    console.error("Element with id 'reyomikomi' not found.");
  }

  jsBtnDisabledUnlock();

  // 書き込みフォームの「返信/新規投稿」表示を、開いた時点の hidden の値に合わせておきます
  // （PHPが出した初期表示と同じ結果になりますが、JSが後から値を触った場合にも追随させるため、
  //   入口をここに一本化しておきます）。
  // 新着の見張りを開始します（一覧を差し替えるのは、お知らせが押された時だけ）。
  PipsNewPosts.start();

  // 【返信件数の色分けはここから外しました】
  // .replyCD を集めて 0なら赤・1件以上なら青、という処理がここにありました。色そのものが
  // 「返信が付いているか」という情報なので、JSが無い環境ではその情報が丸ごと落ちますし、
  // ここはDOMContentLoadedで1回きりなので、一覧を差し替えた後(getDispPost)は塗り直されず、
  // ページ送りをした瞬間から全部が同じ色になっていました。
  // 今はPHPが .replyCD_has / .replyCD_none の印を付け、色はCSSが塗ります
  // （PipsPostTemplate::shareButton() と style.css）。ここへ戻さないでください。

  /*
  // 選択壁紙のロジック 
  const select = document.getElementById('background-select');
  const body = document.body;
  const uploadInput = document.getElementById('upload-input');
  const maxFileSize = 1 * 1024 * 1024; // 1MB in bytes
  const selectedImage = localStorage.getItem('PusyuuBBS_selectedImage');
  if (selectedImage) {
    body.style.backgroundImage = `url(${selectedImage})`;
    select.value = selectedImage;
  }
  select.addEventListener('change', function() {
    const selectedImage = select.value;
    body.style.backgroundImage = `url(${selectedImage})`;
    localStorage.setItem('PusyuuBBS_selectedImage', selectedImage); // Changed the key to 'SyuukiHyou_selectedImage'
  });
  uploadInput.addEventListener('change', function(event) {
    const file = event.target.files[0];
    const reader = new FileReader();
    if (file.size > maxFileSize) {
      alert('The file size exceeds the maximum limit of 1MB.');
      return;
    }
    reader.onload = function() {
      const uploadedImage = reader.result;
      const randomThreeDigitNumber = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
      const imageName = "Your wallpaper" + randomThreeDigitNumber;
      localStorage.setItem(imageName, uploadedImage);
      addImageOption(imageName, uploadedImage);
    };
    reader.readAsDataURL(file);
  });
  for (let i = 0; i < localStorage.length; i++) {
    const key = localStorage.key(i);
    if (key.startsWith("Your wallpaper")) {
      const uploadedImage = localStorage.getItem(key);
      addImageOption(key, uploadedImage);
    }
  }

  function addImageOption(imageName, uploadedImage) {
    const option = document.createElement('option');
    option.value = uploadedImage;
    option.text = imageName;
    select.add(option);
    if (uploadedImage === selectedImage) {
      option.selected = true;
    }
  }

  function applyBackgroundStyles() {
    body.style.backgroundSize = "cover";
    body.style.backgroundRepeat = "no-repeat";
    body.style.backgroundPosition = "center";
    body.style.backgroundAttachment = "fixed";
  }
  applyBackgroundStyles();

  function addPreloadedImages() {
    const preloadedImages = ["./assets/images/sys_wallpaper/8.jpg", "./assets/images/sys_wallpaper/6.jpg", "./assets/images/sys_wallpaper/3.jpg", "./assets/images/sys_wallpaper/1.jpg"];
    addImageOption("Select Wallpaper", "");
    preloadedImages.forEach(function(image, index) {
      const wallpaperNumber = (index + 1).toString();
      const imageName = "wallpaper " + wallpaperNumber;
      addImageOption(imageName, image);
    });
  }
  addPreloadedImages();
  */
  
  function modalStopper() {
    const ele = document.querySelector("body");
    const loadingEle = document.getElementById("loading");

    // 【loadingEleが無い前提で書くこと】ローディング画面はPHP側で廃止されました（投稿が最初から
    // HTMLに入るようになり、応答を待つ間だけ画面を覆う理由が無くなったためです）。
    // ここを素通りさせないと、getComputedStyle(null)が0.5秒ごとに例外を投げ、この関数の
    // 本来の仕事であるモーダル表示中の背面スクロール止めまで道連れで動かなくなります。
    if (loadingEle === null) {
      if (window.location.hash.startsWith("#modal-") || window.location.hash.startsWith("#zoom") || window.location.hash == "#shareDisp") {
        ele.style.overflow = "hidden";
      } else {
        ele.style.overflow = "unset";
      }
    } else if (window.getComputedStyle(loadingEle).display !== "none") {
      ele.style.overflow = "hidden";
      Array.prototype.slice.call(document.querySelectorAll("#loading > div > form")).forEach(function (loadingNoJsForm) {
        loadingNoJsForm.style.display = "none";
      });
      setTimeout(function() {
        loadingEle.style.display = "none";
        ele.style.overflow = "unset";
      }, 3000);
    } else {
      if (window.location.hash.startsWith("#modal-") || window.location.hash.startsWith("#zoom") || window.location.hash == "#shareDisp") {
        ele.style.overflow = "hidden";
      } else {
        ele.style.overflow = "unset";
      }
    }
  }

  if (setInterval) {
    setInterval(function() {
      modalStopper();
    }, 500);
  } else {
    modalStopper();
  }
  
  var checkSwitch = document.getElementById("tapsoundswitch");
  var inputEle = [...document.querySelectorAll("input")];
  var textareaEle = [...document.querySelectorAll("textarea")];
  var selectEle = [...document.querySelectorAll("select")];
  var buttonEle = [...document.querySelectorAll("button")];
  var ankerEle = [...document.querySelectorAll("a")];
  var audioFile = new Audio("./assets/audios/effect.mp3");
  var checkSwitchBool = "";

  // 有無を確かめてから触ります(理由は上の datetimeField と同じ。ここで例外が出ると、
  // この下の効果音・投稿カードのクリック記録・noJavaScriptMsgの消去まで巻き添えになります)。
  if (checkSwitch) {
    checkSwitch.checked = localStorage.getItem("checkSwitchSave") === "true";
  }

  function soundLogic() {
    if (checkSwitch && checkSwitch.checked === true) {
      audioFile.currentTime = 0;
      // 再生はブラウザの都合で拒まれることがあります。拒まれた時にここで未処理のまま
      // 例外にすると、押した側(投稿ボタン等)の処理まで一緒に止まります。
      var played = audioFile.play();
      if (played && typeof played.catch === "function") { played.catch(function () {}); }
      checkSwitchBool = true;
    } else {
      checkSwitchBool = false;
    }
    try { localStorage.setItem("checkSwitchSave", checkSwitchBool); } catch (e) {}
  }
  inputEle.map(function(item) {
    item.addEventListener("input", function() {
      soundLogic();
    });
  });
  textareaEle.map(function(item) {
    item.addEventListener("input", function() {
      soundLogic();
    });
  });
  selectEle.map(function(item) {
    item.addEventListener("change", function() {
      soundLogic();
    });
  });
  buttonEle.map(function(item) {
    item.addEventListener("click", function() {
      soundLogic();
    });
  });
  ankerEle.map(function(item) {
    item.addEventListener("click", function() {
      soundLogic();
    });
  });

  // POSTLISTを取得しセッションストレージに保存
  var postCard = Array.prototype.slice.call(document.getElementsByClassName("post"));
  for (var i = 0; i < postCard.length; i++) {
    postCard[i].addEventListener("click", function() {
      sessionStorage.setItem("postUrl", window.location.href);
    });
  };

  var noJsMsg = document.getElementById("noJavaScriptMsg");
  if (noJsMsg) { noJsMsg.innerHTML = ""; }
});

// ================================================================
// 新着のお知らせ
//
// 一定間隔で「新着があるか」だけをサーバへ聞き、増えていたらお知らせを出します。
// 一覧そのものは**押されるまで差し替えません**。読んでいる最中に表示が動くと、
// 読んでいた場所を見失うためです。
//
// 聞きに行く先(?pipsNewCheck=1)は、一覧のHTMLを作らず件数とidだけを返す軽い口です。
// getDispPost（一覧を丸ごと組み立て直す）を定期的に叩かないこと。負荷が見合いません。
// ================================================================
var PipsNewPosts = {
  intervalMs: 30000,   // 30秒ごと。短くするほどサーバへの問い合わせが増えます。
  timer: null,
  marker: null,        // 今表示している一覧の「新しさ」。{count, newest}

  // 一覧を描画した時点の目印を覚えます（getDispPostの応答に同梱されています）。
  remember: function (data) {
    if (!data) { return; }
    this.marker = { count: Number(data.count || 0), newest: String(data.newest || "") };
    this.hide();
  },

  // サーバが投稿を入れ終えたHTMLを返した回のための、目印だけの取得です。
  //
  // 【なぜ別に用意したか】目印は本来 getDispPost の応答に同梱されていて、一覧を描き直した
  // ついでに受け取ります。ところが投稿がもう画面に在る回はgetDispPostを呼ばないので、
  // 目印だけが手に入りません。marker が null のままだと check() が即座に戻り、
  // 「新しい投稿があります」が二度と出なくなります。
  //
  // 【一覧を取り直さないこと】ここで欲しいのは件数と先頭のidだけです。目印のために
  // 一覧のHTMLを丸ごと組み立てさせるのは、このクラスの冒頭に書いたとおり割に合いません。
  prime: function () {
    var params = new URLSearchParams(window.location.search);
    var query = "?pipsNewCheck=1";
    if (params.get("id")) { query += "&id=" + encodeURIComponent(params.get("id")); }

    var xhr = new XMLHttpRequest();
    xhr.open("get", "./" + query, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4 || xhr.status < 200 || xhr.status >= 300) { return; }
      try {
        var data = JSON.parse(xhr.responseText);
        if (data.ok) { PipsNewPosts.remember(data); }
      } catch (e) {
        // 目印が作れなくても、投稿はもう画面に出ています。お知らせが出ないだけなので黙って諦めます。
        console.warn("新着の基準づくりに失敗しました：" + e);
      }
    };
    xhr.send();
  },

  hide: function () {
    var el = document.getElementById("new_posts_notice");
    if (el) { el.hidden = true; el.innerHTML = ""; }
  },

  show: function (added) {
    var el = document.getElementById("new_posts_notice");
    if (!el) { return; }
    var label = added > 0 ? ("新しい投稿が" + added + "件あります") : "新しい投稿があります";
    el.innerHTML = '<button type="button" id="new_posts_load">' + label + '／Show new posts</button>';
    el.hidden = false;
    document.getElementById("new_posts_load").onclick = function () {
      PipsNewPosts.hide();
      urlHandle(); // 押された時だけ一覧を取り直す
    };
  },

  check: function () {
    if (this.marker === null) { return; } // まだ一覧を描けていない
    var params = new URLSearchParams(window.location.search);
    var query = "?pipsNewCheck=1";
    if (params.get("id")) { query += "&id=" + encodeURIComponent(params.get("id")); }

    var xhr = new XMLHttpRequest();
    xhr.open("get", "./" + query, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4 || xhr.status < 200 || xhr.status >= 300) { return; }
      try {
        var data = JSON.parse(xhr.responseText);
        if (!data.ok) { return; }
        var m = PipsNewPosts.marker;
        if (m === null) { return; }
        // 件数が増えたか、先頭の投稿が入れ替わっていたら新着とみなします。
        // （削除で件数が減ることもあるため、増えた時だけをお知らせにします）
        var added = Number(data.count || 0) - m.count;
        var topChanged = String(data.newest || "") !== m.newest;
        if (added > 0 || (topChanged && m.newest !== "")) {
          PipsNewPosts.show(added > 0 ? added : 0);
        }
      } catch (e) {
        // 新着の確認は失敗しても本来の閲覧に影響しないので、画面には出しません。
        console.warn("新着の確認に失敗しました：" + e);
      }
    };
    xhr.onerror = function () { /* 通信が切れているだけの可能性が高いので黙って見送ります */ };
    xhr.send();
  },

  start: function () {
    if (this.timer !== null) { return; }
    var self = this;
    this.timer = setInterval(function () {
      // 画面が見えていない間は聞きに行きません（裏で開きっぱなしのタブが
      // 延々と問い合わせ続けるのを避けるため）。
      if (document.hidden) { return; }
      self.check();
    }, this.intervalMs);
  }
};

// ================================================================
// 書きかけの自動保存
//
// 保存の本体はサーバ側(?draft_save)です。ここがやるのは「打鍵が止まったら、
// JSが無いときに人が押すのと同じボタンを、代わりに押す」だけです。
// 保存する場所も形式もJSの有無で変わらないので、途中でJSが動かなくなっても
// それまでにサーバへ届いた分はそのまま残ります。
//
// 【localStorageは"保険の保険"に降格しています】以前は書きかけを覚えているのが
// localStorageだけで、JSがうまく動かない環境ほど失いやすいという逆さまな状態でした。
// 今の役割は「サーバへまだ届いていない、直前の打鍵ぶんを拾う」ことだけです。
// サーバが値を持っていればそちらが常に優先されます（下のrestoreLocalIfEmpty参照）。
// ================================================================
var PipsDraft = {
  delayMs: 3000,   // 打鍵が止まってから保存するまで
  timer: null,

  fields: function () {
    return {
      subject: document.querySelector('#modal-1 [name="subject"]'),
      text: document.querySelector('#modal-1 [name="text"]'),
      sensitive: document.querySelector('#modal-1 [name="sensitive"]'),
      noConvert: document.querySelector('#modal-1 [name="no_convert_links"]')
    };
  },

  save: function () {
    var f = this.fields();
    if (!f.text) { return; }
    var tokenEl = document.querySelector('input[name="server_token"]');
    if (!tokenEl) { return; }

    var body = "draft_save=1&ajax=1"
      + "&server_token=" + encodeURIComponent(tokenEl.value)
      + "&subject=" + encodeURIComponent(f.subject ? f.subject.value : "")
      + "&text=" + encodeURIComponent(f.text.value);
    if (f.sensitive && f.sensitive.checked) { body += "&sensitive=1"; }
    if (f.noConvert && f.noConvert.checked) { body += "&no_convert_links=1"; }

    var xhr = new XMLHttpRequest();
    xhr.open("post", "./", true);
    xhr.withCredentials = true;
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4 || xhr.status < 200 || xhr.status >= 300) { return; }
      try {
        var data = JSON.parse(xhr.responseText);
        PipsComposer.refreshTokens(data.server_token);
        if (!data.ok) { return; }
        var stateEl = document.getElementById("draft_state");
        if (stateEl && data.saved_at) {
          stateEl.textContent = "下書きを " + data.saved_at + " に保存しました（画像・動画は選び直しが必要です）／Draft saved at " + data.saved_at;
        }
      } catch (e) {
        // 保存できなかったことを騒ぎ立てません。画面の時刻が増えないので、
        // 保存されていないことは利用者から見て分かります。
        console.warn("下書きの保存に失敗しました：" + e);
      }
    };
    xhr.send(body);
  },

  schedule: function () {
    var self = this;
    if (this.timer !== null) { clearTimeout(this.timer); }
    this.timer = setTimeout(function () { self.timer = null; self.save(); }, this.delayMs);
  },

  // サーバが何も持っていない欄だけ、直前の打鍵ぶん(localStorage)で埋めます。
  // サーバの値を上書きしないこと。上書きすると、保持の本体がどちらなのか分からなくなります。
  restoreLocalIfEmpty: function () {
    var f = this.fields();
    try {
      if (f.subject && f.subject.value === "") { f.subject.value = localStorage.getItem("subjectsave") || ""; }
      if (f.text && f.text.value === "") { f.text.value = localStorage.getItem("textsave") || ""; }
    } catch (e) { /* プライベートモード等でlocalStorageが使えない場合は何もしません */ }
  },

  start: function () {
    var self = this;
    var f = this.fields();
    this.restoreLocalIfEmpty();

    var onInput = function (key, el) {
      el.addEventListener("input", function () {
        try { localStorage.setItem(key, el.value); } catch (e) {}
        self.schedule();
      });
    };
    if (f.subject) { onInput("subjectsave", f.subject); }
    if (f.text) { onInput("textsave", f.text); }
    if (f.sensitive) { f.sensitive.addEventListener("change", function () { self.schedule(); }); }
    if (f.noConvert) { f.noConvert.addEventListener("change", function () { self.schedule(); }); }

    // 画面から離れるとき・隠れるときに、待たずに送っておきます。
    document.addEventListener("visibilitychange", function () {
      if (document.hidden && self.timer !== null) { clearTimeout(self.timer); self.timer = null; self.save(); }
    });
  }
};

// ================================================================
// 書き込みフォームの返信先の切り替え
//
// 【JSはHTMLを作りません】投稿一覧と同じ考え方です。返信先が変わったら、その事実を
// サーバへ知らせてセッションを更新し、PHPが組み立てたカードのHTMLを受け取って
// 差し込むだけにしてあります。以前はJS側で文字列を組み立て、hiddenの値だけを
// 書き換えていたため、サーバは何も知らないまま画面だけが「返信中」になり、
// 再読込すると新規投稿へ戻る、という宙に浮いた状態ができていました。
//
// 【通信できなかったときは普通のPOSTへ落とします】その場合はJSが無い環境と
// まったく同じ経路（サーバがセッションを更新してモーダルを開く）を通ります。
// 押したのに何も起きない、という終わり方をさせないためです。
// ================================================================
var PipsComposer = {
  // ページ内の全フォームのCSRFトークンを新しい値へ揃えます。
  // 返信先の切り替えでトークンが作り直されるため、これを怠ると次の送信が弾かれます。
  refreshTokens: function (token) {
    if (!token) { return; }
    var inputs = document.querySelectorAll('input[name="server_token"]');
    for (var i = 0; i < inputs.length; i++) { inputs[i].value = token; }
  },

  apply: function (data) {
    var slot = document.getElementById("composer_head_slot");
    if (slot && typeof data.html === "string") { slot.innerHTML = data.html; }
    var replyToEl = document.getElementById("reply_to");
    if (replyToEl) { replyToEl.value = data.reply_to || ""; }
    var subjectEl = document.getElementById("subject");
    if (subjectEl) { subjectEl.value = data.subject || ""; }
    this.refreshTokens(data.server_token);
  },

  /**
   * サーバへ状態の変更を伝え、PHPが組み立てたHTMLを受け取って差し込みます。
   * 返信・編集・削除のどれもこの1本を通します。
   *
   * body     … 送る内容（server_tokenとajaxはここで足します）
   * slotId   … 差し替える場所のid。nullならapplyFn側で処理します
   * buttonEl … 押されたボタン。通信できなかったときに、その所属フォームを
   *            普通にPOSTして「JSが無いときと同じ経路」へ落とすために使います
   */
  /**
   * 差し替える場所へ「今サーバに聞いている」と出します。
   * 一覧の取得(getDispPost)が同じことをしているのに合わせています。
   * これが無いと、押してから返事が来るまでの間、画面が前の状態のまま止まって見え、
   * 押せていないのか処理中なのか区別が付きません（回線の細い環境ほど長くなります）。
   */
  showPending: function (slotId) {
    if (!slotId) { return; }
    var slot = document.getElementById(slotId);
    if (!slot) { return; }
    slot.innerHTML = '<p class="composer_pending">'
      + '<img class="composer_pending_mark" src="./assets/images/loading.svg" alt="" width="16" height="16" />'
      + '状態を取得しています…／Checking…</p>';
  },

  /**
   * 返信先を取得している間、送信ボタンを押せなくします。
   *
   * 【なぜ止める必要があるか】返信先(hiddenのreply_to)が新しい値になるのは、
   * サーバから返事が届いた**後**です。その前に送信されると、フォームはまだ
   * 前に開いていた投稿のidを持ったままなので、**別の相手への返信として投稿されます**。
   * 押した本人には、狙った相手に返信したようにしか見えません。
   *
   * JSが無い環境では、そもそも返信ボタンが本物のPOSTで、返事が返ってから
   * 画面が作り直されるため、この待ち時間自体が存在しません。ここはJSで往復を
   * 省いたことで新しく生まれた隙間なので、JS側で塞ぎます。
   */
  pending: false,
  setSendBusy: function (busy) {
    this.pending = busy;
    var sendButton = document.querySelector('#modal-1 [name="postSend"]');
    if (!sendButton) { return; }
    if (busy) {
      // datasetが無い古いブラウザでも動くよう、要素そのものに控えます。
      if (typeof sendButton.pipsOriginalLabel !== "string") {
        sendButton.pipsOriginalLabel = sendButton.textContent;
      }
      sendButton.disabled = true;
      sendButton.textContent = "取得中…／Please wait";
    } else {
      sendButton.disabled = false;
      if (typeof sendButton.pipsOriginalLabel === "string") {
        sendButton.textContent = sendButton.pipsOriginalLabel;
      }
    }
  },

  send: function (body, slotId, buttonEl, applyFn, blocksSend) {
    var tokenEl = document.querySelector('input[name="server_token"]');
    if (!tokenEl) { return; }
    body += "&ajax=1&server_token=" + encodeURIComponent(tokenEl.value);

    this.showPending(slotId);
    // 返信先が変わる往復の間だけ、送信を止めます（編集・削除の往復は
    // フォームの返信先に影響しないので止めません）。
    if (blocksSend) { this.setSendBusy(true); }

    var done = function () { if (blocksSend) { PipsComposer.setSendBusy(false); } };

    var fallback = function () {
      // form.submit()はonsubmitハンドラを通らないので、ボタンに付いている
      // return false に邪魔されずそのまま送信されます。
      // 画面はこのあと本物のPOSTで作り直されるので、「取得しています」の表示は
      // そのままにしておきます（消すと一瞬空になって、かえって不安に見えます）。
      // 【送信の解除はここでも必ず行うこと】落とし先のPOSTが何らかの理由で
      // 始まらなかった場合、解除し忘れると送信ボタンが押せないまま残ります。
      done();
      if (buttonEl && buttonEl.form) { buttonEl.form.submit(); }
    };

    var xhr = new XMLHttpRequest();
    xhr.open("post", "./", true);
    xhr.withCredentials = true;
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) { return; }
      if (xhr.status < 200 || xhr.status >= 300) { fallback(); return; }
      try {
        var data = JSON.parse(xhr.responseText);
        if (!data.ok) { fallback(); return; }
        if (slotId) {
          var slot = document.getElementById(slotId);
          if (slot && typeof data.html === "string") { slot.innerHTML = data.html; }
        }
        PipsComposer.refreshTokens(data.server_token);
        if (applyFn) { applyFn(data); }
        done();
      } catch (e) {
        fallback();
      }
    };
    xhr.onerror = fallback;
    xhr.send(body);
  },

  setTarget: function (postId, postTitle, buttonEl) {
    var replyToEl = document.getElementById("reply_to");
    // 既にその状態なら、わざわざ聞きに行きません（サーバの状態は変わらないため）。
    if (replyToEl && replyToEl.value === postId) { return; }

    var body = postId === ""
      ? "reply_cancel=1"
      : "reply=1&reply_id=" + encodeURIComponent(postId) + "&reply_submit=" + encodeURIComponent(postTitle);

    this.send(body, "composer_head_slot", buttonEl, function (data) {
      // 件名欄と本文欄は差し替えの対象外です（書きかけを消さないため）。
      // 件名だけはサーバが決めた値（RE：付き）に合わせます。
      var replyToEl = document.getElementById("reply_to");
      if (replyToEl) { replyToEl.value = data.reply_to || ""; }
      var subjectEl = document.getElementById("subject");
      if (subjectEl) { subjectEl.value = data.subject || ""; }
    }, true); // ← 返信先が変わるので、この往復の間は送信を止めます
  }
};

function setReplyInfo(postId, postTitle, buttonEl) {
  PipsComposer.setTarget(postId, postTitle, buttonEl);
}

// 編集・削除も返信と同じ形です。押されたことをサーバへ知らせ、PHPが組み立てた
// モーダルの中身を受け取って差し込むだけで、JSは中身を作りません。
//
// 【以前の作りに戻さないこと】かつては、編集できる投稿すべての本文と件名を
// 隠し要素(raw_text_<id> / raw_subject_<id>)として一覧のHTMLに埋め込んでおき、
// JSがそれを読んで編集欄へ写していました。本文が画面の中に二重に存在することになり、
// 自分の投稿が多い人ほどページが重くなっていました。加えて、権限の確認を通らずに
// 中身を埋められるため、サーバが持っている状態と画面がずれる余地もありました。
function setEditInfo(postId, buttonEl) {
  PipsComposer.send("edit_request=" + encodeURIComponent(postId), "edit_modal_slot", buttonEl, null);
}

function setDeleteInfo(postId, buttonEl) {
  PipsComposer.send("delete_request=" + encodeURIComponent(postId), "delete_modal_slot", buttonEl, null);
}

// 感想スタンプのモーダル。編集・削除と同じく、中身(数と押すボタン)はPHPが作ります。
function setStampInfo(postId, buttonEl) {
  PipsComposer.send("stamp_request=" + encodeURIComponent(postId), "stamp_modal_slot", buttonEl, null);
}

// 現在のURLのクエリパラメータ（id, at, q, search_text, search_subject, search_name, sort等）はそのまま維持しつつ、
// pageパラメータだけを書き換えたURLを作ります。
// これが無いと、ページ送りのたびに検索・並び替え条件などの元のパラメータが失われてしまいます。
function buildPaginationUrl(page) {
  var params = new URLSearchParams(window.location.search);
  params.set('page', page);
  return './?' + params.toString();
}

function nextPage() {
  var currentPage = parseInt(getParameterByName('page')) || 1;
  urlHandle(buildPaginationUrl(currentPage + 1));
}

function prevPage() {
  var currentPage = parseInt(getParameterByName('page'), 10) || 1;
  var idParam = getParameterByName('id');

  if (idParam !== null && currentPage === 1) {
    urlHandle(sessionStorage.getItem("postUrl") ? sessionStorage.getItem("postUrl") : "./");
    sessionStorage.removeItem("postUrl");
    return;
  }

  urlHandle(buildPaginationUrl(currentPage - 1));
}

function getParameterByName(name, url) {
  if (!url) url = window.location.href;
  name = name.replace(/[\[\]]/g, '\\$&');
  var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
    results = regex.exec(url);
  if (!results) return null;
  if (!results[2]) return '';
  return decodeURIComponent(results[2].replace(/\+/g, ' '));
}
// シェアボタンのリンク処理
function copyToClipboard(text) {
  // モーダルを作成
  showShareModal(text);
}

function showShareModal(shareUrl) {
  // すでにある場合は削除して作り直す
  const existingModal = document.getElementById("shareModal");
  if (existingModal) {
    existingModal.remove();
    window.location.hash = "shareClose";
  }
  window.location.hash = "shareDisp";

  const message = "私はこの方の投稿を応援したいです！、皆さんもアクセスして応援しましょう！！";
  const hashtags = "PusyuuWanko,Tech,PusyuuIPS";
  const encodedUrl = encodeURIComponent(shareUrl);
  const encodedMessage = encodeURIComponent(message);
  const encodedHashtags = encodeURIComponent(hashtags);

  const modal = document.createElement("div");
  modal.id = "shareModal";
  modal.style.position = "fixed";
  modal.style.top = "0";
  modal.style.left = "0";
  modal.style.width = "100%";
  modal.style.height = "100%";
  modal.style.backgroundColor = "rgba(0,0,0,0.6)";
  modal.style.backdropFilter = "blur(10px)";
  modal.style.display = "flex";
  modal.style.justifyContent = "center";
  modal.style.alignItems = "center";
  modal.style.zIndex = "9999";

  modal.innerHTML = `
    <div style="background: #fff; color: #000; padding: 20px; border-radius: 8px; width: 300px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.3);">
      <h3>この投稿をシェア</h3>
      <p style="font-size: 0.9em; word-break: break-all;">${shareUrl}</p>
      <div style="display: flex; justify-content: space-around; align-items: center; flex-wrap: wrap; gap: 10px; margin: 10px;">
        <button onclick="window.open('https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedMessage}&hashtags=${encodedHashtags}', '_blank')">Twitter</button>
        <button onclick="window.open('https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}', '_blank')">Facebook</button>
        <button onclick="window.open('https://social-plugins.line.me/lineit/share?url=${encodedUrl}', '_blank')">LINE</button>
        <button onclick="window.open('https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}', '_blank')">LinkedIn</button>
        <button onclick="window.open('https://api.whatsapp.com/send?text=${encodedMessage}%20${encodedUrl}', '_blank')">WhatsApp</button>
        <button onclick="window.open('mailto:?subject=${encodedMessage}&body=${encodedUrl}', '_blank')">Email</button>
        <button id="nativeShareBtn">その他の方法</button>
      </div>
      <button onclick="document.getElementById('shareModal').remove(); document.body.style.overflow = 'hidden'; window.location.hash = '#cs';">閉じる</button>
    </div>
  `;

  document.body.appendChild(modal);

  // ネイティブ共有
  const nativeBtn = document.getElementById("nativeShareBtn");
  if (navigator.share) {
    nativeBtn.onclick = function() {
      navigator.share({
        title: 'Pusyuu BBS投稿',
        text: 'この投稿をチェックしてみて！',
        url: shareUrl
      }).catch(console.error);
    };
  } else {
    nativeBtn.textContent = "クリップボードにコピー";
    nativeBtn.onclick = function() {
      var textarea = document.createElement("textarea");
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand("copy");
      document.body.removeChild(textarea);
      alert("Link copied to clipboard: " + text);
    };
  }
}

// コンソールへの警告処理
console.log("%c 注意事項：あなた、あるいは他人の情報を盗み出そうとしている人のたくらみですので、コードの入力や開発者ツールの使用を要求されて開いている場合は危険ですので使用を行わないでください。\n\nNote: This is the work of someone who is trying to steal your or someone else's information, so if you are required to enter code or use developer tools, please do not use it, as it is dangerous.", "font-size: 25px; color: #ff0000;");
// 初回起動メッセージ
window.onload = function() {
  const info = "プシューPIPS/PusyuuPIPSへようこそ！！、このサービスでは感想を投稿して共有できるサービスです。信憑性や信頼性の低い内容を見て投稿して楽しむサービスです。ご利用には利用規約プライバシーポリシーへの同意が必要です。\n\nWelcome to Shu PIPS/Pusyuu PIPS!! This service allows you to post and share your impressions. It is a service that enjoys seeing and posting content that is not credible or unreliable. You must agree to the Terms of Use and Privacy Policy.";
  let visitedBefore = localStorage.getItem("MessageBefore");
  if (visitedBefore === null) {
    alert(info);
    localStorage.setItem("MessageBefore", "true")
  } else {}
}

let validate = function() {
  let flag = true;
  removeElementsByClass("error-info");
  removeClass("error-form");
  if (document.form.name.value === "") {
    errorElement(document.form.name, "お名前が入力されていません。");
    flag = false;
  } else {
    if (!validateName(document.form.name.value)) {
      errorElement(document.form.name, "アルファベットと”-”以外の文字が入っています。");
      flag = false;
    }
  }
  if (document.form.email.value !== "") {
    if (!validateMail(document.form.email.value)) {
      errorElement(document.form.email, "メールアドレスが正しくありません。");
      flag = false;
    }
  }
  if (document.form.item.value === "") {
    errorElement(document.form.item, "お問い合わせ項目が選択されていません。");
    flag = false;
  }
  if (document.form.content.value === "") {
    errorElement(document.form.content, "お問い合わせ内容が入力されていません。");
    flag = false;
  }
  return flag;
}
let errorElement = function(form, msg) {
  form.className = "error-form";
  let newElement = document.createElement("div");
  newElement.className = "error-info";
  newElement.style.color = "#ff0000";
  newElement.style.margin = "0px 5px 10px 5px";
  newElement.style.padding = "3px";
  newElement.style.border = "1px solid #ff0000";
  let newText = document.createTextNode(msg);
  newElement.appendChild(newText);
  form.parentNode.insertBefore(newElement, form.nextSibling);
}
let removeElementsByClass = function(className) {
  let elements = document.getElementsByClassName(className);
  while (elements.length > 0) {
    elements[0].parentNode.removeChild(elements[0]);
  }
}
let removeClass = function(className) {
  let elements = document.getElementsByClassName(className);
  while (elements.length > 0) {
    elements[0].className = "";
  }
}
let validateMail = function(val) {
  if (val.match(/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/) == null) {
    return false;
  } else {
    return true;
  }
}
let validateNumber = function(val) {
  if (val.match(/[^0-9]+/)) {
    return false;
  } else {
    return true;
  }
}
let validateTel = function(val) {
  if (val.match(/^[0-9-]{6,13}$/) == null) {
    return false;
  } else {
    return true;
  }
}
let validateName = function(val) {
  if (val.match(/^[a-z,A-Z,-]+$/) == null) {
    return false;
  } else {
    return true;
  }
}