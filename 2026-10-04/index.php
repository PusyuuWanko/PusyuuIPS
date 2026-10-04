<?php

// ================================================================
// 設定(置き場所)
//
// pips が読み書きするファイルやフォルダの置き場所(パス)は、すべてここに並べてあります。
// **パスはここ以外に書き足さないこと**。動かす場所を変えたときに、ファイル中を探して
// 回らずに済むようにです。
// 値を変えるときは、各行のコメント(その値でなければならない理由)を先に読んでください。
//
// パスはすべて index.php のあるフォルダからの相対です。
// ================================================================

// --- 置き場所(pips の中) -----------------------------------------

// 投稿データの控え。投稿APIに届かないときだけ読み書きする、本体とは同期しない物です
// (本体の写しではありません。空や壊れた中身のときに読み出しがエラーになるのは正常です)。
const PIPS_POST_FILE = "./assets/posts/data.json";
// 通報された投稿の記録。
const PIPS_DISABLE_LOG_FILE = "./assets/posts/disable_post.json";
// 閲覧数などの重複防止台帳(「この人はもう数えたか」だけを持つ)。
// 【鍵つきハッシュ以外を書かないこと】公開フォルダの中にあります(理由は PipsPostIO の台帳の解説)。
const PIPS_LEDGER_FILE = "./assets/posts/count_ledger.json";
// サイトマップと、その作り直しの記録。
const PIPS_SITEMAP_FILE = "./sitemap.xml";
const PIPS_SITEMAP_DATE_FILE = "./assets/documents/sitemap_generation_date.json";
// 調べ物のときだけ使う記録(debugTrace())。
const PIPS_DEBUG_TRACE_FILE = __DIR__ . "/debug_trace.log";

// --- 置き場所(pips の外) -----------------------------------------

// 共有ライブラリの置き場所。pipsは管理画面(oppai)の隣に置く前提で、この配置でしか
// 使いません(単独で動かすときは main/ ごと無いのが普通で、その場合は全部が
// 「読めなかった」になり、各機能が畳まれた状態で動きます。PipsLibraries 参照)。
const PIPS_LIB_DIR = "./../main/pusyuusystem/scripts/php_scripts/";
// 非公開フォルダ(Webから開けない場所)。鍵ファイルは PipsHiddenKeys がここからだけ読み書きします。
// 閲覧数・感想スタンプの重複防止(IPに鍵をかけたハッシュ)は、この中の鍵が最後の砦です。
// 鍵が使えないと、ログインしていない人の閲覧と、感想スタンプを数えません。
//
// 【置き場所を変えるときはここを書き換えること】初期値は、プシューのサービス群が共有している
// 隣のフォルダです。pips を単独で置く・公開するときは、その環境で**公開フォルダの外**にある
// フォルダを指定してください(フォルダが無ければ pips は作りません。理由は PipsHiddenKeys)。
// 【固定のパス1つだけにすること。上へ登って探さないこと】関係ない場所に置かれたとき、
// 無関係な同名フォルダの鍵を掴む危険があります。
const PIPS_HIDDEN_DIR = "../.pusyuuHiddenFiles";

// 他のプロダクト(main 等)の住所の頭。台帳(pusyuu_registry)から決まります。
// 【ドメインを書き戻さないこと】以前はテンプレートに https://pusyuuwanko.com 等を固定で
// 書いていたため、IPやLAN、別のドメインから入った人の画面でも公開ドメインへ取りに
// 行っていました。$public は OGP のように、よそ(SNS)が取りに来るものだけ true。
// 台帳の係が無い・読めないときは空文字(=このページのホストの / から)に倒れます。
function pipsSiteBase(string $id, bool $public = false): string {
  return class_exists('PusyuuRegistryClient') ? PusyuuRegistryClient::baseHtml($id, $public) : '';
}
// ================================================================
// セッションの窓口(pipsの「顔」)
//
// セッションの実体は共有スクリプト pusyuu_session.php(PusyuuSession)が持ちます。
// 保存先をどこにするか・合言葉をどう回すか・Cookieの属性をどうするかは、すべて
// 向こうの都合で、pipsは何も知りません。pipsが知っているのは「値を置く・読む・消す」と
// 「ログインの資格(accounts_token)は機密として預ける」ことだけです。
//
// 【本文から $_SESSION を直接触らないこと】共有スクリプトが使える環境では、セッションは
// PHP標準の仕組みの外に保存されます。そのとき $_SESSION はただの空の配列で、書いても
// 次のページには何も残りません。エラーも警告も出ず、「ログインしても次のページで
// ログアウトしている」「送った通知が出ない」という形でだけ表に出ます。
//
// 【共有スクリプトが無い環境でも動くこと】PipsAccountFeature と同じ考え方です。
// pusyuu_session.php が見つからなければ、以前と同じPHP標準のセッションで動きます。
// 各メソッドが「共有スクリプト経由」と「PHP標準」の2本の枝を持つのはそのためで、
// どちらの枝も同じ結果を返すように揃えてあります。片方だけ直さないでください。
// ================================================================
class PipsSession {
  // 共有スクリプトを通しているか。start() で1回だけ決まり、以後は変わりません。
  private static $viaLibrary = false;

  /** セッションを始めます。出力より前に1回だけ呼んでください。 */
  public static function start() {
    if (class_exists('PusyuuSession')) {
      self::$viaLibrary = true;
      // 保存先の選び方もCookieの属性も、共有スクリプト側が決めます。
      PusyuuSession::startBestAvailable();
    } else {
      self::$viaLibrary = false;
      // 共有スクリプトが無い環境。以前のpipsとまったく同じ始め方をします。
      //
      // 【SameSiteは必ずLax】Strictにすると、メイキィのログイン画面から戻ってきた
      // トップレベル遷移にCookieが送られず、毎回「別のセッション」として扱われます。
      // 往路で控えたstateごと消えるので照合が必ず外れ、ログインが永久に成立しません。
      // php.ini任せにしていると、サーバ設定が変わっただけで黙ってこの状態になります。
      //
      // 【secureは実際にHTTPSで来ているかで決める】無条件にtrueにすると、HTTPで
      // 配信されている環境ではブラウザがセッションCookieを一切保存できず、ログイン状態が
      // 1リクエストも保ちません。逆に無条件にfalseだと、HTTPS環境で保護が1段落ちます。
      // (共有スクリプト側の PusyuuSession も同じ判定で揃えてあります。)
      $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
      session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
      session_cache_limiter("nocache");
      session_start();
    }
  }

  public static function get($key, $default = null) {
    $result = $default;

    if (self::$viaLibrary) {
      $result = PusyuuSession::get($key, $default);
    } else {
      $result = $_SESSION[$key] ?? $default;
    }

    return $result;
  }

  public static function set($key, $value) {
    if (self::$viaLibrary) {
      PusyuuSession::set($key, $value);
    } else {
      $_SESSION[$key] = $value;
    }
  }

  /** isset() と同じ意味です(値が null なら false)。 */
  public static function has($key) {
    $result = false;

    if (self::$viaLibrary) {
      $result = PusyuuSession::has($key);
    } else {
      $result = isset($_SESSION[$key]);
    }

    return $result;
  }

  public static function forget(...$keys) {
    if (self::$viaLibrary) {
      PusyuuSession::forget(...$keys);
    } else {
      foreach ($keys as $key) {
        unset($_SESSION[$key]);
      }
    }
  }

  /**
   * $key の配列の末尾へ足します($_SESSION[$key][] = $value の代わり)。
   * 【get して足して set する形に分けないこと】set を書き忘れた箇所だけ、
   * 足したはずの値が黙って消えます。1つの操作として閉じてあります。
   */
  public static function push($key, $value) {
    if (self::$viaLibrary) {
      PusyuuSession::push($key, $value);
    } else {
      if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
      }
      $_SESSION[$key][] = $value;
    }
  }

  /**
   * 機械(クローラ・AIの取得・道具)らしい要求か。閲覧数などを数えない判断に使います。
   *
   * 【共有スクリプトが無いときは false(人として扱う)】ライブラリ自身の方針が
   * 「判断できないときは人として扱う」です(人を機械と誤って締め出す方が害が大きいため)。
   * 無いときは全員が「判断できない」なので、その方針に揃えます。機械の閲覧が数に
   * 入るだけで、これは以前の pips(機械を除外していなかった)と同じ状態です。
   */
  public static function looksLikeRobot() {
    $result = false;

    if (self::$viaLibrary) {
      $result = PusyuuSession::looksLikeRobot();
    } else {
      $result = false;
    }

    return $result;
  }

  /**
   * 他の人にも見える物を動かす操作(みんなに見える数を足す等)を、このセッションに許してよいか。
   *
   * 【共有スクリプトが無いときは true】合言葉(クッキー無しで運ぶセッション)の段位を
   * 持っているのはライブラリの側だけです。無いときのセッションは常にクッキーで運ぶ
   * 通常のもので、ライブラリ自身もその場合は true と答えるので、それに揃えます。
   */
  public static function mayAffectOthers() {
    $result = true;

    if (self::$viaLibrary) {
      $result = PusyuuSession::mayAffectOthers();
    } else {
      $result = true;
    }

    return $result;
  }

  /**
   * 共有スクリプト(pusyuu_session.php)を通しているか。
   *
   * クッキーを使えない人のためのセッション(URLの札・合言葉で運ぶ)は、共有スクリプトの側に
   * しかありません。false のときも閲覧数・感想スタンプは止めませんが(PHP標準のセッションで動き、
   * 重複防止の最後の砦は鍵をかけたIPの台帳です)、クッキーの無い人はページを開くたびに別の
   * セッションになるので、知らせの枠でその旨を伝えます(PipsDispData::run())。
   */
  public static function usingLibrary() {
    return self::$viaLibrary;
  }

  /** 今のセッションID。記録(debugTrace)にだけ使います。 */
  public static function id() {
    $result = "";

    if (self::$viaLibrary) {
      $result = PusyuuSession::id();
    } else {
      $result = (string)session_id();
    }

    return $result;
  }

  /** IDを振り直します。ログイン(権限の昇格)の直後に必ず呼ぶこと(セッション固定攻撃の防止)。 */
  public static function regenerateId() {
    if (self::$viaLibrary) {
      PusyuuSession::regenerateId(true);
    } else {
      session_regenerate_id(true);
    }
  }

  /**
   * セッションを破棄して、新しい空のセッションで始め直します。
   * 【出力より前に呼ぶこと】新しいCookieを送るため、出力の後では効きません。
   */
  public static function reset() {
    if (self::$viaLibrary) {
      PusyuuSession::destroy();
      PusyuuSession::start();
      PusyuuSession::regenerateId(true);
    } else {
      session_unset();
      session_destroy();
      session_start();
      session_regenerate_id(true);
    }
  }

  /**
   * ログインの資格のような、漏れると困る値を預けます。
   *
   * 【使える場所では必ず機密の枠へ】共有スクリプトの自前の保存先では、機密は利用者の
   * 合言葉からしか開けない鍵で封印され、画面やログへ出しても伏せ字になります。
   * 普通の set() で置くと、この保障が何も効きません。
   *
   * 【保障できない環境では、以前と同じ普通の値として持ちます】共有スクリプトが無い、
   * または暗号化キーが無くPHP標準で動いている場合です。ここで預けるのを拒むと、
   * そういう環境ではログインそのものが成立しなくなります。以前のpipsと同じ状態に
   * 戻るだけなので、止めずに進めます。
   */
  // $kind = この機密を「どうやって失効させるか」の種別。渡しておくと、セッションが
  // 破棄・期限切れになった時に「kind と sha256(値)」が失効保留箱へ積まれ、その kind を
  // 失効できる者が消します。PIPS自身はメイキィのトークンを消す手段(生トークンもAPIも)を
  // 持ちませんが、保留箱は共有の非公開層にあるので、次に誰かがメイキィを開いた時に
  // メイキィ側(onRevoke 登録)が排出して消します。渡さないと手掛かりが積まれず、
  // セッションを消してもトークンは寿命(最長30日)まで生き残ります。
  public static function keepSecret($name, $value, $kind = null) {
    if (!self::$viaLibrary) {
      $_SESSION[$name] = $value;
    } else if (!PusyuuSession::canKeepSecrets()) {
      PusyuuSession::set($name, $value);
    } else if (!PusyuuSession::putSecret($name, $value, $kind)) {
      error_log("[PIPS] 機密 " . $name . " をセッションへ預けられませんでした。");
    }
  }

  /**
   * keepSecret() で預けた値の生の中身。無ければ空文字。
   * 【外部APIへ渡す直前にだけ呼ぶこと】戻り値を画面・ログ・URL・hiddenへ出さないでください。
   */
  public static function secretValue($name) {
    $result = "";
    $wrapped = null;

    if (!self::$viaLibrary) {
      $result = (string)($_SESSION[$name] ?? "");
    } else if (!PusyuuSession::canKeepSecrets()) {
      $result = (string)PusyuuSession::get($name, "");
    } else {
      $wrapped = PusyuuSession::secret($name);
      $result = ($wrapped === null) ? "" : $wrapped->reveal();
    }

    return $result;
  }
}

// ================================================================
// 共有ライブラリの読み込み(pipsの「入口」)
//
// pipsが使う共有ライブラリ(main の中の php_scripts)は、すべてここで読み込みます。
// どれも「無ければ飛ばして、その機能だけ畳んで動く」作りなので、読めなくてもページは
// 止まりません。止まらないからこそ、読めていないことに誰も気づけません。
// 以前は読み込みがファイルのあちこちに同じ書き方で散らばっていて、セッションの
// ライブラリが読めずにPHP標準のセッションへ切り替わっても、ログにも画面にも何も
// 出ませんでした。ここは「読めなかった物を覚えて、知らせる」ために1か所にしてあります。
//
// 【ライブラリを足すときは、ここに1行足すこと】ファイルの途中で直に require すると、
// そのライブラリだけが「読めなくても誰も気づかない」状態に戻ります。
//
// 【loadAll() は PipsSession::start() より前に呼ぶこと】セッションのライブラリが
// 読まれる前に始めると、ライブラリがあってもPHP標準のセッションで始まります。
// 症状は「クッキー無しの人のセッションが続かない」「ログインの資格が暗号化されない」で、
// どちらも見た目には分かりません。
//
// 【関数の中で読み込んで大丈夫な理由】ライブラリのトップレベルの変数は、この関数の
// 中だけの変数になります。今のライブラリはどれも、トップレベルの変数を後から
// global で読む作りになっていないので問題ありません(panic_handler は $GLOBALS に
// 直接書いています)。global に頼るライブラリを足すときは、ここを見直してください。
// ================================================================
class PipsLibraries {
  // 共有ライブラリの置き場所は、先頭の設定(PIPS_LIB_DIR)にあります。

  // 読めなかったライブラリ。ファイル名 => ['visible' => 利用者の目に見えて動きが変わるか, 'stops' => 止まるもの]
  private static $missing = [];

  /**
   * pipsが使う共有ライブラリを、決まった順に全部読み込みます。1回の要求につき1回だけ。
   *
   * 2つ目の引数(visible)は「読めないと、利用者の目に見える形で動きが変わるか」です。
   * true のものが1つでも読めなければ、投稿一覧のシステムの知らせに1行足します(needsNotice())。
   * 【迷ったら true にすること】知らせが余分に出るのは害が小さく、黙っているのは
   * 「誰も気づかないまま何日も壊れている」という形で表に出ます。
   */
  public static function loadAll() {
    self::load("pusyuu_session.php", true, "PHP標準のクッキーのセッションに切り替わります(クッキー無しの人のセッションが続かない・ログインの資格が暗号化されない・機械の判定ができない)。"
      . "閲覧数と感想スタンプは止めずに動きますが、クッキー無しの人はページごとに別のセッションになるため、重複防止が鍵つきIPの台帳だけになり加算があいまいになります"
      . "(同じIPの別人は数えられず、IPが変わった同じ人は数え直されます)。クッキー無しの人は感想スタンプの選び直し・取り消しもできません");
    self::load("panic_handler.php", false, "致命的なエラーのときに専用の画面が出ず、PHPの既定の動きになります");
    self::load("usage_tracker.php", false, "pipsの利用状況が記録されません");
    self::load("pusyuu_registry_client.php", true, "投稿APIに繋がらずローカルの控え(data.json)で動きます。通知・プロダクト一覧・他のプロダクトへのリンクが止まります");
    self::load("meikiee_client.php", true, "ログイン・フォロー・お気に入りの保存(ログイン中の分)が使えません");
    self::load("notation.php", false, "フッターの表記と著作権表示が空になります");
  }

  private static function load($file, $visible, $stops) {
    $path = PIPS_LIB_DIR . $file;

    if (file_exists($path)) {
      require_once($path);
    } else {
      self::$missing[$file] = ['visible' => $visible, 'stops' => $stops];
      // 知らせる画面はきっかけで、原因を調べる手掛かりはこの記録です。画面には
      // ファイル名を出さないので、どれが読めなかったかはここでしか分かりません。
      error_log("[PIPS] 共有ライブラリを読めませんでした: " . $path . " / 止まるもの: " . $stops);
    }
  }

  /**
   * 利用者に知らせる必要があるか(利用者の目に見えて動きが変わるライブラリが読めなかったか)。
   *
   * このクラスが答えるのは「要るかどうか」だけです。文言と見た目は、投稿一覧の他の
   * システムの知らせと一緒に PipsDispData::run() と PipsPostTemplate が持ちます。
   * 【ここでHTMLを作らないこと】別の枠を作ると、検索の案内などと枠が縦に並びます。
   */
  public static function needsNotice() {
    $result = false;

    foreach (self::$missing as $info) {
      if ($info['visible']) {
        $result = true;
      }
    }

    return $result;
  }
}

// ================================================================
// 非公開フォルダ(.pusyuuHiddenFiles)の鍵ファイル
//
// pipsが使う鍵ファイルは、読むのも作るのもここだけです。中身の形は
// "<?php\nreturn '<32バイトをbase64にした文字列>';\n" で、メイキーの鍵ファイルと同じです。
//
// 【固定の相対パス1つだけを見ること。上へ登って探さないこと】規約どおりです。
// 以前 PipsPush は __DIR__ から最大8段上まで探していました。関係ない場所に置かれたとき、
// 無関係な同名フォルダの鍵を掴む危険があります。無ければ見つからないと答え、理由を返します。
// ================================================================
class PipsHiddenKeys {
  // 非公開フォルダの指定は、先頭の設定(PIPS_HIDDEN_DIR)の1か所だけです。

  /**
   * 鍵ファイルを読みます。作りません。
   *
   * 戻り値は ['key' => 32バイトの鍵 or null, 'state' => 状態, 'problem' => ログ用の理由(使えるなら空文字)]。
   * state は次のどれかです。呼び出し側は、利用者への知らせをこれで分けてください。
   *   ok        … 使える
   *   no_folder … 非公開フォルダが無い(置き場所そのものが不完全)
   *   missing   … フォルダはあるが鍵ファイルが無い
   *   broken    … 鍵ファイルはあるが読めない・32バイトの鍵ではない
   */
  public static function read($name) {
    $path = PIPS_HIDDEN_DIR . "/" . $name;
    $result = ["key" => null, "state" => "", "problem" => ""];

    if (!is_dir(PIPS_HIDDEN_DIR)) {
      $result = ["key" => null, "state" => "no_folder", "problem" => "非公開フォルダが見つかりません(" . PIPS_HIDDEN_DIR . ")"];
    } else if (!is_file($path)) {
      $result = ["key" => null, "state" => "missing", "problem" => "鍵ファイルがありません(" . $path . ")"];
    } else {
      $key = null;
      // 別の要求が書いている途中のファイルを読むと、PHPとして壊れていて ParseError になります。
      // その要求では鍵を使わないだけにして、ページ全体を巻き込まないようにします。
      try {
        $encoded = require $path;
        $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
        $key = ($decoded !== false && strlen($decoded) === 32) ? $decoded : null;
      } catch (Throwable $e) {
        $key = null;
      }

      if ($key === null) {
        $result = ["key" => null, "state" => "broken", "problem" => "鍵ファイルの中身が読めないか、32バイトの鍵ではありません(" . $path . ")"];
      } else {
        $result = ["key" => $key, "state" => "ok", "problem" => ""];
      }
    }

    return $result;
  }

  /**
   * 鍵ファイルを読みます。フォルダがあって鍵ファイルだけが無いときに限り、作ります
   * (メイキーの loadOrCreateStorageKey() と同じ考え方)。戻り値の形と state は read() と同じで、
   * 作れなかったときは次のどちらかになります。
   *   unwritable   … フォルダはあるが書き込めない
   *   write_failed … 作り始めたが最後まで書けなかった
   *
   * 判断の順番(この順を崩さないこと):
   *   1. フォルダが無い → 作らない(no_folder)。フォルダが無いのは置き場所が不完全な証拠で、
   *      ここで勝手にフォルダや鍵を作ると、公開フォルダの中など意図しない場所に鍵ができます。
   *   2. 鍵ファイルがある → 読むだけ。壊れていても作り直さない(broken)。
   *      上書きすると、手で直せば戻せたはずの鍵が消えます。投稿データの控えが壊れているときに
   *      止めて「破損しています」と知らせるのと同じ扱いです(PipsPostIO::localRead())。
   *   3. フォルダはあるが鍵ファイルが無い → 作る。
   *
   * 【失っても困らない鍵にだけ使うこと】作り直された鍵では、前の鍵で作った物が一致しなくなります。
   * 他のサービスと共有している鍵(pips_account_key.php 等)には使わず、read() を使ってください。
   * 【作るのは「まだ無いとき」だけ(fopen の 'x')】初めての要求が2つ同時に来ると、
   * 両方が鍵を作り、後から書いた方が先の鍵を上書きします。'x' なら片方しか作れず、
   * 負けた側は相手の作った鍵を読みます。
   */
  public static function loadOrCreate($name) {
    $path = PIPS_HIDDEN_DIR . "/" . $name;
    $result = self::read($name);

    if ($result["state"] !== "missing") {
      // ok / no_folder / broken。どれも作らずに、読んだ答えのまま返します。
    } else if (!is_writable(PIPS_HIDDEN_DIR)) {
      $result = ["key" => null, "state" => "unwritable", "problem" => "非公開フォルダに書き込めないため、鍵を作れません(" . PIPS_HIDDEN_DIR . ")"];
    } else {
      $fp = @fopen($path, "x");

      if ($fp === false) {
        // 同時に来た別の要求が先に作った場合です。そちらの鍵を使います。
        $result = self::read($name);
      } else {
        $raw = random_bytes(32);
        $php = "<?php\nreturn '" . base64_encode($raw) . "';\n";
        $written = fwrite($fp, $php);
        fclose($fp);

        if ($written !== strlen($php)) {
          // 【書き切れなかった鍵を使わないこと】次の要求は別の中身を読むので、
          // 今作った物が二度と一致しなくなります。消して、次の要求で作り直させます。
          @unlink($path);
          $result = ["key" => null, "state" => "write_failed", "problem" => "鍵ファイルを最後まで書き込めませんでした(" . $path . ")"];
        } else {
          @chmod($path, 0600);
          $result = ["key" => $raw, "state" => "ok", "problem" => ""];
        }
      }
    }

    return $result;
  }
}

PipsLibraries::loadAll();
PipsSession::start();
// 古いInternet Explorerは、クロスドメインのSSOリダイレクト後に戻ってきた
// 側でCookie(セッション)が保持されない場合がある。IEはP3Pポリシーの宣言が
// 無いCookieを"サードパーティ的"とみなして拒否することがあるための既知の
// 挙動で、この宣言はその互換性のためだけのものです(実際のプライバシー
// ポリシーの内容を表すものではありません)。accounts側のログイン画面にも
// 同じ宣言を入れています。
header('P3P: CP="CAO PSA OUR"');

if (function_exists('pusyuuRecordProductUsage')) {
  pusyuuRecordProductUsage('pips');
}

// 機械(クローラ・AIの取得)の判定は、上の pusyuu_session.php の中にあります
// (PusyuuSessionRobot。以前は bot_detect.php という別ファイルでした)。
// PusyuuSession::looksLikeRobot() か、従来どおり pusyuuIsKnownCrawler() で使えます。

// 他のプロダクトの住所・サーバ間で繋ぐ先(投稿API・通知API)は、台帳(pusyuu_registry)から
// 受け取ります。無い環境では、投稿はローカルの控え(data.json)で動き、通知と一覧だけが止まります。
// (読み込みは PipsLibraries::loadAll() です)

function debugTrace(string $point, array $extra = []) {
  $line = sprintf(
    "[PIPS_TRACE] %s | sid=%s | point=%s | post_token=%s | session_token=%s | %s\n",
    date("Y-m-d H:i:s.u"),
    PipsSession::id(),
    $point,
    substr($_POST["server_token"] ?? "-", 0, 8),
    substr(PipsSession::get("server_token", "-"), 0, 8),
    json_encode($extra, JSON_UNESCAPED_UNICODE)
  );
  file_put_contents(PIPS_DEBUG_TRACE_FILE, $line, FILE_APPEND | LOCK_EX);
}

//エラーレポート設定
//error_reporting(E_ALL & ~E_NOTICE & ~E_PARSE & ~E_DEPRECATED);

// 出力タイプを HTML に設定
header("Content-Type: text/html; charset=utf-8");

// グローバル変数(ファイルの置き場所はファイル先頭の設定にあります)

// 投稿API(pusyuu_ips)は main(台帳のID)の中にあります。繋ぐ先と名乗るホスト名は
// 台帳から受け取ります(PipsPostIO::apiRequest)。
// 【ここに 127.0.0.1 やドメインを書き戻さないこと】以前は接続先を 127.0.0.1、Host を
// pusyuuwanko.com に固定していました。構成を変えるたびに、ここを探して直すことになります。
const PIPS_POST_API_REGISTRY_ID = 'main';
const PIPS_POST_API_PATH        = '/pusyuusystem/apis/pusyuu_ips/index.php';

$postDisplay = $nextPageUrl = $prevPageUrl = "";
$isNojs = $dispDoneProsessComleteFulg = $isRegularPage = $isNoIndex = false;

// =====================================================================
// アカウント機能(作成・編集・削除・ログイン・フォロー・お気に入り)の実体は
// pipsの中にはありません。共有スクリプトが提供する pusyuuAccount〜() の関数群へ
// 頼み、pipsはその答えを自分の言葉へ翻訳するだけです。
//
// 【なぜpipsが接続先もクラスも持たないか】
// 以前はここに、アカウント基盤と話すクラスの全文(500行あまり)が埋め込まれていました。
// 同じものが他のプロダクトにも複製されていて、「直したら他へ貼り替える」運用で
// 揃えることになっていましたが、実際には揃いませんでした。2026-09-06に調べたところ、
// 6つの複製のうちtoolboxのものだけが1文字違っており、そのせいでtoolboxの
// アカウント機能が全滅していました。持ち物が1つなら、そもそも食い違いようがありません。
//
// pipsが知っているのは「アカウント機能が使えるか / 使えないか」だけです。それがどこの
// 何で実現されているかは知りませんし、知る必要もありません。
//
// 【使うときは必ず PipsAccountFeature を通すこと】
// 本文から直接 pusyuuAccount〜() を呼ばないでください。共有スクリプトが無い環境では
// それらの関数自体が存在せず、「Call to undefined function」で致命的エラーになり、
// pipsのページが1行も表示されなくなります。投稿が読めなくなる、という壊れ方です。
// PipsAccountFeature は、その確認を1箇所にまとめて引き受けるためにあります。
// =====================================================================

// 読み込みは他の共有ライブラリと同じく PipsLibraries::loadAll() です。見つからなければ
// アカウント機能だけが畳まれた状態で普通に動きます。
//
// 【上へ探しに行く必要はありません】pipsは管理画面(oppai)の隣に置かれる前提で、
// この配置でしか使いません。単独で動かすというのは「階層をずらす」ことではなく
// 「別の環境へ丸ごと持って行く」ことなので、その先には main/ ごと無いのが普通です。
// それが狙いどおりの姿なので、探索を足しても得るものはありません。

// ================================================================
// pips独自のアカウント利用ロジック(pipsの「顔」)
//
// アカウント基盤との生のやり取りは共有スクリプトが持ちます。このクラスが持つのは
// 「pipsにとっての意味」だけです——自分のセッションの持ち方、フォロー・お気に入りと
// いったpips固有の機能、ログイン状態の解決など。
//
// 【本文からは必ずこのクラスを通すこと】
// pipsの本文(描画やPOST処理)から直接 pusyuuAccount〜() を呼ばないでください。
// 共有スクリプトが無い環境ではそれらの関数自体が存在せず、致命的エラーで
// pipsのページが1行も表示されなくなります。存在確認をこの1箇所に集めてあるので、
// 本文は「使えないときは空っぽの答えが返ってくる」とだけ思っていれば足ります。
//
// 【使えないときに何を返すか】
// 各メソッドは「アカウント機能が無い世界でのpipsの正解」を返します。フォロー数は0、
// フォロー中一覧は空配列、ログイン中ユーザーはnull。呼び出し側に新しい分岐を
// 増やさせないためです。投稿の閲覧・検索・ページ送りは、この状態でも普通に動きます。
// ================================================================
class PipsAccountFeature {
  /**
   * アカウント機能が今使えるかどうか。このクラスの他のメソッドは全部これを先に見ます。
   * 画面に「ログイン」ボタンを出すかどうかの判断にも使ってください。基盤へ届かない
   * ときにボタンを出すと、押した利用者は行き止まりに突き当たります。
   */
  public static function ready() {
    $result = false;

    if (!function_exists('pusyuuAccountReady')) {
      $result = false;
    } else {
      $result = pusyuuAccountReady();
    }

    return $result;
  }

  /**
   * 使えないときの理由(利用者にそのまま見せてよい1文)。使える場合は空文字。
   * 【JavaScriptに頼らないこと】この文字列はPHPがHTMLとして出力します。JSが無い
   * 環境でも、なぜアカウント機能が使えないのかが読めなければ意味がありません。
   */
  public static function unavailableReason() {
    $result = "";

    if (!function_exists('pusyuuAccountUnavailableReason')) {
      $result = "このサーバにはアカウント連携機能が設置されていないため、ログインとフォローはご利用いただけません。投稿の閲覧と書き込みは通常どおりご利用いただけます。";
    } else {
      $result = pusyuuAccountUnavailableReason();
    }

    return $result;
  }

  /**
   * 「アカウント機能が使えるなら $work を実行し、使えないなら $fallback を返す」。
   * このクラスのほとんどのメソッドはこの形をしているので、判定を1箇所に集めています。
   *
   * 【なぜまとめたか】以前は各メソッドの先頭に同じ if (!self::ready()) が並んでいました。
   * 12箇所もあると、新しいメソッドを足すときに1つ書き忘れても誰も気づけません。
   * 書き忘れた1つが、アカウント基盤の無い環境で致命的エラーを起こし、pipsのページが
   * まるごと真っ白になります。ここを通す形にしておけば、そもそも書き忘れようがありません。
   *
   * 【$fallback は先に評価されます】引数なので、使える場合でも組み立てだけは走ります。
   * 空配列・0・null・短い文字列のような軽いものだけを渡してください。ここで重い処理や
   * 通信を伴うものを渡すと、正常時にも毎回それが走ってしまいます。
   */
  private static function whenReady(callable $work, $fallback) {
    $result = null;

    if (!self::ready()) {
      $result = $fallback;
    } else {
      $result = $work();
    }

    return $result;
  }

  /**
   * 直近のログインが失敗していれば、その理由を1度だけ返します(読んだら消えます)。
   * 失敗していなければ空文字。ページ描画の前に1回だけ呼んでください。
   */
  public static function takeSignInError() {
    $result = "";

    if (!function_exists('pusyuuAccountTakeSignInError')) {
      $result = "";
    } else {
      $result = pusyuuAccountTakeSignInError();
    }

    return $result;
  }

  // このサイト自身のセッションに機密として預けているログイントークン(PipsSession::keepSecret())。
  // 【戻り値を画面・ログへ出さないこと】pusyuuAccount〜() へ渡す引数の位置でだけ呼んでください。
  public static function token() {
    return PipsSession::secretValue("accounts_token");
  }

  /** pips自身がログイン中だと思っているかどうか。 */
  public static function isLoggedIn() {
    return PipsSession::get("user_logged_in") === true;
  }

  /**
   * ログイン中ユーザーの詳細(email・保存済みデータ込み)。未ログインなら通信せずnull。
   *
   * 【失敗しても、いつでもログアウトさせてよいわけではありません】
   * 以前ここは「ok以外なら問答無用で allResetSession()」と書かれていました。その結果、
   * アカウント基盤が一時的に落ちているだけで利用者のセッションが丸ごと破棄され、
   * SSOの控え(state)まで消えるため、ログインし直そうとしても必ず失敗する、という
   * 状態になっていました。2026-09-06のerror.logに「stateが一致しない」が並んでいたのは
   * これが原因の候補です。
   * 畳んでよいのは「そのトークンはもう無効だ」と基盤が答えたときだけです。
   * 届かなかっただけのときは、何もせずnullを返して次の機会を待ちます。
   */
  public static function currentAccount() {
    // 「聞きに行くまでもない」2つの事情と、「聞きに行った結果」の3つを一続きに並べます。
    // 聞きに行くのは最後の枝だけなので、通信が起きる条件がこの1画面で読み取れます。
    $res    = null;
    $result = null;

    if (!self::isLoggedIn()) {
      // pips自身がログイン中だと思っていない。聞く相手がいません。
      $result = null;
    } else if (!self::ready()) {
      // アカウント基盤が無い/届かない。聞きに行っても待たされるだけです。
      $result = null;
    } else {
      $res = pusyuuAccountSelf(self::token());

      if (!empty($res["ok"])) {
        $result = $res["user"];
      } else if (!empty($res["rejected"])) {
        // 「そのトークンはもう無効だ」と基盤がはっきり答えた場合だけ畳みます。
        allResetSession();
        PipsSession::set("user_logged_in", false);
        $result = null;
      } else {
        // 届かなかっただけ。何もせず次の機会を待ちます(ここで畳まないこと)。
        $result = null;
      }
    }

    return $result;
  }

  // ユーザー名から公開プロフィール(userid/username/name等)を取得します。見つからなければnull。
  public static function profile($username) {
    return self::whenReady(static function () use ($username) {
      return pusyuuAccountProfile($username);
    }, null);
  }

  // ----------------------------------------------------------------
  // フォロー・お気に入り(likes)
  //
  // アカウント基盤は「service名+key名を指定してもらえば、その中身の意味を知らなくても
  // 保存・集計できる」という汎用の入れ物しか提供しません。「フォロー」や「お気に入り」が
  // pipsにとって何を意味するかは、その汎用の入れ物を組み合わせてpips自身が組み立てます。
  // followingにはユーザー名ではなく、相手のuserid(sha256ハッシュ)を保存します。
  // pipsは他ユーザーの生idを知り得ないため、これだけで十分安全な参照になります。
  // ----------------------------------------------------------------

  /** 自分自身のフォロー中一覧(useridハッシュの配列)。取得に失敗したら空配列。 */
  public static function myFollowingList() {
    return self::whenReady(static function () {
      $res    = pusyuuAccountDataGet(self::token(), 'pips', 'following');
      $result = [];

      if (!empty($res['ok']) && is_array($res['value'] ?? null)) {
        $result = $res['value'];
      } else {
        $result = [];
      }

      return $result;
    }, []);
  }

  public static function isFollowing($targetUserid) {
    return in_array($targetUserid, self::myFollowingList(), true);
  }

  public static function followingCount($username) {
    return self::whenReady(static function () use ($username) {
      return pusyuuAccountListCount($username, 'pips', 'following');
    }, 0);
  }

  public static function followerCount($useridHash) {
    return self::whenReady(static function () use ($useridHash) {
      return pusyuuAccountReverseCount($useridHash, 'pips', 'following');
    }, 0);
  }

  /** フォローする。戻り値は利用者に見せるメッセージ。 */
  public static function follow($targetUserid) {
    return self::whenReady(static function () use ($targetUserid) {
      $res    = pusyuuAccountListAdd(self::token(), 'pips', 'following', $targetUserid);
      $result = "";

      if (!empty($res["ok"])) {
        $result = "フォローしました。/You are now following this user.";
      } else {
        $result = $res["message"] ?? "保存中にエラーが発生しました。/An error occurred while saving.";
      }

      return $result;
    }, self::unavailableReason());
  }

  /** フォローを解除する。戻り値は利用者に見せるメッセージ。 */
  public static function unfollow($targetUserid) {
    return self::whenReady(static function () use ($targetUserid) {
      $res    = pusyuuAccountListRemove(self::token(), 'pips', 'following', $targetUserid);
      $result = "";

      if (!empty($res["ok"])) {
        $result = "フォローを解除しました。/You have unfollowed this user.";
      } else {
        $result = $res["message"] ?? "保存中にエラーが発生しました。/An error occurred while saving.";
      }

      return $result;
    }, self::unavailableReason());
  }

  /** フォロー中の相手を、表示用の公開プロフィール一覧へ解決します。 */
  public static function followingProfiles() {
    return self::whenReady(static function () {
      return pusyuuAccountResolveIds(self::myFollowingList());
    }, []);
  }

  /** 自分をフォローしている人の公開プロフィール一覧。 */
  public static function followerProfiles($useridHash) {
    return self::whenReady(static function () use ($useridHash) {
      return pusyuuAccountReverseList($useridHash, 'pips', 'following');
    }, []);
  }

  // お気に入りを保存するメイキーの欄(userData の pips/bookmarks)。
  //
  // 【"likes" に戻さないこと】"likes" は、投稿idで保存するよう切り替える前の公開URLの形の値を
  // 入れていた欄です。移行はせず新しく始めると決め(2026-09-30)、古い欄は全アカウントから
  // 消しました。戻すと、使っていない名前の欄を読むことになります。
  public const BOOKMARK_KEY = "bookmarks";

  /**
   * お気に入りに追加する。
   * 戻り値は ['ok' => bool, 'error' => string, 'message' => string]。
   * error には 'already_saved' のような基盤側の区分がそのまま入るので、呼び出し側は
   * 「すでに保存済み」と「本当に失敗した」を見分けられます。
   */
  public static function addLike($postId) {
    return self::whenReady(static function () use ($postId) {
      return self::likeResult(pusyuuAccountListAdd(self::token(), 'pips', self::BOOKMARK_KEY, $postId));
    }, self::likeUnavailable());
  }

  /** お気に入りから外す。戻り値の形は addLike() と同じです。 */
  public static function removeLike($postId) {
    return self::whenReady(static function () use ($postId) {
      return self::likeResult(pusyuuAccountListRemove(self::token(), 'pips', self::BOOKMARK_KEY, $postId));
    }, self::likeUnavailable());
  }

  /** 基盤の生の答えを、お気に入り機能の戻り値の形へ揃えます。 */
  private static function likeResult(array $res) {
    return [
      'ok'      => !empty($res['ok']),
      'error'   => (string)($res['error'] ?? ''),
      'message' => (string)($res['message'] ?? ''),
    ];
  }

  /** アカウント機能が使えないときの、お気に入り機能の戻り値。 */
  private static function likeUnavailable() {
    return ['ok' => false, 'error' => 'account_unavailable', 'message' => self::unavailableReason()];
  }

  // ----------------------------------------------------------------
  // アカウント関連の画面へのリンク
  //
  // 作成・編集・削除・ログインはアカウント基盤自身の画面へリンクする形に統一して
  // いるため、pips側は自前フォームを持ちません(ログアウトだけはパスワード不要で
  // 単純なので、引き続きpips側で処理します)。
  //
  // 【URLを自前で組み立てないこと】戻り先には、往路を始めたブラウザだけが復路を
  // 完了できるようにするための使い捨ての合言葉が必要です。手で組み立てるとそれが
  // 抜け落ち、ログインして戻ってきても何のエラーも出さずにログインされません。
  // 使えないときは空文字を返すので、呼び出し側はリンクを出さないでください。
  // ----------------------------------------------------------------

  public static function loginUrl() {
    return self::whenReady(static function () {
      return pusyuuAccountSignInUrl();
    }, "");
  }

  public static function createUrl() {
    return self::whenReady(static function () {
      return pusyuuAccountCreateUrl();
    }, "");
  }

  public static function manageUrl() {
    return self::whenReady(static function () {
      return pusyuuAccountManageUrl();
    }, "");
  }

  /**
   * pips自身のログアウト。
   * pips自身のトークンを失効させるだけでは、アカウント基盤側のログイン状態が
   * 生きたままになり、次のページ読み込みで自動ログインがまたログインさせてしまいます。
   * 基盤のログアウト画面を一瞬経由してからここへ戻ることで、両方を一緒に終了させます。
   * この関数はheader()+exitで終わるため、呼び出し元で以降の処理は不要です。
   */
  public static function handleLogout() {
    if (self::ready()) {
      pusyuuAccountRevokeToken(self::token());
    }
    allResetSession();
    PipsSession::set("user_logged_in", false);
    PipsSession::push("post_complete_msg", "ログアウトしました。/You have been logged out.");

    if (!function_exists('pusyuuAccountSignOutUrl')) {
      // 基盤が無い環境。畳むものが基盤側に無いので、今のページへ戻すだけにします。
      header('Location: ./');
      exit;
    } else {
      header('Location: ' . pusyuuAccountSignOutUrl());
      exit;
    }
  }
}

// ================================================================
// 軽量版（ほとんどCSS/JSが使えない端末向けの専用ページ）のON/OFF。
//
// 【ON・OFFのどちらもフッターのボタン(POST)です。GETのリンクに戻さないこと】
// 軽量版かどうかはセッション(Cookie)で持ち回ります。個々の投稿リンクやページ送りのURLへ
// &lite=1 を仕込んで回らずに済ませるためで、そこは今も変わりません。ただしその結果として、
// 軽量版には固有のアドレスがありません。?id_one_post= のような「共有される正規のURL」が、
// セッションの状態しだいで軽量版の中身を返すということです。
//
// 以前フッターの「軽量版で見る」は ./?lite=1 という普通のリンクでした。リンクである以上
// クローラーは踏みます。Cookieを持ち越す相手だと、そこから先は正規のURLまで全部軽量版で
// 返ることになり、軽量版の<head>はog:*もcanonicalも持たないので共有カードが空になります。
//
// これをUA判定(pusyuuIsKnownCrawler)で避けることもできますが、あれは名前の一覧なので、
// 載っていない新しいクローラーが出てくれば素通りします。かわりに「クローラーはフォームを
// 送信しない」という振る舞いのほうを使います。入口をPOSTのボタンに限れば、
// 相手が誰であるかを当てにいく必要がなくなります。
//
// 【OFFもURLの印(?lite=0)ではなくボタンにした理由】以前はOFFだけ ./?lite=0 のリンクの
// ままでした。ところが切り替えのフォームはaction属性を持たない＝送信先が「今見ている
// URL」です。つまり ?lite=0 の画面から「軽量版で見る」を押すと、POSTでONにした直後に、
// URLに残っている ?lite=0 が同じリクエストの中でOFFへ上書きしていました。押した人には
// 通常版がそのまま返るだけで、何が起きたのか分かりません。一度フル版へ戻ると二度と
// 軽量版へ入れない、ということです。向きをURLに残さないこと。
//
// 【セッションへ書くのはPOSTの分岐ただ1か所だけです】書き込みは下のPOSTのかたまりの中、
// $_POST["liteMode_button"] を見る分岐に集めてあります。ここでは書きません。URLの印と
// ボタンの意思が別々の場所から同じ変数を触ると、上の「押しても効かない」が形を変えて
// 必ず戻ってきます。
// ================================================================

// ボタンのvalueがそのまま「どっち向きか」です。フォーム側と分岐側で文字列が1文字でも
// ずれると、押しても何も起きないボタンになります。突き合わせる相手が常に同じ物である
// ことを、この2つの定数で保証します。
const PIPS_LITE_ON  = "on";
const PIPS_LITE_OFF = "off";

// 【?lite=1 をセッションへ書かない理由】
// processResult()は、軽量版で操作した時のリダイレクト先URLへ "lite=1" を足します(Cookieの
// 扱いが怪しい端末向けの保険)。その保険を残したまま、外から貼られた ?lite=1 のURLを
// クローラーが1回踏んでも後を引かないようにするため、GETの ?lite=1 はその1回だけ効かせます。
// 貼り付く(セッションに残る)のはPOSTのボタンを通った時だけです。
// なお ?lite=0 はもう何の意味も持ちません(上の【OFFも〜】参照)。復活させると、
// あの「押しても効かない」が戻ります。
$isLiteThisRequestOnly = (($_GET["lite"] ?? "") === "1");
$isLite = !empty(PipsSession::get("lite_mode")) || $isLiteThisRequestOnly;

// 軽量版は通常版と内容が重複するページのため、検索エンジンには通常版だけをインデックスさせたく、
// 軽量版自体はnoindex,nofollowにします（$isNoIndexは通常版・軽量版どちらの<head>でも
// meta robotsを決めるのに使われます）。
//
// 【これが安全に置けるのは、上でONの入口をPOSTに限ったからです】軽量版には固有のアドレスが
// ありません。入口がGETのリンクだった頃は、クローラーが踏むとそのセッションの正規URLまで
// 軽量版で返るので、ここでnoindexにすると正規URLにnoindexを撃ちうる状態でした。
// 入口をGETへ戻すなら、この行も一緒に考え直してください。
if ($isLite) {
  $isNoIndex = true;
}

// ページ情報郡
$version = "ThisPageVersion: " . "4.29.16";
$dualTransmissionMsg = "ボタンが連続で一回以上押されためシステム保護のため処理を終了しました、申し訳なく存じますが最初からやり直してください。/The button was pressed more than once in quick succession. For system protection, the process has been terminated. We apologize for the inconvenience, but please start again from the beginning.";

$title = "プシューサービス - プシューIPS/PusyuuIPS";
$description = "PIPS(PusyuuIPS)はPusyuuImpressionsPostService.の略で名前通り感想を書いたり見たりして楽しむサービスで、ミニブログとしてでも、掲示板としてでも、そしてチャットとしても利用可能です！、操作感は某SNSプラットフォームのような使い心地です。/PIPS (PusyuuIPS) is an abbreviation for PusyuuImpressionsPostService., and as the name suggests, it is a service that allows you to write and enjoy your impressions, and can be used as a miniblog, bulletin board, or chat! The operation feels like a certain SNS platform.";
// OGP画像は外のサービス(SNSのカード等)が取りに来るので、公開の顔の住所を使います。
// 台帳が読めないときは相対パスにします(カードの画像は出ませんが、ページは出ます)。
$image = (class_exists('PusyuuRegistryClient') ? PusyuuRegistryClient::publicUrl('pips', '/assets/images/pips_logo.png') : null) ?? "./assets/images/pips_logo.png";

// POSTする値のすべてのHTML 要素を無効にする
// ただしパスワード系の項目はここでエスケープしてしまうとpassword_hash/password_verifyに渡る値が
// 元の入力と変わってしまうため対象から除外します（配列で送られてきた値もhtmlspecialcharsがエラーになるため除外します）
$pipsPasswordFields = ["password", "password_confirm", "new_password", "new_password_confirm"];
// 【画面に出さず、別のAPIへそのまま渡す値も除外すること】通知の購読情報(subscription)はJSON、
// 解除の宛先(endpoint)はURLで、どちらも通知APIが中身を読みます。エスケープすると " が &quot; に
// 化けてJSONとして読めなくなり、通知の登録が必ず「購読情報が不完全でした」(invalid_input)で
// 失敗します(2026-09-30に実際に起きました)。ここへ項目を足すのは、画面に出さない値だけにしてください。
$pipsRawPostFields = ["subscription", "endpoint"];
foreach ($_POST as $key => $value) {
  if (in_array($key, $pipsPasswordFields, true) || in_array($key, $pipsRawPostFields, true) || !is_string($value)) {
    continue;
  }
  $_POST[$key] = htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function allResetSession() {
  PipsSession::reset();
  genelateSession();
}

function genelateSession() {
  PipsSession::set("server_token", bin2hex(random_bytes(32)));
  //debugTrace("token_regenerated", ["backtrace" => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]["function"] ?? "unknown"]);
}

// $_POST["server_token"]とセッションの"server_token"の照合を行います。
// 単純な==比較だとタイミング攻撃に弱いためhash_equals()を使用します。
// また$_POST["server_token"]が配列（例："server_token[]=x"）で送られてきた場合にhash_equals()がTypeErrorで
// 落ちてしまうのを防ぐため、事前にis_stringでチェックします。
function tokenIsValid() {
  return isset($_POST["server_token"]) && is_string($_POST["server_token"]) && hash_equals(PipsSession::get("server_token"), $_POST["server_token"]);
}

if (!PipsSession::has("server_token")) {
  genelateSession();
}

if (!PipsSession::has("post_complete_msg")) {
  PipsSession::set("post_complete_msg", []);
}

// =====================================================================
// 自動ログインの確認(Googleのアカウント連携のような、フォーム無しの自動ログイン)
//
// このブラウザがアカウント基盤に既にログイン済みなら、pipsでもフォームを出さずに
// 自動でログイン状態にします。未ログインの場合は普段どおりログインへのリンクを
// 表示します。1ブラウザセッションにつき1回だけ確認し、無限リダイレクトを防ぎます。
// (PipsAccountFeature::handleLogout()はpips自身のログアウトだけでなく、基盤側の
// ログイン状態も一緒に終了させます。そうしないと基盤側のログイン状態が生きたままに
// なり、次のページ読み込みでこの自動ログインがまたログインさせてしまうためです。)
//
// 【アカウント機能が無い環境では何も起きません】この確認は共有スクリプトの持ち物です。
// 無ければ関数ごと存在しないので、function_exists()で確かめてから呼びます。確かめずに
// 呼ぶと致命的エラーになり、投稿が1件も表示されないページになります。
// なお、基盤へ届かないときに送り出さないという判断は共有スクリプト側が行います
// (送り出してしまうと、利用者はブラウザの「アクセスできません」を見ることになり、
//  pipsのページは一度も描画されません)。
// =====================================================================

// 往復の手順そのものは全プロダクト共通なので、共有スクリプトに任せます。
// pips固有なのは「ログイン中かどうかの判定」と「受け取った結果を自分のセッションへ
// どう入れるか」の2点だけなので、その2つだけを渡します。
/**
 * この要求が「人が画面を開いた」ものではなく、画面の裏で走る問い合わせかどうか。
 *
 * 【これを見ないとSSOが壊れます】共有のアカウントクライアントは、
 * 「GETで、X-Requested-With が XMLHttpRequest でない」ものを画面遷移とみなし、
 * 未ログインならメイキィへの往路(SSO)を始めます。その往路では使い捨てのstateを
 * セッションへ控えますが、控えは上限16件の一覧です。
 *
 * 新着確認は30秒ごとに走るため、ここを素通りさせると8分ほどで控えが裏の問い合わせ
 * ぶんで埋まり、利用者が実際にログインしようとして作った控えが追い出されます。
 * 結果は「いつまでもログインできない」で、しかも画面には何のエラーも出ません
 * (メイキィ側のログには sso_state_missing が並びます)。
 *
 * JS側でも同じヘッダを送っていますが、ヘッダを落とすブラウザや串もあるため、
 * URLからも判別できるようにしてあります。片方だけにしないこと。
 * なお、ここで飛ばすのは「メイキィへ往復しに行く」判断だけです。既にこのセッションが
 * 持っているログイン状態は、そのまま使えます。
 *
 * 【メディア配信(?media_post / ?image)もここに入れること】
 * これらは<img>や<video>や<meta og:image>から引かれる「ページの部品」で、人が画面を
 * 開いた操作ではありません。共有クライアント側の門番(isPageNavigation())は
 * Sec-Fetch-Mode を見て部品の取得を除きますが、この見出しを送るのはChrome 76 /
 * Firefox 90 / Safari 16.4 以降です。送らないブラウザ(少し前のSafariや古いWebView)では、
 * 画像1枚の取得が丸ごと「ページ遷移」に見えます。
 *
 * そうなると未ログインの人が画像付きの投稿を開いたとき、画像1枚ごとに往路へ出て
 * 控えを1枠ずつ使います。復路が終わるまで pusyuu_sso_checked は立たず、ブラウザは
 * 画像を並列に取りに行くので、5枚あれば5枠が同時に消えます。これは2026-09-08に
 * 直したはずの「控えが裏の要求で埋まって本物のログインが成立しない」状態そのものです
 * (経緯は共有スクリプトの accountUrl() のコメント)。
 *
 * 以前は通常版がメディアをbase64でHTMLに直埋めしていたため、?media_post を叩くのは
 * 軽量版と編集モーダルだけで数が知れていました。通常版もURL参照へ変えた今は、
 * ページを開くたびに画像の枚数ぶんここを通ります。外さないでください。
 */
function pipsIsBackgroundRequest() {
  // 「裏の要求」と見なす手がかりは2種類です。名乗ってくれるもの(XHRのヘッダ)と、
  // 名乗らないが用途で分かるもの(ページではなく部品を取りに来ているURL)です。
  $declaredByHeader = (strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest');
  $knownByPurpose   = isset($_GET["pipsNewCheck"]) || isset($_GET["getDispPost"])
                   || isset($_GET["media_post"])   || isset($_GET["image"])
                   || isset($_POST["ajax"]);
  $result = false;

  if ($declaredByHeader) {
    $result = true;
  } else if ($knownByPurpose) {
    $result = true;
  } else {
    $result = false;
  }

  return $result;
}

if (function_exists('pusyuuAccountHandleSignIn') && !pipsIsBackgroundRequest()) {
  pusyuuAccountHandleSignIn(
    static function (): bool {
      return PipsAccountFeature::isLoggedIn();
    },
    static function (array $user, string $token): void {
      PipsSession::regenerateId(); // ログイン=権限昇格。セッション固定化を防ぐ
      PipsSession::set('user_logged_in', true);
      // kind='meikiee': この token はメイキィ発行のログイントークン(meikiee_client 経由)。
      // 失効はメイキィのトークン置き場から sha256(token) を消すことで、PIPSは直接できないが
      // 共有の保留箱へ積めば次にメイキィが開かれた時に消える(keepSecret()参照)。
      PipsSession::keepSecret('accounts_token', $token, 'meikiee');
      PipsSession::set('userid', $user['userid']); // storage_id(ハッシュ)。生idではない
      PipsSession::set('username', $user['username']);
      PipsSession::set('name', $user['name']);
      PipsSession::push("post_complete_msg", "ログインしました。/You are now logged in.");
    }
  );
}

// ログインに失敗していたら、その理由を画面のメッセージ欄へ載せます。
//
// 【これを消さないこと】ログインの判定はリダイレクトの直前で終わるので、ここで拾わない
// 限り失敗は誰にも見えません。利用者には「ログインを押したのに元の画面に戻っただけ」に
// 見えます。実際、pipsはこの状態のまま長くログインできない状態が続いていました。
// post_complete_msg は軽量版・通常版のどちらもPHPがHTMLとして書き出す欄なので、
// JavaScriptが無い環境でも理由が読めます。
$pipsSignInError = PipsAccountFeature::takeSignInError();
if ($pipsSignInError !== "") {
  PipsSession::push("post_complete_msg", $pipsSignInError);
}

// 【開発者の思い出の品】洗練された標準関数に置き換えず、このまま残してください。
function pipsBarsDayCounter() {
  $pipsBarsDay = [2023, 07, 20]; // 年、月、日
  $barsDayStr = implode("-", $pipsBarsDay);
  $barsDay = new DateTime($barsDayStr);
  $currentDay = new DateTime();
  $diff = $currentDay->diff($barsDay);
  $yearsDiff = ($diff->days / 365.25);

  return $yearsDiff;
}

// 【開発者の思い出の品】関数の中に関数を入れて整理するのが開発者の書き方の癖であり、あえてそうしています。
// （generateSitemapEntry()を内部に持つ構成含め）洗練された書き方に整理し直さず、このまま残してください。
/**
 * サイトマップを今日まだ作っていないかどうか。
 *
 * 【この判定を、全件取得より「先」に行うこと】
 * 以前はこの判定が generateSitemapXML() の中にあり、その引数を作るための全件取得
 * (all=1)が毎リクエスト無条件に走っていました。サイトマップは1日1回しか作り直さない
 * のに、**その日の2回目以降のアクセスでも42.7MB全件を読み込んでは捨てていた**わけです。
 * 2026-09-06のerror.logに並んでいたメモリ枯渇(1853回)の主因がこれでした。
 * JSONLを1行ずつ読める形式にしている意味も、これで丸ごと失われていました。
 */
// サイトマップ用の分割取得を、最大何往復まで許すか。
// 1往復あたり最低1MiBは読めるので、この回数に達するのは「APIが続きの位置を
// 返し続けているのに実際には進んでいない」等の異常時だけです。
const PIPS_SITEMAP_MAX_ROUNDS = 500;

// 取得に失敗した直後、次に作り直しを試みるまで待つ秒数。
// 失敗のたびに毎ページ読み込みで全件取得をやり直すと、APIが不調な間じゅう
// その不調を悪化させ続けることになるため、少し間を空けます。
const PIPS_SITEMAP_RETRY_WAIT = 900; // 15分

function pipsSitemapNeedsRegeneration() {
  $jsonFilePath  = PIPS_SITEMAP_DATE_FILE;
  $record        = file_exists($jsonFilePath) ? json_decode(file_get_contents($jsonFilePath), true) : null;
  $lastAttempt   = (is_array($record) && isset($record['last_attempt'])) ? (int)$record['last_attempt'] : 0;
  $waitingRetry  = ($lastAttempt > 0 && (time() - $lastAttempt) < PIPS_SITEMAP_RETRY_WAIT);
  $result        = true;

  if (!file_exists($jsonFilePath)) {
    // まだ一度も作っていない。
    $result = true;
  } else if (!is_array($record) || !isset($record['last_generated'])) {
    // 記録が壊れている。読めない以上「今日の分はまだ」と見なします。
    $result = true;
  } else if ($record['last_generated'] === date('Y-m-d')) {
    // 今日のぶんは作り終えています。
    $result = false;
  } else if ($waitingRetry) {
    // 直前の試みが最後まで辿り着けませんでした。間を空けてから作り直します。
    $result = false;
  } else {
    $result = true;
  }

  return $result;
}

/**
 * サイトマップに載せるid(投稿と返信の両方)を、1件残らず集めます。
 * 戻り値は ["ids" => idの配列, "complete" => 最後まで辿り着けたか]。
 *
 * 【なぜ「全件を1回でください」と頼まないのか】
 * 投稿本文にはBase64の画像が入るため、全件はPHPのメモリ上限に収まりません。
 * API側はそれを知っていて、今のmemory_limitに収まる量ずつ返し、続きの位置を
 * next_offset で知らせてきます。**続きを取りに行くのは呼び出し側の仕事です。**
 *
 * 【ここが2026-09-16まで壊れていました】
 * 以前このファイルは all=1 の応答を1回受け取っただけで、next_offset を一度も
 * 見ていませんでした。つまりサイトマップには**最初の一片に入っていた投稿しか
 * 載っていませんでした**。実測で、投稿と返信あわせて730件あるうち99件だけです。
 * 残る631件は、サイト上には存在するのに検索エンジンへは一度も知らせていない、
 * という状態でした。
 *
 * 軽くするために件数を削ってよい、という話ではありません。軽さが必要なのは
 * 「PHPが落ちないようにするため」であって、載せる件数はあくまで全件です。
 * 分割して何度も往復するぶん時間はかかりますが、それは意図したとおりの遅さです。
 *
 * 【idだけを残して、投稿そのものはその場で捨てること】
 * 必要なのはURLに入るidだけです。往復のたびに受け取った投稿を貯め込むと、
 * 分割して受け取った意味が無くなり、結局は全件をメモリへ載せたのと同じになります。
 * 1往復ぶんを読んだら、idを抜き出して本体は手放します。こうすると、同時に
 * メモリへ載るのは「1往復ぶんの投稿」と「idの一覧(1件あたり数十バイト)」だけです。
 */
function pipsCollectSitemapIds() {
  $ids      = [];
  $offset   = null;
  $rounds   = 0;
  $complete = false;
  $stop     = false;

  while (!$stop) {
    $rounds++;
    $opts = ["all" => 1];
    if ($offset !== null) { $opts["offset"] = $offset; }

    $raw  = PipsPostIO::postData("read", $opts);
    $json = ($raw === false || $raw === null) ? null : json_decode($raw, true);

    if (!is_array($json) || !isset($json["item"]) || !is_array($json["item"])) {
      error_log("[PIPS] サイトマップ用の取得に失敗しました。offset=" . var_export($offset, true));
      $stop = true;
    } else {
      foreach ($json["item"] as $post) {
        if (isset($post["id"])) {
          $ids[] = (string)$post["id"];
        }
        if (isset($post["replies"]) && is_array($post["replies"])) {
          foreach ($post["replies"] as $reply) {
            if (isset($reply["id"])) {
              $ids[] = (string)$reply["id"];
            }
          }
        }
      }

      // 続きの有無と位置だけを控えて、受け取った投稿の本体はここで手放します。
      $chunked = !empty($json["chunked"]);
      $next    = (isset($json["next_offset"]) && $json["next_offset"] !== null) ? (int)$json["next_offset"] : null;
      unset($json, $raw);

      if (!$chunked) {
        // 分割されていない応答。これで全件です
        // (API以外のドライバや、ローカルの控えから読んだ場合がこれに当たります)。
        $complete = true;
        $stop     = true;
      } else if ($next === null) {
        // 続きは無い。最後の一片まで受け取れました。
        $complete = true;
        $stop     = true;
      } else if ($offset !== null && $next <= $offset) {
        // 位置が進んでいない。このまま続けると永久に往復します。
        error_log("[PIPS] サイトマップ用の分割取得で読み出し位置が進みませんでした。offset=" . $offset . " next=" . $next);
        $stop = true;
      } else if ($rounds >= PIPS_SITEMAP_MAX_ROUNDS) {
        error_log("[PIPS] サイトマップ用の分割取得が" . PIPS_SITEMAP_MAX_ROUNDS . "往復を超えたため打ち切りました。");
        $stop = true;
      } else {
        $offset = $next;
      }
    }
  }

  return ["ids" => $ids, "complete" => $complete];
}

function generateSitemapXML() {
  $currentDate     = date('Y-m-d');
  $jsonFilePath    = PIPS_SITEMAP_DATE_FILE;
  $sitemapFilePath = PIPS_SITEMAP_FILE;
  $sitemap         = "";

  // 【この入れ子のまま残すこと】上の「開発者の思い出の品」のコメントで、
  // generateSitemapEntry()を内部に持つ構成ごと残すよう指示されています。
  // 宣言をこの関数の先頭へ出したのは、下の分岐が3通りに増えて「どの枝を通ると
  // 宣言されるのか」が読みづらくなったためです。入れ子であることは変えていません。
  if (!function_exists('generateSitemapEntry')) {
    function generateSitemapEntry($postId) {
      // サイトマップは検索エンジンへ渡すものなので、公開の住所で組み立てます(pipsPublicUrl)。
      $url = pipsPublicUrl('/?id_one_post=' . $postId);
      $entry = "  <url>\n";
      $entry .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
      $entry .= "    <priority>0.8</priority>\n";  // Priorityは任意で設定
      $entry .= "  </url>\n";
      return $entry;
    }
  }

  // 作らない事情は2つあります。
  //   ・今日はもう作ってある(または直前の失敗から間を空けている最中)
  //   ・全件を辿り切れなかった
  // 2つ目は特に大事です。**途中までのサイトマップで今ある物を上書きしないこと。**
  // 上書きすると、載っていたはずのURLが検索エンジンから見て「消えた」ことになります。
  // 何も書かずに帰れば、前回の完全なサイトマップがそのまま残ります。
  if (!pipsSitemapNeedsRegeneration()) {
    $sitemap = "";
  } else {
    $collected = pipsCollectSitemapIds();

    if (!$collected["complete"]) {
      error_log("[PIPS] サイトマップを最後まで組み立てられなかったため、今回は書き換えませんでした。"
        . "集まったid=" . p_count($collected["ids"]) . "件");
      // 次の試みまで間を空けるために、試みた時刻だけを残します
      // (last_generatedは書かないので、今日のぶんはまだ未完了のままです)。
      file_put_contents($jsonFilePath, json_encode(['last_attempt' => time()], JSON_PRETTY_PRINT));
      $sitemap = "";
    } else {
      $sitemap  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
      $sitemap .= '<!-- プシューの手作りサイトマップ、最終、書き込みが行われたのは' . $currentDate . 'です。 -->' . "\n";
      $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

      foreach ($collected["ids"] as $postId) {
        $sitemap .= generateSitemapEntry($postId);
      }

      $sitemap .= '</urlset>';

      file_put_contents($sitemapFilePath, $sitemap);
      // echoで出力するとgetDispPost等のJSON出力の先頭に紛れ込みJSON解析エラーを引き起こすため、ログのみに留めます
      error_log("[PIPS] Sitemapを生成しました。" . p_count($collected["ids"]) . "件");

      $dateData = json_encode(['last_generated' => $currentDate, 'last_attempt' => time()], JSON_PRETTY_PRINT);
      file_put_contents($jsonFilePath, $dateData);
    }
  }

  return $sitemap;
}

// 【全件取得を、作り直しが要るときだけに限ること】
// サイトマップは1日1回で足ります。ここを無条件にすると、その日の2回目以降の
// ページ表示でも42.7MBの全件取得が走り、読み込んだ端から捨てることになります。
// それがメモリ枯渇でAPIが落ち続けていた原因でした。
//
// 【裏の問い合わせと部品の取得には背負わせないこと(pipsIsBackgroundRequest)】
// 「1日1回」は「その日の最初の1本」という意味です。誰がその1本になるかは選べません。
// 素通りさせると、次のようなものが42.7MBの全件取得を丸ごと背負います。
//   ・?pipsNewCheck … 開いているタブから30秒ごとに飛ぶ、件数だけを返すはずの軽い口
//   ・?media_post   … 画像1枚を返すだけの口。ページを開くたび枚数ぶん飛びます
//   ・?getDispPost  … 一覧の取り直し
// どれも「待たされてはいけない」側の要求で、しかも画像の取得が数十秒かかれば、
// 利用者には画像が壊れて見えます。作り直しは人が画面を開いた時に任せます。
// その日じゅう誰もページを開かなければ作り直されませんが、それは「誰も見ていない日」
// なので困りません。次に人が開いた時に作られます。
// 【全件の取得は generateSitemapXML() の中に移しました】
// 以前はここで all=1 を1回だけ叩き、その結果を引数で渡していました。
// APIは収まる量ずつ返して続きの位置を知らせてくるので、1回だけでは
// 最初の一片しか受け取れません。それが「サイトマップが中途半端」の正体でした
// (実測: 全730件のうち99件しか載っていませんでした)。
// 今は generateSitemapXML() が pipsCollectSitemapIds() を通じて
// 続きが無くなるまで自分で辿ります。
if (!pipsIsBackgroundRequest() && pipsSitemapNeedsRegeneration()) {
  generateSitemapXML();
}


// 【開発者の思い出の品】あえてstrpos()等に頼らず1文字ずつ自作で判定しています。しゃれた関数に置き換えないでください。
function p_decimalPointCheck($num) {
  $stringNum = (string)$num;
  $result = false;

  // 見つかった時点でループの条件が偽になり、そこで数えるのをやめます
  // (returnで抜けていた頃と同じで、後ろの文字は見ません)。
  for ($i = 0; $i < strlen($stringNum) && $result === false; $i++) {
    if ($stringNum[$i] === ".") {
      $result = true;
    }
  }

  return $result;
}

// 【開発者の思い出の品】あえてcount()に頼らず自作しています（PHP8のcount()型エラー対策も兼ねます）。
// しゃれた標準関数に置き換えず、このまま残してください。
function p_count($array_num) {
  $counter = 0;
  if (isset($array_num) && is_array($array_num)) {
    foreach ($array_num as $reply) {
      $counter++;
    }
  } else {
    error_log("[PIPS] p_count関数に配列以外の値が渡されました。");
  }
  return $counter;
}

// 【開発者の思い出の品】あえてmb_substr()に頼らず自作しています。しゃれた標準関数に置き換えないでください。
function utf8_substr($str, $start, $length = null) {
  $array  = preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
  $total  = p_count($array);
  $result = "";

  if ($start >= $total) {
    // 開始位置が文字数を越えている。切り出せる文字がありません。
    $result = "";
  } else {
    if ($length === null) {
      $length = $total - $start;
    }
    $result = implode("", array_slice($array, $start, $length));
  }

  return $result;
}

// 【開発者の思い出の品】あえてceil()等に頼らず自作の判定式にしています。しゃれた関数に置き換えないでください。
function pageOverCheck($pageArray, $pageSize) {
  $overNum = p_count($pageArray) / $pageSize;
  $decimal_pos = strpos((string)$overNum, ".");
  $result = 0;

  if ($decimal_pos === false) {
    // 割り切れた場合。0件のときは0のまま返します(1に繰り上げないこと。
    // 以前からこの枝だけ下の「0なら1」を通っておらず、呼び出し側もその前提です)。
    $result = $overNum;
  } else {
    $oneDecimalValue = substr($overNum, 0 , $decimal_pos + 2);

    $resultPageOverNum = p_decimalPointCheck($oneDecimalValue) && floor($oneDecimalValue) != $oneDecimalValue ? $oneDecimalValue+1 : $oneDecimalValue;

    $result = ($resultPageOverNum == 0 ? 1 : $resultPageOverNum);
  }

  return $result;
}

/**
 * pips の中の CSS・JS を読み込む URL。末尾に ?v=<ファイルの更新時刻> を付けます。
 *
 * 【版の印を外さないこと】付けないと、ブラウザや途中のキャッシュが、書き換える前の
 * ファイルを使い続けます。2026-09-30、感想スタンプの関数(setStampInfo)を JS に足した直後、
 * 古い JS のままのブラウザで「setStampInfo is not defined」になり、😊が同期(JS無し)の
 * 経路でしか動きませんでした。ファイルを書き換えれば更新時刻が変わるので、手で版を
 * 上げる必要はありません。更新時刻が取れないときは、印を付けずにそのまま返します。
 */
function pipsAssetUrl(string $path): string {
  $modified = @filemtime($path);
  $result   = "";

  if ($modified === false) {
    $result = $path;
  } else {
    $result = $path . "?v=" . $modified;
  }

  return $result;
}

/**
 * PIPSの公開の住所(台帳 pusyuu_registry の pips の public の顔)に $pathAndQuery を付けたもの。
 * 共有リンク・canonical・サイトマップ・og:image のように、今この画面を見ている本人以外
 * (検索エンジン・SNS・リンクを受け取った人)へ届くものは、必ずこれで組み立てます。
 *
 * 【HTTP_HOST から組み立てないこと】IPやLANから入った人の画面で作ったリンクが、
 * そのまま他人へ渡って開けなくなります。Host を偽った要求で、検索エンジンへ
 * 別のホストを正規URLとして教えることもできてしまいます。
 * 台帳が読めないときは相対URL(./ から始まる)に倒します。
 */
function pipsPublicUrl(string $pathAndQuery): string {
  $url = class_exists('PusyuuRegistryClient') ? PusyuuRegistryClient::publicUrl('pips', $pathAndQuery) : null;
  return ($url === null) ? '.' . $pathAndQuery : $url;
}

// 【開発者の思い出の品】あえてurlを生成する関数に頼らず自作しています。しゃれた関数に置き換えないでください。
// (2026-09-20: 住所の頭だけ台帳の公開の顔に替えました。正規URLは検索エンジンへ渡すものなので、
//  今の入口のホスト名ではなく公開の住所にします。クエリは組み立て方を残したまま値を符号化し、
//  href へ入れるときの穴を塞いでいます。)
function p_getUrl(): string {
  $path   = (basename((string)$_SERVER['PHP_SELF']) === 'index.php') ? '/' : '/' . basename((string)$_SERVER['PHP_SELF']);

  $query = '';
  foreach ($_GET as $key => $value) {
    $query .= ($query === '' ? '?' : '&') . rawurlencode((string)$key) . '=' . rawurlencode(is_string($value) ? $value : '');
  }

  return pipsPublicUrl($path . $query);
}

/**
 * 返信先の切り替えがJS(XHR)から来ていた場合に、書き込みフォームの先頭カードのHTMLを返して
 * このリクエストを終わらせます。JSからでなければ何もせず、呼び出し元の通常の流れ
 * （リダイレクトしてモーダルを開く）へそのまま進みます。
 *
 * 【JSにHTMLを作らせないための口】投稿一覧と同じで、HTMLを組み立てるのはPHPだけです。
 * JSはここが返したものを差し込むだけなので、「PHPが出す表示」と「JSが出す表示」が
 * 食い違うことが起こりません。またセッションの更新はこの関数を呼ぶ前に、JSの有無に
 * 関わらず同じ場所で済ませてあるので、画面を再読込しても状態は変わりません。
 *
 * 【server_tokenを一緒に返す理由】呼び出し元はこの直前にgenelateSession()で
 * CSRFトークンを作り直しています。ページ内のフォームが持っている古いトークンのままだと、
 * 次の送信が「連打」と判定されて弾かれます。JS側でページ内の全フォームを更新させるため、
 * 新しい値をここで渡します。
 */
function pipsRespondModalIfAjax($ok, $part) {
  if (!isset($_POST["ajax"])) {
    // JSからの呼び出しではありません。何も返さず、呼び出し元の通常の流れ
    // (リダイレクトしてモーダルを開く)へそのまま進ませます。
  } else {
    if (!$ok) {
      $html = "";
    } else if ($part === "composer") {
      $html = PipsPostTemplate::composerHeader();
    } else if ($part === "edit") {
      $html = PipsPostTemplate::editModalBody();
    } else if ($part === "delete") {
      $html = PipsPostTemplate::deleteModalBody();
    } else if ($part === "stamp") {
      $html = PipsPostTemplate::stampModalBody();
    } else {
      error_log("[PIPS] 開発者の設定ミス: pipsRespondModalIfAjaxのpartが不正です。part=" . var_export($part, true));
      $html = "";
      $ok = false;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      "ok"           => (bool)$ok,
      "html"         => $html,
      // composerだけは、置き換えるのがカードの部分だけで、件名欄と本文欄は
      // 書きかけを消さないよう手を付けません。そのため値を別に返して画面側で入れ直します。
      "reply_to"     => (string)(PipsSession::get("reply_id", "")),
      "subject"      => (string)(PipsSession::get("reply_submit", "")),
      "server_token" => (string)(PipsSession::get("server_token", "")),
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// ================================================================
// 書きかけの保持（下書き）
//
// 【なぜPHP側に持たせるか】これまで書きかけを覚えていたのはJS(localStorage)だけでした。
// つまり「JSがうまく動かない環境ほど、書いたものを失いやすい」という、守りたい相手と
// 守られる相手が逆さまな状態でした。古い端末で入力中に画面が飛ぶ、という被害が
// 一番起きやすいのはまさにその環境です。そこで保持の本体をサーバへ移し、
// JSは「それを自動で呼ぶ」だけの補助にしています。
//
// 【JSが無いと自動保存はできません。そこは正直に作ること】サーバは、リクエストが
// 届いた瞬間のことしか知りません。打鍵の途中を勝手に受け取る方法はPHPには無いので、
// JSが無い環境では「下書きを保存」「保存して閉じる」を利用者が押した時に保存されます。
// これを自動でやっているかのように見せる作りにしないこと（実際には保存されていない
// のに保存された気にさせるのが、一番害の大きい壊れ方です）。
// 保存された時刻を画面に出しているのはそのためで、押していなければ時刻も増えません。
//
// 【JSがある場合】同じ受け口(draft_save)をXHRで呼ぶだけです。保存する場所も形式も
// 同じなので、途中でJSが動かなくなっても、それまでにサーバへ届いた分は残ります。
// ================================================================

/** 下書きとして預かっている値。1つの形にまとめておき、読む側で散らからないようにします。 */
function pipsDraft() {
  return [
    "subject"          => (string)(PipsSession::get("draft_subject", "")),
    "text"             => (string)(PipsSession::get("draft_text", "")),
    "sensitive"        => !empty(PipsSession::get("draft_sensitive")),
    "no_convert_links" => !empty(PipsSession::get("draft_no_convert_links")),
    "saved_at"         => (string)(PipsSession::get("draft_saved_at", "")),
  ];
}

/**
 * 下書きを預かります。$_POSTの値は既にhtmlspecialchars済み（このファイル冒頭の一括処理）なので、
 * ここで再度エスケープしないこと。二重エスケープすると、書き戻したときに
 * "&amp;lt;" のような文字列が本文欄に現れ、保存のたびに壊れていきます。
 */
function pipsDraftSave($subject, $text, $sensitive, $noConvertLinks) {
  PipsSession::set("draft_subject", (string)$subject);
  PipsSession::set("draft_text", (string)$text);
  PipsSession::set("draft_sensitive", (bool)$sensitive);
  PipsSession::set("draft_no_convert_links", (bool)$noConvertLinks);
  PipsSession::set("draft_saved_at", date("H:i:s"));
}

/** 投稿できた時だけ捨てます。失敗した時に捨てると、書いた物を失わせることになります。 */
function pipsDraftClear() {
  PipsSession::forget(
    "draft_subject",
    "draft_text",
    "draft_sensitive",
    "draft_no_convert_links",
    "draft_saved_at"
  );
}

// ================================================================
// 添付（画像・動画）の受け取り
//
// 投稿と編集の両方から使います。**規則をここ1箇所にまとめておくこと**。
// 以前は投稿の受け口の中に直接書いてあったため、編集側に添付を扱わせようとすると
// 同じ判定をもう一度書くことになり、片方だけ上限や対応形式を直して食い違う形になります。
// ================================================================

const PIPS_MEDIA_MAX_COUNT = 10;          // 1投稿あたりの上限
const PIPS_MEDIA_MAX_BYTES = 1048576;     // 1つあたり1MB

/**
 * 実際のmimeタイプと拡張子の対応。
 * どちらか一方が合っていれば通す(OR)形にすると拡張子の偽装が可能になるため、
 * 両方が一致した場合だけ許可します(AND)。
 */
function pipsMediaAllowedTypes() {
  return [
    "video/mp4"  => ["mp4"],
    "image/jpeg" => ["jpg", "jpeg"],
    "image/png"  => ["png"],
  ];
}

/**
 * アップロードされた1件を検査してbase64にします。
 * 戻り値:
 *   ["ok"=>true,  "data"=>base64文字列]  受け取れた
 *   ["ok"=>true,  "data"=>null]          そもそも選ばれていない（未選択は失敗ではない）
 *   ["ok"=>false, "message"=>文言]       受け取れなかった。messageはそのまま画面に出せます
 */
function pipsEncodeUploadedMedia($name, $size, $tmpName, $error) {
  // 受け取れない事情は6通りあります。順番に意味があるので、並べ替えないでください。
  //   ・未選択(失敗ではない)を最初に外す
  //   ・PHPが弾いた(大きすぎる/その他)を、こちらの検査より先に見る
  //     … 弾かれている場合、tmp_nameは空でfinfoもsizeも当てにならないためです
  //   ・そのうえで、こちら側の上限・形式・読み取りを順に見る
  $code      = (int)$error;
  $finfo     = ($code === UPLOAD_ERR_OK) ? finfo_open(FILEINFO_MIME_TYPE) : false;
  $mime      = ($finfo !== false) ? finfo_file($finfo, $tmpName) : false;
  $extension = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
  $allowed   = pipsMediaAllowedTypes();
  $typeOk    = ($mime !== false) && isset($allowed[$mime]) && in_array($extension, $allowed[$mime], true);
  $raw       = false;
  $result    = [];

  if ($code === UPLOAD_ERR_NO_FILE) {
    $result = ["ok" => true, "data" => null];
  } else if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
    $result = ["ok" => false, "message" => "画像・動画のサイズが大きすぎて、お預かりできませんでした。恐れ入りますが、小さいものでお試しください。/That file was too large for us to accept. Please try a smaller one."];
  } else if ($code !== UPLOAD_ERR_OK) {
    // 番号は利用者には意味が無いのでログへ。
    error_log("[PIPS] メディアのアップロードで想定外のエラー。エラー番号=" . var_export($error, true));
    $result = ["ok" => false, "message" => "申し訳ありません、画像・動画をうまくお預かりできませんでした。恐れ入りますが、別のファイルでもう一度お試しいただけますか。/Sorry, we couldn't accept that file. Please try again with a different one."];
  } else if ((int)$size > PIPS_MEDIA_MAX_BYTES) {
    $result = ["ok" => false, "message" => "画像・動画は1つあたり" . pipsFormatBytes(PIPS_MEDIA_MAX_BYTES) . "までお預かりできます。恐れ入りますが、小さいものに変えるか、圧縮してお試しください。/Each image or video can be up to " . pipsFormatBytes(PIPS_MEDIA_MAX_BYTES) . ". Please use a smaller file, or compress it and try again."];
  } else if (!$typeOk) {
    $result = ["ok" => false, "message" => "画像・動画は mp4・png・jpg のいずれかをお使いください。恐れ入りますが、ファイルを変えてもう一度お試しください。/Please use an mp4, png, or jpg file. Sorry for the trouble — please try again with a different file."];
  } else {
    $raw = @file_get_contents($tmpName);

    if ($raw === false) {
      error_log("[PIPS] アップロードされたファイルを読み取れませんでした。tmp=" . var_export($tmpName, true));
      $result = ["ok" => false, "message" => "申し訳ありません、画像・動画をうまくお預かりできませんでした。恐れ入りますが、もう一度お試しいただけますか。/Sorry, we couldn't read that file. Please try again."];
    } else {
      $result = ["ok" => true, "data" => base64_encode($raw)];
    }
  }

  return $result;
}

/** $_FILES の1エントリから、指定番目のファイル情報を取り出します（複数ファイル欄は配列で入るため）。 */
function pipsUploadedAt($field, $index) {
  $names  = $_FILES[$field]["name"] ?? null;
  $result = null;

  if (!is_array($names)) {
    // そのファイル欄自体が届いていない(または配列の形をしていない)。
    $result = null;
  } else if (!array_key_exists($index, $names)) {
    // 欄はあるが、その番号のファイルは無い。
    $result = null;
  } else {
    $result = [
      "name"  => $_FILES[$field]["name"][$index],
      "size"  => $_FILES[$field]["size"][$index],
      "tmp"   => $_FILES[$field]["tmp_name"][$index],
      "error" => $_FILES[$field]["error"][$index],
    ];
  }

  return $result;
}

// ================================================================
// 送信がサーバーの受け入れ上限を超えた場合の検知
//
// 【なぜ専用の検知が要るか】POST全体の大きさが php.ini の post_max_size を超えると、
// PHPは本文の解析そのものを打ち切り、$_POST と $_FILES を**丸ごと空**にした状態で
// スクリプトを起動します。つまり $_POST["postSend"] も server_token も存在しません。
// 下のPOST分岐は「どのボタンが押されたか」を $_POST のキーで見分けているので、
// この状態ではどの枝にも入らず、何のメッセージも出ないまま元の画面が再表示されます。
// 利用者から見ると「送信ボタンを押したのに、うんともすんとも言わない」状態です。
// 実際、大きめの動画を添付したときにこれが起きていました。
//
// 【$_POSTのキーで判定してはいけない】検知したいのは、まさにその$_POSTが消えている
// 状態です。$_POSTを見る形で書くと、検知したい場面でだけ検知できません。ボディの
// 解析結果に左右されないCONTENT_LENGTHヘッダーを見ます。
// (p-drive・p-5second・p-meikiee も同じ判定を持っています。直すときは揃えてください)
// ================================================================

/** php.iniの "8M" / "2G" のような表記をバイト数へ直します。 */
function pipsPhpIniBytes($value) {
  $text   = trim((string)$value);
  $unit   = strtolower(substr($text, -1));
  $number = (int)$text;
  $result = 0;

  if ($text === "") {
    $result = 0;
  } else if ($unit === "g") {
    $result = $number * 1024 * 1024 * 1024;
  } else if ($unit === "m") {
    $result = $number * 1024 * 1024;
  } else if ($unit === "k") {
    $result = $number * 1024;
  } else {
    $result = $number;
  }

  return $result;
}

/**
 * このサーバーが今まさに使っている上限(post_max_size と upload_max_filesize の
 * 小さい方)をバイト数で返します。0は「上限なし、または読み取れなかった」です。
 *
 * 【固定値を書かないこと】php.iniは移設や相乗りで変わります。定数に書き写すと、
 * 画面の案内だけが古い数字のまま残り、利用者は案内どおりに小さくしたのに
 * また弾かれる、という直しようのない状態になります。毎回ini_get()し直します。
 */
function pipsEffectiveUploadLimitBytes() {
  $postMax   = pipsPhpIniBytes(ini_get("post_max_size"));
  $uploadMax = pipsPhpIniBytes(ini_get("upload_max_filesize"));
  $result    = 0;

  if ($postMax > 0 && $uploadMax > 0) {
    $result = min($postMax, $uploadMax);
  } else if ($postMax > 0) {
    $result = $postMax;
  } else if ($uploadMax > 0) {
    $result = $uploadMax;
  } else {
    $result = 0;
  }

  return $result;
}

/** バイト数を人が読める大きさへ。案内文に埋めるためだけに使います。 */
function pipsFormatBytes($bytes) {
  $result = "";

  if ($bytes >= 1024 * 1024 * 1024) {
    $result = round($bytes / 1024 / 1024 / 1024, 2) . "GB";
  } else if ($bytes >= 1024 * 1024) {
    $result = round($bytes / 1024 / 1024, 1) . "MB";
  } else if ($bytes >= 1024) {
    $result = round($bytes / 1024, 1) . "KB";
  } else {
    $result = $bytes . "B";
  }

  return $result;
}

/** 今回の送信が上限を超えて捨てられたなら true。 */
function pipsPostMaxSizeExceeded() {
  $contentLength = (int)($_SERVER["CONTENT_LENGTH"] ?? 0);
  $result = false;

  if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    $result = false;
  } else if ($contentLength <= 0) {
    // 本文が無い(または長さを名乗っていない)POST。超過ではありません。
    $result = false;
  } else if (!empty($_POST) || !empty($_FILES)) {
    // 本文を解析できている以上、打ち切られてはいません。
    $result = false;
  } else {
    // 長さを名乗って送られてきたのに、解析結果が両方とも空。
    // これが起きるのは上限超過のときだけです。
    $result = true;
  }

  return $result;
}

/** 超過したときに画面へ出す文言。実際の上限をその場で読んで埋め込みます。 */
function pipsPostMaxSizeExceededMessage() {
  $limitBytes = pipsEffectiveUploadLimitBytes();
  $result     = "";

  if ($limitBytes > 0) {
    $limitText = "（このサーバーの上限は1回の送信につき約" . pipsFormatBytes($limitBytes) . "です）";
  } else {
    $limitText = "";
  }

  $result = "送信された内容がサーバーの受け入れ上限を超えていたため、お預かりできませんでした" . $limitText
    . "。恐れ入りますが、添付する画像・動画を減らすか、小さいものに変えてお試しください。"
    . "なお画像・動画は1つあたり" . pipsFormatBytes(PIPS_MEDIA_MAX_BYTES) . "まで、"
    . "1回につき" . PIPS_MEDIA_MAX_COUNT . "個までです。"
    . "/Your submission was larger than this server accepts, so it could not be saved. "
    . "Please attach fewer or smaller files and try again.";

  return $result;
}

// header("Location: ...") + exit を一本化した関数です。
// $mode = "queryParam"（既定）: セッションの"query_param"（保存済みのGETパラメータ）に$urlPathを続けて遷移します。
// $mode = "direct"           : $urlPathをそのまま遷移先として使います（query_paramは無視します）。
// 遷移先が空になる場合は真っ白なページになってしまうため、自動的に"./"を補います。
function processResult($msg = null, $urlPath = "", $mode = "queryParam") {
  global $isLite;

  if (isset($msg)) {
    PipsSession::push("post_complete_msg", $msg);
  }

  // GET メソッドで再表示します
  // (query_paramを使う枝はセッションの"query_param"が非空であることを確認済みのため、
  // 結果が空になることはなく、"./"補完が必要なのはelse側だけです)
  if ($mode !== "direct" && !empty(PipsSession::get("query_param"))) {
    $redirectTo = PipsSession::get("query_param") . $urlPath;
  } else {
    $redirectTo = ($urlPath !== "") ? $urlPath : "./";
  }

  // 軽量版はセッション（Cookie）で維持していますが、軽量版を使うような端末はCookie自体の
  // 扱いも怪しい場合があるため、リダイレクト先のURLにも明示的に "lite=1" を付けて二重に保険をかけます。
  // (呼び出し元がここだけだったため、専用関数pipsAppendLiteParam()は廃止してこの中に統合しました。
  // 例: "./#" -> "./?lite=1#" / "./?at=foo" -> "./?at=foo&lite=1")
  if ($isLite) {
    $hashPos = strpos($redirectTo, "#");
    $base = ($hashPos !== false) ? substr($redirectTo, 0, $hashPos) : $redirectTo;
    $hash = ($hashPos !== false) ? substr($redirectTo, $hashPos) : "";
    $separator = (strpos($base, "?") !== false) ? "&" : "?";
    $redirectTo = $base . $separator . "lite=1" . $hash;
  }

  header("Location: {$redirectTo}");
  exit;
}

// 【開発者の思い出の品】あえてhttp_build_query()等に頼らず、for文で自作しています。
// しゃれた関数で済ませず、このまま残してください。
// 現在のGETパラメータをセッションに保存します（processResult()のqueryParamモードで使用します）。
// postSend時にしか実行されていなかったため、他のPOSTアクション（フォロー、いいね等）では
// 古い（または未設定の）query_paramのまま遷移してしまうバグがありました。
// そのためPOST分岐の先頭で必ず実行するようにしています。
function saveQueryParamToSession() {
  // $resultを空文字で初期化しループの外で必ずセッションへ反映することで、
  // 今回のリクエストに$_GETが無い場合（例：GETパラメータなしでのフォーム送信）でも
  // 前回のquery_paramが使い回されず、きちんと空に更新されるようにしています。
  $result = "";
  foreach ($_GET as $paramName => $paramValue) {
    // 【liteだけは持ち越さないこと】これは「このリクエストの間だけ軽量版で返して」という
    // その場限りの印で、どのページを見ているかを表すパラメータではありません。ここで
    // 拾ってしまうと、processResult()が組む戻り先URLに前回の印が居座ります。軽量版を
    // OFFにした直後の戻り先に lite=1 が残り、ボタンを押したのに軽量版のまま戻ってくる、
    // という形で表に出ます。保険が要るときはprocessResult()が自分で付け直します。
    if ($paramName === "lite") {
      continue;
    }
    if ($result === "") {
      $result = "?" . $paramName . "=" . urlencode($paramValue);
    } else {
      $result .= "&" . $paramName . "=" . urlencode($paramValue);
    }
  }
  PipsSession::set("query_param", $result);
}

// ================================================================
// 通知(プッシュ通知・デスクトップ通知・RSS)との窓口。
//
// 実体は main/pusyuusystem/apis/pusyuu_push/index.php にあり、pipsが持つのは
// 「pipsにとっての意味」だけです——誰宛てなのか、いつ送るのか、何と書くのか。
// PipsAccountFeature がアカウント基盤に対してそうであるのと同じ関係です。
//
// 【pipsでの宛先(userid)はログイン中アカウントのuseridハッシュ】
// 通知APIから見ると userid はただの不透明な文字列で、中身の意味は問われません
// (PUSH_INTEGRATION_SPEC.md 1節)。pipsでは セッションの"userid" をそのまま使います。
// つまり通知を受け取れるのはログインしている人だけです。未ログインでも読み書きできる
// のがpipsの性質なので、ここは「受け取りたい人がログインする」で構いません。
//
// 【新しいスレッドの通知は、購読した人にだけ配られる】
// 投稿のたびに全利用者へ配るわけではありません。通知APIのブロードキャストは
// 「そのサービスで購読かRSSフィードを持っている人」だけを宛先にします。つまり
// 自分でボタンを押した人にしか届きません。
//
// 【失敗しても投稿処理を巻き込まないこと】通知が送れなかったからといって、投稿の
// 保存が失敗したことにはなりません。ここの関数は例外を投げず、失敗はerror_logへ
// 残すだけにしてあります。
// ================================================================
class PipsPush {
  const SERVICE = 'pips';
  // 通知APIは main(台帳のID)の中にあります。繋ぐ先と名乗るホスト名は台帳から受け取ります。
  // 【ここに 127.0.0.1 やドメインを書き戻さないこと】理由は PIPS_POST_API_PATH と同じ。
  const API_REGISTRY_ID = 'main';
  const API_PATH        = '/pusyuusystem/apis/pusyuu_push/index.php';

  /**
   * accountsと同じ鍵ファイル(pips_account_key.php)からHMACで導出する合言葉。使えなければ空文字。
   *
   * 【鍵は PipsHiddenKeys::read() で読むこと。上へ登って探さないこと】以前はここで
   * __DIR__ から1段ずつ上のフォルダへ移りながら最大8段まで鍵ファイルを探していました。
   * 規約(上へ登って探す探索はしない)に反し、関係ない場所に置かれたときに無関係な
   * 同名フォルダの鍵を掴む危険がありました。今は固定の相対パス1つだけを見ます。
   * 【無くても作らないこと】この鍵はメイキーと共有しています。pips が勝手に作ると、
   * メイキー側と合言葉が食い違い、通知APIに必ず断られるようになります。
   */
  private static function secret(): string {
    static $resolved = false;
    static $secret = '';

    // 1リクエストにつき1回だけ読みます。使えなかったという答えも覚えます。
    if ($resolved) {
      // 前回の答えをそのまま使います。
    } else {
      $resolved = true;
      $read = PipsHiddenKeys::read('pips_account_key.php');

      if ($read['key'] === null) {
        error_log('[PIPS push] 合言葉を導出できません。理由=' . $read['problem']);
        $secret = '';
      } else {
        $secret = hash_hmac('sha256', 'pusyuu_push_api', $read['key']);
      }
    }

    return $secret;
  }

  /** 通知APIを1回叩く。届かなくても例外は投げず、失敗を表す配列を返す。 */
  public static function api(string $action, array $params = []): array {
    $params['api_secret'] = self::secret();
    $result = [];

    if ($params['api_secret'] === '') {
      // 使えない理由は secret() が1度だけログに出しています。ここでは送らなかったことだけを残します。
      error_log('[PIPS push] 合言葉が無いため、通知APIへ送りませんでした。action=' . $action);
      $result = ['ok' => false, 'error' => 'no_secret'];
    } else {
      $body = http_build_query($params);

      // 通信は台帳を読む係(PusyuuRegistryClient::call)の1本だけです。宛先・Host・証明書の
      // 確かめ方はあちらが台帳から決めます。台帳の係が無い環境では届かない扱いにします。
      if (class_exists('PusyuuRegistryClient')) {
        $response = PusyuuRegistryClient::call(self::API_REGISTRY_ID, 'POST', self::API_PATH . '?api=' . urlencode($action), ['Content-Type: application/x-www-form-urlencoded'], $body, 5);
        $raw = ($response['problem'] === '') ? $response['body'] : false;
      } else {
        $raw = false;
      }

      $decoded = ($raw === false || $raw === null || $raw === '') ? null : json_decode((string)$raw, true);

      if ($raw === false || $raw === null || $raw === '') {
        $result = ['ok' => false, 'error' => 'api_unreachable'];
      } else if (!is_array($decoded)) {
        $result = ['ok' => false, 'error' => 'api_broken_response'];
      } else {
        $result = $decoded;
      }
    }

    return $result;
  }

  /** 通知の宛先。ログインしていなければ null(購読も通知もできない)。 */
  public static function actorId(): ?string {
    $userid = PipsSession::get('userid', null);
    return (is_string($userid) && $userid !== '') ? $userid : null;
  }

  /**
   * 新しいスレッドが立ったことを、購読している人へ知らせる。
   *
   * 返信では呼びません。返信まで流すと、盛り上がっているスレッド1本で通知が
   * 埋まります。「新しい話題が始まった」だけを知らせる、という線引きです。
   */
  public static function announceNewThread(string $subject, string $text, string $postId): void {
    $body = mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, 100);
    $res = self::api('broadcast', [
      'service' => self::SERVICE,
      'title'   => '新しいスレッド: ' . mb_substr($subject, 0, 40),
      'body'    => $body,
      // 通知は要求と無関係に誰かへ届くので、今の入口ではなく公開の顔の住所を使います。
      'url'     => class_exists('PusyuuRegistryClient') ? (string)PusyuuRegistryClient::publicUrl('pips', '/?id=' . rawurlencode($postId)) : '',
      // 同じtagにしておくと、続けて立った時に通知が積み上がらず1件にまとまります。
      'tag'     => 'pips-new-thread',
    ]);
    if (empty($res['ok'])) {
      error_log('[PIPS push] 新しいスレッドの通知を送れませんでした。error=' . var_export($res['error'] ?? null, true));
    }
  }

  /**
   * 投稿に感想スタンプが届いたことを、その投稿の投稿者へ知らせる。
   * $fromUsername は押した人のユーザー名で、ログインしていない人なら null(「匿名の方」)。
   *
   * 送らない場合:
   *   - 匿名の投稿(userid が無い)。送り先がありません。
   *   - 自分の投稿に自分で押した。
   * 呼ぶのは数に**数えたときだけ**です(PipsStamps::press())。選び直しでは送りません
   * (種類を変えるたびに届くと、同じ人からの知らせが積み上がるため)。
   *
   * 【tag を投稿ごとにすること】人気の投稿で通知が積み上がらないよう、同じ投稿の分は
   * 新しい1件に置き換わります(プッシュ通知の場合。RSSは受信箱の履歴なので1件ずつ載ります)。
   */
  public static function notifyStamped($post, string $type, ?string $fromUsername): void {
    $owner  = (string)($post->userid ?? '');
    $postId = (string)($post->id ?? '');
    $label  = PipsStamps::TYPES[$type] ?? null;

    if ($owner === '' || $postId === '' || $label === null) {
      // 匿名の投稿(知らせる相手がいない)、または呼び出し側の渡し間違い。
    } else if (self::actorId() !== null && hash_equals($owner, (string)self::actorId())) {
      // 自分の投稿に自分で押した。
    } else {
      $subject = html_entity_decode((string)($post->subject ?? ''), ENT_QUOTES, 'UTF-8');
      $who     = ($fromUsername !== null && $fromUsername !== '') ? '@' . $fromUsername . 'さん' : '匿名の方';
      $res = self::api('send', [
        'service' => self::SERVICE,
        'userid'  => $owner,
        'title'   => '感想スタンプが届きました: ' . mb_substr($subject, 0, 40),
        'body'    => $who . 'から「' . $label[0] . $label[1] . '」が届きました。',
        // 通知は要求と無関係に届くので、公開の顔の住所を使います(announceNewThread と同じ理由)。
        'url'     => class_exists('PusyuuRegistryClient') ? (string)PusyuuRegistryClient::publicUrl('pips', '/?id_one_post=' . rawurlencode($postId)) : '',
        'tag'     => 'pips-stamp-' . $postId,
      ]);
      if (empty($res['ok'])) {
        error_log('[PIPS push] 感想スタンプの通知を送れませんでした。error=' . var_export($res['error'] ?? null, true) . ' id=' . $postId);
      }
    }
  }

  public static function vapidPublicKey(): string {
    $result = '';

    if (!empty(PipsSession::get('pips_push_vapid_key'))) {
      // 一度取れた鍵はセッションが続く間ずっと同じなので、聞き直しません。
      $result = (string)PipsSession::get('pips_push_vapid_key');
    } else {
      $res = self::api('vapid_public_key');
      $key = !empty($res['ok']) ? (string)($res['public_key'] ?? '') : '';
      if ($key !== '') { PipsSession::set('pips_push_vapid_key', $key); }
      $result = $key;
    }

    return $result;
  }

  /**
   * 通知チャンネルの状態。ページ描画のたびに見るのでセッションへ短時間だけ控えます。
   * 値を変えるのは自分自身(setChannel)だけなので、そこで控えを捨てれば古くなりません。
   */
  public static function channelStates(string $userid): array {
    return self::serverView($userid)['states'];
  }

  /**
   * 通知API側から見た、この人の状態。channels_get 1回ぶんを控えから返します。
   *   states        … チャンネルごとの unset/allow/deny(取れなければ空配列)
   *   subscriptions … 通知APIに登録されている端末の数(取れなければ null)
   *
   * subscriptions は push.js が「ブラウザは購読しているのにサーバには1台も無い」
   * ずれを見つけるのに使います。購読・解除のたびに forgetChannelStates() で控えを
   * 捨てているので、自分の操作で古くなることはありません(別端末での操作は
   * 最大5分遅れますが、使い道が「0台かどうか」なので害はありません)。
   */
  public static function serverView(string $userid): array {
    $cache     = PipsSession::get('pips_push_channels', null);
    $cacheLive = is_array($cache) && (int)($cache['at'] ?? 0) > time() - 300 && is_array($cache['states'] ?? null)
      && array_key_exists('subscriptions', $cache);
    $result    = ['states' => [], 'subscriptions' => null];

    if ($cacheLive) {
      $result = ['states' => $cache['states'], 'subscriptions' => $cache['subscriptions']];
    } else {
      $res    = self::api('channels_get', ['service' => self::SERVICE, 'userid' => $userid]);
      $states = (!empty($res['ok']) && is_array($res['channels'] ?? null)) ? $res['channels'] : [];
      $subs   = (!empty($res['ok']) && is_int($res['subscriptions'] ?? null)) ? $res['subscriptions'] : null;
      if (!empty($states)) {
        PipsSession::set('pips_push_channels', ['at' => time(), 'states' => $states, 'subscriptions' => $subs]);
      }
      $result = ['states' => $states, 'subscriptions' => $subs];
    }

    return $result;
  }

  /**
   * 購読・解除の結果を、push-subscribe.js が読める形でブラウザへ返して終わる。
   *
   * 【HTTPステータスと error を必ず付ける】以前は通知APIが断っても 200 で
   * {"ok":false} だけを返しており、ブラウザ側は「登録しました」と表示していました。
   * error はブラウザ側が表示文を選ぶための分類で、通知APIの内部の項目名や
   * 鍵の置き場所は出しません(外向けは分類まで)。
   */
  public static function replyToBrowser(array $res): void {
    $apiError = (string)($res['error'] ?? '');

    if (!empty($res['ok'])) {
      $status  = 200;
      $error   = '';
      $message = '';
    } else if ($apiError === 'channel_denied') {
      $status  = 403;
      $error   = 'channel_denied';
      $message = 'このアカウントはプッシュ通知を使わない設定になっています。「RSSの設定を開く」からプッシュ通知を「使う」に戻すと受け取れます。';
    } else if ($apiError === 'invalid_input') {
      $status  = 400;
      $error   = 'invalid_subscription';
      $message = 'ブラウザから受け取った購読情報が不完全でした。ページを再読み込みしてもう一度お試しください。';
    } else if ($apiError === 'api_unreachable') {
      $status  = 502;
      $error   = 'push_server_unreachable';
      $message = '通知サーバーに接続できませんでした。しばらくしてからもう一度お試しください。';
    } else if ($apiError === 'no_secret') {
      $status  = 500;
      $error   = 'server_misconfigured';
      $message = '開発者の設定ミスにより、通知を登録できない状態です。お手数ですがお問い合わせください。';
    } else {
      // api_broken_response や、通知API側の想定外の失敗。
      $status  = 502;
      $error   = 'push_server_error';
      $message = '通知サーバーで処理できませんでした。しばらくしてからもう一度お試しください。';
    }

    if ($error !== '') {
      error_log('[PIPS push] ブラウザからの依頼を処理できませんでした。api_error=' . var_export($apiError, true) . ' → ' . $error);
    }
    http_response_code($status);
    echo json_encode(['ok' => $error === '', 'error' => $error, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
  }

  public static function forgetChannelStates(): void {
    PipsSession::forget('pips_push_channels');
  }

  /**
   * そのチャンネルを使ってよいか。denyのときだけfalse。
   * 状態を取れなかったときはnullを返し、「分からない」をそのまま呼び出し側へ渡します
   * (falseと同じ扱いにすると、APIが一時的に落ちただけで通知が黙って消えます)。
   */
  public static function channelAllowed(string $userid, string $channel): ?bool {
    $states = self::channelStates($userid);
    $result = null;

    if (empty($states)) {
      // 状態そのものを取れていない。「拒否されている」とは違うのでnullのままにします。
      $result = null;
    } else {
      $result = (($states[$channel] ?? 'unset') !== 'deny');
    }

    return $result;
  }

  public static function setChannel(string $userid, string $channel, string $state): array {
    $res = self::api('channels_set', [
      'service' => self::SERVICE, 'userid' => $userid, 'channel' => $channel, 'state' => $state,
    ]);
    self::forgetChannelStates();
    return $res;
  }

  /**
   * RSS設定画面のURL。押されたその場で発行して転送します。
   * URLには短命の資格情報が載るため、ページのHTMLへ埋め込みません
   * (PUSH_INTEGRATION_SPEC.md 6節)。
   */
  public static function settingsUrl(string $userid): string {
    $res = self::api('settings_ticket', [
      'service'    => self::SERVICE,
      'userid'     => $userid,
      // 戻り先は、今この画面を見ている本人が入ってきた入口に合わせます(台帳の顔から)。
      'return_url' => class_exists('PusyuuRegistryClient') ? (string)PusyuuRegistryClient::url('pips', '/') : '',
    ]);
    return !empty($res['ok']) ? (string)($res['url'] ?? '') : '';
  }

  /**
   * push-sw.js の push イベントから叩かれ、表示する中身をJSONで返す。
   *
   * 通知APIのinboxは明示的にackするまで消えないため、取得したら必ずその場で
   * ackします。怠ると、次にpushイベントが起きるたびに過去ぶんまで再表示されます。
   */
  public static function inboxJson(string $userid): string {
    $res = self::api('inbox', ['service' => self::SERVICE, 'userid' => $userid]);
    $items = !empty($res['ok']) ? ($res['items'] ?? []) : [];
    if (!empty($items)) {
      $ids = array_values(array_filter(array_map(
        static fn($it) => is_string($it['id'] ?? null) ? $it['id'] : null, $items
      )));
      self::api('ack', ['service' => self::SERVICE, 'userid' => $userid, 'ids' => $ids]);
    }
    return (string)json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
  }
}

// ================================================================
// 投稿データの読み書き(accounts側と同様、生API呼び出し+ローカルフォールバックの2層構成)。
// PipsPostHandlersのdocコメントで触れている通り、ここは「投稿の意味」を扱うPipsPostHandlers
// とは別の関心事(データの読み書きAPI+フォールバック層)のため、専用クラスに分けています。
// ================================================================
class PipsPostIO {
  // 投稿が見つからない場合JSONが壊れてることを示すNULLを使わず空のJSONであることを明示的にトランザクションに伝える事。
  // page/limitはリクエストごとに異なるため、固定値を1つグローバルに持つのではなく、
  // 呼び出し元(postData / localRead)がその場で自分の値を使って組み立てます。
  private static function notFoundJson(int $page, int $limit): string {
    return json_encode([
        "item"          => [],
        "total"         => 0,
        "total_replies" => 0,
        "page"          => $page,
        "limit"         => $limit,
      ], JSON_UNESCAPED_UNICODE);
  }

  /**
   * 投稿API(pusyuu_ips)を1回呼びます。宛先・Host は台帳から決まります。
   * 戻り値: [$res(本文 or false), $reached(届いたか), $status("HTTP/1.1 200 OK" の形 or "")]
   * 呼び出し側の判定(strpos($status, "200") 等)は以前と同じ形のまま使えます。
   */
  private static function apiRequest(string $method, string $query, array $headers, string $body): array {
    if (class_exists('PusyuuRegistryClient')) {
      $response = PusyuuRegistryClient::call(PIPS_POST_API_REGISTRY_ID, $method, PIPS_POST_API_PATH . $query, $headers, $body, 3);
    } else {
      $response = ['status' => 0, 'body' => null, 'problem' => 'no_client'];
    }

    $reached = ($response['problem'] === '' && $response['status'] !== 0);
    return [$reached ? (string)$response['body'] : false, $reached, $reached ? 'HTTP ' . $response['status'] : ''];
  }

  public static function postData($mode = null, $options = []) {

    // 書き込みと読み出しで返す物の種類が違う(結果配列 / JSON文字列 / null)ため、
    // 途中の値はここに集めて、関数の終わりで1度だけ返します。
    $result = null;

    if ($mode === "write") {
      // 【type は必ず名指しすること】無い・知らない type は、APIへ送る前にここで断ります。
      // 知っている type の一覧は、ローカルの控えに書く処理の一覧(LOCAL_WRITERS)そのものです。
      // 控えの側を書いていない type はAPIにも送れない、という形にしてあります(理由はLOCAL_WRITERSのコメント)。
      $type = $options["type"] ?? null;
      $envelope = ["type" => $type, "data" => $options["data"] ?? []];
      if (isset($options["parent_id"])) {
        $envelope["parent_id"] = $options["parent_id"];
      }
      if (isset($options["id"])) {
        // update / delete / increment の対象idです
        $envelope["id"] = $options["id"];
      }
      $knownType = is_string($type) && isset(self::LOCAL_WRITERS[$type]);

      if ($knownType) {
        [$res, $reached, $status] = self::apiRequest("POST", "", ["Content-Type: application/json"], (string)json_encode($envelope, JSON_UNESCAPED_UNICODE));
      } else {
        [$res, $reached, $status] = [false, false, ""];
      }
      // apiRequest() の $status は "HTTP 200" の形です。数だけを取り出して比べます。
      $code    = $reached ? (int)substr($status, 5) : 0;
      $apiBody = $reached ? json_decode((string)$res, true) : null;
      // APIが「処理の枝が無い」と返したとき(API側の書き忘れ)。5xxですが、控えへ落とすと
      // 控えにだけ書かれてAPIと食い違うので、断られたのと同じ扱いにします。
      $apiUnhandled = is_array($apiBody) && (($apiBody["error"] ?? "") === "unhandled_type");

      if (!$knownType) {
        error_log("[PIPS] 開発者の設定ミス: PipsPostIO::postData に知らない書き込みの種類が渡されました。type=" . var_export($type, true));
        $result = self::writeResult(false, "unknown_type");
      } else if ($reached && $code === 200) {
        $values = (is_array($apiBody) && is_array($apiBody["values"] ?? null)) ? $apiBody["values"] : [];
        $result = self::writeResult(true, "", $values);
      } else if ($reached && $code === 404) {
        // APIが404を返した場合は「通信は成功していて、APIが対象は無いと判断した」という意味です
        // （返信先や編集/削除対象のidが存在しない）。読み出し側(mode==="read")と同じ考え方で、
        // これはローカルへフォールバックしません。ローカルのdata.jsonはAPIが使えない時の
        // 控えであって最新とは限らないため、APIが「無い」と言っているものを控えの側で
        // 見つけて書き込んでしまうと、APIとローカルで中身が食い違ったまま進みます。
        error_log("[PIPS] APIが対象なし(404)を返しました。type=" . var_export($envelope["type"], true)
          . " parent_id=" . var_export($envelope["parent_id"] ?? null, true)
          . " id=" . var_export($envelope["id"] ?? null, true)
          . " message=" . var_export($res, true));
        $result = self::writeResult(false, "not_found");
      } else if ($reached && (($code >= 400 && $code < 500) || $apiUnhandled)) {
        // APIに届いた上で断られた(知らない type、数でない値に足そうとした等)。404と同じ理由で、
        // ローカルへは落としません。控えへ書くと、APIが断ったものが控えにだけ残り、
        // APIとローカルで中身が食い違います(差分なら、控えの数だけが増えていきます)。
        error_log("[PIPS] APIが書き込みを断りました。status=" . $status . " type=" . var_export($envelope["type"], true)
          . " id=" . var_export($envelope["id"] ?? null, true)
          . " message=" . var_export($res, true));
        $result = self::writeResult(false, "rejected");
      } else {
        // 届かない・5xx(保存先の失敗)。ここだけがローカルの控えへ落ちる道です。
        error_log("[PIPS] APIへのPOSTが失敗しました。ローカルにフォールバックします。status=" . var_export($status, true) . " type=" . var_export($envelope["type"], true));
        $result = self::localWrite($options);
      }

    } elseif ($mode === "read") {
      $params = [
        "page"  => (int)($options["page"]  ?? 1),
        "limit" => (int)($options["limit"] ?? 15),
      ];
      if (!empty($options["id"]))          { $params["id"]          = $options["id"]; }
      if (!empty($options["id_one_post"])) { $params["id_one_post"] = $options["id_one_post"]; }
      if (!empty($options["all"]))         { $params["all"]         = 1; }
      // まとめ読み(summaries() 参照)。ids / omit はどちらも配列で受け取り、カンマで繋いで送ります。
      if (!empty($options["ids"]))         { $params["ids"]         = implode(",", $options["ids"]); }
      if (!empty($options["omit"]))        { $params["omit"]        = implode(",", $options["omit"]); }
      // all=1 でAPIが分割して返してきたときの「続きの位置」。元ファイルのバイト位置で、
      // 件数ではありません(件数だと毎回先頭から読み直しになるため)。
      // 0 も有効な位置なので !empty ではなく isset で判定します。
      if (isset($options["offset"]))       { $params["offset"]      = (int)$options["offset"]; }
      [$res, $reached, $status] = self::apiRequest("GET", "?" . http_build_query($params), [], "");

      // APIは「id/id_one_postに一致する投稿が無い」場合、通信自体は正常に完了した上で
      // HTTP 404 + {"status":"error","message":"..."} を返してくる（本物の通信失敗ではない）。
      // これを他の異常系（タイムアウト・5xx・不正なレスポンス等）と同列にローカルへフォールバック
      // させてしまうと、"見つからない" が "システムエラー" として表示されてしまうため、
      // APIが返してきたstatus/messageを見て、本当にフォールバックすべき異常なのかを判定する。
      //
      // API(GET /index.php)がnot-found時に返すmessage文言の一覧。
      // 現状のAPI実装では id / id_one_post どちらの未一致でも同じ文言だが、
      // 単一文字列との===決め打ちにすると文言変更・追加時に静かに壊れるため配列で持つ。
      $readNotFoundMessages = [
        "投稿が見つかりません",
      ];
      $is404      = ($reached && strpos($status, "404") !== false);
      $apiError   = $is404 ? json_decode($res, true) : null;
      $apiStatus  = is_array($apiError) ? ($apiError["status"]  ?? null) : null;
      $apiMessage = is_array($apiError) ? ($apiError["message"] ?? null) : null;

      if ($reached && strpos($status, "200") !== false) {
        $result = $res;
      } else if ($is404 && $apiStatus === "error" && in_array($apiMessage, $readNotFoundMessages, true)) {
        // APIは生きていて正しく「対象なし」と判断しているので、ローカルへはフォールバックしない。
        // 呼び出し元(disp_data等)が期待する「item: []」形式に整形して返す。
        $result = self::notFoundJson($params["page"], $params["limit"]);
      } else {
        // ここから下はすべてフォールバックです。なぜそうなったのかだけを書き分けます。
        if ($is404 && $apiStatus === "error") {
          // status=errorだが既知のnot-foundメッセージと一致しない404。
          // API側の仕様変更やバグの可能性があるため、messageを残して安全側（フォールバック）に倒す。
          error_log("[PIPS] APIが404(status=error)を返しましたが、既知のnot-foundメッセージと一致しませんでした。ローカルにフォールバックします。message=" . var_export($apiMessage, true));
        } else if ($is404) {
          // JSONとして壊れている、あるいはstatusキー自体が無いなど、想定外の404レスポンス。
          error_log("[PIPS] APIが404を返しましたが、レスポンス形式が想定外でした。ローカルにフォールバックします。raw=" . var_export($res, true));
        }

        error_log("[PIPS] APIからのGET通信に失敗しました。ローカルにフォールバックします。");
        $result = self::localRead($options);
      }

    } else {
      error_log("[PIPS] 開発者の設定ミス: PipsPostIO::postDataのmodeが不正です。mode=" . var_export($mode, true));
      PipsSession::push("post_complete_msg", "システムエラーが発生しました。管理者にご連絡ください。/A system error occurred. Please contact the administrator.");
      $result = null;
    }

    return $result;
  }

  private static function localRead($options = []) {

    $page        = (int)($options["page"]        ?? 1);
    $limit       = (int)($options["limit"]       ?? 15);
    $id          = $options["id"]          ?? "";
    $id_one_post = $options["id_one_post"] ?? "";
    $all         = !empty($options["all"]); // 検索・並び替え用に全件をそのまま返す(PipsDispData::fetchAllForSearchSort()参照)
    $ids         = $options["ids"]  ?? [];  // まとめ読み(summaries()参照)
    $omit        = $options["omit"] ?? [];

    $exists = @file_exists(PIPS_POST_FILE);
    $raw    = $exists ? @file_get_contents(PIPS_POST_FILE) : false;
    $json   = ($raw === false) ? null : json_decode($raw, true);
    $broken = (!is_array($json) || !isset($json["item"]));
    $result = null;

    if (!$exists) {
      // 控えのファイルそのものが無い。空の一覧として答えます
      // (「壊れている」とは区別します。まだ一度も書かれていないだけなので)。
      $result = json_encode(["item" => [], "total" => 0, "page" => $page, "limit" => $limit]);
    } else if ($broken) {
      error_log("[PIPS] ローカルファイルのJSONが不正または破損しています。file=" . PIPS_POST_FILE);
      $result = null;
    } else if ($id !== "") {
      // スレッド表示。idは親にも返信にも当たり得ますが、どちらでも「親のitem」を返します。
      $target = null;
      foreach ($json["item"] as $item) {
        if ((string)($item["id"] ?? "") === $id) {
          $target = $item; break;
        }
        foreach ($item["replies"] ?? [] as $reply) {
          if ((string)($reply["id"] ?? "") === $id) {
            $target = $item; break 2;
          }
        }
      }

      $replies       = ($target === null) ? [] : ($target["replies"] ?? []);
      $total_replies = p_count($replies);

      if ($target === null) {
        $result = self::notFoundJson($page, $limit);
      } else if ($all) {
        $result = json_encode(["item" => [$target], "total_replies" => $total_replies]);
      } else {
        $target["replies"] = array_slice($replies, ($page - 1) * $limit, $limit);
        $result = json_encode(["item" => [$target], "total_replies" => $total_replies, "page" => $page, "limit" => $limit]);
      }

    } else if ($id_one_post !== "") {
      // 単体表示。こちらは当たった本人(親でも返信でも)をそのまま返します。
      $found = null;
      foreach ($json["item"] as $item) {
        if ((string)($item["id"] ?? "") === $id_one_post) {
          $found = $item; break;
        }
        foreach ($item["replies"] ?? [] as $reply) {
          if ((string)($reply["id"] ?? "") === $id_one_post) {
            $found = $reply; break 2;
          }
        }
      }

      if ($found === null) {
        $result = self::notFoundJson($page, $limit);
      } else {
        $result = json_encode(["item" => [$found], "total" => 1]);
      }

    } else if (!empty($ids)) {
      // まとめ読み。当たった本人(親でも返信でも)を、求められたidの順に返します。
      // 見つからないidは抜け落ちます。API(pusyuu_ips)の ids 分岐と同じ答え方です。
      $byId = [];
      foreach ($json["item"] as $item) {
        $byId[(string)($item["id"] ?? "")] = $item;
        foreach ($item["replies"] ?? [] as $reply) {
          $byId[(string)($reply["id"] ?? "")] = $reply;
        }
      }
      $found = [];
      foreach ($ids as $wanted) {
        if (isset($byId[$wanted])) {
          $found[] = array_diff_key($byId[$wanted], array_flip($omit));
        }
      }
      $result = json_encode(["item" => $found, "total" => p_count($found)]);

    } else {
      $items = $json["item"];
      usort($items, function ($a, $b) {
          return pipsParseDatetime($b["datetime"] ?? "") - pipsParseDatetime($a["datetime"] ?? "");
      });
      $total = p_count($items);

      if ($all) {
        // API と同じく、全件のときも omit(外すキー)を効かせます。
        $result = json_encode(["item" => array_map(function ($item) use ($omit) {
          return array_diff_key($item, array_flip($omit));
        }, $items), "total" => $total]);
      } else {
        $paged  = array_slice($items, ($page - 1) * $limit, $limit);
        $result = json_encode(["item" => $paged, "total" => $total, "page" => $page, "limit" => $limit]);
      }
    }

    return $result;
  }

  // 投稿をidでまとめて読むときの1回の件数(readByIds())。
  // 【API(pusyuu_ips)の IDS_MAX と同じ数にすること】大きくするとAPIに断られ、読み出しが
  // 控えへ落ちます。控えは本体と同期しないので、本体にある投稿が「見つかりません」になります。
  private const SUMMARY_BATCH = 100;

  /**
   * 決まった何件かの投稿を、見出しに要る分だけまとめて読みます(ブックマークの一覧用)。
   * 戻り値は readByIds() と同じです。
   *
   * 【media と replies は運びません】画像はBase64で1件あたり数十KB〜数MBあり、見出しには
   * 要りません。親投稿の replies にも返信の画像が入っています。
   */
  public static function summaries(array $ids) {
    return self::readByIds($ids, ["media", "replies"]);
  }

  /**
   * 決まった何件かの投稿を、まとめて読みます。$omit は外すトップレベルのキーです。
   * 戻り値: [投稿id => 投稿(オブジェクト)] / 読めなかった(APIにも控えにも届かない)なら null。
   * 見つからないid(削除された投稿など)は、戻り値に入りません。
   *
   * 【id_one_post を件数ぶん繰り返さないこと】APIは1回の読み出しごとに全件を読むので、
   * 20件あれば20回の全件読みになります。SUMMARY_BATCH 件ずつまとめて読みます。
   */
  public static function readByIds(array $ids, array $omit) {
    $posts  = [];
    $failed = false;

    foreach (array_chunk(array_values(array_unique($ids)), self::SUMMARY_BATCH) as $batch) {
      $raw  = self::postData("read", ["ids" => $batch, "omit" => $omit]);
      $json = ($raw === null || $raw === false) ? null : json_decode($raw);

      if ($json === null || !isset($json->item) || !is_array($json->item)) {
        // 残りの組も読みに行きません。一部だけ読めても、読めなかった分を「削除された投稿」と
        // 見分けられず、一覧に嘘が混じるためです。
        error_log("[PIPS] 投稿をまとめて(ids)読めませんでした。");
        $failed = true;
        break;
      } else {
        foreach ($json->item as $post) {
          $posts[(string)($post->id ?? "")] = $post;
        }
      }
    }

    return $failed ? null : $posts;
  }

  // ----------------------------------------------------------------
  // 重複防止の台帳(「この人はもう数えたか」)
  //
  // 数そのもの(views 等)は投稿データの側にあり、差分の書き込み(increment)で足します。
  // 台帳が覚えるのは「この投稿で、この人はもう数えたか」だけで、件数は持ちません。
  // 台帳を触るのはこのクラスの中だけです。呼び出し側は countView() のような
  // 操作の入口だけを使い、台帳・鍵・IPには触れません(台帳に書いたのに数を足し忘れる、
  // という食い違いを呼び出し側で起こせないようにするためです)。
  //
  // 【台帳には鍵つきハッシュ以外を書かないこと】台帳は pips の中(公開フォルダ)にあります。
  // 以前の view_dedup.json は鍵なしの sha256(IP) で、IPv4 は総当たりで戻せるので実質IPの一覧が
  // URLで誰でも読める状態でした。今は非公開フォルダの鍵で HMAC を取った値だけを書くので、
  // 台帳を覗かれても、鍵が無ければ意味のある物は取れません。
  // HMACには投稿のidも混ぜています。同じ人でも投稿ごとに別の値になるので、台帳から
  // 「同じ人がこの投稿とあの投稿を見た」という結び付けもできません。
  //
  // 【鍵を日替わりにしないこと】閲覧数は「同じ人は一度だけ」数えます。日替わりにすると
  // 翌日には同じ人がもう一度数えられます。
  //
  // 【鍵が使えないとき】ログインしていない人を数えません(見分ける手段が無いため)。
  // 理由はログに1度だけ残し、利用者には投稿一覧のシステムの知らせで伝えます
  // (PipsDispData::run() が ledgerReady() を見ています)。
  // ----------------------------------------------------------------
  // 台帳の置き場所(PIPS_LEDGER_FILE)は、ファイル先頭の設定にあります。
  // 鍵ファイルの置き場所・読み方・作り方は PipsHiddenKeys が持ちます(固定の相対パス1つだけを見る)。
  private const LEDGER_KEY_NAME = "pips_ledger_key.php";

  /** 台帳の鍵が使えるか(ログインしていない人を数えられるか)。 */
  public static function ledgerReady() {
    return self::ledgerKeyState()["key"] !== null;
  }

  /**
   * 台帳の鍵の状態(PipsHiddenKeys::read() の state)。利用者への知らせを原因で分けるのに使います。
   * 'broken'(鍵ファイルが壊れている)と、それ以外の使えない状態(フォルダが無い等)で文言が違います。
   */
  public static function ledgerState() {
    return self::ledgerKeyState()["state"];
  }

  /** 台帳の鍵(32バイト)。使えなければ null。 */
  private static function ledgerKey() {
    return self::ledgerKeyState()["key"];
  }

  /**
   * 台帳の鍵を読んだ答え(PipsHiddenKeys::loadOrCreate() の戻り値)。1回の要求につき1回だけ読みます。
   * フォルダがあって鍵だけが無ければ作ります。失っても台帳がやり直しになる
   * (全員がもう一度だけ数えられる)だけなので、作ってよい鍵です。
   */
  private static function ledgerKeyState() {
    static $loaded = null;

    if ($loaded !== null) {
      // 前回の答えをそのまま使います(使えないという答えも覚えます)。
    } else {
      $loaded = PipsHiddenKeys::loadOrCreate(self::LEDGER_KEY_NAME);

      if ($loaded["key"] === null) {
        error_log("[PIPS] 重複防止台帳の鍵が使えないため、ログインしていない方の閲覧を数えていません。状態=" . $loaded["state"] . " 理由=" . $loaded["problem"]);
      }
    }

    return $loaded;
  }

  /** 台帳に書く「この人」の値。同じ人でも投稿ごと・欄ごとに別の値になります(上のコメント参照)。 */
  private static function ledgerIdentity($key, $section, $postId) {
    return substr(hash_hmac("sha256", $section . "\0" . $postId . "\0" . ($_SERVER["REMOTE_ADDR"] ?? ""), $key), 0, 32);
  }

  /**
   * 台帳の $section/$postId に $identity を足します($add が true)、または取り除きます(false)。
   * 戻り値: true … 足した/取り除いた(数を動かしてよい) / false … 足す前からあった・取り除く前から無かった
   *         null … 台帳が使えない
   *
   * 取り除くのは、数えた物を取り消すとき(感想スタンプをセッションの中で取り消したとき)だけです。
   * 取り除かないと、同じ人がもう一度押しても数えられず、数が1つ少ないまま残ります。
   *
   * 【読めない台帳に書かないこと】中身がJSONとして壊れているときに空として書き直すと、
   * それまでの「数えた」が全部消え、全員がもう一度数えられます。止めてログに残します。
   * 【このロックの中でHTTP通信をしないこと】数を足す書き込みは、ここから戻った後で送ります。
   */
  private static function ledgerChange($section, $postId, $identity, $add) {
    $fp = @fopen(PIPS_LEDGER_FILE, "c+");
    $result = null;

    if ($fp === false) {
      error_log("[PIPS] 重複防止台帳を開けませんでした。file=" . PIPS_LEDGER_FILE);
      $result = null;
    } else {
      flock($fp, LOCK_EX);
      $raw = stream_get_contents($fp);
      $ledger = (trim((string)$raw) === "") ? [] : json_decode($raw, true);
      $present = is_array($ledger) && in_array($identity, $ledger[$section][$postId] ?? [], true);

      if (!is_array($ledger)) {
        error_log("[PIPS] 重複防止台帳がJSONとして読めないため、書き込みを止めました。file=" . PIPS_LEDGER_FILE);
        $result = null;
      } else if ($add === $present) {
        // 足そうとしたらもうあった / 取り除こうとしたらもう無かった。書き換えません。
        $result = false;
      } else {
        if ($add) {
          $ledger[$section][$postId][] = $identity;
        } else {
          $ledger[$section][$postId] = array_values(array_diff($ledger[$section][$postId], [$identity]));
          if (empty($ledger[$section][$postId])) {
            unset($ledger[$section][$postId]);
          }
        }
        $encoded = json_encode($ledger, JSON_UNESCAPED_UNICODE);
        ftruncate($fp, 0);
        rewind($fp);
        $written = fwrite($fp, $encoded);
        fflush($fp);

        if ($written === false || $written !== strlen($encoded)) {
          error_log("[PIPS] 重複防止台帳へ最後まで書き込めませんでした。file=" . PIPS_LEDGER_FILE);
          $result = null;
        } else {
          $result = true;
        }
      }

      flock($fp, LOCK_UN);
      fclose($fp);
    }

    return $result;
  }

  /**
   * 投稿の閲覧を数えます(閲覧数 views に1足す)。
   *
   * 数えない場合:
   *   - 同じセッションで、もうその投稿を見ている
   *   - 機械(クローラ等)らしい要求(PipsSession::looksLikeRobot())
   *   - 同じIPから、もうその投稿を見ている(台帳)
   *   - 台帳の鍵が使えず、ログインもしていない(見分ける手段が無い)
   * 鍵が使えなくてもログイン中の人は数えます。そのときの重複防止はセッションだけです。
   */
  public static function countView($post) {
    $postId = (isset($post->id) && !empty($post->id)) ? (string)$post->id : "";

    if (!PipsSession::has("viewed_post_ids") || !is_array(PipsSession::get("viewed_post_ids"))) {
      PipsSession::set("viewed_post_ids", []);
    }

    $key = self::ledgerKey();
    $count = false;

    if ($postId === "") {
      // idの無い投稿(システムメッセージ等)。数える対象がありません。
    } else if (in_array($postId, PipsSession::get("viewed_post_ids"), true)) {
      // 同じセッションでは数えません。
    } else if (PipsSession::looksLikeRobot()) {
      // 機械は数えません。セッションにも控えません(機械のセッションを太らせないため)。
    } else if ($key === null && PipsAccountFeature::isLoggedIn()) {
      PipsSession::push("viewed_post_ids", $postId);
      $count = true;
    } else if ($key === null) {
      // ログインしていない人は見分けられないので数えません(理由はログに出してあります)。
    } else {
      $added = self::ledgerChange("views", $postId, self::ledgerIdentity($key, "views", $postId), true);
      // 台帳が使えなかった(null)ときは数えず、次に開いたときにもう一度試せるよう、
      // セッションにも控えません。
      if ($added !== null) {
        PipsSession::push("viewed_post_ids", $postId);
      }
      $count = ($added === true);
    }

    // 台帳の鍵はもう返してあります(ロックを握ったままHTTP通信をしないため)。
    if ($count) {
      $res = self::postData("write", ["type" => "increment", "id" => $postId, "data" => ["views" => 1]]);
      if (empty($res["ok"])) {
        error_log("[PIPS] 閲覧数を足せませんでした。理由=" . var_export($res["reason"] ?? null, true) . " id=" . $postId);
      }
    }
  }

  /**
   * 感想スタンプを1つ数えます(投稿の $field に1足す)。台帳の "stamps" 欄で、同じIPからは
   * 1つの投稿につき1回しか数えません(種類は問いません。1人1つの投稿に1つ、という決まりのため)。
   * 押した記録(どの種類を押したか・数えたか)は呼び出し側(PipsStamps)がセッションに持ちます。
   *
   * 戻り値: "counted" … 1足した / "not_counted" … 同じIPで数え済み / "failed" … 台帳か投稿データに書けなかった
   * 【鍵が使えるときだけ呼ぶこと】PipsStamps::available() が確かめています。
   */
  public static function stampAdd($postId, $field) {
    $key = self::ledgerKey();
    $result = "failed";

    if ($key === null) {
      error_log("[PIPS] 開発者の設定ミス: 台帳の鍵が無いのに stampAdd が呼ばれました。id=" . $postId);
      $result = "failed";
    } else {
      $identity = self::ledgerIdentity($key, "stamps", $postId);
      $added = self::ledgerChange("stamps", $postId, $identity, true);

      if ($added === null) {
        $result = "failed";
      } else if ($added === false) {
        $result = "not_counted";
      } else if (self::stampIncrement($postId, [$field => 1])) {
        $result = "counted";
      } else {
        // 台帳にだけ残すと、この人は二度と数えられません。台帳の側を取り消します。
        self::ledgerChange("stamps", $postId, $identity, false);
        $result = "failed";
      }
    }

    return $result;
  }

  /** 数えたスタンプの種類を $fromField から $toField へ移します(選び直し)。移せたか。 */
  public static function stampMove($postId, $fromField, $toField) {
    return self::stampIncrement($postId, [$fromField => -1, $toField => 1]);
  }

  /**
   * 数えたスタンプを取り消します(台帳から外し、$field から1引く)。
   * 鍵が使えなくなっていても数は引きます(数を正しく保つ方を優先します)。
   */
  public static function stampRemove($postId, $field) {
    $key = self::ledgerKey();

    if ($key !== null) {
      self::ledgerChange("stamps", $postId, self::ledgerIdentity($key, "stamps", $postId), false);
    }

    return self::stampIncrement($postId, [$field => -1]);
  }

  /** 感想スタンプの数へ差分を足します(1回の書き込みで、全部の欄をまとめて)。足せたか。 */
  private static function stampIncrement($postId, array $deltas) {
    $res = self::postData("write", ["type" => "increment", "id" => $postId, "data" => $deltas]);

    if (empty($res["ok"])) {
      error_log("[PIPS] 感想スタンプの数を動かせませんでした。差分=" . json_encode($deltas) . " 理由=" . var_export($res["reason"] ?? null, true) . " id=" . $postId);
    }

    return !empty($res["ok"]);
  }

  /**
   * 書き込みの結果。呼び出し側は必ずこれを見て、成功と失敗で違う案内を出してください。
   *
   * 【boolではなく理由まで返す理由】画面に出す文言が変わるからです。
   *   ok           … 保存できた。**この時だけ**成功メッセージを出してよい。
   *   not_found    … 対象（返信先・編集/削除対象）が見つからない。もう一度送っても同じ結果に
   *                  なるので、「やり直してください」ではなく「もう無い」と伝えるべき。
   *   rejected     … APIに届いた上で、中身がおかしいと断られた(数でない値に足そうとした等)。
   *                  もう一度送っても同じ結果になります。ローカルの控えにも書いていません。
   *   unknown_type … 知らない書き込みの種類を渡された(呼び出し側の書き間違い)。どこにも送っていません。
   *   failed       … 保存そのものに失敗（APIが不通で、ローカルにも書けない）。時間をおけば
   *                  直る可能性があるので、再送を案内してよい。
   *
   * values は、差分を足す書き込み(increment)が成功したときの、足した後の値です
   * (['views' => 12] の形)。それ以外の書き込みでは空の配列です。
   *
   * 【呼び出し側で戻り値をそのまま条件にしないこと】配列は常に真になるため、
   * if ($res) と書くと失敗が成功として通ります。必ず $res["ok"] を見てください。
   */
  private static function writeResult(bool $ok, string $reason = "", array $values = []) {
    return ["ok" => $ok, "reason" => $reason, "values" => $values];
  }

  // ----------------------------------------------------------------
  // ローカルの控え(data.json)への書き込み
  //
  // 【書き込みの種類の一覧は、この表そのものです】種類名 => その種類を控えに書くメソッド名。
  // postData("write") はこの表に無い種類を、APIへ送る前に断ります。
  // だから新しい種類を足すときは、ここへ1行と、その種類のメソッドを1つ書くしかありません。
  // 控えの側を書き忘れた種類は、APIにも送れないので、開発中の最初の1回で気づけます。
  //
  // 【なぜそこまでするか】控えへ落ちるのは、APIに届かない時だけです。普段は通らないので、
  // 控えの側だけ書き忘れても誰も気づかず、APIが止まった日に初めて表に出ます。以前は
  // 知らない種類を新規投稿として追記していたので、その日には中身の無い投稿が1件増えていました。
  //
  // 各メソッドは $json(控えの中身)を直接書き換え、次の形を返します。
  //   ['outcome' => 'done' | 'not_found' | 'rejected', 'values' => 足した後の値(increment だけ)]
  // 'done' の時だけ控えへ書き戻します。探索順・結果はAPI(pusyuu_ips)側の同じ種類と揃えてあります。
  // APIに届かない時だけ挙動が変わる、という事態を避けるためです。片方を変えるときは両方を見てください。
  // ----------------------------------------------------------------
  private const LOCAL_WRITERS = [
    "post"      => "localPost",
    "reply"     => "localReply",
    "update"    => "localUpdate",
    "delete"    => "localDelete",
    "increment" => "localIncrement",
  ];

  private static function localWrite($options = []) {
    $type = (string)($options["type"] ?? "");
    $data = json_decode(json_encode($options["data"] ?? []), true);
    if (!is_array($data)) { $data = []; }

    $fp = @fopen(PIPS_POST_FILE, "c+");
    $result = null;

    // 【後始末(flock解除とfclose)は1箇所にまとめてあります】
    // 以前は「対象が見つからない」枝ごとに flock/fclose/return の3点セットが並んでいて、
    // 枝を1つ足すたびに3行を正しく書き写す必要がありました。1つでも書き忘れると、
    // ファイルを掴んだまま帰るので、その後の書き込みが全部待たされます(症状としては
    // 「投稿ボタンを押すと固まる」)。今は開けたかどうかで先に分け、開けた場合の
    // 出口を1つにしてあるので、種類を足しても後始末は自動的に通ります。
    if (!isset(self::LOCAL_WRITERS[$type])) {
      // postData() が先に断るので、普段はここへ来ません。直接呼ばれた時のための守りです。
      error_log("[PIPS] 開発者の設定ミス: ローカルの控えに知らない書き込みの種類が渡されました。type=" . var_export($type, true));
      $result = self::writeResult(false, "unknown_type");
    } else if ($fp === false) {
      error_log("[PIPS] ローカルの投稿ファイルを開けませんでした。file=" . PIPS_POST_FILE);
      $result = self::writeResult(false, "failed");
    } else {
      flock($fp, LOCK_EX);
      $raw = (string)stream_get_contents($fp);
      // 【壊れた控えを空として上書きしないこと】以前は読めなければ ["item" => []] として扱い、
      // そのまま書き戻していたので、壊れた控えが1件だけの控えで消えていました。読む側
      // (localRead())は壊れた控えを止めて「破損しています」と知らせるのに、書く側だけ消していた形です。
      // 今は読む側と揃えて、空(0バイト・空白だけ)なら空の控えとして続け、
      // 中身があるのに読めなければ、書かずに止めてログに残します。
      $json = (trim($raw) === "") ? ["item" => []] : json_decode($raw, true);
      $broken = (!is_array($json) || !isset($json["item"]) || !is_array($json["item"]));
      $writer = self::LOCAL_WRITERS[$type];
      $outcome = $broken ? ["outcome" => "broken", "values" => []] : self::$writer($json, $options, $data);

      if ($outcome["outcome"] === "broken") {
        error_log("[PIPS] ローカルの投稿ファイルのJSONが不正または破損しているため、書き込みを止めました。file=" . PIPS_POST_FILE . " type=" . $type);
        $result = self::writeResult(false, "failed");
      } else if ($outcome["outcome"] !== "done") {
        // 対象が見つからない・断った。ファイルには一切手を付けずに帰ります。
        // (この時点で$jsonを書き戻すと、読み込み時の整形やusortの結果だけが
        //  保存されてしまい、「何もしていないのに更新日時が変わる」ことになります)
        $result = self::writeResult(false, $outcome["outcome"]);
      } else {
        usort($json["item"], function ($a, $b) {
            return pipsParseDatetime($b["datetime"] ?? "") - pipsParseDatetime($a["datetime"] ?? "");
        });

        ftruncate($fp, 0);
        rewind($fp);
        // 書き込み量を確認してから「成功した」と言うこと。ディスクが一杯・書き込み権限が無い等では
        // fwrite()が途中までしか書かず（または0を返し）、その状態で成功を返すと、
        // 利用者には「投稿できました」と出たのに中身が壊れている、という最悪の形になります。
        $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $written = fwrite($fp, $encoded);
        fflush($fp);

        if ($written === false || $written !== strlen($encoded)) {
          error_log("[PIPS] ローカルの投稿ファイルへ最後まで書き込めませんでした。file=" . PIPS_POST_FILE
            . " 書けたバイト数=" . var_export($written, true) . " / 必要=" . strlen($encoded));
          $result = self::writeResult(false, "failed");
        } else {
          $result = self::writeResult(true, "", $outcome["values"]);
        }
      }

      flock($fp, LOCK_UN);
      fclose($fp);
    }

    return $result;
  }

  /** 新規投稿。探す相手がいないので、必ず 'done' です。 */
  private static function localPost(array &$json, array $options, array $data) {
    array_unshift($json["item"], $data);
    return ["outcome" => "done", "values" => []];
  }

  private static function localReply(array &$json, array $options, array $data) {
    // $parent_idには「トップレベル投稿のid」だけでなく「返信のid」も来ます（返信に付いている
    // 返信ボタンを押した場合）。返信のidだった時は、その返信を含む親投稿のreplies末尾へ
    // 追加します。探索順もAPI側(pusyuu_ips の appendReply())と同じ「トップレベル→replies内」です。
    //
    // 表示側にある findPostById() が同じ解決をしていますが、あちらはstdClass、こちらは
    // 連想配列を扱うため共用できません。片方だけ直すと読みと書きで食い違うので、
    // どちらかを変えるときは必ず両方を見てください。
    $parent_id = (string)($options["parent_id"] ?? "");
    $found     = false;

    foreach ($json["item"] as &$item) {
      if ((string)($item["id"] ?? "") === $parent_id) {
        if (!isset($item["replies"]) || !is_array($item["replies"])) { $item["replies"] = []; }
        $item["replies"][] = $data;
        $found = true;
        break;
      }
    }
    unset($item);

    if (!$found) {
      foreach ($json["item"] as &$item) {
        if (!isset($item["replies"]) || !is_array($item["replies"])) { continue; }
        foreach ($item["replies"] as $existingReply) {
          if (is_array($existingReply) && (string)($existingReply["id"] ?? "") === $parent_id) {
            $found = true;
            break;
          }
        }
        if ($found) {
          $item["replies"][] = $data;
          break;
        }
      }
      unset($item);
    }

    return ["outcome" => $found ? "done" : "not_found", "values" => []];
  }

  private static function localUpdate(array &$json, array $options, array $data) {
    // 既存item（トップレベル投稿・返信いずれも可）の部分更新。$data内のキーだけを上書きし、他は保持します。
    // pusyuu_ips API側の各StorageDriver::updateItem()と同じマージ方式です。
    $targetId = (string)($options["id"] ?? "");
    unset($data["id"]);
    $found = false;

    foreach ($json["item"] as &$item) {
      if ((string)($item["id"] ?? "") === $targetId) {
        $item = array_merge($item, $data);
        $item["id"] = $targetId;
        $found = true;
        break;
      }
      if (isset($item["replies"]) && is_array($item["replies"])) {
        foreach ($item["replies"] as &$reply) {
          if ((string)($reply["id"] ?? "") === $targetId) {
            $reply = array_merge($reply, $data);
            $reply["id"] = $targetId;
            $found = true;
            break;
          }
        }
        unset($reply);
        if ($found) { break; }
      }
    }
    unset($item);

    return ["outcome" => $found ? "done" : "not_found", "values" => []];
  }

  private static function localDelete(array &$json, array $options, array $data) {
    // 既存item（トップレベル投稿・返信いずれも可）の削除。
    // pusyuu_ips API側の各StorageDriver::deleteItem()と同じ探索順（トップレベル→replies内）です。
    $targetId = (string)($options["id"] ?? "");
    $found    = false;
    $before   = p_count($json["item"]);
    $json["item"] = array_values(array_filter($json["item"], function ($item) use ($targetId) {
          return (string)($item["id"] ?? "") !== $targetId;
    }));

    if (p_count($json["item"]) !== $before) {
      $found = true;
    } else {
      foreach ($json["item"] as &$item) {
        if (isset($item["replies"]) && is_array($item["replies"])) {
          $repliesBefore = p_count($item["replies"]);
          $item["replies"] = array_values(array_filter($item["replies"], function ($reply) use ($targetId) {
                return (string)($reply["id"] ?? "") !== $targetId;
          }));
          if (p_count($item["replies"]) !== $repliesBefore) {
            $found = true;
            break;
          }
        }
      }
      unset($item);
    }

    return ["outcome" => $found ? "done" : "not_found", "values" => []];
  }

  /**
   * 数値のキーへ差分を足します。API側(pusyuu_ips の applyIncrements())と同じ決まりです。
   *   - 無いキーは0から数える / 結果が0未満なら0で止める
   *   - 足す先が数でなければ何も変えずに 'rejected'(APIの409と同じ)
   *   - 差分が整数でない、id・replies に足そうとした場合も 'rejected'(APIの400と同じ)
   * 【読んでから足すまでは localWrite() のロックの中です】ここでロックを取り直さないでください。
   */
  private static function localIncrement(array &$json, array $options, array $data) {
    $targetId = (string)($options["id"] ?? "");
    $result   = ["outcome" => "not_found", "values" => []];
    $valid    = !empty($data);

    foreach ($data as $key => $delta) {
      if (!is_string($key) || $key === "id" || $key === "replies" || !is_int($delta)) {
        $valid = false;
      }
    }

    if (!$valid) {
      error_log("[PIPS] ローカルの控えで差分を足せませんでした(差分の形が不正)。id=" . $targetId . " data=" . var_export($data, true));
      $result = ["outcome" => "rejected", "values" => []];
    } else {
      foreach ($json["item"] as &$item) {
        if ($result["outcome"] === "not_found" && (string)($item["id"] ?? "") === $targetId) {
          $result = self::applyIncrements($item, $data);
        }
        if ($result["outcome"] === "not_found" && isset($item["replies"]) && is_array($item["replies"])) {
          foreach ($item["replies"] as &$reply) {
            if ($result["outcome"] === "not_found" && is_array($reply) && (string)($reply["id"] ?? "") === $targetId) {
              $result = self::applyIncrements($reply, $data);
            }
          }
          unset($reply);
        }
      }
      unset($item);
    }

    return $result;
  }

  /** localIncrement() の中身。先に全部のキーを確かめてから書きます(途中で断ると中途半端に残るため)。 */
  private static function applyIncrements(array &$target, array $deltas) {
    $values = [];
    $isNumber = true;

    foreach ($deltas as $key => $delta) {
      $current = $target[$key] ?? null;
      if ($current === null) {
        $values[$key] = 0;
      } else if (is_int($current)) {
        $values[$key] = $current;
      } else if (is_string($current) && preg_match('/^\d+$/', $current)) {
        $values[$key] = (int)$current;
      } else {
        $isNumber = false;
      }
    }

    if (!$isNumber) {
      error_log("[PIPS] ローカルの控えで差分を足せませんでした(足す先が数ではない)。keys=" . implode(",", array_keys($deltas)));
      $result = ["outcome" => "rejected", "values" => []];
    } else {
      foreach ($deltas as $key => $delta) {
        $values[$key] = max(0, $values[$key] + $delta);
        $target[$key] = $values[$key];
      }
      $result = ["outcome" => "done", "values" => $values];
    }

    return $result;
  }
}

// ================================================================
// 感想スタンプ
//
// 投稿への反応です(お気に入り=本人用のしおり、とは別の機能です)。種類は TYPES の5つで、
// 数は投稿データの "stamp_<種類>" に、閲覧数と同じく差分の書き込みで足します。
//
// 決まり:
//   - 1人が1つの投稿に押せるのは1つだけです。
//   - 選び直しと取り消しは、**同じセッションが続いている間だけ**できます。押した記録
//     (投稿id => [種類, 数えたか])をセッションの "stamps" に持ち、それが本人の証拠です。
//     セッションが切れたら確定で、誰も取り消せません(後から本人を見分ける仕組みを持たないため)。
//   - 同じIPから同じ投稿へは1回しか数えません(台帳の "stamps" 欄)。数えなかった分は、
//     本人の画面では押した状態になりますが、数は動きません(理由は出しません)。
//
// 【クッキーの有無はセッションライブラリに任せること】クッキーを使えない人のセッションは
// 共有スクリプトが代わりに運びます。独自の見分け方を足さないでください(お気に入り数では
// それで設計がこじれました)。
//   - 共有スクリプトが読めないとき … 止めずに動かします(PHP標準のセッション)。クッキーの無い人は
//     選び直し・取り消しができなくなりますが、数は下の台帳が守ります。知らせの枠で伝えます。
//   - 台帳の鍵が使えないとき … 押せなくします。鍵をかけたIPの台帳が最後の砦で、これが無いと
//     同じ人が何度でも数えられるためです。知らせの枠で伝えます。
// ================================================================
class PipsStamps {
  // 種類 => [絵文字, 日本語, 英語]。並び順が画面の並び順です。
  // 【キーを変えないこと】投稿データの "stamp_<キー>" に数が入っています。変えると数が消えて見えます。
  public const TYPES = [
    "wakaru"   => ["🙆", "わかる",   "Relatable"],
    "naruhodo" => ["💡", "なるほど", "Insightful"],
    "waratta"  => ["😂", "笑った",   "Funny"],
    "naita"    => ["😢", "泣いた",   "Moving"],
    "sugoi"    => ["✨", "すごい",   "Amazing"],
  ];

  /** 投稿データの欄名。 */
  public static function field($type) {
    return "stamp_" . $type;
  }

  /** 種類ごとの数([種類 => 数])。 */
  public static function counts($post) {
    $result = [];

    foreach (self::TYPES as $type => $label) {
      $field = self::field($type);
      $result[$type] = (int)($post->$field ?? 0);
    }

    return $result;
  }

  /** このセッションでその投稿に押した種類。押していなければ null。 */
  public static function chosen($postId) {
    $stored = PipsSession::get("stamps", []);
    $entry  = (is_array($stored) && isset($stored[$postId]) && is_array($stored[$postId])) ? $stored[$postId] : null;
    $type   = is_array($entry) ? ($entry["type"] ?? null) : null;

    return (is_string($type) && isset(self::TYPES[$type])) ? $type : null;
  }

  /**
   * 押せない理由。押せるなら空文字です(画面に出してよい文だけを返します。仕組みは出しません)。
   * 知らせの枠(サイト全体の事情)とモーダル(その人の事情)の両方から使います。
   */
  public static function unavailableReason() {
    $result = "";

    if (!PipsPostIO::ledgerReady()) {
      $result = "感想スタンプは、開発者の設定ミスにより現在お使いいただけません。お手数ですが管理者にお伝えください。/Reaction stamps are currently unavailable due to the server configuration. Please let the administrator know.";
    } else if (PipsSession::looksLikeRobot()) {
      $result = "感想スタンプは、この接続からはお使いいただけません。/Reaction stamps are not available from this connection.";
    } else if (!PipsSession::mayAffectOthers()) {
      $result = "今の接続のままでは感想スタンプを押せません。いつものブラウザで開き直してからお試しください。/You cannot send reaction stamps with the current connection. Please reopen the page in your usual browser.";
    } else {
      $result = "";
    }

    return $result;
  }

  /** サイト全体として押せる状態か(知らせの枠に出すかどうかの判断用)。最後の砦の鍵があるかどうかです。 */
  public static function available() {
    return PipsPostIO::ledgerReady();
  }

  /** 押す・選び直す。処理の最後に processResult() で戻ります。 */
  public static function press($postId, $type) {
    $validId  = is_string($postId) && preg_match('/^[A-Za-z0-9_]{1,64}$/', $postId) === 1;
    $validTyp = is_string($type) && isset(self::TYPES[$type]);
    $reason   = self::unavailableReason();
    $found    = ($validId && $validTyp && $reason === "") ? PipsPostIO::summaries([$postId]) : [];
    $stored   = PipsSession::get("stamps", []);
    $stored   = is_array($stored) ? $stored : [];
    $entry    = ($validId && isset($stored[$postId]) && is_array($stored[$postId])) ? $stored[$postId] : null;
    $label    = $validTyp ? self::TYPES[$type][1] . "/" . self::TYPES[$type][2] : "";

    if ($reason !== "") {
      processResult($reason);
    } else if (!$validId || !$validTyp) {
      processResult("感想スタンプを押せませんでした。ページを開き直してから、もう一度お試しください。/Could not send the reaction stamp. Please reload the page and try again.");
    } else if ($found === null) {
      processResult("申し訳ありません、うまく押せませんでした。少し時間をおいてからもう一度お試しください。/Sorry, it could not be sent. Please try again in a little while.");
    } else if (!isset($found[$postId])) {
      processResult("この投稿は削除されたか、見つかりませんでした。/This post was deleted or could not be found.");
    } else if ($entry !== null && ($entry["type"] ?? "") === $type) {
      processResult("このスタンプはもう押してあります。/You have already sent this stamp.");
    } else if ($entry !== null) {
      // 選び直し。数えていた分だけ、種類を移します。
      $moved = empty($entry["counted"]) || PipsPostIO::stampMove($postId, self::field((string)$entry["type"]), self::field($type));
      if ($moved) {
        $stored[$postId] = ["type" => $type, "counted" => !empty($entry["counted"])];
        PipsSession::set("stamps", $stored);
        processResult("感想スタンプを「" . $label . "」に選び直しました。/Changed your stamp.");
      } else {
        processResult("申し訳ありません、うまく選び直せませんでした。少し時間をおいてからもう一度お試しください。/Sorry, the stamp could not be changed. Please try again in a little while.");
      }
    } else {
      $counted = PipsPostIO::stampAdd($postId, self::field($type));
      if ($counted === "failed") {
        processResult("申し訳ありません、うまく押せませんでした。少し時間をおいてからもう一度お試しください。/Sorry, it could not be sent. Please try again in a little while.");
      } else {
        $stored[$postId] = ["type" => $type, "counted" => ($counted === "counted")];
        PipsSession::set("stamps", $stored);
        if ($counted === "counted") {
          PipsPush::notifyStamped($found[$postId], $type, PipsAccountFeature::isLoggedIn() ? (string)PipsSession::get("username", "") : null);
        }
        processResult("感想スタンプ「" . $label . "」を押しました。/Stamp sent.");
      }
    }
  }

  /** 取り消す。このセッションで押した物だけです。処理の最後に processResult() で戻ります。 */
  public static function undo($postId) {
    $stored = PipsSession::get("stamps", []);
    $stored = is_array($stored) ? $stored : [];
    $entry  = (is_string($postId) && isset($stored[$postId]) && is_array($stored[$postId])) ? $stored[$postId] : null;
    $type   = is_array($entry) ? (string)($entry["type"] ?? "") : "";

    if ($entry === null || !isset(self::TYPES[$type])) {
      // セッションが切れた後など。本人と確かめられないので、取り消せません。
      processResult("取り消せる感想スタンプがありません(取り消せるのは、押したときと同じ閲覧が続いている間だけです)。/There is no stamp you can undo (you can only undo while the same visit continues).");
    } else if (!empty($entry["counted"]) && !PipsPostIO::stampRemove($postId, self::field($type))) {
      processResult("申し訳ありません、うまく取り消せませんでした。少し時間をおいてからもう一度お試しください。/Sorry, the stamp could not be undone. Please try again in a little while.");
    } else {
      unset($stored[$postId]);
      PipsSession::set("stamps", $stored);
      processResult("感想スタンプを取り消しました。/Stamp removed.");
    }
  }
}

// PIPSのPOSTアクション（フォロー/フォロー解除、投稿の編集権限判定、投稿の編集・削除・新規作成）を
// まとめたハンドラ用クラスです。全メソッドpublicなのは、このクラスが「入口の1関数+専用の非公開
// ヘルパー」という構成(PipsPostTemplate/PipsDispData)ではなく、トップレベルのPOSTディスパッチャ
// (下の方の if ($_SERVER["REQUEST_METHOD"] === "POST") {...} 、今回は意図的に触っていません)や、
// 別クラスPipsPostTemplateからも直接呼ばれる「操作の入口」の集まりだからです。
// PipsPostIO(投稿データの読み書きAPI+フォールバック層)は
// 別の関心事のため、意図的にここへは含めず別クラスのまま呼び出しています。
class PipsPostHandlers {
  // ----------------------------------------------------------------
  // フォロー/フォロワー機能（POST）のハンドラ
  // 実体(followingの保存・照合)は accounts サービス側にあります。ここではAPIを呼ぶだけです。
  // ----------------------------------------------------------------

  public static function followUser() {
    // 【processResult()はこの中でexitします】だからどの枝も、呼んだ時点でこの関数の話は
    // そこで終わります。以前は枝ごとに processResult() と return を並べていましたが、
    // returnは決して実行されない飾りでした(読む人に「ここから先も続きがある」と
    // 誤解させます)。枝を横に並べ、行き着く先が1つだけであることを形で示します。
    $loggedIn       = (PipsAccountFeature::isLoggedIn());
    $targetUsername = trim($_POST["follow_username"] ?? "");
    // ユーザー名比較(呼び出し元)に加えて、変動しないID(ハッシュ)同士でも自分自身で
    // ないことを確認します。改名直後の巡り合わせ等で、ユーザー名比較だけでは
    // 自分自身だと判定しきれないケースを防ぐためです。
    $selfByName     = ($targetUsername === "" || $targetUsername === (PipsSession::get("username", "")));
    $target         = ($loggedIn && !$selfByName) ? PipsAccountFeature::profile($targetUsername) : null;

    if (!$loggedIn) {
      processResult("フォローするにはログインが必要です。/You must be logged in to follow.");
    } else if ($selfByName) {
      processResult("フォローできません。/You cannot follow this user.");
    } else if ($target === null || $target["userid"] === PipsSession::get("userid")) {
      processResult("フォローできません。/You cannot follow this user.");
    } else {
      if (PipsAccountFeature::isFollowing($target["userid"])) {
        PipsSession::push("post_complete_msg", "既にフォローしています。/You are already following this user.");
      } else {
        PipsSession::push("post_complete_msg", PipsAccountFeature::follow($target["userid"]));
      }
      processResult(null, "./?@=" . urlencode($targetUsername), "direct");
    }
  }

  public static function unfollowUser() {
    $loggedIn       = (PipsAccountFeature::isLoggedIn());
    $targetUsername = trim($_POST["unfollow_username"] ?? "");
    $target         = $loggedIn ? PipsAccountFeature::profile($targetUsername) : null;

    if (!$loggedIn) {
      processResult("ログインが必要です。/You must be logged in.");
    } else if ($target === null) {
      processResult("ユーザーが見つかりません。/User not found.");
    } else {
      PipsSession::push("post_complete_msg", PipsAccountFeature::unfollow($target["userid"]));
      processResult(null, "./?@=" . urlencode($targetUsername), "direct");
    }
  }

  // ----------------------------------------------------------------
  // お気に入り(ブックマーク)の追加・削除(POST)のハンドラ
  //
  // 保存するのは**投稿のid**です(以前は公開URLでした)。一覧に見出しを出すのに、
  // どの投稿かをidで特定するためです。お気に入りは本人用のしおりで、数は持ちません
  // (投稿への反応は、別の機能の感想スタンプが受け持ちます)。
  // 保存先は、ログイン中ならメイキーの pips/bookmarks(PipsAccountFeature::BOOKMARK_KEY)、
  // ログインしていなければセッションの "guest_bookmarks" です。
  //
  // 【どちらも古い欄("likes" / "saveUserData")に戻さないこと】古い欄には、idへ切り替える前の
  // 公開URLの形の値が入っています。移行はせず新しく始めると決めたので(2026-09-30)、
  // 新しい欄名にして古い欄は読まないようにしてあります。
  //
  // "guest_bookmarks" の中身は ["id" => 投稿id] の並びで、加えた順です。
  // ----------------------------------------------------------------

  // ログインしていない人がセッションに持てる件数。メイキーの一覧の上限
  // (USER_DATA_LIST_MAX_ITEMS)と揃えてあります。ログインの有無で上限が変わらないように。
  private const GUEST_BOOKMARK_MAX = 1000;

  /** 投稿idとして受け取ってよい形か。POSTの値は誰でも作れるので、保存・台帳へ入れる前に見ます。 */
  private static function isBookmarkId($postId) {
    return is_string($postId) && preg_match('/^[A-Za-z0-9_]{1,64}$/', $postId) === 1;
  }

  /** ログインしていない人のお気に入り(加えた順)。形の崩れた要素は読み飛ばします。 */
  public static function guestBookmarks() {
    $stored = PipsSession::get("guest_bookmarks", []);
    $result = [];

    foreach (is_array($stored) ? $stored : [] as $entry) {
      if (is_array($entry) && self::isBookmarkId($entry["id"] ?? null)) {
        $result[] = ["id" => $entry["id"]];
      }
    }

    return $result;
  }

  public static function addBookmark() {
    $postId    = (string)($_POST["like_button"] ?? "");
    $validId   = self::isBookmarkId($postId);
    $loggedIn  = PipsAccountFeature::isLoggedIn();
    // 投稿が本当にあるかを先に確かめます。無いidを入れると、一覧に「見つかりません」が並びます。
    $found     = $validId ? PipsPostIO::summaries([$postId]) : [];
    $guestList = $loggedIn ? [] : self::guestBookmarks();

    if (!$validId) {
      processResult("お気に入りに追加できませんでした。ページを開き直してから、もう一度お試しください。/Could not add to favorites. Please reload the page and try again.");
    } else if ($found === null) {
      processResult("申し訳ありません、うまく保存できませんでした。まだ保存されていませんので、少し時間をおいてからもう一度お試しください。/Sorry, it could not be saved yet. Please try again in a little while.");
    } else if (!isset($found[$postId])) {
      processResult("この投稿は削除されたか、見つかりませんでした。/This post was deleted or could not be found.");
    } else if ($loggedIn) {
      $likeResult = PipsAccountFeature::addLike($postId);
      if (!empty($likeResult["ok"])) {
        processResult("お気に入り登録しました。/Added to favorites.");
      } else if ($likeResult["error"] === "already_saved") {
        processResult("この項目はすでに保存されています。/This item is already saved.");
      } else if ($likeResult["message"] !== "") {
        processResult($likeResult["message"]);
      } else {
        processResult("保存中にエラーが発生しました。/An error occurred while saving.");
      }
    } else if (in_array($postId, array_column($guestList, "id"), true)) {
      processResult("この項目はすでに保存されています。/This item is already saved.");
    } else if (count($guestList) >= self::GUEST_BOOKMARK_MAX) {
      processResult("保存できる件数の上限(" . self::GUEST_BOOKMARK_MAX . "件)に達しています。/You have reached the maximum number of saved items (" . self::GUEST_BOOKMARK_MAX . ").");
    } else {
      $guestList[] = ["id" => $postId];
      PipsSession::set("guest_bookmarks", $guestList);
      processResult("お気に入り登録しました。/Added to favorites.");
    }
  }

  public static function removeBookmark() {
    $postId    = (string)($_POST["remove_like"] ?? "");
    $loggedIn  = PipsAccountFeature::isLoggedIn();
    $guestList = $loggedIn ? [] : self::guestBookmarks();
    $guestHit  = array_search($postId, array_column($guestList, "id"), true);

    if ($loggedIn) {
      $likeResult = PipsAccountFeature::removeLike($postId);
      if (empty($likeResult["ok"])) {
        processResult("お気に入りを削除できませんでした。少し時間をおいてからもう一度お試しください。/Could not remove from favorites. Please try again in a little while.");
      } else {
        processResult("お気に入りを削除しました。/Removed from favorites.");
      }
    } else if ($guestHit === false) {
      // もう無い(別のタブで外した等)。外したい状態にはなっているので、成功として伝えます。
      processResult("お気に入りを削除しました。/Removed from favorites.");
    } else {
      array_splice($guestList, $guestHit, 1);
      PipsSession::set("guest_bookmarks", $guestList);
      processResult("お気に入りを削除しました。/Removed from favorites.");
    }
  }

  // ----------------------------------------------------------------
  // 投稿の編集・削除の権限判定
  //
  // 次の3つをすべて満たす場合のみ true を返します。
  //   1. ログイン中である(セッションの"userid"にログイン中アカウントのuseridハッシュがある)
  //   2. 対象の投稿に userid キーがある(postCreate()で本人確認できなかった投稿はnullのため対象外)
  //   3. 投稿のuseridハッシュが、ログイン中ユーザーのuseridハッシュと一致する
  // タイミング攻撃対策のため、===ではなくhash_equals()で比較します
  // (postCreate()内の本人確認と同じ照合パターンです)。
  // ----------------------------------------------------------------
  public static function canEditOrDeletePost($post) {
    $postUserId = is_array($post) ? ($post["userid"] ?? null) : ($post->userid ?? null);
    $result     = false;

    if (!PipsAccountFeature::isLoggedIn()) {
      // 1. ログインしていない。
      $result = false;
    } else if (empty(PipsSession::get("userid")) || !is_string(PipsSession::get("userid"))) {
      // 2. ログイン中のはずなのに本人のidが無い(セッションが壊れている)。
      $result = false;
    } else if (empty($postUserId) || !is_string($postUserId)) {
      // 3. 投稿側に持ち主が記録されていない(本人確認できなかった投稿)。
      $result = false;
    } else {
      $result = hash_equals(PipsSession::get("userid"), $postUserId);
    }

    return $result;
  }

  // 編集・削除の権限判定(canEditOrDeletePost())は投稿データそのものを引数に取るため、
  // フォームから送られてきたid(=クライアントの自己申告)を信用せず、実際のデータストアから
  // 対象の投稿を読み直した上で判定します。見つからなければnull。
  public static function findPostForPermissionCheck($postId) {
    $raw    = PipsPostIO::postData("read", ["id_one_post" => $postId]);
    $json   = json_decode($raw, true);
    $result = null;

    if (!is_array($json) || empty($json["item"][0])) {
      $result = null;
    } else {
      $result = $json["item"][0];
    }

    return $result;
  }

  // ----------------------------------------------------------------
  // 投稿の編集・削除（POST）のハンドラ
  // 実際のファイル操作は、既存のPipsPostIO::postData()(内部でlocalWrite()にフォールバック)に委譲します
  // (編集は type="update"、削除は type="delete" を使います。どちらも PipsPostIO::localWrite() と
  // API(pusyuu_ips)側の両方に対応しています)。
  // ----------------------------------------------------------------

  /**
   * 編集後の添付を組み立て直します。
   *
   * 元の投稿の添付を1つずつ見て、
   *   ・「削除する」が付いていれば飛ばす
   *   ・「差し替え」にファイルが入っていれば、**その位置のまま**新しい物に入れ替える
   *   ・どちらでもなければ、元の物をそのまま残す
   * そのあとに「新しく追加」ぶんを後ろへ足します。
   *
   * 【元の添付は画面から送られてきません】残す物は、送られてきたデータからではなく
   * $post（サーバが持っている元の投稿）から取ります。画面から送らせると、添付を
   * 触らない編集でも毎回全部が往復してしまうためです（editMediaFields()のコメント参照）。
   *
   * 戻り値:
   *   ["ok"=>true, "changed"=>bool, "media"=>配列]  changedがfalseなら添付は一切触っていません
   *   ["ok"=>false, "message"=>文言]                そのまま画面に出せます
   */
  private static function rebuildEditedMedia($post) {
    $original = is_array($post) ? ($post["media"] ?? []) : ($post->media ?? []);
    if (!is_array($original)) {
      $original = ($original === "" || $original === null) ? [] : [$original];
    }
    $original = array_values($original);

    $removeFlags = (isset($_POST["edit_media_remove"]) && is_array($_POST["edit_media_remove"])) ? $_POST["edit_media_remove"] : [];
    $changed = false;
    $media   = [];
    // 受け取れない添付が1つでもあれば、その時点で組み立てをやめます。$failureに
    // 文言が入っているかどうかが、そのまま「途中でつまずいたか」の印になります。
    // ループの途中でreturnしないのは、差し替えと追加の2つのループが同じ$mediaを
    // 育てており、どちらで折れたのかを出口の1箇所で見分けたいからです。
    $failure = null;

    foreach ($original as $i => $existing) {
      if ($failure !== null) {
        break;
      }
      if (!empty($removeFlags[$i])) {
        $changed = true;
        continue;
      }

      $replacement = pipsUploadedAt("edit_media_replace", $i);
      $encoded     = ($replacement !== null && (int)$replacement["error"] !== UPLOAD_ERR_NO_FILE)
        ? pipsEncodeUploadedMedia($replacement["name"], $replacement["size"], $replacement["tmp"], $replacement["error"])
        : null;

      if ($encoded !== null && !$encoded["ok"]) {
        $failure = $encoded["message"];
      } else if ($encoded !== null && $encoded["data"] !== null) {
        // 差し替えが届いた。元の物と同じ位置へ入れ替えます。
        $media[] = $encoded["data"];
        $changed = true;
      } else {
        // 差し替え欄が空、または未選択。元の物をそのまま残します。
        $media[] = $existing;
      }
    }

    // 追加ぶん
    if ($failure === null && isset($_FILES["edit_media_add"]) && is_array($_FILES["edit_media_add"]["name"] ?? null)) {
      $addCount = p_count($_FILES["edit_media_add"]["name"]);
      for ($i = 0; $i < $addCount && $failure === null; $i++) {
        $added   = pipsUploadedAt("edit_media_add", $i);
        $encoded = ($added === null) ? null : pipsEncodeUploadedMedia($added["name"], $added["size"], $added["tmp"], $added["error"]);

        if ($encoded === null) {
          // その番号のファイル自体が届いていない。
        } else if (!$encoded["ok"]) {
          $failure = $encoded["message"];
        } else if ($encoded["data"] === null) {
          // 未選択。
        } else {
          $media[] = $encoded["data"];
          $changed = true;
        }
      }
    }

    $result = [];

    if ($failure !== null) {
      $result = ["ok" => false, "message" => $failure];
    } else if (p_count($media) > PIPS_MEDIA_MAX_COUNT) {
      $result = ["ok" => false, "message" => "画像・動画は合わせて" . PIPS_MEDIA_MAX_COUNT . "個までです。恐れ入りますが、いくつか削除してからお試しください。/You can have up to " . PIPS_MEDIA_MAX_COUNT . " files in total. Please remove some and try again."];
    } else {
      $result = ["ok" => true, "changed" => $changed, "media" => array_values($media)];
    }

    return $result;
  }

  public static function postEdit() {
    $targetId  = trim($_POST["edit_target_id"] ?? "");
    $newText   = $_POST["edit_text"] ?? "";
    $newSubject = trim($_POST["edit_subject"] ?? "");

    // 【processResult()はこの中でexitします】どの枝も、呼んだ時点でこの関数は終わりです。
    // 「指定が無い」「権限が無い」「添付でつまずいた」の3つを横に並べ、そのどれでもない
    // ときだけ実際の保存へ進みます。
    $post    = ($targetId === "") ? null : self::findPostForPermissionCheck($targetId);
    $allowed = ($post !== null && self::canEditOrDeletePost($post));
    $rebuilt = $allowed ? self::rebuildEditedMedia($post) : null;

    if ($targetId === "") {
      processResult("編集対象が指定されていません。/No post specified to edit.");
    } else if (!$allowed) {
      processResult("この投稿を編集する権限がありません。/You don't have permission to edit this post.");
    } else if (!$rebuilt["ok"]) {
      // 添付でつまずいた場合、本文だけ保存して添付は元のまま、という中途半端な状態を作らず、
      // 何も変更せずに理由を伝えて終わります。プリフィル用のセッションは残すので、
      // モーダルを開き直せば書いた内容はそのまま残っています。
      processResult($rebuilt["message"]);
    } else {
      $updateData = ["text" => $newText];
      // タイトルが空欄で送られてきた場合は、既存のタイトルを消さずそのまま残します
      // (type="update"は$data内のキーだけを上書きするマージ方式のため、キー自体を含めなければ変更されません)。
      if ($newSubject !== "") {
        $updateData["subject"] = $newSubject;
      }
      if ($rebuilt["changed"]) {
        // 触っていない場合はキー自体を渡しません（マージ方式なので、渡さなければ元のまま）。
        $updateData["media"] = $rebuilt["media"];
      }

      $writeResult = PipsPostIO::postData("write", [
          "type" => "update",
          "id"   => $targetId,
          "data" => $updateData,
      ]);

      // 成功メッセージは保存できたと確認できた時だけ。理由は画面に出さずerror_logへ
      // （postCreate()のコメント参照）。
      if (!empty($writeResult["ok"])) {
        // 次にモーダルを開いたときに古い投稿の内容が残らないよう、プリフィル用のセッションをクリアします
        // (postCreate()が送信完了時にセッションの"reply_id"等をクリアするのと同じ理由です)。
        PipsSession::set("edit_target_id", "");
        PipsSession::set("edit_text_prefill", "");
        PipsSession::set("edit_subject_prefill", "");
        processResult("投稿を編集しました。/The post has been edited.");
      } else {
        // 書いた内容を失わせないよう、プリフィル用のセッションは残したままにします。
        error_log("[PIPS] 編集を保存できませんでした。理由=" . var_export($writeResult["reason"] ?? null, true) . " id=" . $targetId);
        processResult("申し訳ありません、編集内容をうまく保存できませんでした。変更はまだ反映されていませんので、少し時間をおいてからもう一度お試しいただけますか。/Sorry, we couldn't save your changes. They haven't been applied, so please try again in a little while.");
      }
    }
  }

  public static function postDelete() {
    $targetId = trim($_POST["delete_target_id"] ?? "");
    $post     = ($targetId === "") ? null : self::findPostForPermissionCheck($targetId);
    $allowed  = ($post !== null && self::canEditOrDeletePost($post));

    if ($targetId === "") {
      processResult("削除対象が指定されていません。/No post specified to delete.");
    } else if (!$allowed) {
      processResult("この投稿を削除する権限がありません。/You don't have permission to delete this post.");
    } else {
      $writeResult = PipsPostIO::postData("write", [
          "type" => "delete",
          "id"   => $targetId,
          "data" => [],
      ]);

      // 成功メッセージは削除できたと確認できた時だけ（postCreate()のコメント参照）。
      // ここで確認せずに「削除しました」と出すと、消えていない投稿を消えたと思わせることになり、
      // 「消したはずのものが残っている」という最も困る形の誤報になります。
      //
      // 削除だけは"not_found"を別扱いにします。これは失敗の理由ではなく**結果が違う**ためです。
      // 対象が既に無い状態は、消したい人にとっては目的が達成されています。ここを他の失敗と
      // まとめて「削除できませんでした。まだ残っています」と出すと、実際には消えているのに
      // 残っていると伝える嘘になります。
      PipsSession::set("delete_target_id", "");

      if (!empty($writeResult["ok"])) {
        processResult("投稿を削除しました。/The post has been deleted.");
      } else if ((string)($writeResult["reason"] ?? "") === "not_found") {
        processResult("その投稿は既に削除されているようです。/That post appears to have already been deleted.");
      } else {
        error_log("[PIPS] 削除できませんでした。理由=" . var_export($writeResult["reason"] ?? null, true) . " id=" . $targetId);
        processResult("申し訳ありません、うまく削除できませんでした。投稿はまだ残っていますので、少し時間をおいてからもう一度お試しいただけますか。/Sorry, we couldn't delete it. The post is still there, so please try again in a little while.");
      }
    }
  }

  // データの書き込み処理
  public static function postCreate($media) {
    $_POST["text"] = str_replace("\r", "", $_POST["text"]);

    $postCheckWord = function ($mode, $checkText) {
      $common_dangerous = [
        "ポア", "殺す", "死ね", "児ポ", "児童ポルノ", "殺しに行く", "ヤドン爆", "荒らし共栄圏", "[url=", "[/url]"
      ];
      $common_sensitive = [
        "マンコ", "まんこ", "ちんこ", "チンコ", "ちんちん", "チンチン", "性器", "性病", "金玉", "キン玉",
        "きん玉", "きんたま", "キンタマ", "性行為", "セックス", "せっくす", "SEX", "ちんぽ", "チンポ",
        "エロ", "えろ", "マンカス", "まんかす", "マンゲ", "まんげ", "マン毛", "チンゲ", "ちんげ", "チン毛"
      ];
      $list   = ($mode === "sensitive") ? $common_sensitive : $common_dangerous;
      $result = false;

      // 1つ見つかった後は、残りの語に対して照合そのものを行いません
      // ($result === false が偽になり、&& の右側が評価されないため)。
      foreach ($list as $string) {
        if ($result === false && stripos($checkText, $string) !== false) {
          $result = true;
        }
      }

      return $result;
    };

    $is_reply = !empty($_POST["reply_to"]);
    $subject  = isset($_POST["subject"]) && !empty($_POST["subject"]) ? $_POST["subject"] : "No Thread Name";

    // 投稿フォームの user_id 隠しフィールドは常に セッションの"userid"(ログイン中アカウントの
    // sha256ハッシュ)からセットされているため、それと一致する場合だけ本人の投稿として扱います。
    if (!empty($_POST["user_id"]) && !empty(PipsSession::get("userid")) && hash_equals(PipsSession::get("userid"), $_POST["user_id"])) {
      $verify_user_id = $_POST["user_id"];
    } else {
      $verify_user_id = null;
    }

    $post_arr = [
      "id"        => $is_reply ? (uniqid() . "_" . bin2hex(random_bytes(8))) : (uniqid() . bin2hex(random_bytes(8))),
      "userid"    => $verify_user_id,
      "text"      => $_POST["text"],
      "media"     => $media,
      "subject"   => $is_reply ? "RE：" . $subject : $subject,
      "name"      => isset($_POST["name"]) && !empty($_POST["name"]) ? $_POST["name"] : "Unknown Pusyuu User",
      "userinfo"  => (PipsAccountFeature::isLoggedIn()) ? PipsSession::get("username") : null,
      "datetime"  => date("Y-m-d H:i:s"), //$_POST["datetime"],
      "sensitive" => !empty($_POST["sensitive"]) || $postCheckWord("sensitive", $_POST["text"]),
      "dangerous" => $postCheckWord("dangerous", $_POST["text"]),
      "no_convert_links" => !empty($_POST["no_convert_links"]),
    ];

    if ($is_reply) {
      $writeResult = PipsPostIO::postData("write", [
          "type"      => "reply",
          "parent_id" => $_POST["reply_to"],
          "data"      => $post_arr,
      ]);
    } else {
      $writeResult = PipsPostIO::postData("write", [
          "type" => "post",
          "data" => $post_arr,
      ]);
    }

    // 【成功メッセージは保存できたと確認できた時だけ】以前はここで戻り値を一切見ず、
    // 無条件に「正常に投稿されました！！」と出していました。APIが対象なしを返した時も、
    // ディスクに書けなかった時も同じ文言が出るため、投稿できていないのにできたと
    // 思い込ませる作りでした。分岐は3通りすべてを書き、どれにも当たらない道
    // （＝何も出ないまま戻る）を残さないこと。
    // 【失敗した内部の事情は画面に出さない】APIが答えなかったのか、ローカルへ切り替えたのか、
    // 保存先がJSONなのかSQLなのかは、読む人にとって意味がありません（保存先は今後変わる
    // 可能性もあります）。切り分けに要る情報はerror_logへ送り、画面には
    // 「どうなったか」と「次にどうすればよいか」だけを書きます。
    //
    // 【戻り先は投稿前に見ていた画面のまま】新規投稿でも返信でも、元いたページへ戻します。
    // 一覧の先頭へ飛ばすと、読んでいた場所を勝手に奪うことになります。
    // 「投稿したのに一覧に出てこない」への対処は、戻り先を変えることではなく、
    // 表示中の一覧を取り直す側で行います。
    if (!empty($writeResult["ok"])) {
      // 投稿できた時だけ下書きを捨てます。
      pipsDraftClear();

      // 保存できたと確認できた後にだけ通知します。保存の前や、成否を見ずに送ると
      // 「通知は来たのに投稿が無い」が起きます。
      // 返信では送りません(理由は PipsPush::announceNewThread のコメント)。
      if (!$is_reply) {
        PipsPush::announceNewThread($post_arr["subject"], $post_arr["text"], $post_arr["id"]);
      }

      processResult("正常に投稿されました！！/The post was successfully submitted!!", "#sended");
    } else {
      // 保存できなかったので、書いた内容は下書きとして預かっておきます。
      // これをしないと、送信して失敗した瞬間に本文が消えます（一番つらい失い方です）。
      pipsDraftSave($subject, $_POST["text"] ?? "", !empty($_POST["sensitive"]), !empty($_POST["no_convert_links"]));
      error_log("[PIPS] 投稿を保存できませんでした。理由=" . var_export($writeResult["reason"] ?? null, true)
        . " 返信=" . var_export($is_reply, true)
        . " 返信先=" . var_export($is_reply ? $_POST["reply_to"] : null, true));
      processResult("申し訳ありません、うまく投稿できませんでした。まだ保存されていませんので、少し時間をおいてからもう一度お試しいただけますか。/Sorry, we couldn't post that. It hasn't been saved, so please try again in a little while.");
    }
  }
}

/**
 * 「あなたのブックマーク」(#likeList)の中身。新しく加えた順に、見出しと本文の抜粋を出します。
 *
 * 1件ずつの見た目は PipsPostTemplate::render($post, "bookmark") が決めます。
 * 投稿は PipsPostIO::summaries() でまとめて読みます(件数ぶん読みに行かないこと。理由は向こうの解説)。
 * 見つからなかった投稿(削除された等)は、外せるように「見つかりません」の1件として出します。
 * 読むこと自体に失敗したときは、全部を「見つかりません」にすると嘘になるので、
 * 知らせを1行出したうえで、同じく外せる形で並べます。
 */
function usersSaveContent($foundUser = null) {
  $outputData = "";
  $loggedIn   = PipsAccountFeature::isLoggedIn();

  if ($loggedIn) {
    $savedIds = $foundUser !== null ? ($foundUser["userData"]["pips"][PipsAccountFeature::BOOKMARK_KEY] ?? []) : [];
    $emptyMsg = "まだ、" . htmlspecialchars((string)PipsSession::get("name"), ENT_QUOTES, "UTF-8") . "さんが保存したブックマークがありません。/You don't have any saved bookmarks yet.";
  } else {
    $savedIds = array_column(PipsPostHandlers::guestBookmarks(), "id");
    $emptyMsg = "まだ、ゲストさんが保存したブックマークがありません。/You don't have any saved bookmarks yet.";
  }
  $savedIds = array_reverse(array_values(array_filter(is_array($savedIds) ? $savedIds : [], "is_string")));

  if (empty($savedIds)) {
    $outputData = $emptyMsg;
  } else {
    $posts = PipsPostIO::summaries($savedIds);

    if ($posts === null) {
      $outputData .= '<li class="bookmark bookmark_missing"><div class="title">ブックマークした投稿を読み込めませんでした。少し時間をおいてから開き直してください。/Could not load your bookmarked posts. Please reopen this in a little while.</div></li>';
    }
    foreach ($savedIds as $savedId) {
      $post = ($posts !== null && isset($posts[$savedId])) ? $posts[$savedId] : (object)["id" => $savedId, "pips_bookmark_missing" => true];
      $outputData .= PipsPostTemplate::render($post, "bookmark");
    }
  }

  return $outputData;
}

/**
 * idから投稿を探します。見つからなければnull。
 *
 * $isOneOnlyPost で「返信に当たったときに何を返すか」だけが変わります。
 *   true  … 当たった本人(返信そのもの)
 *   false … その返信を含む親投稿
 * 探し方は同じなので、探索は1つにまとめ、当たった時に何を$resultへ入れるかだけを
 * 分けています。以前は同じ二重ループが2つ並んでおり、片方だけ直すと
 * 「単体表示では出るのにスレッド表示では出ない」といった食い違いが起きる形でした。
 */
function findPostById($posts, $postId, $isOneOnlyPost = false) {
  $result = null;

  foreach ($posts as $post) {
    if ($result !== null) {
      break;
    }
    if (isset($post->id) && $post->id === $postId) {
      $result = $post;
    } else if (isset($post->replies)) {
      // 返信がある場合は、replies配列を探索
      foreach ($post->replies as $reply) {
        if ($result === null && $reply->id === $postId) {
          $result = $isOneOnlyPost ? $reply : $post;
        }
      }
    }
  }

  return $result;
}

// 投稿本文中のURL文字列をHTMLリンクに変換します。
// ローカル/プライベートIP・内部ホスト名の判定はこのクラス専用のヘルパーのため、
// private staticメソッドとしてこのクラスの中だけに閉じ込めています
// (PHPの入れ子関数定義と違い、クラス外からは本当に呼び出せません)。
class PipsLinkConverter {
  private const LOCAL_HOST_SUFFIXES = [
    "localhost",
    ".localhost",
    ".local",
    ".internal",
    ".lan",
    ".home",
    ".corp",
    ".intranet",
  ];

  // リンクの仕様変換
  public static function convert($text) {
    PipsQuote::resetPerText();
    $pattern = "/(https?:\/\/[^\s<>'\'()]+)/u";
    $replacement = function ($match) {
      $url = $match[1];
      $safeUrl = htmlspecialchars($url, ENT_QUOTES, "UTF-8");

      // 【この判定を下の警告より後ろへ動かさないこと】
      // 自分自身の投稿を指すリンクなら、引用カードとして展開します。ここを
      // isSuspiciousLocalHost()より後ろに置くと、手元で 192.168.x.x のようなIP直打ちで
      // 動かしている環境で書かれた引用が、自分の投稿なのに「ローカルアドレス宛の疑い」の
      // 赤い警告になります。カードの中に外部を指すものは何も置かないので(PipsQuote参照)、
      // 先に自分の物かどうかを確かめて構いません。自分の物でなければ空が返り、
      // これまでどおり下の判定へ進みます。
      $quoteCard = PipsQuote::cardFor($url);
      if ($quoteCard !== "") {
        return $quoteCard;
      }

      $host = (string)(parse_url($url, PHP_URL_HOST) ?? "");
      if (self::isSuspiciousLocalHost($host)) {
        return '<a rel="nofollow ugc" href="' . $safeUrl . '" target="_blank">'
        . '<span style="color:#ff0000; font-weight:bold;">⚠ ローカル/内部アドレス宛の疑いがあるリンクです/Warning: this link may point to a local or internal address: '
        . $safeUrl . '</span></a>';
      }

      if (preg_match("/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/", $url, $matches) ||
        preg_match("/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/", $url, $matches) ||
        preg_match("/youtube\.com\/playlist\?(?:.*&)?list=([a-zA-Z0-9_-]+)/", $url, $matches) ||
        preg_match("/youtu\.be\/([a-zA-Z0-9_-]+)/", $url, $matches) ||
        preg_match("/m\.youtube\.com\/([a-zA-Z0-9_-]+)/", $url, $matches)) {
        $videoId = $matches[1];
        if (preg_match("/youtube\.com\/playlist\?(?:.*&)?list=([a-zA-Z0-9_-]+)/", $url)) {
          return '<iframe style="width: 100%; height: 30vh;" src="https://www.youtube.com/embed/videoseries?list=' . $videoId . '" frameborder="0" allowfullscreen></iframe>';
        } else {
          // It"s a regular video
          return '<iframe style="width: 100%; min-height: 30vh" src="https://www.youtube.com/embed/' . $videoId . '" frameborder="0" allowfullscreen></iframe>';
        }
      }

      return '<a rel="nofollow ugc" href="' . $safeUrl . '" target="_blank">' . $safeUrl . '</a>';
    };
    return preg_replace_callback($pattern, $replacement, $text);
  }

  // ローカル/プライベートIPとみなされるホストの判定
  // 画像・動画・音声をURLから直接<img>/<video>/<audio>のsrcとして埋め込む機能は、
  // 閲覧者のブラウザに任意のURL(ローカルIPや内部ホスト名を含む)へアクセスさせて
  // しまい、内部ネットワークの走査(SSRF的な悪用)や閲覧者IPの収集に使われる
  // おそれがあるため廃止しました。リンクとしては残しますが、ローカル/内部アドレスと
  // 思われるURLについては、アンカーテキスト部に赤文字で警告を表示します。
  private static function isSuspiciousLocalHost(string $host): bool {
    $host   = strtolower(trim($host, "[]"));
    $isIp   = (bool)filter_var($host, FILTER_VALIDATE_IP);
    $result = false;

    if ($host === "") {
      $result = false;
    } else if ($isIp) {
      // IPアドレス表記の場合は、プライベート/予約済みレンジ(10.0.0.0/8、172.16.0.0/12、
      // 192.168.0.0/16、127.0.0.0/8、169.254.0.0/16、::1、fc00::/7、fe80::/10 等)かどうかを
      // FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE で判定します。これらに該当するIPは
      // 検証に失敗(false)するため、そのIPを「ローカルの疑いあり」として扱います。
      $result = (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);
    } else {
      // IPアドレス表記でない場合は、ローカル向けによく使われるホスト名/サフィックスと照合します
      foreach (self::LOCAL_HOST_SUFFIXES as $suffix) {
        $bareSuffix = ltrim($suffix, ".");
        if ($result === false && ($host === $bareSuffix || substr($host, -strlen($suffix)) === $suffix)) {
          $result = true;
        }
      }
    }

    return $result;
  }
}

// ================================================================
// 本文に貼られた「うちの投稿へのリンク」を、引用カードとして展開します。
// 使い方はYouTubeのURLをiframeに展開しているのと同じ考え方で、対象が自分の投稿になります。
//
// 【ホスト名で「うちの物か」を判定しないこと】
// 同じPIPSでも、見えているホスト名は時期と環境で変わります。過去には 21emin.wjg.jp で
// 動いていた時期があり、今は pips.pusyuuwanko.com、手元では 192.168.x.x のようなIP直打ちで
// 起動します。既存の投稿にも https://pips.pusyuuwanko.com/index.php?id_one_post=... のように
// 「その当時のホスト名とパス」で書かれたものが残っています。
//
// ホスト名の一覧と照合する形にすると、
//   ・昔のホスト名で書かれた引用が、ある日から展開されなくなる
//   ・別の場所へ丸ごと持って行った瞬間、全部ただのリンクに戻る
//   ・一覧に足し忘れても例外は出ないので、静かに壊れて気づくのが遅れる
// という3つを抱えます。ホスト名は「うちかどうか」の根拠として弱すぎます。
//
// かわりに【その投稿idがうちのデータに在るかどうか】を証拠にします。うちが持っている投稿を
// 指しているなら、書かれた当時のホスト名が何であれ、それは自分自身の引用です。idは十分に
// 長いので、よそのURLがたまたま一致することは実質ありません。一致しなければ何も起きず、
// これまでどおりのリンクとして描かれます。判定に使うのはURLの「形」だけです。
//
// 【カードの中のリンクは、貼られたURLではなく自分で組み立てること】
// 貼られたURLは古いホスト名やローカルIPのことがあります。そのまま<a href>にすると、今は
// 届かない場所や、閲覧者から見て別ネットワークの私有IPを指すリンクになります
// (PipsLinkConverter::isSuspiciousLocalHost()が警告している話と同じです)。相対リンクの
// ./?id_one_post=<id> にしておけば、どのホスト名で見ていても必ず正しく開きます。
// カードの中に外部を指すものを置かないのは、この理由と、埋め込みを口実にした
// 外部への通信を発生させないためです。
// ================================================================
class PipsQuote {
  // 1つの本文で展開する上限。URLを大量に並べた投稿1件のために、引用元の読み出しを
  // 何十回も走らせないための歯止めです。超えた分はこれまでどおりのリンクになります。
  private const MAX_PER_TEXT = 3;
  private const EXCERPT_LIMIT = 90;

  private static $expanded = 0;
  // 同じリクエストの中では、同じ投稿を二度読みに行きません。
  private static $cache = [];

  /** 本文1件ぶんの描画に入るたびに上限を数え直します(PipsLinkConverter::convert()が呼びます)。 */
  public static function resetPerText() {
    self::$expanded = 0;
  }

  /**
   * URLが投稿を指す「形」をしていれば、読み出し条件を返します。していなければ空配列。
   * ここではホスト名を一切見ません(理由はクラス冒頭)。見るのはクエリだけです。
   *   ?id_one_post=<id> … その投稿そのもの
   *   ?id=<id>          … そのスレッドの親投稿
   * パスも見ません。/ でも /index.php でも、サブディレクトリ設置でも同じ意味だからです。
   */
  private static function readOptionsFor($url) {
    $query = (string)(parse_url($url, PHP_URL_QUERY) ?? "");

    // 本文は保存時にhtmlspecialchars済みで、URL中の & が &amp; になっていることがあります。
    // 戻してからでないと2つ目以降のパラメータが正しく読めません。
    $params = [];
    if ($query !== "") {
      parse_str(html_entity_decode($query, ENT_QUOTES, "UTF-8"), $params);
    }

    $result = [];

    if ($query === "") {
      $result = [];
    } else if (isset($params["id_one_post"]) && is_string($params["id_one_post"]) && $params["id_one_post"] !== "") {
      $result = ["id_one_post" => $params["id_one_post"]];
    } else if (isset($params["id"]) && is_string($params["id"]) && $params["id"] !== "") {
      // スレッドのリンクは親投稿を引用として見せます。返信は要らないので1件だけ取ります。
      $result = ["id" => $params["id"], "page" => 1, "limit" => 1];
    } else {
      $result = [];
    }

    return $result;
  }

  /** 引用元の投稿。うちに無ければnull(=よそのURL、あるいは消された投稿)。 */
  private static function fetch(array $options) {
    $key    = isset($options["id_one_post"]) ? ("one:" . $options["id_one_post"]) : ("thread:" . $options["id"]);
    $result = null;

    if (array_key_exists($key, self::$cache)) {
      // 同じリクエストの中では二度読みに行きません。
      // (見つからなかったというnullも控えてあるので、無いものを何度も探しません)
      $result = self::$cache[$key];
    } else {
      $raw  = PipsPostIO::postData("read", $options);
      $json = ($raw !== false && $raw !== null) ? json_decode($raw) : null;

      if (isset($json->item) && !empty($json->item) && isset($json->item[0]->id)) {
        $result = $json->item[0];
      } else {
        $result = null;
      }

      self::$cache[$key] = $result;
    }

    return $result;
  }

  /**
   * URLを引用カードのHTMLへ。うちの投稿でなければ空文字を返すので、
   * 呼び出し側はそのまま今までどおりの描画へ進んでください。
   */
  public static function cardFor($url) {
    // 展開しない事情は3つあり、どれも「空文字を返して、これまでどおりのリンクにする」で
    // 終わります。順番に意味があります(上限に達していれば読み出しに行かない、
    // 形が違えば読み出しに行かない)ので、並べ替えないでください。
    $overLimit = (self::$expanded >= self::MAX_PER_TEXT);
    $options   = $overLimit ? [] : self::readOptionsFor($url);
    $post      = ($overLimit || empty($options)) ? null : self::fetch($options);
    $result    = "";

    if ($overLimit) {
      $result = "";
    } else if (empty($options)) {
      $result = "";
    } else if ($post === null) {
      $result = "";
    } else {
      self::$expanded++;
      $result = self::card($post);
    }

    return $result;
  }

  private static function card($post) {
    global $isLite;

    $postId  = (string)($post->id ?? "");
    $href    = "./?id_one_post=" . urlencode($postId);
    // subject / name は保存時にエスケープ済みのため、contents()と同じくそのまま出します。
    // userinfo だけは contents() に合わせてここでエスケープします。
    $subject = (string)($post->subject ?? "");
    $name    = (string)($post->name ?? "");
    $when    = (string)($post->datetime ?? "");
    if (isset($post->userinfo) && $post->userinfo !== "") {
      $who = "@" . htmlspecialchars((string)$post->userinfo, ENT_QUOTES, "UTF-8");
    } else {
      $who = "@匿名ユーザー";
    }

    // 本文は抜粋だけを素のテキストで出します。引用の中で更にリンクを展開すると、
    // 互いを引用し合う2投稿で終わらなくなります。抜粋にしておけば入れ子そのものが起きません。
    if (isset($post->dangerous) && $post->dangerous) {
      $body = "危険なポストとして検知されたため表示していません。/This post was flagged as dangerous and is not shown.";
    } else if (isset($post->sensitive) && $post->sensitive) {
      $body = "⚠ センシティブな投稿です。開いて確認してください。/Sensitive post. Open it to view.";
    } else {
      $body = PipsPostTemplate::excerpt($post->text ?? "", self::EXCERPT_LIMIT);
    }

    $mediaCount = p_count(PipsMedia::listOf($post));

    $html = "";

    if ($isLite) {
      // 軽量版は素のHTMLだけで完結させます(renderLite()と同じ、属性に頼らない書き方)。
      // 絵文字やCSSに意味を持たせず、読める言葉で書きます。
      $html  = '<div style="border:1px dashed #888; margin:6px 0; padding:6px;">';
      $html .= '<p>引用/Quote：<strong>【' . $subject . '】</strong> ' . $who . '：' . $name . '（' . $when . '）</p>';
      $html .= '<p>' . $body . '</p>';
      if ($mediaCount > 0) {
        $html .= '<p>画像・動画' . $mediaCount . '件/' . $mediaCount . ' media</p>';
      }
      $html .= '<p><a href="' . $href . '">この投稿を見る/View this post</a></p>';
      $html .= '</div>';
    } else {
      // 【onclickでの伝播止めについて】一覧の投稿カードは外側の<div>にonclickが付いていて、
      // 押すとスレッドへ移動します(render()のmain分岐)。引用のリンクを押した時に両方が
      // 動くと、行き先が2つ競合します。JSが無い環境では外側のonclickがそもそも動かず、
      // このリンクは普通のリンクとして機能するので、JS有無のどちらでも壊れません。
      $html  = '<div class="quote_post">';
      $html .= '<span class="quote_post_mark" aria-hidden="true">❞</span>';
      $html .= '<p class="quote_post_head">';
      $html .= '<span class="quote_post_subject">' . $subject . '</span>';
      $html .= '<span class="quote_post_user">' . $who . '</span>';
      $html .= '<span class="quote_post_name">' . $name . '</span>';
      $html .= '</p>';
      $html .= '<p class="quote_post_body">' . $body . '</p>';
      $html .= '<p class="quote_post_foot">';
      $html .= '<span class="quote_post_time">' . $when . '</span>';
      if ($mediaCount > 0) {
        $html .= '<span class="quote_post_chip" title="画像・動画' . $mediaCount . '件">🖼 ' . $mediaCount . '</span>';
      }
      $html .= '<a class="quote_post_open" href="' . $href . '" onclick="event.stopPropagation();">開く/Open</a>';
      $html .= '</p>';
      $html .= '</div>';
    }

    return $html;
  }
}

// 投稿メディアの直リンク用エンドポイント(?image, ?media_post&media_index)向けの
// ホットリンク(外部サイトからの直リンク埋め込み)判定です。.htaccessは使わず、PHP側の
// Refererチェックだけで行います。Refererが自サイト(pusyuuwanko.com系列のサブドメイン)以外の
// 場合だけホットリンクとみなします。Refererが送られてこないアクセス(直接アクセス、
// OGP用クローラーの多く、Referrer-Policyでrefererを送らないブラウザ等)は許可します。
class PipsHotlinkGuard {
  public static function isHotlink(): bool {
    $referer     = $_SERVER['HTTP_REFERER'] ?? '';
    $refererParts = ($referer === '') ? [] : (parse_url($referer) ?: []);
    $refererHost = strtolower((string)($refererParts['host'] ?? '') . (isset($refererParts['port']) ? ':' . $refererParts['port'] : ''));
    // 自サイトの範囲は、台帳(pusyuu_registry)に載っている全ての顔のホストです。
    // 【ドメインをここに書き戻さないこと】以前は 'pusyuuwanko.com' とその下を自サイトと
    // していたため、IPやLAN、別のドメインから入った人の画面では画像が直リンク扱いで
    // 弾かれていました。
    $ownHosts    = class_exists('PusyuuRegistryClient') ? PusyuuRegistryClient::knownHosts() : [];
    $result      = false;

    if ($referer === '') {
      // どこから来たか名乗っていない。許可します(直接アクセス、OGP用クローラー、
      // Referrer-Policyでrefererを送らないブラウザ等が該当します)。
      $result = false;
    } else if ($refererHost === '') {
      // 名乗ってはいるがホスト名を取り出せない。責める根拠が無いので許可します。
      $result = false;
    } else if ($ownHosts === []) {
      // 台帳が読めず、自サイトの範囲が分からない。判定そのものを止めて許可します。
      // 【拒否側に倒さないこと】台帳が止まっただけで、自サイトの画面の画像まで全部消えます。
      $result = false;
    } else {
      $result = !in_array($refererHost, $ownHosts, true);
    }

    return $result;
  }

  // ホットリンクと判定したリクエストに対して403を返して終了します。
  public static function reject(): void {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "直リンクは許可されていません。/Hotlinking is not allowed.";
    exit;
  }
}

// ================================================================
// 投稿メディア(画像・動画)の扱いを1箇所に集めたクラスです。
//
// 【なぜ束ねたか】
// 以前は「メディアをHTMLにする」仕事が3箇所に分かれ、しかも答えが2通りありました。
//   ・軽量版(renderLite)            -> ?media_post= へのリンク(軽い)
//   ・編集モーダル(editMediaFields) -> ?media_post= へのリンク(軽い)
//   ・通常版(contents/mediaZoom)    -> base64をdata: URIでHTMLへ直埋め(重い)
// 同じものを作る場所が分かれている時に何が起きるかは editMediaFields() や
// composerHeader() のコメントに書いたとおりで、ここでも「軽量版だけが軽い」という
// 食い違いがそのまま固定化していました。窓口をこのクラス1つにしてあります。
//
// 【この先ぜったいに守ること：base64をHTMLへ埋めないこと】
// 投稿データのmediaはbase64文字列です。2026-09時点のdata.jsonを数えると、883投稿のうち
// 56投稿(6.3%)がメディアを持ち、メディアは全部で65件、1件あたり58KB〜1.34MB(中央値406KB)、
// 合計で29.7MBありました。これをdata: URIでHTMLに入れると、
//   ・HTMLがそのままメガバイト級になる(base64は生バイナリの約1.33倍)
//   ・ブラウザキャッシュが効かない(次に開いた時もHTMLごと再取得になる)
//   ・画像を受け取り終わるまでHTMLのパースが止まる
//   ・OGPカードを取りに来るクローラーは待ち時間が短く、取得ごと諦めることがある
// の4つが同時に起きます。表示は必ずURL参照(PipsMedia::url())にしてください。
//
// base64という文字列がこのファイルに現れてよいのは、次の2種類の場所だけです。
//   ・投稿の保存処理(受け取ったファイルをbase64にして預ける所)
//   ・?media_post= と ?image の配信エンドポイント(復号して吐く所)
// 描画側がbase64を触る形へ戻さないでください。
// ================================================================
class PipsMedia {
  /**
   * 投稿のmediaを必ず配列で返します。
   *
   * 【なぜ正規化が要るか】過去に保存された投稿には、mediaが配列ではなく文字列1つのものが
   * あります。しかも少数派ではありません。2026-09時点でメディアを持つ56投稿のうち、50投稿が
   * この旧形式です(配列なのは6投稿だけ)。「古いデータの保険」ではなく本流だと思ってください。
   * 以前は呼び出し側それぞれが is_array() で二股に分かれていて、
   * 配列版と非配列版で拡大表示のアンカーidが違う(">1"が付くか付かないか)といった
   * 細かい食い違いまで抱えていました。入口でならしてしまえば、以後は枚数が1枚でも
   * 5枚でも同じ道を通ります。
   */
  public static function listOf($post) {
    $result = [];

    if (!isset($post->media) || empty($post->media)) {
      $result = [];
    } else if (is_array($post->media)) {
      $result = array_values($post->media);
    } else {
      // 旧形式(文字列1つ)。以後は枚数1枚の配列として同じ道を通します。
      $result = [$post->media];
    }

    return $result;
  }

  /**
   * base64の先頭だけを復号してMIMEを判定します。
   *
   * 【なぜ全体を復号しないか】種類を知るのに要るのはファイルの先頭数十バイトだけです。
   * 以前はここで最大2.4MBを丸ごと復号してfinfoに渡していました。しかも呼び出し側は
   * 「画像かどうか」を知りたいだけの場面でも同じことをしていたため(og:imageの判定)、
   * 1回の描画で同じ巨大データを何度も復号しては捨てていました。
   *
   * 【なぜ128文字か】base64は4文字=3バイトなので128文字=96バイトです。2026-09時点の
   * 実データ(image/jpeg 46件・image/png 13件・video/mp4 6件)で確かめたところ、32文字
   * (24バイト)で全件が全体復号と同じ結果になり、16文字ではPNGが application/octet-stream
   * に化けました。余裕を見て128文字です。
   * 減らす時は必ず実データで確かめてください。ここが外れると、正常な画像が
   * 「壊れている」と表示されます。
   *
   * 【判定できなかった時に全体を試す理由】96バイトに満たないほど小さいファイルや、
   * 先頭が欠けたデータのためです。この救済を省くと、小さすぎるメディアだけが
   * 壊れている扱いになります。
   */
  public static function mimeOf($base64) {
    $usable = (is_string($base64) && $base64 !== "");
    $finfo  = $usable ? new finfo(FILEINFO_MIME_TYPE) : null;
    $head   = $usable ? base64_decode(substr($base64, 0, 128)) : false;
    $result = "";

    if ($head === false || $head === "") {
      $headMime = "";
    } else {
      $headMime = (string)$finfo->buffer($head);
    }

    if (!$usable) {
      $result = "";
    } else if (strpos($headMime, "image/") === 0 || strpos($headMime, "video/") === 0) {
      // 先頭だけで種類が分かった。ここで終わるのが普通の道です。
      $result = $headMime;
    } else {
      // 先頭96バイトでは判定できなかった場合の救済(小さすぎるファイル等)。
      $full = base64_decode($base64);

      if ($full === false || $full === "") {
        $result = "";
      } else {
        $result = (string)$finfo->buffer($full);
      }
    }

    return $result;
  }

  /** 画像かどうか(og:imageに出せるかどうかの判定に使います)。 */
  public static function isImage($base64) {
    return strpos(self::mimeOf($base64), "image/") === 0;
  }

  /**
   * メディア1件を配信するURL。実体はこのファイル下部の ?media_post= エンドポイントです。
   * エスケープはしていません。HTMLへ入れる時は呼び出し側でhtmlspecialchars()してください。
   */
  public static function url($postId, $index) {
    return './?media_post=' . urlencode($postId) . '&media_index=' . (int)$index;
  }

  /**
   * 通常版の本文・拡大表示に置くメディアの実体タグ。中身ではなくURLを指します。
   *
   * loading="lazy" / preload="none" は、画面に出るまで取りに行かせないためのものです。
   * 拡大表示のモーダルは開かれるまで表示されないので、開かない限り通信も起きません。
   */
  public static function embed($postId, $index, $base64) {
    $mime   = self::mimeOf($base64);
    $url    = htmlspecialchars(self::url($postId, $index), ENT_QUOTES, "UTF-8");
    $result = "";

    if (strpos($mime, "image/") === 0) {
      $result = '<img src="' . $url . '" alt="投稿された画像/Images that have been posted." loading="lazy" decoding="async" style="width: 100%; height: auto;" />';
    } else if (strpos($mime, "video/") === 0) {
      $result = '<video controls preload="none" style="width: 100%; height: auto;"><source src="' . $url . '" type="' . htmlspecialchars($mime, ENT_QUOTES, "UTF-8") . '">Your browser does not support the video element.</video>';
    } else {
      $result = '<p>メディアファイルが正常に処理されなかったか、壊れているため表示できません。/The media file could not be processed correctly or is corrupted, so it cannot be displayed.</p>';
    }

    return $result;
  }

  /**
   * 軽量版に置く「押した時だけ開く」テキストリンク。
   *
   * 【出力は以前の軽量版と1文字も変えていません】軽量版はとても古い端末でも壊れないことが
   * 最優先のページで、クローラーに見せるためのものではありません。共通化の巻き添えで
   * 見た目やマークアップが動くと目的が崩れるため、href内の & を &amp; にしていない点まで
   * 当時のままにしてあります(整えたくなっても、軽量版の意図を確認してからにしてください)。
   */
  public static function link($postId, $index, $total) {
    $number = (int)$index + 1;
    return '<a href="' . self::url($postId, $index) . '" target="_blank">画像・動画を見る（' . $number . '/' . $total . '）/View media (' . $number . '/' . $total . ')</a>';
  }
}

// ================================================================
// 投稿の検索・ソート・閲覧数カウンター関連の大半はPipsDispDataの専用ロジックのため
// 同クラスのprivateメソッドへ移しましたが、この関数だけはPipsPostIO(投稿データの
// 並び替え)からも呼ばれる共通ユーティリティのため、ここではグローバル関数のまま
// 残しています。
// ================================================================

// "datetime"フィールドは過去の経緯でフォーマットが統一されていません。
// 現在保存されている投稿の大半は「2025年11月18日 21:01:41」のような全角の年月日区切り形式（おそらくJS側で
// 組み立てていた頃の名残）ですが、PHPのstrtotime()はこの区切り文字を解釈できず常にfalse（算術上は0扱い）を
// 返してしまい、日時での比較・ソートが実質的に効かなくなっていました。
// 一方、現在のPHP側の投稿処理は date("Y-m-d H:i:s") 形式で新規保存するため、今後は西暦区切り形式の投稿も
// 混在していきます。この関数は両方の形式（と、strtotime()がそのまま解釈できるその他の形式）に対応し、
// どうしても解釈できない場合は0（比較上は最も古い扱い）を返します。
function pipsParseDatetime($str) {
  $usable = (is_string($str) && $str !== "");

  // 全角の「年」「月」「日」区切りを、strtotime()が解釈できるハイフン区切りに変換します
  // 例: "2025年11月18日 21:01:41" -> "2025-11-18 21:01:41"
  $normalized = $usable
    ? preg_replace('/^(\d{1,4})年(\d{1,2})月(\d{1,2})日\s*/u', '$1-$2-$3 ', trim($str))
    : "";
  $fromNormalized = $usable ? strtotime($normalized) : false;
  // 正規化しても解釈できなければ、念のため元の文字列でも試します
  $fromRaw = ($usable && $fromNormalized === false) ? strtotime($str) : false;
  $result  = 0;

  if (!$usable) {
    $result = 0;
  } else if ($fromNormalized !== false) {
    $result = $fromNormalized;
  } else if ($fromRaw !== false) {
    $result = $fromRaw;
  } else {
    // どうしても解釈できない。比較上は最も古い扱いにします。
    $result = 0;
  }

  return $result;
}

// 投稿・返信・システムメッセージのHTML描画をまとめたクラスです。
// mediaZoom/contents/buttons/shareButton/info/renderLiteは、公開エントリポイントである
// render()専用のヘルパーのためprivateにしています(以前はdisp_data()の中に入れ子定義する
// ことで擬似的にprivate化していましたが、PHPの入れ子関数定義は本当のスコープを作らず、
// 実行順序に依存する脆さ(searchNoticeHtml()からの呼び出しがdisp_data()の該当行を
// 通過済みかどうかに依存していた)や、二重入れ子だったtemplateInfo()の再定義対策
// (function_exists()ガード)が必要になるなど不格好だったため、クラス化して解消しました)。
class PipsPostTemplate {
  // 軽量版（?lite=1）用の投稿・返信・メッセージ表示です。CSS/JSに一切依存せず、素のHTMLだけで
  // 完結させます（フォームはonsubmit抑止などのJS前提の仕組みを使わず、そのまま普通にPOSTされます）。
  // render()と同じ引数（投稿オブジェクト+表示種別 / null+メッセージ配列）を受け取ります。
  private static function renderLite($postData = null, $whereDisp = "main") {
    // 出しうる物は「投稿1件のカード」「システムメッセージ1行」「何も無し」の3つです。
    // どれになるかを先に見分け、組み立てた物を最後に1度だけ返します。
    $result = "";

    if (isset($postData)) {
      $isDangerous = isset($postData->dangerous) && $postData->dangerous;
      $isNoConvertLinks = isset($postData->no_convert_links) && $postData->no_convert_links;
      $dangerousMsg = "危険なポストとして検知されたため表示していません。/This post was flagged as dangerous and is not shown.";
      $textHtmlBr = str_replace("\n", "<br>\n", $postData->text ?? "");
      $textHtml = $isDangerous ? $dangerousMsg : ($isNoConvertLinks ? $textHtmlBr : PipsLinkConverter::convert($textHtmlBr));

      $isSensitive = isset($postData->sensitive) && $postData->sensitive ? 'true' : 'false';

      $userInfo = isset($postData->userinfo)
      ? '<a href="./?@=' . urlencode($postData->userinfo) . '">@' . htmlspecialchars($postData->userinfo, ENT_QUOTES, "UTF-8") . '</a>'
      : "@匿名ユーザー";

      $postId  = $postData->id ?? "";
      $subject = $postData->subject ?? "";

      $html  = '<div style="border:1px solid #888; margin:8px 0; padding:8px;">';
      $html .= '<p><strong>【' . $subject . '】</strong> ' . $userInfo . '：' . ($postData->name ?? "") . '（' . ($postData->datetime ?? "") . '）' . self::viewsLabel($postData) . '</p>';
      $html .= '<div class="content" data-sensitivity="' . $isSensitive . '">';
      $html .= '<p>' . $textHtml . '</p>';

      if (!$isDangerous && isset($postData->media) && !empty($postData->media)) {
        // base64データをページ本体に埋め込まず、クリックした時だけ別リクエストで取得する
        // リンク（OGP画像のような扱い）にすることで、投稿一覧のHTML自体を軽量に保ちます。
        // 【組み立てはPipsMediaへ移しましたが、出るHTMLは以前と同一です】通常版も同じ考え方に
        // 揃えたため窓口を1つにまとめただけで、軽量版の見た目・マークアップは変えていません。
        $mediaList  = PipsMedia::listOf($postData);
        $mediaTotal = p_count($mediaList);
        $mediaLinks = [];
        foreach ($mediaList as $mediaIndexNum => $media) {
          $mediaLinks[] = PipsMedia::link($postId, $mediaIndexNum, $mediaTotal);
        }
        $html .= '<p>' . implode(' 、 ', $mediaLinks) . '</p>';
      }
      $html .= '</div>';

      $html .= '<p>';
      if ($whereDisp !== "onePost") {
        $html .= '<form method="post" style="display:inline;"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><input type="hidden" name="reply_id" value="' . htmlspecialchars($postId, ENT_QUOTES, "UTF-8") . '" /><input type="hidden" name="reply_submit" value="' . htmlspecialchars($subject, ENT_QUOTES, "UTF-8") . '" /><button name="reply" type="submit">返信/Reply</button></form> ';
      }
      if ($whereDisp === "main" || $whereDisp === "reply") {
        $html .= '<a href="./?id_one_post=' . urlencode($postId) . '">この投稿だけ見る/View alone</a> ';
      }
      if ($whereDisp === "main" || $whereDisp === "toReply") {
        $replyCount = isset($postData->replies) ? p_count($postData->replies) : 0;
        $html .= '<a href="./?id=' . urlencode($postId) . '">スレッドを見る（返信' . $replyCount . '件）/View thread</a> ';
      }
      // 単体表示(?id_one_post=)には、通常版の👀ボタン(render()のonePost分岐)に相当する
      // スレッドへの導線が軽量版だけ抜けていました。検索結果から返信を単体表示で開いた人が
      // 会話の流れを追えなくなるため、同じ導線をここにも出します。
      // ?id=<返信のid> は親スレッドを開きます(PipsPostIO::localRead()のid分岐参照)。
      // 件数を付けないのは、返信自身のrepliesは常に0件で、そのまま出すと
      // 「返信0件」という事実と違う表示になるためです。
      if ($whereDisp === "onePost") {
        $html .= '<a href="./?id=' . urlencode($postId) . '">スレッドを見る/View thread</a> ';
      }
      // お気に入りに保存するのは投稿のidです(PipsPostHandlers::addBookmark() の解説参照)。
      $html .= '<form method="post" style="display:inline;"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="like_button" value="' . htmlspecialchars($postId, ENT_QUOTES, "UTF-8") . '">お気に入り/Like</button></form> ';
      $html .= '<form method="post" style="display:inline;"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="disablePost_button" value="' . htmlspecialchars($postId, ENT_QUOTES, "UTF-8") . '">通報/Report</button></form>';
      $html .= '</p>';
      // 軽量版にはモーダルが無いので、感想スタンプのボタンを投稿の下に直接並べます。
      $html .= self::stampForms($postData);
      $html .= '</div>';
      $result = $html;

    } else if (is_array($whereDisp)) {
      // 見た目は通常版と一緒に info() が決めます(軽量版かどうかも info() が見ます)。
      $result = self::info($whereDisp);

    } else {
      // 投稿も無く、メッセージの形でもない。出す物がありません。
      $result = "";
    }

    return $result;
  }

  // ================================================================
  // 書き込みフォームの先頭に出すカード（今から何を投稿するのか）
  //
  // 【HTMLを作るのはここだけ】投稿一覧と同じ考え方です。一覧はPHPが組み立てた
  // HTMLをJSが受け取って差し込むだけで、JSがHTMLを作ることはありません。
  // 書き込みフォームの状態表示も同じにしてあります。JS側で文字列を組み立てて
  // しまうと、PHPが出す初期表示とJSが出す表示という「同じものを作る場所」が
  // 2つでき、片方だけ直して食い違います。
  //
  // 【判断のもとはセッションだけ】返信中かどうかはサーバが持っている状態が唯一の
  // 答えです。JSから返信ボタンを押した場合も、サーバへ知らせてセッションを更新し、
  // その結果として作られたこのカードを受け取ります（?ajax付きのreply参照）。
  // そのため、画面を再読込しても表示は変わりません。
  // ================================================================

  /** アバター画像。アカウント基盤が使えない・匿名投稿の場合は印だけを出します。 */
  private static function composerAvatar($username) {
    $username = (string)$username;
    // 名前が無いなら基盤に聞きに行きません(匿名投稿で毎回1往復させないため)。
    $profile  = ($username === "") ? null : PipsAccountFeature::profile($username);
    $avatar   = is_array($profile) ? (string)($profile["avatar_url"] ?? "") : "";
    $result   = "";

    if ($username === "") {
      $result = '<span class="composer_avatar composer_avatar-anon" aria-hidden="true">👤</span>';
    } else if ($avatar === "") {
      $result = '<span class="composer_avatar composer_avatar-anon" aria-hidden="true">👤</span>';
    } else {
      $result = '<img class="composer_avatar" src="' . htmlspecialchars($avatar, ENT_QUOTES, "UTF-8") . '" alt="" width="40" height="40" />';
    }

    return $result;
  }

  /**
   * 保存済みの本文から抜粋を作ります。保存時にhtmlspecialchars済みなので、一度戻してから切ります。
   *
   * 書き込みフォームの返信先カード(composerHeader)と、引用カード(PipsQuote)の両方が使います。
   * 同じ「本文を短く見せる」仕事なので、2つ書いて片方だけ直す形にしないでください。
   * privateからpublicへ変えたのはそのためです。
   */
  /**
   * 閲覧数の目安。正確な数ではなく「10人以上」のような幅で出します。
   *
   * 【正確な数を出さないこと】閲覧数は、同じ人の数え落とし(同じIPの別人)も数えすぎ
   * (IPが変わった同じ人)も起こり得ます。台帳の鍵が無い間はログインしていない人を数えません。
   * 1の位まで出すと、その誤差がそのまま「事実」として読まれます。幅で出せば、多少ずれても
   * 表示は変わりません。
   *
   * 【区切りは一覧で持たず、決まりで作ること】区切りは 10, 50, 100, 500, 1000, 5000, 1万 … と
   * 「1と5の繰り返し」です(viewsStep())。一覧で持つと、書き足し忘れた先はずっと最後の区切りの
   * ままになります(10万人見た投稿が「1000人以上」のまま、等)。幅が一定の割合で広がるので、
   * 数が大きいほど大きくなる誤差に対しても、表示が細かくなりすぎません。
   */
  private static function viewsLabel($post) {
    $step = self::viewsStep((int)($post->views ?? 0));

    if ($step === null) {
      $result = '<span class="views_rough">閲覧 10人未満/Views: under 10</span>';
    } else {
      $result = '<span class="views_rough">閲覧 ' . self::viewsJa($step) . '人以上/Views: ' . self::viewsEn($step) . '+</span>';
    }

    return $result;
  }

  /**
   * $views 以下で一番大きい区切り(1か5 × 10のべき乗、10以上)。10未満なら null。
   * 例: 9 → null / 10〜49 → 10 / 50〜99 → 50 / 120 → 100 / 73000 → 50000
   */
  private static function viewsStep($views) {
    $result = null;

    if ($views < 10) {
      $result = null;
    } else {
      // $views の桁の頭(10, 100, 1000 …)。浮動小数の log10 を使わず、整数だけで求めます
      // (log10 は 1000 を 2.9999… と返すことがあり、区切りの境目で1つ下にずれるため)。
      $power = 10;
      while ($power <= intdiv($views, 10)) {
        $power *= 10;
      }
      $result = ($views >= $power * 5) ? $power * 5 : $power;
    }

    return $result;
  }

  /** 区切りの日本語表記。1万以上は「万」、1億以上は「億」で書きます(区切りは割り切れる数だけです)。 */
  private static function viewsJa($step) {
    $result = "";

    if ($step >= 100000000) {
      $result = intdiv($step, 100000000) . "億";
    } else if ($step >= 10000) {
      $result = intdiv($step, 10000) . "万";
    } else {
      $result = (string)$step;
    }

    return $result;
  }

  /** 区切りの英語表記。1000以上は K、100万以上は M、10億以上は B で書きます。 */
  private static function viewsEn($step) {
    $result = "";

    if ($step >= 1000000000) {
      $result = intdiv($step, 1000000000) . "B";
    } else if ($step >= 1000000) {
      $result = intdiv($step, 1000000) . "M";
    } else if ($step >= 1000) {
      $result = intdiv($step, 1000) . "K";
    } else {
      $result = (string)$step;
    }

    return $result;
  }

  public static function excerpt($storedText, $limit = 60) {
    $plain = html_entity_decode(strip_tags(str_replace("<br>", " ", (string)$storedText)), ENT_QUOTES, "UTF-8");
    $plain = trim(preg_replace('/\s+/u', " ", $plain));
    return htmlspecialchars(mb_strimwidth($plain, 0, $limit, "…"), ENT_QUOTES, "UTF-8");
  }

  public static function composerHeader() {
    // カードの顔は3つあります。「新規投稿」「返信先あり」「返信先が消えている」です。
    // どれになるかはセッションの返信先idと、その投稿が今も在るかどうかだけで決まります。
    $replyId = (string)(PipsSession::get("reply_id", ""));
    $raw     = ($replyId === "") ? null : PipsPostIO::postData("read", ["id_one_post" => $replyId]);
    $json    = ($raw !== false && $raw !== null) ? json_decode($raw, true) : null;
    $target  = is_array($json) ? ($json["item"][0] ?? null) : null;
    $result  = "";

    if ($replyId === "") {
      $me = (PipsAccountFeature::isLoggedIn())
        ? (string)(PipsSession::get("username", "")) : "";
      $who = $me !== ""
        ? '@' . htmlspecialchars($me, ENT_QUOTES, "UTF-8") . ' さん'
        : '匿名のユーザー';
      $result = '
        <div class="composer_head composer_head-new">
          ' . self::composerAvatar($me) . '
          <div class="composer_head_body">
            <p class="composer_head_who">' . $who . '</p>
            <p class="composer_head_state"><span class="composer_mark">✎</span> みんなに向けて投稿します／Posting to everyone</p>
          </div>
        </div>
      ';

    } else if (!is_array($target)) {
      // 返信先が消えている場合。ここで黙って新規投稿の顔に戻すと、返信のつもりで
      // 書いた人が気づけないまま送信して失敗します。見えるようにしておきます。
      $result = '
        <div class="composer_head composer_head-lost">
          <span class="composer_avatar composer_avatar-anon" aria-hidden="true">❓</span>
          <div class="composer_head_body">
            <p class="composer_head_state">返信しようとした投稿が見つかりませんでした。削除されたのかもしれません。／The post you were replying to could not be found.</p>
          </div>
        </div>
      ';

    } else {
      $targetUser = (string)($target["userinfo"] ?? "");
      $who = $targetUser !== ""
        ? '@' . htmlspecialchars($targetUser, ENT_QUOTES, "UTF-8") . ' さん'
        : '匿名のユーザー';

      $result = '
        <div class="composer_head composer_head-reply">
          ' . self::composerAvatar($targetUser) . '
          <div class="composer_head_body">
            <p class="composer_head_who">' . $who . '</p>
            <p class="composer_head_subject">' . htmlspecialchars((string)($target["subject"] ?? ""), ENT_QUOTES, "UTF-8") . '</p>
            <p class="composer_head_excerpt">' . self::excerpt($target["text"] ?? "") . '</p>
            <p class="composer_head_state"><span class="composer_mark">↩</span> この投稿に返信します／Replying to this post</p>
          </div>
        </div>
      ';
    }

    return $result;
  }

  /**
   * 編集モーダルの中身。返信先カードと同じで、HTMLを作るのはここだけです。
   *
   * 【プリフィルの値をエスケープし直さないこと】セッションの"edit_*_prefill"に入っているのは
   * 投稿時にhtmlspecialchars()済みの値です。ここで再度エスケープすると、編集欄に
   * "&amp;lt;" のような文字列がそのまま出て、保存するたびに壊れていきます。
   * 逆に、この節へ生の入力を入れてはいけません（そのままHTMLに出るため）。
   */
  public static function editModalBody() {
    $targetId = (string)(PipsSession::get("edit_target_id", ""));
    $result   = "";

    if ($targetId === "") {
      $result = '<p>編集する投稿が選ばれていません。お手数ですが、編集したい投稿の ✏ を押し直してください。／No post is selected. Please press ✏ on the post you want to edit.</p>';
    } else {
      $result = '
      <form method="POST" action="' . htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8") . '" enctype="multipart/form-data">
        <input type="text" name="edit_subject" id="edit_subject" value="' . (string)(PipsSession::get("edit_subject_prefill", "")) . '" placeholder="Thread Name" />
        <textarea name="edit_text" id="edit_text" placeholder="Comment">' . (string)(PipsSession::get("edit_text_prefill", "")) . '</textarea>
        ' . self::editMediaFields($targetId, (int)(PipsSession::get("edit_media_count", 0))) . '
        <input type="hidden" name="edit_target_id" id="edit_target_id" value="' . htmlspecialchars($targetId, ENT_QUOTES, "UTF-8") . '" />
        <input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" />
        <button onclick="location.hash=\'#sent\'" type="submit" name="post_edit_submit">保存/Save</button>
      </form>
    ';
    }

    return $result;
  }

  /**
   * 編集モーダルの添付欄。今ある添付を1つずつ並べ、それぞれに「削除」と「差し替え」を付け、
   * 最後に「追加」を置きます。
   *
   * 【今ある添付の中身は送り返させないこと】ここで出しているのは、既存の1件配信用URL
   * (?media_post=<id>&media_index=<n>)への参照だけです。base64をこのフォームへ埋めて
   * 送り返す作りにすると、添付を1つも触らない編集でも投稿全体（base64は元の約1.33倍）が
   * 毎回往復し、post_max_sizeを超えた瞬間に$_POSTごと消えます。
   * サーバ側(PipsPostHandlers::postEdit())は「元の投稿から残す物を拾い、差し替え・追加分を
   * 足す」形で組み立て直すので、画面から送るのは**新しいファイルだけ**で足ります。
   *
   * 【並び順】残った物は元の順番のまま、追加した物が後ろに付きます。差し替えは位置を
   * 保ったまま中身だけが入れ替わります（消してから足すのとは結果が違うので、
   * 「入れ替え」は削除＋追加ではなくこの欄で行えるようにしています）。
   */
  private static function editMediaFields($targetId, $mediaCount) {
    $html = '<div class="edit_media">';

    if ($mediaCount > 0) {
      $html .= '<p class="edit_media_title">今ある画像・動画／Current attachments</p>';
      for ($i = 0; $i < $mediaCount; $i++) {
        $url = './?media_post=' . urlencode($targetId) . '&media_index=' . $i;
        $html .= '
          <div class="edit_media_row">
            <a href="' . htmlspecialchars($url, ENT_QUOTES, "UTF-8") . '" target="_blank" class="edit_media_thumb">' . ($i + 1) . '枚目を見る／View ' . ($i + 1) . '</a>
            <label class="edit_media_remove"><input type="checkbox" name="edit_media_remove[' . $i . ']" value="1" /> 削除する／Remove</label>
            <label class="edit_media_replace">差し替え／Replace <input type="file" name="edit_media_replace[' . $i . ']" accept=".mp4,.jpg,.png" /></label>
          </div>
        ';
      }
    } else {
      $html .= '<p class="edit_media_title">この投稿に画像・動画はありません／No attachments on this post</p>';
    }

    $html .= '
      <label class="edit_media_add">新しく追加／Add new
        <input type="file" name="edit_media_add[]" accept=".mp4,.jpg,.png" multiple />
      </label>
      <p class="edit_media_note">合わせて' . PIPS_MEDIA_MAX_COUNT . '個まで、1つあたり' . pipsFormatBytes(PIPS_MEDIA_MAX_BYTES) . 'までです。／Up to ' . PIPS_MEDIA_MAX_COUNT . ' files, ' . pipsFormatBytes(PIPS_MEDIA_MAX_BYTES) . ' each.</p>
    </div>';

    return $html;
  }

  /** 削除モーダルの中身。対象が選ばれていない場合は、押せるボタンを出しません。 */
  /**
   * 感想スタンプのモーダルの中身。種類ごとの数と、押すボタンを並べます。
   * 編集モーダルと同じく、どの投稿かはセッションの "stamp_target_id" で決まります。
   * 押せないとき(PipsStamps::unavailableReason())は、数だけを出して理由を添えます。
   */
  public static function stampModalBody() {
    $targetId = (string)(PipsSession::get("stamp_target_id", ""));
    $found    = ($targetId === "") ? [] : PipsPostIO::summaries([$targetId]);
    $result   = "";

    if ($targetId === "") {
      $result = '<p>投稿が選ばれていません。お手数ですが、感想スタンプを押したい投稿の 😊 を押し直してください。／No post is selected. Please press 😊 on the post again.</p>';
    } else if ($found === null) {
      $result = '<p>投稿を読み込めませんでした。少し時間をおいてから開き直してください。／Could not load the post. Please try again in a little while.</p>';
    } else if (!isset($found[$targetId])) {
      $result = '<p>この投稿は削除されたか、見つかりませんでした。／This post was deleted or could not be found.</p>';
    } else {
      $result = '<p class="stamp_target">【' . self::excerpt($found[$targetId]->subject ?? "", 40) . '】' . self::excerpt($found[$targetId]->text ?? "", 60) . '</p>'
        . self::stampForms($found[$targetId]);
    }

    return $result;
  }

  /**
   * 感想スタンプのボタンの並び(通常版のモーダルと軽量版の投稿の下で共通)。
   * このセッションで押した種類には印(stamp_chosen)を付け、取り消しボタンを添えます。
   */
  private static function stampForms($post) {
    $postId = htmlspecialchars((string)($post->id ?? ""), ENT_QUOTES, "UTF-8");
    $counts = PipsStamps::counts($post);
    $chosen = PipsStamps::chosen((string)($post->id ?? ""));
    $reason = PipsStamps::unavailableReason();
    $token  = '<input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" />';
    $html   = '<div class="stamp_list">';

    foreach (PipsStamps::TYPES as $type => $label) {
      $text  = $label[0] . $label[1] . '(' . $counts[$type] . ')';
      $class = ($chosen === $type) ? ' class="stamp_chosen"' : '';
      if ($reason === "") {
        $html .= '<form method="post" style="display:inline;">' . $token . '<input type="hidden" name="stamp_post_id" value="' . $postId . '" /><button type="submit" name="stamp_submit" value="' . $type . '"' . $class . ' title="' . $label[1] . '/' . $label[2] . '">' . $text . '</button></form> ';
      } else {
        $html .= '<span' . $class . '>' . $text . '</span> ';
      }
    }

    if ($reason !== "") {
      $html .= '<p class="note">' . htmlspecialchars($reason, ENT_QUOTES, "UTF-8") . '</p>';
    } else if ($chosen !== null) {
      $html .= '<form method="post" style="display:inline;">' . $token . '<button type="submit" name="stamp_undo" value="' . $postId . '">取り消す/Undo</button></form>';
      $html .= '<p class="note">選び直しと取り消しは、押したときと同じ閲覧が続いている間だけできます。／You can change or undo only while the same visit continues.</p>';
    } else {
      $html .= '<p class="note">1つの投稿に押せるのは1つだけです。／You can send one stamp per post.</p>';
    }

    return $html . '</div>';
  }

  public static function deleteModalBody() {
    $targetId = (string)(PipsSession::get("delete_target_id", ""));
    $result   = "";

    if ($targetId === "") {
      $result = '<p>削除する投稿が選ばれていません。お手数ですが、削除したい投稿の 🗑 を押し直してください。／No post is selected. Please press 🗑 on the post you want to delete.</p>';
    } else {
      $result = '
      <p>この投稿を削除しますか？この操作は取り消せません。/Are you sure you want to delete this post? This action cannot be undone.</p>
      <form method="POST" action="' . htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8") . '">
        <input type="hidden" name="delete_target_id" id="delete_target_id" value="' . htmlspecialchars($targetId, ENT_QUOTES, "UTF-8") . '" />
        <input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" />
        <button type="submit" name="post_delete_submit">削除する/Delete</button>
      </form>
    ';
    }

    return $result;
  }

  // 拡大表示用のモーダル。アンカーidは contents() 側が出すリンク先と必ず揃っている必要があります
  // (揃っていないと、押しても何も開かないリンクになります)。両方ともPipsMedia::listOf()で
  // ならした配列の添字+1を使うので、mediaが配列でも文字列1つでも同じidになります。
  private static function mediaZoom($post = null) {
    $result = "";

    if (isset($post) && isset($post->id)) {
      foreach (PipsMedia::listOf($post) as $mediaIndex => $media) {
        $result .= '
          <div class="modal" id="zoom_img&gt;' . $post->id . '&gt;' . ($mediaIndex + 1) . '">
            <div>
              <a href="#mc"></a>
              <div>
                <span>拡大</span>
                <div>
                  ' . PipsMedia::embed($post->id, $mediaIndex, $media) . '
                </div>
              </div>
            </div>
          </div>
        ';
      }
    } else {
      error_log("[PIPS] 開発者の設定ミス: PipsPostTemplate::mediaZoomにpostがnullまたはidなしで渡されました。");
      $result = "";
    }

    return $result;
  }

  // $excerptLimit を1以上にすると、本文をその幅の抜粋(excerpt())にします。一覧の中に
  // 何件も並べる場面(あなたのブックマーク)で、長い本文に場所を取られないためです。
  private static function contents($post = null, $isZoomAnker = true, $excerptLimit = 0) {
    $result = "";

    if (isset($post) && is_bool($isZoomAnker)) {
      $mediaList = PipsMedia::listOf($post);
      if (empty($mediaList)) {
        $mediaContent = "";
      } else {
        $mediaContent = '<div class="media">';
        foreach ($mediaList as $mediaIndex => $media) {
          // 中身(base64)ではなく1件配信URLを指すタグです。理由はPipsMediaの解説を読んでください。
          $mediaTag = PipsMedia::embed($post->id, $mediaIndex, $media);
          if ($isZoomAnker) {
            $mediaContent .= '<div><a href="#zoom_img&gt;' . $post->id . '&gt;' . ($mediaIndex + 1) . '">' . $mediaTag . '</a></div>';
          } else {
            $mediaContent .= '<div>' . $mediaTag . '</div>';
          }
        }
        $mediaContent .= '</div>';
      }

      $dangerounsMsg = "危険なポストを検知しました、申し訳なく存じますがあなたの投稿は表示できません。危険でないと管理者に伝えたい際は、＋ボタン（もっと多くの機能）内のお問い合わせ欄から、再審査して欲しいという旨をお伝えください。/We've detected a dangerous post, we're sorry, but we can't see your post. If you want to tell the administrator that it is not dangerous, please tell them that you would like to be re-examined from the inquiry field in the + button (more functions).";
      $isSensitive = isset($post->sensitive) && $post->sensitive ? 'true' : 'false';
      $isDangerous = isset($post->dangerous) && $post->dangerous;
      $userInfo = isset($post->userinfo) ? '<a href="./?@=' . urlencode($post->userinfo) . '" style="color: #00ff00;">@' . htmlspecialchars($post->userinfo, ENT_QUOTES, "UTF-8") . '</a>' : "@匿名ユーザー";
      if ($isDangerous) {
        $mainText = $dangerounsMsg;
      } else if ($excerptLimit > 0) {
        $mainText = self::excerpt($post->text, $excerptLimit);
      } else {
        $mainText = $post->text;
      }
      $result = '
        <div class="title">【' . $post->subject . '】『' . $userInfo . '：' . $post->name . '』('. $post->datetime . ') ' . self::viewsLabel($post) . '</div>
        <div class="content" data-sensitivity="' . $isSensitive . '">' . $mainText . $mediaContent . '</div>
      ';
    } else {
      error_log("[PIPS] 開発者の設定ミス: PipsPostTemplate::contentsの引数が不正です。post=" . gettype($post) . ", isZoomAnker=" . gettype($isZoomAnker));
      $result = '<p style="color:#ff0000;">表示エラーが発生しました。/A display error occurred.</p>';
    }

    return $result;
  }

  private static function buttons($post = null, $rawText = null) {
    $result = "";

    if (isset($post)) {
      // 共有リンクは他人へ渡るものなので、公開の住所で組み立てます(pipsPublicUrl)。
      $shareLink = pipsPublicUrl("/?id_one_post=" . $post->id);
      // お気に入りに保存するのは投稿のidです(PipsPostHandlers::addBookmark() の解説参照)。

      // 編集・削除ボタンは、本人確認(PipsPostHandlers::canEditOrDeletePost())が通った投稿にのみ表示します。
      // shareButton()の返信ボタンと同じ二段構えです。
      //   ・JS無効時: <form onsubmit="return false;">はブラウザに無視されるため、普通にPOSTされる。
      //     サーバー側の isset($_POST["edit_request"]) / isset($_POST["delete_request"]) ハンドラが
      //     権限を再検証した上でセッションに保存し、#modal-edit / #modal-delete へリダイレクトする。
      //   ・JS有効時: onsubmit="return false;"がPOSTを止め、setEditInfo()/setDeleteInfo()が
      //     **同じPOSTをXHRで送って**セッションを更新し、PHPが組み立てたモーダルの中身を
      //     受け取って差し込む（PipsPostTemplate::editModalBody()/deleteModalBody()）。
      //
      // 【JSに本文を渡すための隠し要素は置かないこと】以前はここに
      //   <textarea id="raw_text_<id>" style="display:none;">…本文…</textarea>
      //   <input type="hidden" id="raw_subject_<id>" value="…件名…" />
      // を投稿ごとに出し、JSがそれを読んで編集欄へ写していました。つまり編集できる投稿の
      // 本文が、一覧のHTMLの中に**もう一度まるごと**入っていたことになります(自分の投稿が
      // 多い人ほどページが重くなる)。今はモーダルの中身をPHPが作って返すので、この重複は
      // 不要です。JS用の控えを画面に埋める形へ戻さないでください。
      $editDeleteButtons = "";
      if (PipsPostHandlers::canEditOrDeletePost($post)) {
        $editDeleteButtons = '
          <form method="post" onsubmit="return false;"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="edit_request" value="' . $post->id . '" title="このポストを編集する/Edit this post" onclick="setEditInfo(\'' . $post->id . '\', this); location.hash=\'modal-edit\'">✏</button></form>
          <form method="post" onsubmit="return false;"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="delete_request" value="' . $post->id . '" title="このポストを削除する/Delete this post" onclick="setDeleteInfo(\'' . $post->id . '\', this); location.hash=\'modal-delete\'">🗑</button></form>
        ';
      }

      // 感想スタンプの合計。0か1以上かの印はPHPが決め、色はCSSが塗ります
      // (返信数の shareButton() と同じ理由。JSに任せると、JSが無い環境で色の情報が落ちます)。
      $stampTotal = array_sum(PipsStamps::counts($post));
      if ($stampTotal > 0) {
        $stampClass = "stampCD stampCD_has";
      } else {
        $stampClass = "stampCD stampCD_none";
      }

      // 【title を消さないこと。ヘルプと同じ言葉にすること】
      // ここのボタンは絵文字だけで、何をする物なのかは modal-3(ヘルプ画面)の
      // 「ポスト一についてる各ボタンについて」を読まないと分かりません。ヘルプを開かずに
      // 確かめられるよう、同じ説明を title に持たせています。読み上げソフトにとっても、
      // 絵文字1文字よりこちらのほうが手掛かりになります。
      // ヘルプの文言を直した時は、こちらも揃えてください（食い違うと、どちらが本当か
      // 分からなくなります）。
      $result = '
        <form onsubmit="return false;"><button onclick="copyToClipboard(\'' . $shareLink . '\')" title="ポストのリンクをコピー・シェアする/Copy or share this post\'s link" disabled>📨</button></form>
        <form method="post"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="disablePost_button" value="' . $post->id . '" title="このポストを通報する/Report this post">🙅</button></form>
        <form method="post"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="like_button" value="' . htmlspecialchars((string)$post->id, ENT_QUOTES, "UTF-8") . '" title="お気に入りに追加する（あなたのブックマークに保存されます）/Add to your favorites (saved in Your bookmark)">❤</button></form>
        <form method="post" onsubmit="return false;"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="stamp_request" value="' . htmlspecialchars((string)$post->id, ENT_QUOTES, "UTF-8") . '" title="感想スタンプを押す・見る/Send or view reaction stamps" onclick="setStampInfo(\'' . htmlspecialchars((string)$post->id, ENT_QUOTES, "UTF-8") . '\', this); location.hash=\'modal-stamp\'">😊<span class="' . $stampClass . '">' . $stampTotal . '</span></button></form>
        ' . $editDeleteButtons . '
      ';
    } else {
      error_log("[PIPS] 開発者の設定ミス: PipsPostTemplate::buttonsにpostがnullで渡されました。");
      $result = "";
    }

    return $result;
  }

  // 返信ボタン(⤴)。$isNoCauntがfalseのときだけ返信の件数を添えます。
  //
  // 【件数の色はPHPが決めること】以前この色分けはJSがやっていました
  // （.replyCD を集めて、0なら赤・1件以上なら青、という処理をDOMContentLoadedで1回だけ）。
  // 色そのものが「返信が付いているかどうか」を表す情報なので、JSが無い環境では
  // その情報が丸ごと落ちます。そのうえ一覧を差し替えた後(getDispPost)には塗り直されず、
  // ページ送りをした瞬間から全部が同じ色になっていました。
  // どちらが正しいかはサーバが知っているので、ここで印(クラス)を付け、色はCSSが塗ります。
  private static function shareButton($post = null, $isNoCaunt = true) {
    $result = "";

    if (!isset($post) || !is_bool($isNoCaunt)) {
      error_log("[PIPS] 開発者の設定ミス: PipsPostTemplate::shareButtonの引数が不正です。post=" . gettype($post) . ", isNoCaunt=" . gettype($isNoCaunt));
      $result = "";
    } else {
      $postId    = isset($post->id) ? $post->id : "";
      $subject   = isset($post->subject) ? $post->subject : "";
      $replyForm = '<form method="post" onsubmit="return false;"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><input type="hidden" name="reply_id" value="' . $postId . '" /><input type="hidden" name="reply_submit" value="' . $subject . '" />';
      $replyBtn  = '<button name="reply" title="このポストに返信する/Reply to this post" onclick="setReplyInfo(\'' . $postId . '\', \'' . $subject . '\', this); location.hash=\'modal-1\'">⤴';

      if ($isNoCaunt) {
        $result = $replyForm . $replyBtn . '</button></form>';
      } else {
        $replyCount = isset($post->replies) ? p_count($post->replies) : 0;

        if ($replyCount > 0) {
          $countClass = "replyCD replyCD_has";
          $countTitle = "返信" . $replyCount . "件/" . $replyCount . " replies";
        } else {
          $countClass = "replyCD replyCD_none";
          $countTitle = "返信はまだありません/No replies yet";
        }

        $result = $replyForm . $replyBtn . '<span class="' . $countClass . '" title="' . $countTitle . '">' . $replyCount . '</span></button></form>';
      }
    }

    return $result;
  }

  // 投稿・返信・システムメッセージを描画する公開エントリポイントです(旧pipsTemplateLogic関数)。
  public static function render($postData = null, $whereDisp = "main") {
    global $isLite;

    // この関数が返しうる物は「1件ぶんのHTML」だけです。軽量版へ委譲する場合も、
    // 投稿を描く場合も、システムメッセージを描く場合も、引数の組み合わせが
    // おかしくてエラー表示にする場合も、すべてここへ入れて最後に1度だけ返します。
    $result = "";

    if ($isLite) {
      // 軽量版（?lite=1）ではCSS/JSに依存しないrenderLite()に委譲します。
      // ルーティング・ページネーション・検索/ソートのロジックはdisp_data()側で共通のまま、
      // ここで最終的な表示のみを軽量版用に差し替えます。
      $result = self::renderLite($postData, $whereDisp);

    } else if (isset($postData)) {
      // 編集モーダル用に、<br>変換・PipsLinkConverter::convert適用前の生の値を確保しておきます
      // (buttons()の$rawText引数に渡します。詳細はbuttons()側のコメント参照)。
      // 見つからなかったブックマーク(下の "bookmark" 参照)は本文を持たないので、無い物は空として扱います。
      $rawText = $postData->text ?? "";
      if ($whereDisp === "bookmark") {
        // 本文は抜粋にしか使わないので、<br>・リンクへの変換はしません。リンクの変換は
        // 引用カードを作るために他の投稿を読みに行くことがあり、件数ぶん無駄に通信します。
        $postData->text = $rawText;
      } else if (empty($postData->no_convert_links)) {
        $postData->text = PipsLinkConverter::convert(str_replace("\n", "<br>\n", $rawText));
      } else {
        $postData->text = str_replace("\n", "<br>\n", $rawText);
      }
      $postId = isset($postData->id) ? $postData->id : "";

      if ($whereDisp === "main") {
        $result = '
          <div class="post">
            <div onclick="urlHandle(\'?id=' . $postData->id . '\');" class="wrapper">
              ' . self::contents($postData, false) . '
            </div>
            <div class="buttons">
              ' . self::shareButton($postData, false) . self::buttons($postData, $rawText) . '
              <form method="post"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="viewPost_button" value="' . "./?id=" . $postId . '" title="スレッド（返信一覧）を開く/Open the thread">👀</button></form>
            </div>
          </div>
        ';
      } else if ($whereDisp === "toReply") {
        $result = '
          <div class="post to_reply">
            <div class="wrapper wrre">
              ' . self::contents($postData) . '
            </div>
            <div class="buttons">
              ' . self::shareButton($postData, false) . self::buttons($postData, $rawText) . '
              <form method="post"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="viewPost_button" value="' . "./?id_one_post=" . $postId . '" title="この投稿だけを単体で表示する/View this post on its own">👁</button></form>
            </div>
          </div>
          ' . self::mediaZoom($postData) . '
        ';
      } else if ($whereDisp === "reply") {
        $result = '
          <div class="post">
            <div class="wrapper wrre">
              ' . self::contents($postData) . '
            </div>
            <div class="buttons">
              '. self::shareButton($postData, true) . self::buttons($postData, $rawText) . '
              <form method="post"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="viewPost_button" value="' . "./?id_one_post=" . $postId . '" title="この投稿だけを単体で表示する/View this post on its own">👁</button></form>
            </div>
          </div>
          ' . self::mediaZoom($postData) . '
        ';
      } else if ($whereDisp === "onePost") {
        $result = '
          <div class="one_post">
            ' . self::contents($postData) . '
            <div class="buttons">
              ' . self::buttons($postData, $rawText) . '
              <form method="post"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="viewPost_button" value="' . "./?id=" . $postId . '" title="スレッド（返信一覧）を開く/Open the thread">👀</button></form>
            </div>
          </div>
          ' . self::mediaZoom($postData) . '
        ';
      } else if ($whereDisp === "bookmark") {
        // 「あなたのブックマーク」(#likeList の中)の1件です。<li> ごと返します。
        // 投稿は PipsPostIO::summaries() で読んだ物で、media と replies を持ちません。
        // 見つからなかった投稿は、id と pips_bookmark_missing だけを持つ形で渡されます
        // (usersSaveContent() 参照)。その場合も、外せるように削除ボタンだけは出します。
        $removeForm = '<form method="post"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="remove_like" value="' . htmlspecialchars((string)$postId, ENT_QUOTES, "UTF-8") . '" title="お気に入りから外す/Remove from favorites">削除/Remove</button></form>';

        if (!empty($postData->pips_bookmark_missing)) {
          $result = '
            <li class="bookmark bookmark_missing">
              <div class="title">この投稿は削除されたか、見つかりませんでした。/This post was deleted or could not be found.</div>
              <div class="buttons">' . $removeForm . '</div>
            </li>
          ';
        } else {
          $result = '
            <li class="bookmark">
              <div onclick="urlHandle(\'?id_one_post=' . urlencode((string)$postId) . '\');" class="wrapper">
                ' . self::contents($postData, false, 100) . '
              </div>
              <div class="buttons">
                <form method="post"><input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" /><button type="submit" name="viewPost_button" value="' . "./?id_one_post=" . urlencode((string)$postId) . '" title="この投稿だけを単体で表示する/View this post on its own">👁</button></form>
                ' . $removeForm . '
              </div>
            </li>
          ';
        }
      } else {
        error_log("[PIPS] 開発者の設定ミス: PipsPostTemplate::renderのwhereDispが不正な値です。whereDisp=" . var_export($whereDisp, true));
        $result = '<p style="color:#ff0000;">表示エラーが発生しました。/A display error occurred.</p>';
      }

    } else if (is_array($whereDisp)) {
      $result = self::info($whereDisp);

    } else {
      error_log("[PIPS] 開発者の設定ミス: PipsPostTemplate::renderが無効な引数の組み合わせで呼ばれました。postData=" . gettype($postData) . ", whereDisp=" . gettype($whereDisp));
      $result = '<p style="color: #ff0000;">表示エラーが発生しました。/A display error occurred.</p>';
    }

    return $result;
  }

  // システム通知/警告/エラーメッセージの表示(旧・pipsTemplateLogic内に二重入れ子定義されていたtemplateInfo())。
  // クラスメソッドとして1回だけ定義されるため、以前必要だったfunction_exists()での
  // 再定義ガードはそもそも不要です。
  //
  // 【知らせは何件でも1つの枠にまとめます】$notices は [種類, 本文] の組の並びで、
  // 種類は "error" / "warning" / "notification" のどれかです。
  //   例: [["notification", "検索範囲が…"], ["notification", "一致する投稿が…"]]
  // 以前は知らせ1件ごとに枠を作っていたので、検索のときなどに枠が縦に2つ3つ並びました。
  // 呼ぶ側(PipsDispData::run())は知らせを溜めておき、最後に1回だけここへ渡します。
  //
  // 見出しは、中にある一番重い種類で決まります(エラー > 警告 > 通知)。本文の色は1件ずつ
  // その種類の色です。知らせが1件だけのときは、以前の1件ずつの枠と同じ見た目になります。
  //
  // 【軽量版の警告の色を黄色にしないこと】軽量版にはCSSが無く白地なので、通常版の
  // #fff000 では読めません。軽量版だけ #aa8800 にしてあります。
  private static function info($notices = null) {
    global $isLite;

    $result = "";
    // 種類ごとの重さ・見出し・色。見出しの決め方と色は、この表だけを見ます。
    $kinds = [
      "notification" => ["rank" => 0, "title" => "システム通知/System Notifications", "color" => "unset",   "liteColor" => "unset"],
      "warning"      => ["rank" => 1, "title" => "システム警告/System Warning",       "color" => "#fff000", "liteColor" => "#aa8800"],
      "error"        => ["rank" => 2, "title" => "システムエラー/System Error",       "color" => "#ff0000", "liteColor" => "#ff0000"],
    ];
    $invalid = !is_array($notices) || empty($notices);
    $topKind = "notification";

    if (!$invalid) {
      foreach ($notices as $notice) {
        if (!is_array($notice) || !isset($notice[0], $notice[1]) || !isset($kinds[$notice[0]])) {
          $invalid = true;
        } else if ($kinds[$notice[0]]["rank"] > $kinds[$topKind]["rank"]) {
          $topKind = $notice[0];
        }
      }
    }

    if ($invalid) {
      error_log("[PIPS] 開発者の設定ミス: PipsPostTemplate::infoの知らせの形が不正です。notices=" . var_export($notices, true));
      $result = '<p style="color:#ff0000;">表示エラーが発生しました。/A display error occurred.</p>';
    } else if ($isLite && count($notices) === 1) {
      // 本文の中にはリンクが入ることがあるため、ここはエスケープしません
      // (中身を作っているのは利用者ではなく、このファイル自身です)。
      $result = '<p style="color:' . $kinds[$topKind]["liteColor"] . ';"><strong>' . $kinds[$topKind]["title"] . '</strong>：' . $notices[0][1] . '</p>';
    } else if ($isLite) {
      $lines = "";
      foreach ($notices as $notice) {
        $lines .= '<p style="color:' . $kinds[$notice[0]]["liteColor"] . ';">' . $notice[1] . '</p>';
      }
      $result = '<div><p><strong>' . $kinds[$topKind]["title"] . '</strong></p>' . $lines . '</div>';
    } else {
      $lines = "";
      foreach ($notices as $notice) {
        $lines .= '<div class="content" style="color: ' . $kinds[$notice[0]]["color"] . '">' . $notice[1] . '</div>';
      }
      $result = '
        <div class="post">
          <div class="wrre wrapper">
            <div class="title">' . $kinds[$topKind]["title"] . '</div>
            ' . $lines . '
          </div>
        </div>
      ';
    }

    return $result;
  }
}

// 投稿一覧のルーティング・ページネーション・検索/ソート・プロフィール表示を担当するクラスです。
// userPosts/userList/renderProfileは、公開エントリポイントであるrun()(disp_data()の後継)
// からしか使われない専用ヘルパーのためprivateにしています(PipsPostTemplateがテンプレート
// 〈見た目〉担当なのに対し、こちらは「どのデータをどう組み立てて表示するか」のロジック担当です)。
class PipsDispData {
  // ----------------------------------------------------------------
  // システムの知らせ(検索の案内・「見つかりません」・設定の知らせ等)
  //
  // 知らせは出す場所でその都度枠を作らず、ここへ [種類, 本文] で溜めておき、run() の最後に
  // PipsPostTemplate::render(null, $notices) へ1回だけ渡して1つの枠にします。
  // 【直に render(null, [...]) を $postDisplay へ足さないこと】その知らせだけ別の枠になり、
  // 検索のときなどに枠が縦に並ぶ形に戻ります。
  //
  // $noticeAt は、その枠を $postDisplay の何文字目に差し込むかです。普段は先頭(0)で、
  // 一覧の前に別の物がある画面(プロフィールの見出し、スレッドの親投稿)だけ、その後ろを指します。
  // ----------------------------------------------------------------
  private static $notices = [];
  private static $noticeAt = 0;

  // ----------------------------------------------------------------
  // 投稿の検索・ソート(閲覧数を数える処理は PipsPostIO::countView() へ移しました)
  // ソート機能・軽量版などを段階的に追加していく過程であちこちに散らばっていたため、
  // ここに集約しています(このクラスのrun()/userPosts()以外からは呼ばれないため
  // すべてprivateです。pipsParseDatetime()だけはPipsPostIO側からも使う共通処理の
  // ため、グローバル関数のまま別の場所に残しています)。
  // ----------------------------------------------------------------

  // ソートのキーとして許可する値の一覧です（不正な値は既定の"date_desc"に丸めます）
  private static function validSortKeys() {
    return ["date_desc", "date_asc", "replies_desc", "replies_asc", "views_desc", "views_asc"];
  }

  // GETパラメータ（?q, ?search_text, ?search_subject, ?search_name, ?sort）から検索・ソート条件を取り出します
  private static function searchSortParams() {
    $query = isset($_GET["q"]) ? trim($_GET["q"]) : "";
    // 検索フォーム自体は送信された（qパラメータが存在する）が、キーワードが空
    // （未入力、または空白のみ）だったかどうか。この場合は検索条件が何も無いまま
    // 通常の一覧が表示されるだけになるが、以前はその旨の案内が一切無く、
    // 「検索ボタンを押しても何も起きない」ように見えてしまっていた。
    $submittedBlank = isset($_GET["q"]) && $query === "";

    $fields = [];
    if (isset($_GET["search_text"]))    { $fields[] = "text"; }
    if (isset($_GET["search_subject"])) { $fields[] = "subject"; }
    if (isset($_GET["search_name"]))    { $fields[] = "name"; }
    // 検索語はあるのにチェックボックスが一つも指定されていない場合は、全項目を対象にします。
    // ただしこれも以前は完全に無言で行われていたため、fieldsAutoAllで呼び出し側が
    // 「対象範囲が未選択だったので全項目を検索した」旨を案内できるようにする。
    $fieldsAutoAll = ($query !== "" && empty($fields));
    if ($fieldsAutoAll) {
      $fields = ["text", "subject", "name"];
    }

    // 返信も検索対象に含めるか。値は3通りあり、それぞれ意味が違うので明示的に分けます。
    //   ・パラメータ自体が無い … 検索フォームを通っていない（?q= を直接開いた、古い
    //                            ブックマーク、外部からのリンク）。既定どおり含めます。
    //   ・"0"                  … フォームでチェックを外して送信された。含めません。
    //   ・それ以外（"1"）      … チェックが入ったまま送信された。含めます。
    //
    // 【なぜhiddenと対にしているか】チェックボックスは、外された状態だとブラウザが
    // 何も送りません。そのため「外した」と「そもそもフォームを通っていない」を
    // 受け取り側で区別できません。フォーム側に同じ名前で value="0" のhiddenを
    // チェックボックスの直前に置くことで、外した時は"0"だけが届くようにしています。
    // 同名が2つ送られた場合、PHPは後に来た方（=チェック時の"1"）を採用します。
    // JSを使わずに3通りを区別するための形なので、hiddenを消さないでください。
    $rawIncludeReplies = $_GET["search_replies"] ?? null;
    if ($rawIncludeReplies === null) {
      $includeReplies = true;
    } elseif (is_string($rawIncludeReplies) && $rawIncludeReplies === "0") {
      $includeReplies = false;
    } else {
      $includeReplies = true;
    }

    $sort = $_GET["sort"] ?? "date_desc";
    if (!is_string($sort) || !in_array($sort, self::validSortKeys(), true)) {
      $sort = "date_desc";
    }

    return [
      "query"          => $query,
      "fields"         => $fields,
      "sort"           => $sort,
      "fieldsAutoAll"  => $fieldsAutoAll,
      "submittedBlank" => $submittedBlank,
      "includeReplies" => $includeReplies,
    ];
  }

  /**
   * 検索フォームの「返信も探す」チェックボックスを、最初からチェック済みで出すかどうか。
   *
   * フォーム側で$_GETを直接見ないためのものです。直接見ると、パラメータが無い時
   * （フォームを通っていない時）の扱いが表示側と検索側でずれて、「チェックが外れて
   * 見えるのに返信が結果に出ている」という食い違いが起きます。判定は
   * searchSortParams()の1箇所だけに置いてください。
   */
  public static function includeRepliesChecked() {
    $params = self::searchSortParams();
    return $params["includeReplies"];
  }

  // 検索・ソート条件が既定値と異なるかどうかを返します
  private static function hasActiveSearchOrSort(array $params) {
    return $params["query"] !== "" || $params["sort"] !== "date_desc";
  }

  // 検索フォームの状態について、結果一覧の前に一言添えるための案内メッセージ。
  // 以前はどちらのケースも完全に無言だったため、「検索ボタンを押しても何も
  // 起きていないように見える」という報告があった。何も案内すべきことが無ければ
  // 空文字を返す。
  private static function searchNotice(array $searchSort) {
    if ($searchSort["fieldsAutoAll"]) {
      self::$notices[] = ["notification", "検索範囲(本文/件名/投稿者名)が選択されていなかったため、すべての項目を対象に検索しました。/No search scope was selected, so all fields (text, subject, name) were searched."];
    } else if ($searchSort["submittedBlank"]) {
      self::$notices[] = ["notification", "検索キーワードが入力されていません。全件を表示しています。/No search keyword was entered — showing all results."];
    } else {
      // 案内すべきことは何もありません。
    }
  }

  // ページネーションのリンク等に検索・ソート条件を引き継ぐためのクエリ文字列断片を作ります（先頭"&"付き、条件が無ければ空文字）
  private static function searchSortQueryString(array $params) {
    $qs = "";
    if ($params["query"] !== "") {
      $qs .= "&q=" . urlencode($params["query"]);
      $fieldToParamName = ["text" => "search_text", "subject" => "search_subject", "name" => "search_name"];
      foreach ($fieldToParamName as $field => $paramName) {
        if (in_array($field, $params["fields"], true)) {
          $qs .= "&{$paramName}=1";
        }
      }
      // 返信を含めるのが既定なので、既定と違う時（外した時）だけ印を残します。
      // searchSortParams()の3通りの読み取りと対になっています。
      if (!$params["includeReplies"]) {
        $qs .= "&search_replies=0";
      }
    }
    if ($params["sort"] !== "date_desc") {
      $qs .= "&sort=" . urlencode($params["sort"]);
    }
    return $qs;
  }

  // 投稿配列（stdClassの配列）を検索語句・対象項目でフィルタし、指定のキーでソートします
  private static function searchAndSortPosts(array $items, $query, array $fields, $sortKey) {
    if ($query !== "" && !empty($fields)) {
      $items = array_values(array_filter($items, function ($post) use ($query, $fields) {
            // どれか1つの項目に当たれば残します。当たった後は照合そのものを行いません
            // ($hit === false が偽になり、&& の右側が評価されないため)。
            $hit = false;

            foreach ($fields as $field) {
              if ($hit === false && $field === "text" && isset($post->text) && stripos($post->text, $query) !== false) {
                $hit = true;
              }
              if ($hit === false && $field === "subject" && isset($post->subject) && stripos($post->subject, $query) !== false) {
                $hit = true;
              }
              if ($hit === false && $field === "name" && isset($post->name) && stripos($post->name, $query) !== false) {
                $hit = true;
              }
              if ($hit === false && $field === "name" && isset($post->userinfo) && stripos($post->userinfo, $query) !== false) {
                $hit = true;
              }
            }

            return $hit;
      }));
    }

    if ($sortKey === "replies_desc" || $sortKey === "replies_asc") {
      usort($items, function ($a, $b) use ($sortKey) {
          $diff = p_count($b->replies ?? []) - p_count($a->replies ?? []);
          return $sortKey === "replies_desc" ? $diff : -$diff;
      });
    } elseif ($sortKey === "views_desc" || $sortKey === "views_asc") {
      // 閲覧数は投稿データ自身の"views"キーに保存されているため、そのまま読みます
      usort($items, function ($a, $b) use ($sortKey) {
          $diff = (int)($b->views ?? 0) - (int)($a->views ?? 0);
          return $sortKey === "views_desc" ? $diff : -$diff;
      });
    } elseif ($sortKey === "date_asc") {
      usort($items, function ($a, $b) {
          return pipsParseDatetime($a->datetime ?? "") - pipsParseDatetime($b->datetime ?? "");
      });
    } else { // date_desc（既定）
      usort($items, function ($a, $b) {
          return pipsParseDatetime($b->datetime ?? "") - pipsParseDatetime($a->datetime ?? "");
      });
    }

    return $items;
  }

  /**
   * 検索の対象に返信も含めるため、各投稿の replies を親投稿と同じ並びへ展開します。
   *
   * 【なぜ印(pips_reply_of)を付けるか】保存されている返信は、キーの構成が親投稿と
   * 完全に同じ（id/text/media/subject/name/userinfo/datetime/sensitive/dangerous/views）で、
   * 親を指す値も持っていません。つまり一度展開してしまうと、後から
   * 「これは返信だったのか」も「どのスレッドの返信か」も判別できなくなります。
   * そのため展開したその場で印を付けます。描画側(renderSearchHit())はこの印だけを見て
   * 出し分けます。印を付けずに親投稿として出すと、返信には replies が無いため
   * 「スレッドを見る（返信0件）」という、押しても何も無いリンクが付いてしまいます。
   *
   * 【展開してよい場面】検索語がある時だけです。並び替えだけの時や通常の一覧で
   * 展開すると、トップページが「スレッドの一覧」ではなくなります（返信が親から
   * 切り離されて時系列に混ざるだけの、意味の分からない一覧になります）。
   */
  private static function withRepliesFlattened(array $items) {
    $flat = [];
    foreach ($items as $post) {
      $flat[] = $post;
      $replies = (isset($post->replies) && is_array($post->replies)) ? $post->replies : [];
      foreach ($replies as $reply) {
        if (!is_object($reply)) { continue; }
        $reply->pips_reply_of = (string)($post->id ?? "");
        $flat[] = $reply;
      }
    }
    return $flat;
  }

  /**
   * 一覧の1件を描画します。withRepliesFlattened()が付けた印がある（＝返信である）なら
   * "reply"として、無ければ従来どおり"main"として出します。
   *
   * 返信を"reply"で出すと、スレッド内の返信と同じ見た目になり、単体表示への導線
   * (?id_one_post=)が付きます。スレッド全体を見たい人は、その単体表示の画面から
   * 進めます（?id=<返信のid> は親スレッドを開きます。PipsPostIO::localRead()のid分岐参照）。
   * 検索結果から直接スレッドを開かせないのは、検索語に一致したのはその返信であって
   * スレッドではないためです。
   */
  private static function renderSearchHit($post) {
    return PipsPostTemplate::render($post, isset($post->pips_reply_of) ? "reply" : "main");
  }

  // 検索・ソートは対象範囲の全件に対して行う必要があります。$all=1（pusyuu_ips APIの
  // 通常のGET /index.php、およびそのローカルフォールバックPipsPostIO::localRead()の
  // 両方が対応）を指定し、1回のリクエストで全件を取得します。
  // 【以前の実装についての注記】以前はAPIの通常時の1リクエストあたりの上限（最大100件）を
  // 回避するため、page=1,2,3...と繰り返し呼んで全件を集めていました。しかしAPI側の
  // getAllItems()は呼ばれるたびに毎回ファイル全体を読み直す作りのため、ページ送りで
  // 繰り返すたびに全件読み込みが走ってしまい、投稿数が増えるほど
  // (総ページ数)×(全件読み込みコスト)というほぼ二乗のコストで検索が遅くなっていました。
  // $all=1はAPI側で1回の呼び出しにつき1回だけ全件を読み込んで返すため、この二乗コストを
  // 解消します（詳しくは main/pusyuusystem/apis/pusyuu_ips/index.php 側の$all=1周辺の
  // コメント参照）。
  // $id が指定されていればそのスレッドの全返信を、無ければ投稿一覧の全件を返します。
  // 通常の読み込み結果と同じ形（"item"と"total"または"total_replies"を持つオブジェクト）を返します。
  // 取得に失敗した場合はnullを返します。
  // $omit は外すトップレベルのキーです(画像が要らない集計のとき ["media"] を渡します)。
  private static function fetchAllForSearchSort($id, array $omit = []) {
    $opts = ["all" => 1];
    if ($omit !== []) { $opts["omit"] = $omit; }
    if ($id !== null) { $opts["id"] = $id; }

    $data = PipsPostIO::postData("read", $opts);
    $json = ($data === false || $data === null) ? null : json_decode($data);

    // 取れなかった/形が違う場合は、ここから先の分割取得もできません。
    // 最後にもう1つ「スレッド指定なのに中身が空」という条件があり、結末は同じnullですが、
    // 分割取得を終えないと判定できないため、下で改めて見ます。
    if ($json === null || !isset($json->item)) {
      $json = null;
    } else {
    // API側が分割して返してきた場合は、続きが無くなるまで受け取って繋ぎます。
    //
    // 【なぜ分割されるのか】投稿本文にBase64の画像が入るため、全件はPHPのメモリ上限に
    // 収まりません(2026-09-06の実測で42.7MB・478件、一度に読むとピーク108MBで上限128MBに
    // 迫ります)。API側は今のmemory_limitを実際に読み、その半分に収まる量ずつ返し、
    // 続きの位置を next_offset で知らせてきます。
    //
    // 位置は「何件目」ではなく元ファイルのバイト位置です。件数で区切ると、そこへ
    // 辿り着くまで毎回先頭から読み直すことになり、分割した回数だけ全件読みが走って
    // かえって重くなります。バイト位置なら続きから直接読めます。
    //
    // 上限回数を設けているのは、API側の不具合で next_offset が進まなくなったときに、
    // ここが永久に往復し続けるのを防ぐためです。打ち切った場合は集まったぶんだけで
    // 検索します(結果が欠けるのは困りますが、応答が返らないよりはましです)。
      if (!empty($json->chunked)) {
        $rounds = 0;
        while (isset($json->next_offset) && $json->next_offset !== null && $rounds < 200) {
          $rounds++;
          $nextOpts = $opts;
          $nextOpts["offset"] = $json->next_offset;
          $more = PipsPostIO::postData("read", $nextOpts);
          if ($more === false || $more === null) {
            break;
          }
          $moreJson = json_decode($more);
          if ($moreJson === null || !isset($moreJson->item)) {
            break;
          }
          $json->item = array_merge($json->item, $moreJson->item);
          $json->next_offset = $moreJson->next_offset ?? null;
        }
        if ($rounds >= 200) {
          error_log("[PIPS] 検索用の分割取得が200回を超えたため打ち切りました。API側のnext_offsetが進んでいない可能性があります。");
        }
        $json->total = count($json->item);
      }

      // スレッド指定なのに1件も無い＝そのスレッドが無い、という意味です。
      // 「取得に失敗した」と同じnullで答えます(呼び出し側の扱いが同じため)。
      if ($id !== null && empty($json->item)) {
        $json = null;
      }
    }

    return $json;
  }

  /**
   * 指定ユーザー名が投稿した投稿一覧と、その人の投稿(返信も含む)に届いた感想スタンプの種類ごとの合計。
   * 戻り値: ["items" => そのページの投稿, "total" => 一致した件数, "stamps" => [種類 => 合計], "ok" => 読めたか]
   *
   * 【控え(data.json)を直接読まないこと】以前はここだけが控えのファイルを直接読んでいました。
   * 控えはAPIに届かないときだけ使う物で、本体と同期はしません(本体の写しではありません)。
   * 直接読むと、APIが生きていても本体の投稿が出ません。検索と同じく PipsPostIO 経由の
   * 全件読みを使います(APIに届かないときは、そこで控えへ落ちます)。
   *
   * 【画像は2段で読みます】全件を画像ごと読むと数十MBになります。絞り込みと合計には
   * 画像を外した全件を使い、画面に出す1ページぶんだけ、idで画像込みの中身を読み直します。
   */
  private static function userPosts($username, $page, $pageSize, $searchQuery = "", array $searchFields = [], $sortKey = "date_desc", $includeReplies = true) {
    $json  = self::fetchAllForSearchSort(null, ["media"]);
    $items = ($json !== null && isset($json->item)) ? $json->item : [];
    $flat  = self::withRepliesFlattened($items);
    $mine  = function ($post) use ($username) {
      return isset($post->userinfo) && $post->userinfo === $username;
    };

    // 感想スタンプの合計は、検索の有無に関わらず、その人の投稿と返信のすべてから数えます。
    $stamps = array_fill_keys(array_keys(PipsStamps::TYPES), 0);
    foreach (array_filter($flat, $mine) as $post) {
      foreach (PipsStamps::counts($post) as $type => $count) {
        $stamps[$type] += $count;
      }
    }

    // 検索語があり「返信も含める」が選ばれている時は、そのユーザーの返信も対象に含めます
    // （トップページの検索と揃えるため。検索語が無い通常のプロフィール一覧は、
    // 従来どおりトップレベル投稿だけを並べます）。
    $pool = ($searchQuery !== "" && $includeReplies) ? $flat : $items;

    $userPosts = array_values(array_filter($pool, $mine));
    $userPosts = self::searchAndSortPosts($userPosts, $searchQuery, $searchFields, $sortKey);

    $total = p_count($userPosts);
    $paged = array_slice($userPosts, ($page - 1) * $pageSize, $pageSize);

    // 画面に出す分だけ画像込みで読み直します。読めなかった物は画像なしのまま出します
    // (本文は出せるので、1件ごと消すよりましです)。返信の印(pips_reply_of)は引き継ぎます。
    $full = empty($paged) ? [] : PipsPostIO::readByIds(array_map(function ($post) {
      return (string)($post->id ?? "");
    }, $paged), []);
    foreach ($paged as $i => $post) {
      $id = (string)($post->id ?? "");
      if (is_array($full) && isset($full[$id])) {
        if (isset($post->pips_reply_of)) {
          $full[$id]->pips_reply_of = $post->pips_reply_of;
        }
        $paged[$i] = $full[$id];
      }
    }

    return ["items" => $paged, "total" => $total, "stamps" => $stamps, "ok" => ($json !== null)];
  }

  /**
   * フォロー中一覧・フォロワー一覧を、accountsから返ってきた公開プロフィール配列
   * (userid/username/name/bio/avatar_url)からリストとして描画します。
   * $withUnfollowをtrueにすると各行に解除ボタンを付けます(PipsPostHandlers::unfollowUser()を
   * そのまま使うため、既存のunfollow_usernameフォームと同じ形にしています)。
   */
  private static function userList(array $users, bool $withUnfollow) {
    $html = "";

    if (empty($users)) {
      $html = '<p>まだいません。/None yet.</p>';
    } else {
      $html = '<ul class="user_list">';

      foreach ($users as $u) {
        $uname  = htmlspecialchars((string)($u["username"] ?? ""), ENT_QUOTES, "UTF-8");
        $name   = htmlspecialchars((string)($u["name"] ?? ""), ENT_QUOTES, "UTF-8");
        $avatar = htmlspecialchars((string)($u["avatar_url"] ?? ""), ENT_QUOTES, "UTF-8");
        $html .= '
        <li>
          <a href="./?@=' . urlencode((string)($u["username"] ?? "")) . '">
            <img src="' . $avatar . '" alt="" class="post_author_avatar" />
            @' . $uname . '（' . $name . '）
          </a>
          ' . ($withUnfollow ? '
            <form method="post" style="display:inline;">
              <input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" />
              <input type="hidden" name="unfollow_username" value="' . $uname . '" />
              <button type="submit">解除/Unfollow</button>
            </form>
          ' : '') . '
        </li>
      ';
      }

      $html .= '</ul>';
    }

    return $html;
  }

  // ?at=username のプロフィール画面（プロフィール情報 + フォローボタン + そのユーザーの投稿一覧）を組み立てます
  private static function renderProfile($username, $pageNum, $pageSize) {
    global $postDisplay, $title, $description, $nextPageUrl, $prevPageUrl;

    $profileUser = PipsAccountFeature::profile($username);

    if ($profileUser === null) {
      // 【ユーザーが見つからない場合】この下の組み立ては一切行いません。
      // 以前はここでreturnしていましたが、returnだと「この先にまだ何かあるのか」が
      // 読んだだけでは分からず、150行ほど下まで目で追う必要がありました。
      header("HTTP/1.1 404 Not Found");
      $prevPageUrl = "./?page=1";
      self::$notices[] = ["warning", "指定されたユーザーは見つかりませんでした。/The specified user was not found."];
    } else {
      $safeUsername = htmlspecialchars($username, ENT_QUOTES, "UTF-8");
      $title        = "プシューIPS/PusyuuIPS - @" . $username . "さんのプロフィール";
      $description  = ($profileUser["name"] ?? $username) . "さんのプロフィールページです。";

      $followerCount  = PipsAccountFeature::followerCount($profileUser["userid"]);
      $followingCount = PipsAccountFeature::followingCount($username);
      // ユーザー名比較に加えて、変動しないID(ハッシュ)同士の一致も確認します。
      // ユーザー名は改名され得るため、この二重チェックでセッションのユーザー名が
      // 古いままの場合などに誤って「自分のプロフィール」と判定してしまうのを防いでいます。
      $isOwnProfile   = PipsAccountFeature::isLoggedIn()
      && PipsSession::get("username") === $username
      && $profileUser["userid"] === PipsSession::get("userid");

      $followButton = "";
      if (!$isOwnProfile) {
        if (PipsAccountFeature::isLoggedIn()) {
          if (PipsAccountFeature::isFollowing($profileUser["userid"])) {
            $followButton = '
            <form method="post">
              <input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" />
              <input type="hidden" name="unfollow_username" value="' . $safeUsername . '" />
              <button type="submit">フォロー解除/Unfollow</button>
            </form>
          ';
          } else {
            $followButton = '
            <form method="post">
              <input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" />
              <input type="hidden" name="follow_username" value="' . $safeUsername . '" />
              <button type="submit">フォロー/Follow</button>
            </form>
          ';
          }
        } else {
          // アカウント機能へ届かないときは、押しても行き止まりにしかならないログイン
          // リンクを出さず、理由をそのまま書きます(JSではなくここで出力するので、
          // JavaScriptが無い環境でも理由が読めます)。
          $followLoginUrl = PipsAccountFeature::loginUrl();
          if ($followLoginUrl === "") {
            $followButton = '<p class="note">' . htmlspecialchars(PipsAccountFeature::unavailableReason(), ENT_QUOTES, 'UTF-8') . '</p>';
          } else {
            $followButton = '<a href="' . htmlspecialchars($followLoginUrl, ENT_QUOTES, 'UTF-8') . '" class="button">フォローするにはログイン/Log in to follow</a>';
          }
        }
      }

      $bio = trim((string)($profileUser["bio"] ?? ""));

      // 投稿一覧と感想スタンプの合計は、見出しより先に読みます(合計を見出しに出すため)。
      $searchSort = self::searchSortParams();
      $searchSortQs = self::searchSortQueryString($searchSort);
      $result   = self::userPosts($username, $pageNum, $pageSize, $searchSort["query"], $searchSort["fields"], $searchSort["sort"], $searchSort["includeReplies"]);
      // 読めなかったときは合計を出しません(0と出すと「1つも届いていない」と読めてしまうため)。
      $stampTotal = "";
      if ($result["ok"]) {
        $stampParts = [];
        foreach (PipsStamps::TYPES as $type => $label) {
          $stampParts[] = $label[0] . $label[1] . ' ' . $result["stamps"][$type];
        }
        $stampTotal = '<p>届いた感想スタンプ/Reaction stamps received: ' . implode('　', $stampParts) . '</p>';
      }

      // フォロー中一覧は自分のトークンでしか取得できない(?api=data_getが本人のみ許可)ため、
      // モーダル自体、自分自身のプロフィールを見ているときだけ用意します。他人の
      // プロフィールでは「フォロー: N」を(モーダルが無いので)リンクにしません。
      $followingLink = $isOwnProfile
      ? '<a href="#modal-following">フォロー: ' . $followingCount . '</a>'
      : '<span>フォロー: ' . $followingCount . '</span>';
      // フォロワー一覧はdata_reverse_list(公開・認証不要)で取得できるので、
      // 自分・他人どちらのプロフィールでもモーダルを開けます。
      $followersLink = '<a href="#modal-followers">フォロワー: ' . $followerCount . '</a>';

      $postDisplay .= '
      <div class="profile_header">
        <div class="profile_avatar"><img src="' . htmlspecialchars($profileUser["avatar_url"] ?? "", ENT_QUOTES, "UTF-8") . '" alt="" width="80" height="80" /></div>
        <h2>@' . $safeUsername . '</h2>
        <p>' . htmlspecialchars($profileUser["name"] ?? "", ENT_QUOTES, "UTF-8") . '</p>
        ' . ($bio !== "" ? '<p class="profile_bio">' . htmlspecialchars($bio, ENT_QUOTES, "UTF-8") . '</p>' : '') . '
        <p>' . $followingLink . '　' . $followersLink . '</p>
        ' . $stampTotal . '
        ' . $followButton . '
      </div>
    ';

      // フォロー中一覧・フォロワー一覧は、pipsの他のモーダル(書き込み画面・+ボタン等)と
      // 同じCSSオンリーのモーダル機構(.modal + #id + :target、JS不要)で表示します。
      if ($isOwnProfile) {
        $followingUsers = PipsAccountFeature::followingProfiles();
        $postDisplay .= '
        <div class="modal" id="modal-following">
          <div>
            <a href="#mc"></a>
            <div>
              <span>フォロー中一覧/Following</span>
              <div>' . self::userList($followingUsers, true) . '</div>
            </div>
          </div>
        </div>
      ';
      }

      $followerUsers = PipsAccountFeature::followerProfiles($profileUser["userid"]);
      $postDisplay .= '
      <div class="modal" id="modal-followers">
        <div>
          <a href="#mc"></a>
          <div>
            <span>フォロワー一覧/Followers</span>
            <div>' . self::userList($followerUsers, false) . '</div>
          </div>
        </div>
      </div>
    ';

      // 知らせの枠は、プロフィールの見出しとモーダルの後ろ、投稿一覧の頭に置きます。
      self::$noticeAt = strlen($postDisplay);
      self::searchNotice($searchSort);
      $pageOver = max(1, (int)ceil($result["total"] / $pageSize));

      // ページ送りの決め方は投稿一覧(run())と同じです。渡ってきた$pageNumが現在ページで、
      // ?page= が無ければ1になっています。$_GET["page"]をここで読み直さないでください。
      // 読み直す形にしていたせいで「?page=が無い＝プロフィールを開いた直後」の
      // 画面だけ前へのリンクが決まらない、という食い違いが起きていました。
      $profileBase = "./?@=" . urlencode($username) . "&page=";

      if ($pageNum < $pageOver) {
        $nextPageUrl = $profileBase . ($pageNum + 1) . $searchSortQs;
      } else {
        $nextPageUrl = "";
      }

      if ($pageNum > 1) {
        $prevPageUrl = $profileBase . ($pageNum - 1) . $searchSortQs;
      } else {
        $prevPageUrl = "";
      }

      if (!$result["ok"]) {
        self::$notices[] = ["error", "投稿を読み込めませんでした。少し時間をおいてから開き直してください。/Could not load the posts. Please try again in a little while."];
      } else if (empty($result["items"])) {
        $emptyMsg = $searchSort["query"] !== ""
        ? "検索条件に一致する投稿が見つかりませんでした。/No posts matched your search."
        : "@" . $safeUsername . "さんの投稿はまだありません。/This user has no posts yet.";
        self::$notices[] = ["notification", $emptyMsg];
      } else {
        foreach ($result["items"] as $postList) {
          $postDisplay .= self::renderSearchHit($postList);
        }
      }
    }
  }

  /**
   * og:imageに出すURLを決めます。出せる画像が無ければ$fallbackUrl(既定のPIPSロゴ)を返します。
   * スレッド表示と単体表示の2箇所に同じ処理が並んでいたため、こちらへ寄せました。
   *
   * 【media[0]決め打ちで正しい理由】og:imageに載せられるのは代表1枚だけなので、
   * 「何枚目を出すか」という選択がそもそも存在しません。本文に並べる方(全枚数・動画も)は
   * ?media_post= が受け持ちます。役割が違うので、この2つを1本にまとめないでください。
   *
   * 【?image と ?media_post の違い】?image は外(SNSのカード取得)から取りに来られる前提の
   * URLなのでnoindexを付けません。?media_post= は本文の実体用なのでnoindexを付けます。
   *
   * 【この中でexit()する理由】?image が付いているリクエストは画像そのものを求めており、
   * HTMLを組み立ててはいけません。呼び出し側へ戻さず、ここで返し切って終わらせます。
   */
  private static function ogImage($post, $fallbackUrl) {
    $mediaList = PipsMedia::listOf($post);
    $hasImage  = (isset($mediaList[0]) && PipsMedia::isImage($mediaList[0]));
    $result    = "";

    if (!$hasImage) {
      // 出せる画像がありません。既定のPIPSロゴを使います。
      $result = $fallbackUrl;
    } else if (isset($_GET["image"])) {
      // 画像そのものを求められています。HTMLは組み立てず、ここで返し切って終わります。
      //
      // og:image用のURLでもあるため noindex は付けませんが、外部サイトからの
      // ホットリンク(直リンク埋め込み)は拒否します(PipsHotlinkGuard::isHotlink()参照)。
      if (PipsHotlinkGuard::isHotlink()) {
        PipsHotlinkGuard::reject();
      }
      // 【Content-Typeを決め打ちしないこと】以前はimage/jpeg固定でした。2026-09時点の実データでは
      // og候補になる1枚目が jpeg 44件・png 6件で、そのpng 6件は「JPEGだと名乗るPNG」を配っていた
      // ことになります。受け取り側によってはこの食い違いだけでカード画像を出しません。
      // 必ず中身から判定した値を送ってください。
      // (残り6件は1枚目がmp4で、og:imageには出せないため上のisImage()で弾かれます。)
      header("Content-Type: " . PipsMedia::mimeOf($mediaList[0]));
      echo base64_decode($mediaList[0]);
      exit;
    } else {
      // この投稿の画像を取りに来られるURLを、og:imageの値として返します。
      // og:image はSNSのカードが取りに来るので、公開の住所で組み立てます(今のクエリはそのまま)。
      $result = pipsPublicUrl("/?" . (string)($_SERVER["QUERY_STRING"] ?? "") . "&image");
    }

    return $result;
  }

  // 【開発者の思い出の品・更新】以前はこの関数の中でさらに関数（templatePostContents等）を
  // 入れ子定義して整理していましたが、実行順序に依存する脆さ(searchNoticeHtml()からの
  // 呼び出しがこの関数の該当行を通過済みかに依存していた)や、二重入れ子だったtemplateInfo()の
  // 再定義対策(function_exists()ガード)が不格好だったため、テンプレート描画はPipsPostTemplate
  // クラスへ切り出しました(PipsPostTemplate::render()を使用)。この関数自身(旧disp_data())と、
  // これ専用のヘルパー(userPosts/userList/renderProfile)もPipsDispDataクラスにまとめています。
  public static function run() {
    // 埋め込み用データを global 宣言
    // $dispPips（画面に実際に出す文字列）はここでは触りません。ここは$postDisplayを組み立てるだけで、
    // それを画面のどこへ入れるかはHTMLを組み立てる直前の1箇所が決めます。
    global $postDisplay, $title, $description, $image, $nextPageUrl, $prevPageUrl, $isRegularPage;

    // ページのサイズを設定
    $pageSize = 15;

    // URLから現在のページ番号を取得し、デフォルトは1
    $pageNum_N = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;

    // URLから現在のページ番号を取得し、デフォルトは1
    $pageNum_I = isset($_GET["id"]) && isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;

    $id = isset($_GET["id"]) ? $_GET["id"] : null;

    $id_one_post = isset($_GET["id_one_post"]) ? $_GET["id_one_post"] : null;

    // ?at=username（ユーザープロフィール表示）
    $at = isset($_GET["@"]) ? trim($_GET["@"]) : null;

    // 検索・ソート条件（?q, ?search_text, ?search_subject, ?search_name, ?sort）
    $searchSort   = self::searchSortParams();
    $searchSortQs = self::searchSortQueryString($searchSort);
    $applySearchSort = self::hasActiveSearchOrSort($searchSort);

    // 知らせの入れ物は要求ごとに空から始めます(1回の要求で run() が2度呼ばれても、
    // 前の分が混ざらないように)。設定の知らせは一番大事なので、枠の最初の行にします。
    self::$notices  = [];
    self::$noticeAt = 0;
    if (PipsLibraries::needsNotice()) {
      // 【ライブラリ名・ファイル名・置き場所を出さないこと】利用者に直せることではなく、
      // 出せば仕組みを外へ教えるだけです。どれが読めなかったかは error_log に残っています。
      self::$notices[] = ["error", "一部の機能が開発者の設定ミスにより制限されています。お手数ですが管理者にお伝えください。/Some features are restricted due to the server configuration. We apologize for the inconvenience, but please let the administrator know."];
    }
    // 重複防止台帳の鍵が使えないときは、ログインしていない人の閲覧を数えていません。
    // 数だけ見ても「伸びない」としか分からないので、理由に気づけるよう知らせます。
    // 鍵ファイルが壊れているときは、投稿データが壊れているときと同じく「破損」として、
    // それ以外(非公開フォルダが無い・書けない等)は「設定」として、文を分けます。
    // (鍵・ファイル名は出しません。詳しいことは error_log にあります)
    $ledgerState = PipsPostIO::ledgerState();
    if ($ledgerState === "ok") {
      // 数えられています。
    } else if ($ledgerState === "broken") {
      self::$notices[] = ["error", "閲覧数を数えるための設定データが破損しています、管理者にこの事をいち早くお伝えください。直るまでの間、ログインしていない方の閲覧は数に含まれません。/The data used to count views is corrupted. Please inform the administrator as soon as possible. Until it is fixed, views from visitors who are not logged in are not counted."];
    } else {
      self::$notices[] = ["error", "現在、開発者の設定ミスにより、ログインしていない方の閲覧は数に含まれていません。お手数ですが管理者にお伝えください。/Views from visitors who are not logged in are currently not counted due to the server configuration. We apologize for the inconvenience, but please let the administrator know."];
    }

    // 感想スタンプが押せない状態(台帳の鍵が使えない)を知らせます。
    // 押そうとした人にだけ伝えると、押さない人は気づかず、管理者へ届くまでに時間がかかります。
    if (!PipsStamps::available()) {
      self::$notices[] = ["error", PipsStamps::unavailableReason()];
    }
    // 共有のセッションが読めないときは、止めずに動かしたうえで知らせます(PipsSession::usingLibrary())。
    // クッキーを使えない方は、選び直し・取り消しができず、閲覧もページごとに別の人として扱われます。
    // (数そのものは鍵をかけたIPの台帳が守るので、膨らみはしません。仕組みは画面に出しません)
    if (!PipsSession::usingLibrary()) {
      self::$notices[] = ["warning", "開発者の設定ミスにより、一部の方は感想スタンプの選び直し・取り消しができない状態です。お手数ですが管理者にお伝えください。/Due to the server configuration, some visitors cannot change or undo their reaction stamps. Please let the administrator know."];
    }

    // 全データを取得
    //$log_data = PipsPostIO::postData('read');

    // プロフィール表示は、投稿一覧とはまったく別の組み立てです。
    // 以前はここでrenderProfile()を呼んでreturnしていましたが、returnだと
    // 「この先の200行はプロフィールのときも通るのか」が読んだだけでは分かりません。
    if ($at !== null) {
      self::renderProfile($at, $pageNum_N, $pageSize);
    } else {
      // スレッドビューは1ページ目14件（親ポスト表示分を考慮）、以降15件
      if ($id !== null) {
        $pageSize = $pageNum_N > 1 ? 15 : 14;
      } else {
        $pageSize = 15;
      }

      $read_opts = ["page" => $pageNum_N, "limit" => $pageSize];
      if ($id !== null)          { $read_opts["id"]          = $id; }
      if ($id_one_post !== null) { $read_opts["id_one_post"] = $id_one_post; }

      if ($id_one_post === null) {
        self::searchNotice($searchSort);
      }

      if ($applySearchSort && $id_one_post === null) {
        // 検索・ソートは対象範囲の全件に対して行う必要があります。APIは1リクエストあたり最大100件までしか
        // 返さない仕様のため、ページ送りで全件を集めてから絞り込み・並び替え・PHP側での再ページ分割を行います
        // （id指定時はそのスレッド内の全返信を対象にします）。
        $json = self::fetchAllForSearchSort($id);
        $fetchFailed = ($json === null);
      } else {
        $log_data = PipsPostIO::postData("read", $read_opts);
        $fetchFailed = ($log_data === false || $log_data === null);
        $json = $fetchFailed ? null : json_decode($log_data);
      }

      if ($fetchFailed) {
        error_log("[PIPS] 投稿データの取得に失敗しました。APIおよびローカルフォールバックがともに応答しませんでした。");
        self::$notices[] = ["error", "投稿データの取得中にエラーが発生しました。時間をおいて再度お試しください。/An error occurred while retrieving post data. Please try again later."];
      } else {
        if ($json === null || !isset($json->item)) {
          self::$notices[] = ["error", '投稿データが破損しています、管理者にこの事をいち早くお伝えください。お問い合わせは<a href="#modal-2">こちらのもっと多くの機能</a>内のあ問い合わせフォームからお伝えください。/The post data is corrupted, please inform the administrator of this as soon as possible. If you have any questions, please contact us using the contact form in <a href="#modal-2">More features here'];
        } elseif ($id === "" || $id_one_post === "") {
          header("HTTP/1.1 404 Not Found");
          $prevPageUrl = "./?page=1";
          self::$notices[] = ["warning", '投稿idが指定されていません。URLの"?id="または"?id_one_post="の後に表示したいポストIDを入力してください。<a href="./?page=1">ホームへもどる</a>'];
        } elseif ($id !== null) {
          if (empty($json->item)) {
            header("HTTP/1.1 404 Not Found");
            $prevPageUrl = "./?page=1";
            self::$notices[] = ["warning", "指定されたIDの投稿が見つかりませんでした。/The post with the specified ID was not found."];
          } else {
            $targetPost = $json->item[0];
            if ($pageNum_N <= 1) {
              $title       = "プシューIPS/PusyuuIPS - " . utf8_substr($targetPost->subject, 0, 20);
              $description = utf8_substr($targetPost->text, 0, 20);
              $image = self::ogImage($targetPost, $image);
              PipsPostIO::countView($targetPost);
              $postDisplay .= PipsPostTemplate::render($targetPost, "toReply");
            }
            // 知らせの枠は親投稿の後ろ、返信一覧の頭に置きます(2ページ目以降は親投稿が無いので先頭)。
            self::$noticeAt = strlen($postDisplay);

            $replies = (isset($targetPost->replies) && is_array($targetPost->replies)) ? $targetPost->replies : [];
            if ($applySearchSort) {
              // 検索・ソートが有効な場合は、取得した全返信を絞り込み・並び替えしてから、このページの分だけを切り出します
              $replies       = self::searchAndSortPosts($replies, $searchSort["query"], $searchSort["fields"], $searchSort["sort"]);
              $total_replies = p_count($replies);
              $replies       = array_slice($replies, ($pageNum_N - 1) * $pageSize, $pageSize);
            } else {
              $total_replies = isset($json->total_replies) ? (int)$json->total_replies : 0;
            }
            $pageOver = max(1, (int)ceil($total_replies / $pageSize));
            if ($total_replies === 0) {
              $emptyMsg = $searchSort["query"] !== ""
              ? "検索条件に一致する返信が見つかりませんでした。/No replies matched your search."
              : "ここにあなたのポストやスレッド（返信）が表示されます。/Here you will see the threads (replies) of your post.";
              self::$notices[] = ["notification", $emptyMsg];
            } else {
              // ページ送りの決め方は投稿一覧・プロフィールと同じで、$pageNum_N だけを見ます。
              //
              // 【スレッドの1ページ目だけ「前へ」が一覧へ戻る】返信の1ページ目より前には
              // 返信がありませんが、その位置での「戻る」は利用者にとって
              // 「スレッドを閉じて一覧へ帰る」意味です。JS側のprevPage()も同じ扱いを
              // していて、1ページ目では直前に見ていた一覧のURLへ戻します。
              // ここを空にするとJSの無い環境でだけ戻る手段が消えるので、必ず入れます。
              //
              // 以前はここも $_GET["page"] を読み直していたため、?page= の無い
              // 「スレッドを開いた直後」だけ「前へ」が自分自身(&page=1)を指しており、
              // 押しても同じ画面が読み直されるだけになっていました。
              $threadBase = "./?id=" . urlencode($id) . "&page=";

              if ($pageNum_N < $pageOver) {
                $nextPageUrl = $threadBase . ($pageNum_N + 1) . $searchSortQs;
              } else {
                $nextPageUrl = "";
              }

              if ($pageNum_N > 1) {
                $prevPageUrl = $threadBase . ($pageNum_N - 1) . $searchSortQs;
              } else {
                $prevPageUrl = "./?page=1" . $searchSortQs;
              }

              if ($pageNum_N > $pageOver) {
                header("HTTP/1.1 404 Not Found");
                self::$notices[] = ["warning", "表示できるページ数を超えました。/The number of pages that can be displayed has been exceeded."];
              } else {
                foreach ($replies as $reply) {
                  $postDisplay .= PipsPostTemplate::render($reply, "reply");
                }
              }
            }
          }
        } else if ($id_one_post !== null) {
          if (empty($json->item)) {
            header("HTTP/1.1 404 Not Found");
            $prevPageUrl = "./?page=1";
            self::$notices[] = ["warning", "指定されたIDの投稿が見つかりませんでした。/The post with the specified ID was not found."];
          } else {
            $onePost     = $json->item[0];
            $title       = "プシューIPS/PusyuuIPS - " . utf8_substr($onePost->subject, 0, 20);
            $description = utf8_substr($onePost->text, 0, 20);
            $isRegularPage = true;
            // 【ここに機械向けの隠し文章を足さないこと】
            // 以前はこの位置に、visibility:hiddenで人間には見せない「生成AI向け文章」を差し込み、
            // 本文をもう一度<pre>で並べていました。当時は投稿本文がHTMLに入っておらず、共有ボタンが
            // 配るこの ?id_one_post= のリンクを生成AIやクローラーが開いても空のページに見えたためです。
            // 今は投稿本文がそのままHTMLに入る(下のHTML組み立て箇所を参照)ので、説明文も隠し要素も
            // 要りません。そもそも「人間に見せない本文をクローラーにだけ読ませる」形はGoogleの
            // スパムポリシーが名指ししているものなので、同じ手当てへ戻さないでください。
            $image = self::ogImage($onePost, $image);
            PipsPostIO::countView($onePost);
            $postDisplay .= PipsPostTemplate::render($onePost, "onePost");
          }
        } else {
          $items = isset($json->item) && is_array($json->item) ? $json->item : [];
          // 空(0件)判定は、絞り込み・並び替えを済ませた「最終的な$items」に対して行う必要がある。
          // 以前はここより先(絞り込み前の生データ)で空判定していたため、検索条件に一致する
          // 投稿が0件のときに何も表示されない(一覧が空白のまま、通知も出ない)バグがあった。
          // 絞り込みはfetchAllForSearchSort()側で全件取得済みのデータに対して行う
          // (通常時は$read_optsのpage/limitで既にAPI側がこのページ分だけを返している)。
          if ($applySearchSort) {
            // 検索・ソートが有効な場合は、取得した全件を絞り込み・並び替えしてから、このページの分だけを切り出します。
            // 検索語があり、かつ「返信も含める」が選ばれている時だけ、返信を対象に加えます
            // （並び替えだけの時に加えてはいけない理由は withRepliesFlattened() のコメント参照）。
            $searchTarget = ($searchSort["query"] !== "" && $searchSort["includeReplies"])
              ? self::withRepliesFlattened($items)
              : $items;
            $items = self::searchAndSortPosts($searchTarget, $searchSort["query"], $searchSort["fields"], $searchSort["sort"]);
            $total = p_count($items);
            $items = array_slice($items, ($pageNum_N - 1) * $pageSize, $pageSize);
          } else {
            $total = isset($json->total) ? (int)$json->total : p_count($json->item);
          }
          $pageOver = max(1, (int)ceil($total / $pageSize));

          if (empty($items) && !(isset($_GET["page"]) && (int)$_GET["page"] > $pageOver)) {
            $emptyMsg = $searchSort["query"] !== ""
            ? "検索条件に一致する投稿が見つかりませんでした。/No posts matched your search."
            : "まだ投稿がありません。最初の投稿を書いてみましょう！/There are no posts yet. Try writing the first post!";
            self::$notices[] = ["notification", $emptyMsg];
          } else {
            foreach ($items as $postList) {
              $postDisplay .= self::renderSearchHit($postList);
            }
            // 【ページ送りのURLは、?page= が付いているかどうかで分けないこと】
            //
            // 以前はここが「?page=に値がある / ?page=が空 / ?page=が無い」の三つに
            // 割れていて、三つ目(= 利用者が最初に開くトップページ)だけ$nextPageUrlを
            // 一度も入れていませんでした。通常版のボタンは href="" のまま描かれるので
            // 押しても同じページを読み直すだけ、軽量版は !empty() で隠すのでリンク自体が
            // 現れません。どちらも「ページ送りが効かない」という形で表に出ます。
            // 2ページ目以降へ手でURLを打って入ると、そこから先は動くので、
            // 症状としては「最初の1回だけ進めない」という分かりにくい出方をしていました。
            //
            // 現在ページは上で $pageNum_N に一本化済みです(?page=が無ければ1、
            // 数字でなければ1)。ここはその値だけを見て、前後が在るか無いかを決めます。
            // ?page= が付いているかどうかは、canonicalを出すか($isRegularPage)にしか
            // 関係しません。条件を足すときも、この二つを混ぜないでください。
            if ($pageNum_N > $pageOver) {
              header("HTTP/1.1 404 Not Found");
              // 一覧は空にして、知らせだけを出します(以前は = で作り直していた所です。
              // 検索の案内などの知らせは入れ物の側にあるので、ここで消えません)。
              $postDisplay = "";
              self::$notices[] = ["warning", "表示できるページ数を超えました。/The number of pages that can be displayed has been exceeded."];
            } else {
              if ($pageNum_N < $pageOver) {
                $nextPageUrl = "./?page=" . ($pageNum_N + 1) . $searchSortQs;
              } else {
                // 最後のページ。これ以上先が無いので、ボタンごと出しません。
                $nextPageUrl = "";
              }

              if ($pageNum_N > 1) {
                $prevPageUrl = "./?page=" . ($pageNum_N - 1) . $searchSortQs;
              } else {
                // 1ページ目の前は無いので、ボタンごと出しません。
                $prevPageUrl = "";
              }
            }

            // canonicalは「?page=の付かない素のURL」のときだけ出します。
            // 2ページ目以降にまで同じcanonicalを出すと、検索エンジンには
            // すべてのページが1ページ目の複製に見えます。
            if (isset($_GET["page"])) {
              $isRegularPage = false;
            } else {
              $isRegularPage = true;
            }
          }
        }
      }
    }

    // 溜めた知らせを1つの枠にして、$noticeAt の位置へ差し込みます。
    // 【ここ(組み立ての最後)で差し込むこと】上の枝には $postDisplay を空に作り直すもの
    // (「表示できるページ数を超えました」)があるので、途中で差し込むとその枝でだけ消えます。
    if (!empty(self::$notices)) {
      $noticeHtml  = PipsPostTemplate::render(null, self::$notices);
      $postDisplay = substr($postDisplay, 0, self::$noticeAt) . $noticeHtml . substr($postDisplay, self::$noticeAt);
    }
  }
}

// 軽量版（?lite=1）の「クリックして開く」メディアリンク用のエンドポイントです。
// 通常表示のようにbase64データをページ本体に埋め込まず、クリックされた時だけ別リクエストとして
// 該当の1件だけを配信することで、投稿一覧のHTML自体を軽量に保ちます（OGP画像のような扱い方です）。
if (isset($_GET["media_post"]) && isset($_GET["media_index"])) {
  // このURLはog:imageには使われないため、外部サイトからのホットリンクを拒否した上で、
  // 検索エンジンにはインデックス/画像検索とも拾わせません。
  if (PipsHotlinkGuard::isHotlink()) {
    PipsHotlinkGuard::reject();
  }
  header('X-Robots-Tag: noindex, nofollow');
  $mediaLookup = PipsPostIO::postData("read", ["id_one_post" => $_GET["media_post"]]);
  $mediaJson   = ($mediaLookup !== false && $mediaLookup !== null) ? json_decode($mediaLookup) : null;
  $mediaPost   = (isset($mediaJson->item) && !empty($mediaJson->item)) ? $mediaJson->item[0] : null;
  $mediaIndex  = (int)$_GET["media_index"];
  $mediaList   = ($mediaPost !== null && isset($mediaPost->media))
  ? (is_array($mediaPost->media) ? $mediaPost->media : [$mediaPost->media])
  : [];

  if ($mediaPost === null || !isset($mediaList[$mediaIndex]) || empty($mediaList[$mediaIndex])) {
    http_response_code(404);
    exit;
  }

  $mediaBase64 = $mediaList[$mediaIndex];
  $decoded     = base64_decode($mediaBase64, true);
  $finfo       = new finfo(FILEINFO_MIME_TYPE);
  $mimeType    = $decoded !== false ? $finfo->buffer($decoded) : false;

  if ($decoded === false || $mimeType === false || (strpos($mimeType, "image/") !== 0 && strpos($mimeType, "video/") !== 0)) {
    http_response_code(415);
    exit;
  }
  header("Content-Type: " . $mimeType);
  echo $decoded;
  exit;
}

// フォームの送信とテキストの入力チェック
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  // GETメソッドで再表示する際に元のURLパラメータを維持するため、POST分岐の先頭で保存します
  saveQueryParamToSession();

  // 【この判定は、他のどの分岐よりも先に置くこと】
  // POSTが post_max_size を超えていた場合、$_POST と $_FILES は丸ごと空です
  // (pipsPostMaxSizeExceeded() の説明を参照)。この下の分岐はすべて $_POST の
  // キーの有無で「どのボタンが押されたか」を見分けているので、順番を入れ替えて
  // ここより後ろへ置くと、どの枝にも入らないまま素通りして、利用者には
  // 「押しても何も起きない」ようにしか見えません。
  //
  // CSRFトークン(tokenIsValid())の確認より前に置いてあるのも同じ理由です。
  // トークン自体も$_POSTで届くはずの物なので、超過時には必ず欠けており、
  // 先にトークンを見ると本当の原因(大きすぎた)ではなく「二重送信です」という
  // 見当違いの案内が出ます。
  if (pipsPostMaxSizeExceeded()) {
    error_log("[PIPS] 受け入れ上限を超えた送信を受け取りました。CONTENT_LENGTH="
      . (int)($_SERVER["CONTENT_LENGTH"] ?? 0)
      . " / post_max_size=" . ini_get("post_max_size")
      . " / upload_max_filesize=" . ini_get("upload_max_filesize"));
    processResult(pipsPostMaxSizeExceededMessage());
  }

  $accountAction = $_POST["account_action"] ?? "";

  switch ($accountAction) {
    case "logout":
    if (tokenIsValid()) {
      PipsAccountFeature::handleLogout();
    } else {
      processResult($dualTransmissionMsg);
    }
    break;
  }

  // push-sw.js の pushsubscriptionchange から直接叩かれる再購読の受け口。
  //
  // 【なぜここだけCSRFトークンを要求しないか】Service Workerはページではないので、
  // ページに埋め込まれるトークンを持っていません。ブラウザが購読を作り直した時
  // (端末の更新等)にここを通れないと、通知が黙って止まります。代わりに
  // セッション(=ログイン中の本人)の有無だけで許可します。第三者が何かを
  // 仕込めたとしても、できるのは「その人自身の端末を購読に加える」ことだけで、
  // 情報が漏れる向きの操作ではありません。
  if (isset($_POST["push_resubscribe"])) {
    header('Content-Type: application/json; charset=UTF-8');
    $pushActor = PipsPush::actorId();
    if ($pushActor === null) {
      http_response_code(401);
      echo json_encode(["ok" => false, "error" => "login_required"]);
      exit;
    }
    $res = PipsPush::api('subscribe', [
      'service' => PipsPush::SERVICE, 'userid' => $pushActor,
      'subscription' => (string)($_POST["subscription"] ?? ''),
      'ua' => mb_substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ''), 0, 200),
    ]);
    PipsPush::forgetChannelStates();
    PipsPush::replyToBrowser($res);
  }

  // -------------------------------------------------------------------
  // 通知まわり。purchase_subscribe等と同じくJSからfetch()で呼ばれるため、
  // 応答はJSONで返して即exitします(ここでリダイレクトを返すと、呼び出し側が
  // HTMLをJSONとして読もうとして「Unexpected token '<'」になります)。
  // notify_settings だけはJSを使わないフォーム送信なので、転送で返します。
  // -------------------------------------------------------------------
  if (isset($_POST["push_subscribe"]) || isset($_POST["push_unsubscribe"]) || isset($_POST["notify_channel"])) {
    header('Content-Type: application/json; charset=UTF-8');
    $pushActor = PipsPush::actorId();
    if ($pushActor === null) {
      http_response_code(401);
      echo json_encode(["ok" => false, "error" => "login_required", "message" => "通知を受け取るにはログインが必要です。"]);
      exit;
    }
    if (!tokenIsValid()) {
      http_response_code(403);
      echo json_encode(["ok" => false, "error" => "token_expired", "message" => "ページの有効期限が切れました。再読み込みしてもう一度お試しください。"]);
      exit;
    }

    if (isset($_POST["push_subscribe"])) {
      $res = PipsPush::api('subscribe', [
        'service' => PipsPush::SERVICE, 'userid' => $pushActor,
        'subscription' => (string)($_POST["subscription"] ?? ''),
        'ua' => mb_substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ''), 0, 200),
      ]);
    } else if (isset($_POST["push_unsubscribe"])) {
      $res = PipsPush::api('unsubscribe', [
        'service' => PipsPush::SERVICE, 'userid' => $pushActor,
        'endpoint' => (string)($_POST["endpoint"] ?? ''),
      ]);
    } else {
      $res = PipsPush::setChannel($pushActor, (string)($_POST["channel"] ?? ''), (string)($_POST["state"] ?? ''));
    }
    // 登録台数が変わったので、ページに渡している「サーバ側の見え方」の控えを捨てます。
    PipsPush::forgetChannelStates();
    PipsPush::replyToBrowser($res);
  }

  // RSS設定画面へ送り出すだけの分岐。押されたその場でチケットを発行して転送します
  // (URLに短命の資格情報が載るため、ページのHTMLには埋め込みません)。
  if (isset($_POST["notify_settings"])) {
    $pushActor = PipsPush::actorId();
    if ($pushActor === null) {
      processResult("通知の設定を開くにはログインが必要です。/Login is required.");
    } else if (!tokenIsValid()) {
      processResult($dualTransmissionMsg);
    } else {
      $settingsUrl = PipsPush::settingsUrl($pushActor);
      if ($settingsUrl === "") {
        // 通知APIへ届かないとき。何も起きないと押した人には区別がつかないので、
        // 必ず理由を1行出します。
        processResult("通知の設定を開けませんでした。しばらく待ってからもう一度お試しください。");
      } else {
        header('Location: ' . $settingsUrl);
        exit;
      }
    }
  }

  if (isset($_POST["follow_username"])) {
    if (tokenIsValid()) {
      genelateSession();
      PipsPostHandlers::followUser();
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["unfollow_username"])) {
    if (tokenIsValid()) {
      genelateSession();
      PipsPostHandlers::unfollowUser();
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["reply"])) {
    if (tokenIsValid()) {
      genelateSession();
      PipsSession::set("reply_id", $_POST["reply_id"]);
      PipsSession::set("reply_submit", $_POST["reply_submit"]);
      // 件名は返信先のものに差し替えます（保存時に "RE：" が付きます）。
      // 本文には手を付けません。返信先を選び直しても書きかけが消えないようにするためです。
      PipsSession::set("draft_subject", $_POST["reply_submit"]);

      pipsRespondModalIfAjax(true, "composer");
      processResult(null, "#modal-1");
    } else {
      pipsRespondModalIfAjax(false, "composer");
      processResult($dualTransmissionMsg);
    }
  }

  // 下書きの保存。JSがあってもなくても、通るのはこの1本です。
  // JSからはXHRで（ajax=1付き）、JSが無ければ「下書きを保存」ボタンで、同じここへ来ます。
  if (isset($_POST["draft_save"])) {
    if (tokenIsValid()) {
      genelateSession();
      pipsDraftSave(
        $_POST["subject"] ?? "",
        $_POST["text"] ?? "",
        !empty($_POST["sensitive"]),
        !empty($_POST["no_convert_links"])
      );

      if (isset($_POST["ajax"])) {
        $savedDraft = pipsDraft();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
          "ok"           => true,
          "saved_at"     => $savedDraft["saved_at"],
          "server_token" => (string)(PipsSession::get("server_token", "")),
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }

      // JSが無い場合。書き込み画面へ戻すだけで、投稿はしません。
      // 「保存して閉じる」が押された時だけ、モーダルを閉じた状態へ戻します。
      processResult(null, isset($_POST["draft_save_close"]) ? "#mc" : "#modal-1");
    } else {
      if (isset($_POST["ajax"])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["ok" => false, "server_token" => (string)(PipsSession::get("server_token", ""))], JSON_UNESCAPED_UNICODE);
        exit;
      }
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["reply_cancel"])) {
    if (tokenIsValid()) {
      genelateSession();
      PipsSession::set("reply_id", "");
      PipsSession::set("reply_submit", "");

      pipsRespondModalIfAjax(true, "composer");
      processResult(null, "#modal-1");
    } else {
      pipsRespondModalIfAjax(false, "composer");
      processResult($dualTransmissionMsg);
    }
  }

  // 「編集」「削除」アイコンをJS無しで押した場合のフォールバック(reply/reply_cancelと同じパターン)。
  // クリック直後の権限確認はここで一度行いますが、これは#modal-editの表示内容を用意するためのもので、
  // 実際の保存・削除を実行するのはPipsPostHandlers::postEdit()/postDelete()側で、そこでも同じ検証を独立に
  // やり直します(このリクエスト自体はセッションへのプリフィルに過ぎず、権限の最終判断ではありません)。
  if (isset($_POST["edit_request"])) {
    if (tokenIsValid()) {
      genelateSession();
      $targetPost = PipsPostHandlers::findPostForPermissionCheck($_POST["edit_request"]);
      if ($targetPost !== null && PipsPostHandlers::canEditOrDeletePost($targetPost)) {
        PipsSession::set("edit_target_id", $_POST["edit_request"]);
        PipsSession::set("edit_text_prefill", is_array($targetPost) ? ($targetPost["text"] ?? "")    : ($targetPost->text ?? ""));
        PipsSession::set("edit_subject_prefill", is_array($targetPost) ? ($targetPost["subject"] ?? "") : ($targetPost->subject ?? ""));
        // 添付の「枚数」だけを控えます。中身(base64)は持ちません。モーダルは1件配信用URLを
        // 参照するだけなので枚数が分かれば足り、セッションに数MBを抱え込まずに済みます。
        $targetMedia = is_array($targetPost) ? ($targetPost["media"] ?? []) : ($targetPost->media ?? []);
        PipsSession::set("edit_media_count", is_array($targetMedia) ? p_count($targetMedia) : ($targetMedia !== "" && $targetMedia !== null ? 1 : 0));
      } else {
        PipsSession::set("edit_target_id", "");
        PipsSession::set("edit_text_prefill", "");
        PipsSession::set("edit_subject_prefill", "");
        PipsSession::set("edit_media_count", 0);
      }

      pipsRespondModalIfAjax(true, "edit");
      processResult(null, "#modal-edit");
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["delete_request"])) {
    if (tokenIsValid()) {
      genelateSession();
      $targetPost = PipsPostHandlers::findPostForPermissionCheck($_POST["delete_request"]);
      PipsSession::set("delete_target_id", ($targetPost !== null && PipsPostHandlers::canEditOrDeletePost($targetPost)) ? $_POST["delete_request"] : "");

      pipsRespondModalIfAjax(true, "delete");
      processResult(null, "#modal-delete");
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  // 感想スタンプ。stamp_request はモーダルを開くだけ(どの投稿かをセッションに覚えます。
  // 編集の edit_request と同じ形で、JSがあればXHRで中身だけを差し替えます)。
  // 押す・取り消すは PipsStamps が受け持ちます。
  if (isset($_POST["stamp_request"])) {
    if (tokenIsValid()) {
      genelateSession();
      $stampTarget = (string)$_POST["stamp_request"];
      PipsSession::set("stamp_target_id", preg_match('/^[A-Za-z0-9_]{1,64}$/', $stampTarget) === 1 ? $stampTarget : "");

      pipsRespondModalIfAjax(true, "stamp");
      processResult(null, "#modal-stamp");
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["stamp_submit"])) {
    if (tokenIsValid()) {
      genelateSession();
      PipsStamps::press((string)($_POST["stamp_post_id"] ?? ""), (string)$_POST["stamp_submit"]);
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["stamp_undo"])) {
    if (tokenIsValid()) {
      genelateSession();
      PipsStamps::undo((string)$_POST["stamp_undo"]);
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["post_edit_submit"])) {
    if (tokenIsValid()) {
      genelateSession();
      PipsPostHandlers::postEdit();
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["post_delete_submit"])) {
    if (tokenIsValid()) {
      genelateSession();
      PipsPostHandlers::postDelete();
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["disablePost_button"])) {
    if (tokenIsValid()) {
      genelateSession();

      $jsonData = @file_get_contents(PIPS_DISABLE_LOG_FILE);
      $data = json_decode($jsonData, true);
      if ($data === null) {
        $data["disablePost"] = [];
      }

      $data["disablePost"][] = $_POST["disablePost_button"];
      $newJsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

      file_put_contents(PIPS_DISABLE_LOG_FILE, $newJsonData);

      $_POST["disablePost_button"] = "";
      processResult("ポストの無効化をリクエストしました。/A request to disable the post has been made.");
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  // 軽量版(ライトモード)のON/OFF。
  //
  // 【セッションの"lite_mode"へ書くのはここだけです】URLの印から書き戻す処理を、
  // ファイル上部やこの下に増やさないこと。理由はファイル上部で$isLiteを決めている
  // 箇所の【OFFもURLの印〜】に書いてあります。
  //
  // 【向きは、押されたボタンのvalueそのものです】隠しinputを別に足さず、同じnameの
  // valueで「ONにしたいのか、OFFにしたいのか」を運びます。入口が1つなら、ONとOFFが
  // 食い違いようがありません。
  //
  // 【知らない値が来たら何も変えないこと】「空でなければON」のように書くと、値が
  // 途中で欠けた送信が来たときに、利用者が頼んでいない向きへ勝手に倒れます。倒れた先が
  // 軽量版だと、通常版のフッターごと消えるので押し戻す手段まで一緒に消えます。
  // 分かる値だけを受け、分からない値は断るのが安全側です。
  if (isset($_POST["liteMode_button"])) {
    $liteRequest = is_string($_POST["liteMode_button"]) ? $_POST["liteMode_button"] : "";

    // 【$isLiteもここで更新すること】この下のprocessResult()は$isLiteを見て、戻り先URLへ
    // 保険の lite=1 を足すかどうかを決めます。$isLiteはこのファイルの上部で、今回の
    // 切り替えが起きる前のセッションから決まっているので、ここで更新しないと、OFFにした
    // 直後の戻り先に lite=1 が付いて軽量版のまま戻ってきます。
    if (!tokenIsValid()) {
      processResult($dualTransmissionMsg);
    } else if ($liteRequest === PIPS_LITE_ON) {
      genelateSession();
      PipsSession::set("lite_mode", true);
      $isLite = true;
      processResult("軽量版に切り替えました。/Switched to the lite version.");
    } else if ($liteRequest === PIPS_LITE_OFF) {
      genelateSession();
      PipsSession::set("lite_mode", false);
      $isLite = false;
      processResult("通常版に戻しました。/Switched back to the full version.");
    } else {
      // 値が欠けている、または知らない値。どちら向きの操作か決められないので、
      // 今の表示のまま、黙らずに戻します。
      processResult("表示の切り替えができませんでした。恐れ入りますが、もう一度お試しください。/Could not switch the display mode. Please try again.");
    }
  }

  // お気に入りの追加・削除。中身は PipsPostHandlers::addBookmark() / removeBookmark() にあります。
  // ログイン中かどうかの振り分けも向こうで行うので、削除のボタン名はどちらも remove_like です。
  if (isset($_POST["like_button"])) {
    if (tokenIsValid()) {
      genelateSession();
      PipsPostHandlers::addBookmark();
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["remove_like"])) {
    if (tokenIsValid()) {
      // トークン照合が通った時点でセッショントークンを更新します（未ログイン時にトークンが使い回されてしまうバグの修正）
      genelateSession();
      PipsPostHandlers::removeBookmark();
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["viewPost_button"])) {
    if (tokenIsValid()) {
      genelateSession();
      processResult(null, $_POST["viewPost_button"], "direct");
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  // 【現在この分岐へは入りません】これを送っていた「手動でローディング画面を閉じる」ボタンは、
  // ローディング画面ごと廃止されました（投稿が最初からHTMLに入るようになり、待たせる理由が
  // 無くなったためです）。$isNojsを読む場所も今はありません。ローディング画面を復活させる
  // 時のために形だけ残してあります。不要だと判断したら、$isNojsの初期化ごと消してください。
  if (isset($_POST["noJsLoadingClose"])) {
    if (tokenIsValid()) {
      genelateSession();
      $isNojs = true;
      //header("Location: ./");
      //exit();
    } else {
      processResult($dualTransmissionMsg);
    }
  }

  if (isset($_POST["postSend"])) {
    //debugTrace("postSend_entry");
    if (tokenIsValid()) {
      genelateSession();

      // 【返信先はここで必ず片付ける】セッションの"reply_id"/"reply_submit"は
      // 「書き込みフォームを開いたときに返信先を入れておく」ためだけの一時的な値です。
      // フォームが送信された時点で役目は終わっており、以降の処理は$_POST["reply_to"]を見ます。
      //
      // 送信の成否に関わらず、この1箇所で捨てること。失敗したときに残しておくと、
      // 「もういいや」と諦めた人が次に新規投稿を書いたときに、意図しないまま
      // その相手への返信として投稿されます。利用者が返信先を自分で外す手段は
      // 用意していないので、残ってしまうと戻す方法がありません。
      // 書きかけの本文は、JSが使える環境ではブラウザのローカルストレージが預かっています。
      PipsSession::set("reply_id", "");
      PipsSession::set("reply_submit", "");

      // 【送信を受け付けたら、まず書いた物を預かる】この先には、本文が空・添付が多すぎる・
      // 形式が違う、といった理由で途中で止まる道がいくつもあります。以前はそこで止まると
      // 書いた内容がそのまま消えていました。先に下書きとして預けておけば、どの道で
      // 止まっても、戻ってきた画面に本文が残っています。投稿できた時だけ捨てます。
      pipsDraftSave(
        $_POST["subject"] ?? "",
        $_POST["text"] ?? "",
        !empty($_POST["sensitive"]),
        !empty($_POST["no_convert_links"])
      );

      if (isset($_POST["text"]) && !empty($_POST["text"])) {
        // テキストデータの前後の空白を削除
        $_POST["text"] = preg_replace("/^[　\s]+/u", "", $_POST["text"]);
        $_POST["text"] = preg_replace("/[　\s]+$/u", "", $_POST["text"]);

        // 【この分岐は必ずどれかに当たること】以前はここに「どこにも当たらない道」があり、
        // 投稿もされずメッセージも出ないまま元の画面へ戻る（＝利用者から見て無言）状態が
        // 起きていました。具体的には $_FILES["media"] が送られてこない場合で、
        // ファイル欄を送らない古いブラウザや、POST全体がpost_max_sizeを超えて
        // $_FILES ごと空になった場合が該当します。条件を足すときは、必ず
        // 「そのどれにも当たらなかった場合」の枝まで書いてください。
        $mediaFiles = $_FILES["media"] ?? null;
        $countMediaFiles = (is_array($mediaFiles) && is_array($mediaFiles["name"] ?? null)) ? p_count($mediaFiles["name"]) : 0;

        if ($countMediaFiles === 0) {
          // ファイル欄そのものが届かなかった場合。添付なしの投稿として扱います
          // （ファイル欄はあるが未選択の場合は、下で「未選択」として飛ばされます）。
          PipsPostHandlers::postCreate("");
        } elseif ($countMediaFiles > PIPS_MEDIA_MAX_COUNT) {
          processResult("一度にお預かりできる画像・動画は" . PIPS_MEDIA_MAX_COUNT . "個までです。恐れ入りますが、数を減らしてお試しください。/You can attach up to " . PIPS_MEDIA_MAX_COUNT . " images or videos at once. Please remove a few and try again.");
        } else {
          // 【1件ずつの検査と変換は pipsEncodeUploadedMedia() に任せること】形式・大きさ・
          // PHPが弾いた場合の扱いは、編集(PipsPostHandlers::rebuildEditedMedia())と同じ関数で
          // 決めます。ここに同じ判定を書き直すと、片方だけ直して食い違う形に戻ります。
          //
          // 未選択の欄は飛ばします。以前は途中に未選択が混じると、それまでに受け取った
          // 添付を捨てて、添付なしで投稿していました。
          // 1件でも受け取れなければ、そこで止めて理由を出します(投稿は保存しません)。
          $encodedMedias = [];
          $failMessage   = null;

          for ($i = 0; $i < $countMediaFiles && $failMessage === null; $i++) {
            $file    = pipsUploadedAt("media", $i);
            $encoded = ($file === null)
              ? ["ok" => true, "data" => null]
              : pipsEncodeUploadedMedia($file["name"], $file["size"], $file["tmp"], $file["error"]);

            if (!$encoded["ok"]) {
              $failMessage = $encoded["message"];
            } else if ($encoded["data"] !== null) {
              $encodedMedias[] = $encoded["data"];
            } else {
              // 未選択の欄。何も足しません。
            }
          }

          if ($failMessage !== null) {
            processResult($failMessage);
          } else if (empty($encodedMedias)) {
            PipsPostHandlers::postCreate("");
          } else {
            PipsPostHandlers::postCreate($encodedMedias);
          }
        }
      } else {
        processResult("本文が空のようです。ひとこと書いてから送信してくださいね。/Your message looks empty. Please write something before sending.");
      }
    } else {
      processResult($dualTransmissionMsg);
    }
  }
} else if ($_SERVER["REQUEST_METHOD"] === "GET") {
  if ((PipsSession::get("dispDoneProsessComleteFulg", null)) === true && ($_GET["ClearMSG"] ?? null) === "true") {
    PipsSession::set("dispDoneProsessComleteFulg", null);
    // GETの場合にメッセージを消す
    PipsSession::set("post_complete_msg", []);
  }

  if (parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) == "/index.php") {
    http_response_code(301); // header('Status: ...')はSAPIによっては無視されるため、http_response_code()を使用します
    // 自分自身への転送なので相対URLで書きます(どの入口から来ても、その入口のまま戻ります)。
    header('location: ./' . (!empty($_GET) ? ("?" . http_build_query($_GET)) : ""));
    exit;
  }
}

/**
 * 新着があるかどうかだけを返す軽い応答（?pipsNewCheck=1）。
 *
 * 【なぜgetDispPostを使い回さないか】getDispPostは一覧のHTMLを最後まで組み立てて返します。
 * 新着の有無を確かめたいだけのために、本文も画像リンクもボタンも全部作らせるのは無駄で、
 * 定期的に呼ぶ用途では負荷が見合いません。ここでは「一番新しい投稿のid」と「件数」だけを
 * 1件ぶんの読み込みで取り、PipsDispData::run()（重い描画）へは進まずに終わります。
 * そのためこの分岐はrun()より**手前**に置いてあります。位置を動かさないこと。
 *
 * 【返す物】
 *   スレッドを開いている時(?id=) … そのスレッドの返信の件数
 *   一覧を見ている時             … 一番新しい投稿のidと総件数
 * 画面側はこれを前回の値と比べ、変わっていたら「新しい投稿があります」と出すだけです。
 * 勝手に一覧を差し替えることはしません（読んでいる最中に表示が動くのを避けるため）。
 */
// =====================================================================
// 通知まわりの特殊GETパラメータ。pipsNewCheckと同じく、重い描画(PipsDispData::run())
// より手前で完結させます。
//
// ?push_sw=1 … Service Workerの本体を配ります。assets/scripts/配下に置いたまま
//   Service-Worker-Allowed: / をPHPから付けることで、ルート直下へのファイル配置にも
//   .htaccess(mod_headers)にも頼らず scope: '/' を成立させます。ログイン状態は
//   問いません。
// ?push_inbox=1 … SWのpushイベントから叩かれ、表示する中身をJSONで返します。
//   同一オリジンなのでセッションCookieがそのまま乗り、ここでログイン中の本人を
//   解決できます。
// =====================================================================
if (isset($_GET["push_sw"])) {
  header('Content-Type: text/javascript; charset=UTF-8');
  header('Service-Worker-Allowed: /');
  readfile('./assets/scripts/push-sw.js');
  exit;
}

if (isset($_GET["push_inbox"])) {
  header('Content-Type: application/json; charset=utf-8');
  $pushActor = PipsPush::actorId();
  if ($pushActor === null) {
    // 未ログイン。SWは何も表示せずに終わります(空の配列を返すのが正しい応答で、
    // エラーにするとSW側のcatchに落ちて原因が見えなくなります)。
    echo json_encode(["items" => []]);
    exit;
  }
  echo PipsPush::inboxJson($pushActor);
  exit;
}

if (isset($_GET["pipsNewCheck"])) {
  header('Content-Type: application/json; charset=utf-8');

  $checkThreadId = isset($_GET["id"]) && $_GET["id"] !== "" ? (string)$_GET["id"] : "";
  if ($checkThreadId !== "") {
    $checkRaw  = PipsPostIO::postData("read", ["id" => $checkThreadId, "page" => 1, "limit" => 1]);
    $checkJson = $checkRaw !== false && $checkRaw !== null ? json_decode($checkRaw) : null;
    $checkPayload = [
      "ok"     => is_object($checkJson),
      "count"  => (int)($checkJson->total_replies ?? 0),
      "newest" => "",
    ];
  } else {
    $checkRaw  = PipsPostIO::postData("read", ["page" => 1, "limit" => 1]);
    $checkJson = $checkRaw !== false && $checkRaw !== null ? json_decode($checkRaw) : null;
    $checkPayload = [
      "ok"     => is_object($checkJson),
      "count"  => (int)($checkJson->total ?? 0),
      "newest" => isset($checkJson->item[0]->id) ? (string)$checkJson->item[0]->id : "",
    ];
  }

  echo json_encode($checkPayload, JSON_UNESCAPED_UNICODE);
  exit;
}


// プロダクト一覧は台帳(pusyuu_registry)から受け取り、自分(台帳のID pips)は外します。
// 【自分を URL で見分けないこと】以前は今のホスト名と一覧のURLを突き合わせていたため、
// ドメインや入口が変わると自分が一覧に混ざりました。
function productLinks() {
  $links = class_exists('PusyuuRegistryClient') ? PusyuuRegistryClient::links('pips') : null;

  if ($links === null) {
    $product_disp = '<p style="color: #f00;">プロダクト一覧の取得中に何らかの問題が発生し取得できませんでした、開発者の設定ミスが原因な可能性がございます。/An issue occurred while attempting to retrieve the product list, and the data could not be loaded. This may be due to a misconfiguration by the developer.</p>';
  } else if ($links === []) {
    $product_disp = "<p>表示できるプロダクトはないようです。/No products appear to be available for display.</p>";
  } else {
    $product_disp = '<ul style="height: 100%; overflow: auto; list-style: decimal-leading-zero;">';
    foreach ($links as $productLink) {
      $product_disp .= '<li><a href="' . htmlspecialchars($productLink["url"], ENT_QUOTES, "UTF-8") . '" target="_blank">' . htmlspecialchars($productLink["title"], ENT_QUOTES, "UTF-8") . '</a></li>';
    }
    $product_disp .= "</ul>";
  }

  return $product_disp;
}

if (isset($_GET["getDispPost"])) {
  // 一覧を組み立てたこの時点の「新しさの目印」を一緒に返します。画面側はこれを覚えておき、
  // ?pipsNewCheck=1 の結果と比べて新着の有無を判断します。ここで返さないと、
  // 描画のたびに目印を取り直すためだけの往復がもう1回増えます。
  // 中身の意味は pipsNewCheck 側のコメントを参照。
  $markerThreadId = isset($_GET["id"]) && $_GET["id"] !== "" ? (string)$_GET["id"] : "";
  $markerRaw = $markerThreadId !== ""
    ? PipsPostIO::postData("read", ["id" => $markerThreadId, "page" => 1, "limit" => 1])
    : PipsPostIO::postData("read", ["page" => 1, "limit" => 1]);
  $markerJson = $markerRaw !== false && $markerRaw !== null ? json_decode($markerRaw) : null;

  $response = [
    "content" => $postDisplay,
    "title" => $title,
    "description" => $description,
    "image" => $image,
    "count"  => $markerThreadId !== "" ? (int)($markerJson->total_replies ?? 0) : (int)($markerJson->total ?? 0),
    "newest" => ($markerThreadId === "" && isset($markerJson->item[0]->id)) ? (string)$markerJson->item[0]->id : "",
  ];

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($response);
  exit;
}

// ================================================================
// このサイトが返すHTMLページを、ここ1箇所で決めます。
//
// ページを足すときは、この if/else に枝を1つ増やすだけです。増えた枝の中に、
// そのページに必要な準備とHTMLを両方書きます。隣の枝のことは何も知らなくて構いません。
// 逆に、ここ以外の場所でHTMLページを出力しないでください。出した瞬間に
// 「どのURLで何が出るのか」がこの1箇所を読んでも分からなくなります。
//
// 【echoせず、文字列を返すこと】
// 返す形にしてあるので、出口はこの関数の下の echo renderPIPS(); ただ1行です。
// 途中で出力して exit で後続を止める、という形が要りません。枝が増えても出口は増えません。
//
// 【出力していないことに意味があります】
// この関数の中ではまだ1バイトも送っていないので、run()が「投稿が見つからない」ときに
// 404ヘッダを送れます。もしここでechoしてしまうと、見つからないページが200を返すように
// なります(ヘッダは最初の1バイトが出た時点で手遅れになるため)。
//
// 【run()は枝ごとに、必要な枝だけが呼びます】
// run()は投稿データを読んで $postDisplay や $title などの材料を用意する関数です。
// 時計ページはその材料を1つも使わないので呼びません。将来LPのような静的ページを足す
// ときも、その枝で呼ばなければよいだけです。ここが枝ごとになっているおかげで、
// 「このページはデータが要るか」を切り替え側が知らずに済んでいます。
// ================================================================
function renderPIPS() {
  // run()が材料を置くのはグローバルなので、ここで見えるようにします。
  // これを書き忘れると、エラーは出ないまま中身が空のページが出ます(気づきにくい壊れ方です)。
  global $isLite, $isNoIndex, $isRegularPage, $postDisplay,
         $title, $description, $image, $nextPageUrl, $prevPageUrl, $version;

  $html = "";

  // ----------------------------------------------------------------
  // 時計ページ(?time)。掲示板とは無関係な独立ページなので run() は呼びません。
  //
  // 【liteより先に見ています】軽量版はセッションに貼り付くので、軽量版の人が
  // ?time を開いたときにどちらを出すかを決める必要があります。URLで名指しした
  // 指定のほうが、セッションに残った好みより強い、という順です。
  // ----------------------------------------------------------------
  if (isset($_GET["time"])) {
    $html = '
      <!DOCTYPE html>
      <html lang="ja">
        <head>
          <meta charset="UTF-8" />
          <meta name="robots" content="regularPage,nofollow" />
          <meta name="viewport" content="width=device-width, user-scalable=yes, maximum-scale=6.0, minimum-scale=1.0" />
          <link rel="shortcut icon" href="' . pipsSiteBase('main') . '/pusyuusystem/icons/favicon.ico" />
          <title>プシューIPS/PusyuuIPS - 時刻表示アプリ</title>
          <script>
            console.log("test_script")
            window.addEventListener("load", function() {
              var ele = document.getElementsByTagName("output")[0];
              setInterval(function() {
                var date2 = new Date();
                ele.innerHTML = date2;
              }, 100);
            }, false)
          </script>
          <style>
            body {
              background-color: #000;
            }
            output {
              color: #00ff00;
              padding: 10px;
              font-size: 58px;
            }
            .center {
              display: flex;
              justify-content: center;
              margin-top: 40vh;
              margin-bottom: 40vh;
              background-color: rgba(0, 0, 0, 0.5);
              border-radius: 10px;
            }
            @media only screen and (max-width: 750px) {
              output {
                color: #fff000;
                font-size: 45px;
              }
              .center {
                margin-top: 25vh;
                margin-bottom: 25vh;
              }
            }
            @media only screen and (max-width: 350px) {
              output {
                color: #fff000;
                font-size: 35px;
              }
            }
          </style>
          <!--
              *----------------------------------
              |  ThisPageVersion: 0.2         |
              |  © 2021-2023 By Pusyuu        |
              |  LastUpdate: 2023-04-23       |
              |  time denote display app      |
            ----------------------------------*
          -->
        </head>
        <body>
          <div class="center">
            <output></output>
          </div>
        </body>
      </html>
    ';

  // ----------------------------------------------------------------
  // 軽量版(?lite)。CSS/JSに一切依存しない専用ページです。
  // ルーティング・ページ送り・検索/ソート・投稿処理は通常版と完全に共通で、
  // 最終的な見た目(HTML)だけを素の状態に差し替えます。
  //
  // 軽量版かどうかの判定は上の $isLite が唯一の答えです。ここで $_GET["lite"] を
  // 見ないこと。軽量版はセッションに貼り付くので、URLの印だけを見ると
  // 「一度ONにした人が次のページで通常版に戻る」ことになります。URLの ?lite=1 は
  // Cookieが怪しい端末向けの保険で、印が付いていないことはOFFを意味しません。
  // ----------------------------------------------------------------
  } else if ($isLite) {
    PipsDispData::run();


    // ================================================================
    // PHP層：画面に出す文字列を、ここで全部決め切ります。
    //
    // 【HTMLの中へ判断を持ち込まないこと】条件によって出す物が変わる箇所は、
    // すべてこの層で1つの変数にしてから、下のHTML層へ差し込みます。
    // ifとendif(や { })をPHPの開始・終了タグで細切れにしてタグの間へ挟むと、
    // 「どこからどこまでがHTMLの構造なのか」が見た目から読み取れなくなり、
    // 閉じタグと制御構文の対応も目で追えなくなります。
    // 下のHTML層に出てくるPHPは、決まった変数を差し込む短縮echoだけです。
    //
    // 【コメントにPHPの終了タグを書かないこと】この注意書きを最初に書いたとき、
    // 例として終了タグをそのまま並べてしまい、そこでPHPブロックが閉じて
    // 構文エラーになりました。// のコメントの中でも終了タグは終了タグとして効きます。
    // ================================================================

    $esc = static function ($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    };

    $liteSortOptions = [
      "date_desc"    => "投稿日時が新しい順/Newest first",
      "date_asc"     => "投稿日時が古い順/Oldest first",
      "replies_desc" => "返信が多い順/Most replies",
      "replies_asc"  => "返信が少ない順/Fewest replies",
      "views_desc"   => "閲覧数が多い順/Most viewed",
      "views_asc"    => "閲覧数が少ない順/Least viewed",
    ];
    $liteCurrentSort   = $_GET["sort"] ?? "date_desc";
    $liteExcludeParams = ["q", "search_text", "search_subject", "search_name", "search_replies", "sort", "page", "lite"];

    // --- robots ---------------------------------------------------------
    // ここは index,follow を直書きしていたため、上の「軽量版はnoindexにする」($isNoIndex)が
    // 長らく効いていませんでした。$isLiteが真ならこのファイル上部で必ず$isNoIndexも真になるので、
    // 実質この<head>は常にnoindexになります。分岐にしてあるのは、通常版の<head>と同じ書き方に
    // 揃えて「robotsは$isNoIndexが決める」という一本の規則にするためです。
    //
    // 【noindexが安全になった経緯】軽量版には固有のアドレスがありません(セッションで決まります)。
    // 以前は軽量版へ入る入口がGETのリンクだったので、クローラーが踏むとそのセッションの
    // 正規URLまで軽量版で返り、ここでnoindexを出すと**正規URLにnoindexを撃つ**恐れがありました。
    // 入口をPOSTのボタンだけにした今、クローラーが軽量版に居座る経路そのものが無いため、
    // ここは万が一の保険として素直に置けます。入口をGETに戻すなら、この行も一緒に考え直すこと。
    if ($isNoIndex === true) {
      $liteRobotsHtml = '<meta name="robots" content="noindex,nofollow" />';
    } else {
      $liteRobotsHtml = '<meta name="robots" content="index,follow" />';
    }

    // --- 処理結果のメッセージ -------------------------------------------
    $liteMessagesHtml = "";

    if (PipsSession::has("post_complete_msg") && !empty(PipsSession::get("post_complete_msg"))) {
      foreach (PipsSession::get("post_complete_msg") as $liteMsg) {
        // メッセージにはリンクが含まれることがあるため、ここはエスケープしません
        // (中身を作っているのは利用者ではなく、このファイル自身です)。
        $liteMessagesHtml .= "<p>" . $liteMsg . "</p>";
      }
      $liteMessagesHtml .= '<p><a href="./?ClearMSG=true">メッセージを消す/Clear message</a></p>';
      PipsSession::set("dispDoneProsessComleteFulg", true);
    }

    // --- プシューメイキィ(アカウント)の案内 ------------------------------
    // 軽量版はJavaScriptが使えない端末向けのページです。ここに出す案内は、すべて
    // このPHPがHTMLとして書き出します。状態の出し分けをJSに任せると、この画面では
    // 何も分からなくなります。
    $liteAccountHtml = "";

    if (PipsAccountFeature::isLoggedIn()) {
      $liteProfileLink = './?@=' . urlencode(PipsSession::get("username"));
      $liteManageUrl   = PipsAccountFeature::manageUrl();

      $liteAccountHtml = '<p>ようこそ' . $esc(PipsSession::get("name")) . 'さん</p>';

      if ($liteManageUrl === "") {
        $liteAccountHtml .= '<p><a href="' . $liteProfileLink . '">プロフィール/Profile</a></p>';
        $liteAccountHtml .= '<p>' . $esc(PipsAccountFeature::unavailableReason()) . '</p>';
      } else {
        $liteAccountHtml .= '<p><a href="' . $liteProfileLink . '">プロフィール/Profile</a> | <a href="' . $esc($liteManageUrl) . '">編集/Edit</a></p>';
      }

      $liteAccountHtml .= '<form method="post"><input type="hidden" name="server_token" value="' . $esc(PipsSession::get("server_token")) . '" /><input type="hidden" name="account_action" value="logout" /><button type="submit">ログアウト/Logout</button></form>';
    } else if (PipsAccountFeature::ready()) {
      $liteAccountHtml = '<p><a href="' . $esc(PipsAccountFeature::loginUrl()) . '">ログイン/Login</a> | <a href="' . $esc(PipsAccountFeature::createUrl()) . '">プシューメイキィ作成/Create account</a></p>';
    } else {
      // 押しても行き止まりになるリンクは出さず、理由だけを書きます。
      $liteAccountHtml = '<p>' . $esc(PipsAccountFeature::unavailableReason()) . '</p>';
    }

    // --- 返信中かどうかの案内 -------------------------------------------
    $liteReplySubject = $esc(PipsSession::get("reply_submit", ""));
    $liteServerToken  = $esc(PipsSession::get("server_token", ""));

    if (!empty(PipsSession::get("reply_id"))) {
      $liteReplyNoticeHtml = '
    <p>「' . $liteReplySubject . '」への返信として投稿します。/Replying to the above thread.
    <form method="post" style="display:inline;"><input type="hidden" name="server_token" value="' . $liteServerToken . '" /><button type="submit" name="reply_cancel">返信をやめる/Cancel reply</button></form>
    </p>';
    } else {
      $liteReplyNoticeHtml = "";
    }

    // --- お名前欄(ログイン中は変えられないようにします) -------------------
    if (PipsAccountFeature::isLoggedIn()) {
      $liteNameFieldHtml = '<input type="hidden" name="name" value="' . $esc(PipsSession::get("name")) . '" />' . $esc(PipsSession::get("name"));
    } else {
      $liteNameFieldHtml = '<input type="text" name="name" placeholder="User Name" />';
    }

    // --- 検索フォームが引き継ぐ、今のGETパラメータ ------------------------
    // 検索欄そのものが持っている項目($liteExcludeParams)は、フォームが改めて送るので
    // ここでは引き継ぎません。二重に送ると後の値で上書きされ、検索条件が壊れます。
    $liteCarryHtml = "";

    foreach ($_GET as $liteKey => $liteValue) {
      if (in_array($liteKey, $liteExcludeParams, true) || is_array($liteValue)) {
        continue;
      }
      $liteCarryHtml .= '<input type="hidden" name="' . $esc($liteKey) . '" value="' . $esc($liteValue) . '" />';
    }

    // --- 並び替えの選択肢 -------------------------------------------------
    $liteSortHtml = "";

    foreach ($liteSortOptions as $liteSortValue => $liteSortLabel) {
      $liteSelected = ($liteCurrentSort === $liteSortValue) ? " selected" : "";
      $liteSortHtml .= '<option value="' . $liteSortValue . '"' . $liteSelected . '>' . $liteSortLabel . '</option>';
    }

    // --- 検索欄のチェック状態 ---------------------------------------------
    $liteQuery         = $esc($_GET["q"] ?? "");
    $liteCheckText     = isset($_GET["search_text"])    ? "checked" : "";
    $liteCheckSubject  = isset($_GET["search_subject"]) ? "checked" : "";
    $liteCheckName     = isset($_GET["search_name"])    ? "checked" : "";
    // 「返信まで探すかどうか」は、上の3つ(どの項目を探すか)とは別の軸です。
    // HTML層でこのチェックボックスの直前に置いてある hidden の search_replies=0 は、
    // チェックを外した時にブラウザが何も送らない問題への対処です
    // (PipsDispData::searchSortParams()の解説を参照。JSは使いません)。
    // hiddenを消すと「外したのに外れない」という直しにくい壊れ方をします。
    $liteCheckReplies  = PipsDispData::includeRepliesChecked() ? "checked" : "";
    $liteUserId        = $esc(PipsSession::get("userid", ""));
    $liteReplyTo       = $esc(PipsSession::get("reply_id", ""));

    // --- ページ送り -------------------------------------------------------
    // 行き先が決まっていないものはリンクごと出しません(href="" は「このページ自身」を
    // 指すので、押しても同じページが読み直されるだけになります)。
    $litePagerParts = [];

    if (!empty($prevPageUrl)) {
      $litePagerParts[] = '<a href="' . $esc($prevPageUrl) . '">← 前へ/Previous</a>';
    }
    if (!empty($nextPageUrl)) {
      $litePagerParts[] = '<a href="' . $esc($nextPageUrl) . '">次へ/Next →</a>';
    }

    $litePagerHtml = implode(" | ", $litePagerParts);

    $liteTitle       = $esc($title);
    $liteDescription = $esc($description);
    $liteVersion     = $esc($version);
    $html = '
      <!DOCTYPE html>
      <html lang="ja">
        <head>
          <meta charset="UTF-8" />
          <title>' . $liteTitle . '</title>
          <meta name="description" content="' . $liteDescription . '" />
          <meta name="viewport" content="width=device-width, initial-scale=1" />
          ' . $liteRobotsHtml . '
          <script src="' . pipsAssetUrl("./assets/scripts/lite_script.js") . '" rel="script/javascript"></script>
        </head>
        <body>
          <h1>プシューIPS/PusyuuIPS（軽量版/Lite version）</h1>
          <form method="post"><input type="hidden" name="server_token" value="' . $liteServerToken . '" /><button type="submit" name="liteMode_button" value="' . PIPS_LITE_OFF . '">フル版で見る/View full version</button></form>
          ' . $liteMessagesHtml . '
          <hr />
          <h2>プシューメイキィ/Account</h2>
          ' . $liteAccountHtml . '
          <hr />
          <h2 id="modal-1">投稿する/Write a post</h2>
          ' . $liteReplyNoticeHtml . '
          <form method="POST" enctype="multipart/form-data">
            <p><label>件名/Subject:<br /><input type="text" name="subject" value="' . $liteReplySubject . '" /></label></p>
            <p><label>お名前/Name:<br />' . $liteNameFieldHtml . '</label></p>
            <p><label>本文/Comment:<br /><textarea name="text" rows="6" cols="40"></textarea></label></p>
            <p><label>画像・動画（任意/optional）:<br /><input type="file" name="media[]" accept=".mp4,.jpg,.png" multiple /></label></p>
            <p><label><input type="checkbox" name="sensitive" /> センシティブな投稿/Sensitive post</label></p>
            <p><label><input type="checkbox" name="no_convert_links" /> URLをリンク化しない/Do not convert URLs into links</label></p>
            <input type="hidden" name="server_token" value="' . $liteServerToken . '" />
            <input type="hidden" name="user_id" value="' . $liteUserId . '" />
            <input type="hidden" name="reply_to" value="' . $liteReplyTo . '" />
            <button type="submit" name="postSend">送信/Send</button>
          </form>
          <hr />
          <h2>検索・並び替え/Search &amp; Sort</h2>
          <form method="get">
            ' . $liteCarryHtml . '
            <p><label>キーワード/Keyword:<input type="text" name="q" value="' . $liteQuery . '" /></label></p>
            <p>
              <label><input type="checkbox" name="search_text" value="1" ' . $liteCheckText . ' /> 本文/Text</label>
              <label><input type="checkbox" name="search_subject" value="1" ' . $liteCheckSubject . ' /> 件名/Subject</label>
              <label><input type="checkbox" name="search_name" value="1" ' . $liteCheckName . ' /> 投稿者名/Name</label>
            </p>
            <p>
              <input type="hidden" name="search_replies" value="0" />
              <label><input type="checkbox" name="search_replies" value="1" ' . $liteCheckReplies . ' /> 返信も探す/Include replies</label>
            </p>
            <p><label>並び替え/Sort:
              <select name="sort">' . $liteSortHtml . '</select>
            </label></p>
            <button type="submit">検索/Search</button>
          </form>
          <hr />
          <h2>投稿一覧/Posts</h2>
          ' . $postDisplay . '
          <p>' . $litePagerHtml . '</p>
          <hr />
          <p>' . $liteVersion . '</p>
        </body>
      </html>
    ';

  // ----------------------------------------------------------------
  // 通常版。どの枝にも当たらなかった場合はここです(必ずどれかに当たります)。
  // ----------------------------------------------------------------
  } else {
    PipsDispData::run();

    // usersSaveContent()が使うアカウント情報(保存済みブックマーク)は、HTMLを組み立てる前に
    // 解決しておきます。HTMLの途中で呼ぶと、トークン失効時に
    // PipsAccountFeature::currentAccount() -> allResetSession() がセッションを作り直して
    // 新しいCookieを送ろうとしますが、出力の後では送れません。利用者の手元には古いCookieが
    // 残り、作り直したはずのセッションに次のページで入れません
    // (この関数は文字列を返すだけなので出力はまだですが、順序の約束は同じです)。
    $currentAccountForSavedContent = (PipsAccountFeature::isLoggedIn())
      ? PipsAccountFeature::currentAccount()
      : null;

    // ================================================================
    // 投稿表示欄(.bbs_content)に入れる中身。
    //
    // 以前はここが二股でした。「JS無しの人がローディング画面を手で閉じたか」で分かれ、
    // そうでなければカエルの文言だけを出してJSが ?getDispPost で取りに来るのを待つ、という
    // 作りです。投稿をHTMLに入れないのはページを軽く保つためでしたが、2026-09にdata.jsonを
    // 実測したところ本文は中央値177バイト・p95で1KB程度しかなく、重かったのは本文ではなく
    // base64で直埋めしていたメディア(65件で合計29.7MB)のほうでした。メディアをURL参照に
    // 変えた今(PipsMedia参照)、同じ投稿群がHTMLへ入れる量は合計13KBまで落ちています。
    // 投稿を出し惜しむ理由が無くなったので、常にサーバが入れて返します。
    //
    // これで、人間・JSの無い環境・検索エンジン・生成AIのすべてが同じHTMLを受け取ります。
    // 共有ボタンが配るリンク(?id_one_post=)を渡された相手に本文が見えるのもこのおかげです。
    //
    // 【JS側との約束】投稿が最初から入っていることは、下の.bbs_contentに付ける
    // data-prerendered="1" でJSへ伝えます。getDispPost()はこの印を見て初回の取り直しを
    // 省きます。印を外すと、JSが表示欄をいったん空にしてから同じ物を取り直すため、
    // 目に見えるちらつきと無駄な往復が戻ってきます。
    // ================================================================
    $dispPips = $postDisplay;

    // ================================================================
    // 通常版のHTMLへ差し込む断片を、ここで全部決めます。
    //
    // 【HTMLの中へ判断を持ち込まないこと】$dispPipsと同じ考え方です。
    // 「出す／出さない」「どちらを出す」が変わる箇所は、この層で1つの変数にしてから
    // 下のHTMLへ流し込みます。ifや { } をPHPの開始・終了タグで細切れにしてタグの間へ
    // 挟むと、どこからどこまでがHTMLの構造なのかが見た目から読み取れなくなります。
    // 下のHTML層に出てくるPHPは、決まった変数を差し込む短縮echoだけにしてください。
    // ================================================================

    $pipsEsc = static function ($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    };

    // --- 検索モーダルの並び替えの選択肢 -------------------------------
    $pipsSortOptions = [
      "date_desc"    => "投稿日時が新しい順/Newest first",
      "date_asc"     => "投稿日時が古い順/Oldest first",
      "replies_desc" => "返信が多い順/Most replies",
      "replies_asc"  => "返信が少ない順/Fewest replies",
      "views_desc"   => "閲覧数が多い順/Most viewed",
      "views_asc"    => "閲覧数が少ない順/Least viewed",
    ];
    $pipsCurrentSort     = $_GET["sort"] ?? "date_desc";
    $pipsSortOptionsHtml = "";

    foreach ($pipsSortOptions as $pipsSortValue => $pipsSortLabel) {
      $pipsSelected = ($pipsCurrentSort === $pipsSortValue) ? " selected" : "";
      $pipsSortOptionsHtml .= '<option value="' . $pipsSortValue . '"' . $pipsSelected . '>' . $pipsSortLabel . '</option>';
    }

    // --- 「検索・並び替え条件をクリア」のリンク ------------------------
    // 既定のまま(何も絞り込んでいない)ときに出しても、押して変わるものがありません。
    if (($_GET["q"] ?? "") !== "" || $pipsCurrentSort !== "date_desc") {
      $pipsClearSearchHtml = '<a class="button" href="./">検索・並び替え条件をクリア/Clear search &amp; sort</a>';
    } else {
      $pipsClearSearchHtml = "";
    }

    // --- ページ送りの矢印ボタン ---------------------------------------
    // 【行き先が無いときはボタンごと出さないこと】
    // 以前はここが無条件でリンクを描いていたため、行き先が決まっていない場面では
    // href が空のリンクが残っていました。空のhrefは「このページ自身」を指すので、
    // 押すと同じページが読み直されます。利用者から見ると、進んだのか進んでいないのか
    // 分からない一番困る反応です。軽量版は最初から隠しており、こちらだけが
    // 揃っていませんでした。
    //
    // JSが動く環境ではonclickのnextPage()/prevPage()が使われてhrefは読まれませんが、
    // それでも消してよいです。ここが空になるのは「本当に次(前)が無いページ」だけなので、
    // JS側にも行かせる先はありません。
    if ($nextPageUrl !== "") {
      $pipsNextButtonHtml = '<a class="top-right_button" title="次へ/next" href="' . $pipsEsc($nextPageUrl)
        . '" onclick="nextPage(); return false;"><img class="image_iconsize" width="auto" height="auto" src="./assets/images/mark_arrow_right.png" alt="button" oncontextmenu="return false;" onselectstart="return false;" onmousedown="return false;"></img></a>';
    } else {
      $pipsNextButtonHtml = "";
    }

    if ($prevPageUrl !== "") {
      $pipsPrevButtonHtml = '<a class="top-left_button" title="戻る/previous" href="' . $pipsEsc($prevPageUrl)
        . '" onclick="prevPage(); return false;"><img class="image_iconsize" width="auto" height="auto" src="./assets/images/mark_arrow_left.png" alt="button" oncontextmenu="return false;" onselectstart="return false;" onmousedown="return false;"></img></a>';
    } else {
      $pipsPrevButtonHtml = "";
    }

    // --- プッシュ通知の設定スクリプト ----------------------------------
    // ログインしていない人には購読させようがないので、まるごと出しません。
    //
    // 【必ず #pips-notification-settings より後ろで読み込むこと】push.js は
    // 読み込まれたその場で config.mountSelector を querySelector で引き、
    // 見つからなければ黙って終わります。<head>に置くとその時点では<body>が
    // まだ解析されていないため必ず見つからず、購読ボタンも失敗メッセージも
    // 一切出ません。画面には(PHPが常に描画している)RSSの案内だけが残り、
    // JSエラーも出ないので「プッシュ通知の選択肢が消えた」ように見えます。
    // 以前このファイルで実際に起きた症状です。p-chatも同じ理由で通知系の
    // スクリプトを</body>の直前にまとめています。
    // この変数を差し込む場所を動かすときは、必ずこの順序を守ってください。
    if (PipsPush::actorId() === null) {
      $pipsPushScriptHtml = "";
    } else {
      // 通知API側の見え方。push.js はこれで「押す前から断られると分かっている」
      // (pushチャンネルがdeny)や「ブラウザは購読済みなのにサーバに1台も無い」を
      // 見分けます。取れなかった時は null で渡し、push.js は判断材料なしとして扱います
      // (「分からない」を deny や 0台 と同じにすると、APIが一瞬落ちただけで表示が狂います)。
      $pipsPushServerView = PipsPush::serverView((string)PipsPush::actorId());
      $pipsPushConfigJson = json_encode([
        "mountSelector" => "#pips-notification-settings",
        "pushChannel"   => isset($pipsPushServerView['states']['push']) ? (string)$pipsPushServerView['states']['push'] : null,
        "serverSubscriptions" => $pipsPushServerView['subscriptions'],
        "vapidKey"      => PipsPush::vapidPublicKey(),
        "token"         => PipsSession::get("server_token", ""),
        "swUrl"         => "./?push_sw=1",
        "swScope"       => "/",
        "postUrl"       => "./",
      ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

      $pipsPushScriptHtml = '
        <script>
          // プッシュ通知の購読に必要な値。#pips-notification-settings の中身は
          // push.js が丸ごと組み立てます(JSが無い環境ではそこは空のまま)。
          window.PipsNotifyConfig = ' . $pipsPushConfigJson . ';
        </script>
        <script src="' . pipsSiteBase('main') . '/pusyuusystem/scripts/push-subscribe.js"></script>
        <script src="' . pipsAssetUrl("./assets/scripts/push.js") . '"></script>';
    }

    // --- フッターの表記(共有スクリプトが無い環境では空) -----------------
    if (function_exists('notationDisp')) {
      $pipsNotationHtml  = notationDisp("notation");
      $pipsCopyrightHtml = notationDisp("copyright");
    } else {
      $pipsNotationHtml  = "";
      $pipsCopyrightHtml = "";
    }

    $pipsUserAgentHtml = $pipsEsc($_SERVER["HTTP_USER_AGENT"] ?? "");

    // --- 書き込みフォームが持つ値 --------------------------------------
    $pipsFormAction      = $pipsEsc($_SERVER["PHP_SELF"]);
    $pipsServerToken     = $pipsEsc(PipsSession::get("server_token", ""));
    $pipsUserId          = $pipsEsc(PipsSession::get("userid", ""));
    $pipsReplyTo         = $pipsEsc(PipsSession::get("reply_id", ""));

    // 書きかけはサーバが預かっています（pipsDraft()参照）。
    // 返信ボタンを押したときは、その相手の件名を入れます。
    //
    // 【$composerDraft はこのPHP層で先に決めること】下のHTML層の途中で決める形にすると、
    // ここのチェック状態($pipsCheckSensitive等)のほうが先に評価され、未定義の値を読んで
    // 「保存したはずのチェックが毎回外れている」という壊れ方をします。
    // PHP層とHTML層を分けた以上、HTML層で新しい値を作らないでください。
    $composerDraft   = pipsDraft();
    $composerSubject = $composerDraft["subject"] !== ""
      ? $composerDraft["subject"]
      : (string)(PipsSession::get("reply_submit", ""));

    $pipsCheckSensitive  = $composerDraft["sensitive"] ? " checked" : "";
    $pipsCheckNoConvert  = $composerDraft["no_convert_links"] ? " checked" : "";

    // 今から何を投稿するのか（新規か、誰への返信か）を書く前に見せるカードです。
    // 中身はPHPが作ります。JSは返信ボタンが押されたときにサーバへ知らせ、
    // 返ってきたこのHTMLを差し替えるだけです（PipsPostTemplate::composerHeader()参照）。
    $pipsComposerHeadHtml = '<div id="composer_head_slot">' . PipsPostTemplate::composerHeader() . '</div>';

    // お名前欄。ログイン中は名乗りを変えられないようにします。
    if (PipsSession::has("user_logged_in") && PipsSession::get("user_logged_in") == true) {
      $pipsComposerNameHtml = '<input type="hidden" name="name" value="' . $pipsEsc(PipsSession::get("name")) . '" />';
    } else {
      $pipsComposerNameHtml = '<input type="text" name="name" placeholder="User Name" />';
    }

    // 【下書きの保存はJSが無くても押せること】保存の本体はフォームのボタンです。JSがある場合も
    // 同じ受け口(draft_save)を呼ぶだけで、保存される場所も形式も同じです。
    // 保存された時刻をそのまま出しているので、押していなければ時刻も増えません
    // （自動保存しているように見せかけない、という約束です）。
    // 添付だけは復元できません（ブラウザがファイル欄への代入を許さないため）。
    if ($composerDraft["saved_at"] === "") {
      $pipsDraftStateHtml = '<p class="draft_state" id="draft_state">下書きはまだ保存されていません。／No draft saved yet.</p>';
    } else {
      $pipsDraftSavedAt   = $pipsEsc($composerDraft["saved_at"]);
      $pipsDraftStateHtml = '<p class="draft_state" id="draft_state">下書きを ' . $pipsDraftSavedAt
        . ' に保存しました（画像・動画は選び直しが必要です）／Draft saved at ' . $pipsDraftSavedAt . '</p>';
    }

    // 編集・削除モーダルの中身。どちらもPHPが作り、JSは受け取って差し替えるだけです。
    // 対象が選ばれていない場合は、押せるボタン自体が出ません。
    $pipsEditModalHtml   = '<div class="form_style-1" id="edit_modal_slot">' . PipsPostTemplate::editModalBody() . '</div>';
    $pipsDeleteModalHtml = '<div class="form_style-1" id="delete_modal_slot">' . PipsPostTemplate::deleteModalBody() . '</div>';
    $pipsStampModalHtml  = '<div id="stamp_modal_slot">' . PipsPostTemplate::stampModalBody() . '</div>';

    // --- 検索欄のチェック状態 ------------------------------------------
    $pipsQuery          = $pipsEsc($_GET["q"] ?? "");
    $pipsCheckText      = isset($_GET["search_text"])    ? "checked" : "";
    $pipsCheckSubject   = isset($_GET["search_subject"]) ? "checked" : "";
    $pipsCheckName      = isset($_GET["search_name"])    ? "checked" : "";
    // 「返信まで探すかどうか」は、上の3つ(どの項目を探すか)とは別の軸です。
    // HTML層でこのチェックボックスの直前に置いてある hidden の search_replies=0 は、
    // チェックを外した時にブラウザが何も送らない問題への対処です
    // (PipsDispData::searchSortParams()の解説を参照。JSは使いません)。
    // hiddenを消すと「外したのに外れない」という直しにくい壊れ方をします。
    $pipsCheckReplies   = PipsDispData::includeRepliesChecked() ? "checked" : "";

    // --- 本文以外の組み立て済みHTML ------------------------------------
    // 【ここで先に済ませる理由】usersSaveContent()はアカウント基盤へ問い合わせることが
    // あります。HTMLの途中で呼ぶと、その時点でヘッダ送信が終わっているため、内部で
    // セッションを開き直す経路に入った瞬間に警告が出ます($currentAccountForSavedContent を
    // 先に解決してあるのと同じ理由です)。
    $pipsSavedContentHtml = usersSaveContent($currentAccountForSavedContent);
    $pipsProductLinksHtml = productLinks();
    // 周年の数字は同じ物を2箇所(日本語と英語)に出します。2回呼ぶと、日付をまたぐ瞬間に
    // 前半と後半で違う数字が出ることがあるので、1回だけ数えて使い回します。
    $pipsAnniversary      = pipsBarsDayCounter();


    // --- robots と canonical -------------------------------------
    // ページ自体はインデックスさせたいが、投稿メディア(直リンクURL)はog:image用のもの以外
    // Google画像検索等に拾わせたくないため、noindexにしない場合はnoimageindexを付けます。
    // noimageindexはページのインデックスや、og:imageをOGP用クローラーが取得すること自体は妨げません。
    if ($isNoIndex === true) {
      $pipsRobotsHtml = '<meta name="robots" content="noindex,nofollow" />';
    } else {
      $pipsRobotsHtml = '<meta name="robots" content="index,follow,noimageindex" />';
    }

    if ($isRegularPage === true) {
      $pipsRobotsHtml .= '<link rel="canonical" href="' . htmlspecialchars(p_getUrl(), ENT_QUOTES, "UTF-8") . '" />';
    }

    // --- 「もっと多くの機能」モーダルのアカウント欄 ---------------
    // このモーダルはCSSだけで開閉します(.modal + :target)。中身の出し分けも
    // ここでPHPが決めているので、JavaScriptが無くてもそのまま読めます。
    $pipsAccountModalHtml = "";

    if (PipsAccountFeature::isLoggedIn()) {
      $modalManageUrl = PipsAccountFeature::manageUrl();

      $pipsAccountModalHtml = '
        <p>ようこそ' . PipsSession::get("name") . 'さん</p>
        <p>プロフィールを見ますか？</p>
        <a href="./?@=' . urlencode(PipsSession::get("username")) . '#mc" class="button">プロフィール</a>
      ';

      if ($modalManageUrl === "") {
        $pipsAccountModalHtml .= '<p>' . $pipsEsc(PipsAccountFeature::unavailableReason()) . '</p>';
      } else {
        $pipsAccountModalHtml .= '
          <p>プシューメイキィの管理をしますか？</p>
          <a href="' . $pipsEsc($modalManageUrl) . '" class="button">プシューメイキィの管理</a>
        ';
      }

      $pipsAccountModalHtml .= '
        <p>ログアウトしますか？</p>
        <form method="post">
          <input type="hidden" name="account_action" value="logout" />
          <input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" />
          <button name="logout">ログアウト</button>
        </form>
      ';
    } else if (PipsAccountFeature::ready()) {
      $pipsAccountModalHtml = '<a href="' . $pipsEsc(PipsAccountFeature::loginUrl()) . '" class="button">ログイン</a>';
    } else {
      // 押しても行き止まりになるリンクは出さず、理由だけを書きます。
      $pipsAccountModalHtml = '<p>' . $pipsEsc(PipsAccountFeature::unavailableReason()) . '</p>';
    }

    // --- 通知(新しいスレッドのお知らせ) ---------------------------
    // 【受け取り方は2つあり、どちらを使うかは見た人が選びます】
    // プッシュ通知(この端末のブラウザへ直接届く)と、RSS(お使いのRSSリーダーで
    // 受け取る)です。両方使っても構いません。
    //
    // 【ここに常時あるのはRSSの入口だけ】プッシュ通知の切り替えはJSが無いと
    // 成立しない(購読・許可の操作そのものがJS前提)ので、見出し
    // 「🔔 プッシュ通知で受け取る」を含めて #pips-notification-settings の中身を
    // JSが丸ごと組み立てます。JSが無い環境ではそこは見出しごと現れません。
    // 一方RSSは、発行も表示もJSを必要としないただのフォーム送信なので、
    // PHPが常に描画します。JSで作れるものをJSで隠さない、という分け方です。
    //
    // 【ログインが要る理由】通知の宛先はログイン中アカウントのuseridです。
    // 未ログインだと誰宛てか決まらないため、購読自体ができません。
    $pipsNotifyModalHtml = "";

    if (PipsPush::actorId() !== null) {
      $pipsNotifyModalHtml = '<h3>通知/Notification</h3>'
        . '<p>新しいスレッドが立った時にお知らせします。受け取りたい人だけが登録する形なので、登録しなければ何も届きません。</p>'
        . '<p>受け取り方はプッシュ通知とRSSの2つがあり、どちらか片方でも両方でも構いません。/You can receive it by push notification, by RSS, or by both.</p>'
        . '<div id="pips-notification-settings"></div>'
        . '
          <h4>📰 RSSで受け取る</h4>
          <p>プッシュ通知に対応していない環境(ホーム画面に追加していないiPhoneや、アプリ内ブラウザなど)でも、お使いのRSSリーダーで受け取れます。</p>
          <form method="post" action="./">
            <input type="hidden" name="server_token" value="' . $pipsEsc(PipsSession::get("server_token", "")) . '" />
            <input type="hidden" name="notify_settings" value="1" />
            <button type="submit">RSSの設定を開く</button>
          </form>
        ';
    } else if (PipsAccountFeature::ready()) {
      $pipsNotifyModalHtml = '<h3>通知/Notification</h3>'
        . '<p>新しいスレッドのお知らせを受け取るには、ログインが必要です(誰宛ての通知かを決めるため)。</p>';
    }

    // --- 検索モーダルが引き継ぐ、今のGETパラメータ ----------------
    // id / at 等、検索・ソート以外の現在のGETパラメータはhiddenで引き継ぎます
    // (pageは検索し直すので引き継ぎません)。
    // フォームのaction末尾の"#mc"は、送信後にモーダルを閉じるためのものです
    // (#mcはどのmodalのidとも一致しないため、.modal:target のCSSルールにより
    //  どのモーダルも表示されなくなります)。
    $searchModalExcludeParams = ["q", "search_text", "search_subject", "search_name", "search_replies", "sort", "page"];
    $pipsSearchCarryHtml = "";

    foreach ($_GET as $searchModalKey => $searchModalValue) {
      if (in_array($searchModalKey, $searchModalExcludeParams, true) || is_array($searchModalValue)) {
        continue;
      }
      $pipsSearchCarryHtml .= '<input type="hidden" name="' . $pipsEsc($searchModalKey) . '" value="' . $pipsEsc($searchModalValue) . '" />';
    }

    // --- 処理結果のメッセージ -------------------------------------
    $pipsCompleteMsgHtml = "";

    if (PipsSession::has("post_complete_msg") && empty(PipsSession::get("post_complete_msg")) === false) {
      foreach (PipsSession::get("post_complete_msg") as $post_compleate_msg) {
        // メッセージを作っているのは利用者ではなくこのファイル自身で、
        // 中にリンクが入ることがあるため、ここはエスケープしません。
        $pipsCompleteMsgHtml .= '<p>' . $post_compleate_msg . '</p>';
      }
      PipsSession::set("dispDoneProsessComleteFulg", true);
      $pipsCompleteMsgHtml .= '<a class="button" href="./?ClearMSG=true" class="clear_msg">メッセージを消す/Clear message</a>';
    }

    // ------------------------------------------------------------
    // ここから下がHTML層です。上で決まった変数を差し込むだけにしてください。
    // 軽量版・時計と同じく、組み立てた物を $html に入れて最後に返します。
    // ------------------------------------------------------------
    $html = '
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <title>' . $title . '</title>
    <meta content="width=device-width minimum-scale=1.0 maximum-scale=6.0 user-scalable=yes" name="viewport" />
    <meta name="description" content="' . $description . '" />
    <meta name="viewport" content="width=device-width, user-scalable=yes, maximum-scale=6.0, minimum-scale=1.0" />
    <meta name="copyright" content="© 2020-2026 created by PusyuuWanko/" />
    <meta name="keywords" content="プシューサービス,PIPS,プシューIPS,PusyuuIPS,プシュー,Pusyuu,PusyuuWanko/,プシューわんこ/,掲示板,感想を投稿するサイト,自由な投稿,PushyuuService,PIPS,PusyuuIPS,PusyuuIPS,Pusyuu,Bulletin Board,Site to post impressions,Free Submission" />
    <meta property="og:title" content="' . $title . '" />
    <meta property="og:site_name" content="' . $title . '" />
    <meta property="og:description" content="' . $description . '" />
    <meta property="og:image" content="' . $image . '" />
    <meta property="og:type" content="application" />
' . $pipsRobotsHtml . '
    <link rel="manifest" href="./assets/documents/manifest.json" />
    <link rel="shortcut icon" href="' . pipsSiteBase('main') . '/pusyuusystem/images/favicon.ico" />
    <link href="' . pipsAssetUrl("./assets/styles/style.css") . '" rel="stylesheet" />
    <script src="' . pipsAssetUrl("./assets/scripts/normal_script.js") . '" rel="script/javascript"></script>
    <script src="' . pipsSiteBase('main') . '/pusyuusystem/scripts/internet_checker.js"></script>
    <script src="' . pipsSiteBase('main') . '/pusyuusystem/scripts/touch-device-tool-tip.js"></script>
    <script src="' . pipsSiteBase('main') . '/pusyuusystem/scripts/pusyuuWallpaper.js"></script>
    <!-- privacy_notice.js の script タグより前に置く -->
    <script>
      window.PusyuuPrivacyNoticeConfig = {
        // ---- 機能1(初回アクセス時の自動お知らせ)のカスタマイズ ----
        title: "プライバシーに関するお知らせ",
        // bodyHtml: "<p>...</p>", // 本文を丸ごと差し替えたい場合のみ指定(指定すると下のserviceNameは使われなくなる)
        serviceName: "プシューサービス", // 省略時は特定サービス名を出さず一般論の文章になる
        fundingNote:
          \'広告についての方針は<a href="' . pipsSiteBase('main') . '/rules" \' +
          \'target="_blank">ルールズ</a>をご覧ください。\',
        closeButtonText: "同意する",
        hostsIntroText: "このページ読み込み時に検出した外部の通信先：",
        hostsNoneText: "このページ読み込み時には、外部への通信は検出されませんでした。",
        storageKey: "pusyuu_privacy_notice_dismissed", // 表示済みフラグの保存キー名

        // ---- "js-dependency"プリセット(機能2)のカスタマイズ ----
        jsDependency: {
          level: "low",
          brokenFeatures: [
            "ポストの共有機能",
            "ページ読み込み時の演出の閉じる機能",
            "壁紙変更や＋ボタン内にある再読み込み機能"
          ],
          fallbackNote:
            \'詳しい方針は<a href="' . pipsSiteBase('main') . '/rules/javascript/" \' +
            \'target="_blank">JavaScriptの取り扱いに関する方針</a>をご覧ください。\'
        },

        triggerCloseButtonText: "閉じる" // 機能2のモーダルの閉じるボタン文言
      };
    </script>
    <script src="' . pipsSiteBase('main') . '/pusyuusystem/scripts/privacy_notice.js"></script>
    <script src="' . pipsSiteBase('main') . '/pusyuusystem/scripts/request-indicator.js" data-include-async="false"></script>
    '
          // プッシュ通知まわりのスクリプトは、ここではなく</body>の直前に置いてあります。
          // 理由はそちらのコメントに書いてあります。
    . '
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4960471482152873" crossorigin="anonymous"></script>
    <!--
        *----------------------------------
        |  ' . $version . '      |
        |  © 2021-2026 By ISAMI ABE     |
        |  LastUpdate: 2026-08-10       |
        |  License: MIT License         |
        |  PusyuuIPS....                |
      ----------------------------------*
    -->
  </head>
  <body>
    '
          // 【ここにあったローディング画面(id="loading")は廃止しました】
          // 投稿が最初からHTMLに入るようになり、JSの応答を待つ間だけ画面全体を覆う理由が無くなった
          // ためです。中に入っていた「手動でローディング画面を閉じる」フォームも一緒に消えています
          // （JSが無い人も、もう何も押さずに投稿が読めます）。
          //
          // 戻す時はJS側も一緒に見てください。normal_script.jsのmodalStopper()は
          // document.getElementById("loading") が**必ず在る**前提で書かれていた時期があり、
          // 無い状態のまま放置するとgetComputedStyle(null)で毎回例外になり、
          // モーダルを開いた時の背面スクロール止めごと動かなくなります(現在はnull時に素通りします)。
    . '
    <header class="header">
      <h1>プシューIPS/PusyuuIPS</h1>
      <p>投稿はすべて”PusyuuImpressionsPostService”のサービス名通り、感想を投稿して共有できるサービスであり、信憑性や信頼性の低い内容を見て投稿して楽しむサービスです、内容を過信せずこのような考えもあるんだなー程度で楽しみましょう、そしてそれがこのサービスの目的です！！このサービスの具体的な使い方については＋ボタンを押しヘルプをご参照ください。<span>お約束：@匿名のユーザー名は飽くまでも仮のものであり誰でも同じ名前を使用することが可能でありその個人へのメッセージのやり取りは大変危険ですので行う際は別の手段を講じてください。</span></p>
      <p style="margin: 0px;" id="noJavaScriptMsg">JavaScriptが有効ではありません、一部機能が機能しない可能性がございますが、基本的になくても機能するように心がけておりますのでご安心ください。</p>
    </header>
    <main class="main">
      <div class="modal" id="modal-1">
        <div>
          <a href="#mc"></a>
          <div>
            <span>書き込み画面/Writing screen</span>
            <div>
              <div class="form_style-1">
                <form method="POST" action="' . $pipsFormAction . '" enctype="multipart/form-data">
                  ' . $pipsComposerHeadHtml . '
                  <input type="text" name="subject" id="subject" value="' . $composerSubject . '" placeholder="Thread Name" />
                  ' . $pipsComposerNameHtml . '
                  <textarea name="text" id="text" placeholder="Comment">' . $composerDraft["text"] . '</textarea>
                  <input type="file" name="media[]" id="media" accept=".mp4,.jpg,.png" multiple />
                  <input type="hidden" name="datetime" id="datetime" />
                  <div style="display: flex; justify-content: center;"><label style="color: #fff; margin-right: 10px;">センシティブな投稿/Sensitive post</label><input type="checkbox" name="sensitive"' . $pipsCheckSensitive . '></div>
                  <div style="display: flex; justify-content: center;"><label style="color: #fff; margin-right: 10px;">URLをリンク化しない/Do not convert URLs into links</label><input type="checkbox" name="no_convert_links"' . $pipsCheckNoConvert . '></div>
                  ' . $pipsDraftStateHtml . '
                  <div class="draft_buttons">
                    <button type="submit" name="draft_save" value="1" formnovalidate>下書きを保存／Save draft</button>
                    <button type="submit" name="draft_save" value="1" formnovalidate onclick="document.getElementById(\'draft_save_close\').value=\'1\';">保存して閉じる／Save and close</button>
                    <input type="hidden" name="draft_save_close" id="draft_save_close" value="" />
                  </div>
                  <input type="hidden" name="server_token" value="' . $pipsServerToken . '" />
                  <input type="hidden" name="user_id" value="' . $pipsUserId . '" />
                  <input type="hidden" name="reply_to" id="reply_to" value="' . $pipsReplyTo . '" />
                  <button onclick="location.hash=\'#sent\'" type="submit" name="postSend">送信/Sent</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal" id="modal-edit">
        <div>
          <a href="#mc"></a>
          <div>
            <span>投稿の編集/Edit post</span>
            <div>
              ' . $pipsEditModalHtml . '
            </div>
          </div>
        </div>
      </div>
      <div class="modal" id="modal-delete">
        <div>
          <a href="#mc"></a>
          <div>
            <span>投稿の削除/Delete post</span>
            <div>
              ' . $pipsDeleteModalHtml . '
            </div>
          </div>
        </div>
      </div>
      <div class="modal" id="modal-stamp">
        <div>
          <a href="#mc"></a>
          <div>
            <span>感想スタンプ/Reaction stamps</span>
            <div>
              ' . $pipsStampModalHtml . '
            </div>
          </div>
        </div>
      </div>
      <div class="modal" id="modal-2">
        <div>
          <a href="#mc"></a>
          <div>
            <span>もっと多くの機能/more features</span>
            <div>
              <h3>ログインボタン</h3>
' . $pipsAccountModalHtml . '
' . $pipsNotifyModalHtml . '
              <h3>ヘルプボタン/Help Button</h3>
              <a class="button" style="cursor: help;" href="#modal-3">ヘルプ/Help</a>
              <h3>自分のコンテンツボタン/My own content button</h3>
              <a class="button" href="#modal-4">自分のコンテンツ/My content</a>
              <h3>検索・並び替え/Search & Sort</h3>
              <a class="button" href="#modal-6">検索/Search</a>
              <h3>システムの再起動/Syatem Reboot</h3>
              <button href="#reload" id="reyomikomi">再読込/Reload</button>
              <h3>タップ音の有無/Presence of tap sound</h3>
              <input type="checkbox" switch id="tapsoundswitch">
              <h3>壁紙の変更/change wallpaper</h3>
              <p>※ブラウザキャッシュを削除すると設定が初期化されるのでご注意ください。/*Please note that deleting your browser cache will initialize the settings.</p>
              <h5>あなたの好きな画像を壁紙を追加↓/Add your favorite image as wallpaper↓</h5>
              <pusyuuWallpaper service-name="pusyuuBBS">現在JavaScriptが古いか無効になっているためプシュー壁紙を読み込めませんでした。</pusyuuWallpaper>
              <h3>フィードバックやお問い合わせ/Feedback and inquiries</h3>
              <div class="form_style-1">
                <form method="post" name="form" onsubmit="return validate()" action="' . pipsSiteBase('main') . '/pusyuusystem/pages/contact/confirm.php">
                  <label>NAME<span style="color: #ff0000; padding-left: 5px;">必須</span></label>
                  <input type="text" name="name" placeholder="Your Name" value="">
                  <label>E-MAILE<span style="color: #00ff00; padding-left: 5px;">任意</span></label>
                  <input type="text" name="email" placeholder="Your Email" value="">
                  <input type="hidden" name="sex" value="なし" check>
                  <label>SELECT INQUIRY<span style="color: #ff0000; padding-left: 5px;">必須</span></label>
                  <select name="item">
                    <!--Safariではoptionタグをつけると余白が出る-->
                    <option value="">お問い合わせ項目を選択/Select inquiry item</option>
                    <option value="ご質問・お問い合わせ">ご質問・お問い合わせ/Questions・Inquiries</option>
                    <option value="ご意見・ご感想">ご意見・ご感想/Opinions・impressions</option>
                    <option value="投稿の削除願い">投稿の削除願い/Request for deletion of post</option>
                  </select>
                  <label>INQUIRYDETAIL<span style="color: #ff0000; padding-left: 5px;">必須</span></label>
                  <textarea name="content" placeholder="Contact of inquiry"></textarea>
                  <button onclick="location.href=\'#modal-2\'" type="submit" name="postSend">送信/Sent</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal" id="modal-3">
        <div>
          <a href="#mc"></a>
          <div>
            <span class="modal_header">ヘルプ画面/Help screen</span>
            <div>
              <h3>各種ボタンの説明/Explanation of the various buttons</h3>
              <p>pipsでは一番最初に表示される画面のことを、スレッド一覧または投稿一覧またはポスト一覧と表記します、そしてそれらに紐付いてる投稿を返信一覧またはポスト一覧または投稿一覧といいます。なぜこれらの呼び方をするかというと、使い方によって変わるためです。掲示板として使うならスレッドと返信、ブログとして使うなら投稿一覧またはポスト一覧、それ以外の使い方呼び方も可能です。それがこのサービスの長所でもあります。</p>
              <p>プシューIPSでは四つ角にボタンを配置しており、左上、左下、右上、右下、にボタンを設置しています。各種ボタンの説明↓/In Pushu IPS, buttons are arranged in the four corners, and buttons are placed in the upper left, lower left, upper right, and lower right. Explanation of various buttons ↓</p>
              <ol>
                <li>左上：進むボタン（過去のポストを見ることができます。）/Top left: Forward button (You can see past posts.) </li>
                <li>左下：書き込みボタン（新しいポストを作ることができます。）/Bottom left: Write button (You can create a new post. ）</li>
                <li>右上：戻るボタン（過去ポストから戻ることができます。）/Top right: Back button (You can go back from the past post.) </li>
                <li>右下：設定ボタン（設定やヘルプを見たりすることができます。。）/Bottom right: Settings button (You can make settings.) </li>
              </ol>
              <h3>ポスト一についてる各ボタンについて/About each button on the post</h3>
              <p>📨ボタン（ボタンを押すことによりポストのリンクをコピーまたは他のSNS等にシェアできます。）⤴ボタン（ボタンを押すことによりそのポストに対し返信を行うことができます。）🙅ボタン（ボタンを押すことによりポストを通報することができます。）❤（ボタンを押すことによりポストをお気に入りに追加でき、＋ボタン内の「自分のコンテンツ」にある「あなたのブックマーク」に保存されます。）😊（ボタンを押すことにより感想スタンプを押したり、届いたスタンプの数を見たりできます。1つの投稿に押せるのは1つで、選び直しと取り消しは同じ閲覧が続いている間だけできます。横の数字は届いたスタンプの合計です。）👀（ボタンを押すことにより、ポスト一覧「スレッド一覧」ではJavaScriptが無効になっていてもポストを閲覧することができます。またスレッド（投稿一覧、返信）欄では👁ボタンになっており、この場合は拡大かつ単品で投稿を表示できます。）/ 📨 Button (By pressing this button, you can copy the link to the post or share it on other social media platforms.) ⤴ Button (By pressing this button, you can reply to the post.) 🙅 Button (By pressing this button, you can report the post.) ❤ (By pressing this button, you can add the post to your favorites; it is saved in "Your bookmark" under "My content" inside the + button.) 😊 (By pressing this button, you can send a reaction stamp or view the stamps received. You can send one per post, and change or undo it only while the same visit continues. The number next to it is the total number of stamps received.)👀 (By pressing this button, you can view the post even if JavaScript is disabled in the post list or "thread list". In the thread view (post and replies), it appears as an 👁 button, which lets you expand and view the post individually.)</p>
              <h3>エラーの対処法について/What to do about the error</h3>
              <p>エラーの対処はとても多いいですし、あなたも対処法はわかりやすいほうが良いでしょう、そのためQ&Aを<a href="./lp#p2" tatget="_blank">プシューIPSの紹介ページ</a>に掲載していますのでそちらをご覧ください。/There are a lot of ways to deal with errors, and you should be able to understand how to deal with them, so please see the Q&A on the <a href="./lp#p2" tatget="_blank">introduction page of PusyuuIPS</a>.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="modal" id="modal-4">
        <div>
          <a href="#mc"></a>
          <div>
            <span>自分のコンテンツ/my own content</span>
            <div>
              <h3>あなたのブックマーク/Your bookmark</h3>
              <p>気になるスレッド（返信）をブックマークが表示されます。/Bookmarks will be displayed for threads (reply) that interest you.</p>
              <div class="center">
                <ol id="likeList">
                  ' . $pipsSavedContentHtml . '
                </ol>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal" id="modal-6">
        <div>
          <a href="#mc"></a>
          <div>
            <span>検索・並び替え/Search & Sort</span>
            <div>
              <div class="form_style-1">
                <form method="get" action="./#mc">
' . $pipsSearchCarryHtml . '
                  <label>検索キーワード/Keyword</label>
                  <input type="text" name="q" value="' . $pipsQuery . '" placeholder="検索キーワード/Search keyword" />
                  <br>
                  <div style="display: flex; justify-content: center; flex-wrap: wrap;">
                    <label style="color: #fff; margin-right: 10px;"><input type="checkbox" name="search_text" value="1" ' . $pipsCheckText . ' /> 本文/Text</label>
                    <label style="color: #fff; margin-right: 10px;"><input type="checkbox" name="search_subject" value="1" ' . $pipsCheckSubject . ' /> 件名/Subject</label>
                    <label style="color: #fff;"><input type="checkbox" name="search_name" value="1" ' . $pipsCheckName . ' /> 投稿者名/Name</label>
                  </div>
                  <p>※検索キーワードがある状態で項目を一つも選ばなかった場合は、すべての項目が対象になります。/*If a keyword is entered but no checkboxes are selected, all fields above will be searched.</p>
                  <div style="display: flex; justify-content: center; flex-wrap: wrap;">
    '
                          // 上の3つ（検索する項目）とは別の軸で、「返信まで探すかどうか」です。
                          // 直前のhiddenは、チェックを外した時にブラウザが何も送らない問題への対処です
                          // （PipsDispData::searchSortParams()の解説を参照。JSは使いません）。
    . '
                    <input type="hidden" name="search_replies" value="0" />
                    <label style="color: #fff;"><input type="checkbox" name="search_replies" value="1" ' . $pipsCheckReplies . ' /> 返信も探す/Include replies</label>
                  </div>
                  <p>※外すとスレッドの1件目だけが対象になります。/*Uncheck to search only the first post of each thread.</p>
                  <label>並び替え/Sort</label>
                  <select name="sort">' . $pipsSortOptionsHtml . '</select>
                  <button type="submit">検索/Search</button>
                </form>
                ' . $pipsClearSearchHtml . '
              </div>
            </div>
          </div>
        </div>
      </div>
      ' . $pipsNextButtonHtml . '
      ' . $pipsPrevButtonHtml . '
      <a href="#modal-1" title="書き込み/write" onclick="setReplyInfo(\'\',\'\');" class="bottom-right_button"><img class="image_iconsize" width="auto" height="auto" src="./assets/images/seizu_pen.png" alt="button" oncontextmenu="return false;" onselectstart="return false;" onmousedown="return false;"></img></a>
      <a href="#modal-2" title="もっと多くの機能/more features" class="bottom-left_button"><img class="image_iconsize" width="auto" height="auto" src="./assets/images/math_mark01_plus.png" alt="button" oncontextmenu="return false;" onselectstart="return false;" onmousedown="return false;"></img></a>
      <div class="action_msg">
' . $pipsCompleteMsgHtml . '
      </div>
    '
            // 新着のお知らせを出す場所。中身はJSが入れます（PipsNewPosts参照）。
            // 【JSが無い環境で邪魔にならないこと】初期状態は空でhidden付きなので、
            // JSが動かなければ最後まで何も表示されません。新着の取り込み自体は
            // 画面を開き直せば従来どおり行われるので、これは純粋な上乗せです。
            // ここにPHPから文言を入れないこと（入れるとJS無しの環境で、押しても
            // 何も起きないボタンが残ります）。
    . '
      <div id="new_posts_notice" class="new_posts_notice" hidden></div>
    '
            // data-prerendered="1" は「この中身はサーバが入れ終えている」という印です。
            // JS(getDispPost)はこれを見て初回の取り直しを省き、印を消して以後は通常どおり動きます。
            // 詳しくは$dispPipsを決めている箇所のコメントを読んでください。
    . '
      <div class="bbs_content" data-prerendered="1">
        ' . $dispPips . '
      </div>
    </main>
    <footer class="footer">
      <div>
        <h3>ページ概要/Page Summary</h3>
        <p>' . $version . '</p>
        <p>PIPSとはPusyuuImpressionsPostService.の略です。/PIPS stands for Pusyuu Impressions Post Service.</p>
        <p>PIPSは現在、約' . $pipsAnniversary . '周年なんだとか、ちなみになぜ小数点以降の数値までも表示させるかというと日にちも含めて、 現在がおおよそ何数年かわかったほうがユニークさがあるかな〜とね。よくある何周年立ったかは一の位の値をみれば一発です。/PIPS is currently about ' . $pipsAnniversary . ' anniversary, and by the way, the reason why the number after the decimal point is displayed is that it would be more unique to know how many years the present is approximately, including the date. The most common number of years is one shot if you look at the value of the first place.</p>
        <p>PIPSについてもっと詳しく<a href="./lp">紹介を見る</a>/Learn more about PIPS<a href="./lp">See introduction</a></p>
        <p><a href="./?time" target="_blank">隠しページ/hidden page</a></p>
    '
              // 【ここを <a href="./?lite=1"> に戻さないこと】
              // 軽量版はセッションに貼り付きます。ここがリンクだった頃は、クローラーがこれを踏んだ
              // だけで以後そのセッションの全URL(共有される ?id_one_post= を含む)が軽量版で返り、
              // og:*もcanonicalも無いHTMLが「正規のURLの中身」になっていました。
              // クローラーはリンクを辿りますがフォームは送信しません。ONの入口をボタン(POST)に
              // したのはそのためで、相手が誰かを当てにいく必要をなくすのが狙いです。
              // 詳しい理由はこのファイル上部の $isLite を決めている箇所に書いてあります。
              //
              // JSは使いません。軽量版を必要とする端末こそJSが動かないので、素のPOSTで完結させます。
    . '
        <form method="post" class="lite_switch">
          <input type="hidden" name="server_token" value="' . PipsSession::get("server_token") . '" />
          <button type="submit" name="liteMode_button" value="' . PIPS_LITE_ON . '" class="button">軽量版で見る（とても古いブラウザでもPusyuuIPSを楽しもう！）/View lite version View the lightweight version (Enjoy PusyuuIPS even on very old browsers!)</button>
        </form>
      </div>
      <div>
        <h3>プシューのその他のプロダクト一覧/List of other Pushu products</h3>
        ' . $pipsProductLinksHtml . '
      </div>
      <div>
        <h3>唐突のブラウザ情報/Abrupt browser information</h3>
        ' . $pipsUserAgentHtml . '
      </div>
      <div>
        ' . $pipsNotationHtml . '
        ' . $pipsCopyrightHtml . '
      </div>
    </footer>
    ' . $pipsPushScriptHtml . '
  </body>
</html>
    ';
  }

  return $html;
}

?>
<?= renderPIPS() ?>
