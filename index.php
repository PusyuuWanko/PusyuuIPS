<?php
// キャッシュ無効化とセッションスタート
session_cache_limiter("nocache");
session_start();

//エラーレポート設定
//error_reporting(E_ALL & ~E_NOTICE & ~E_PARSE & ~E_DEPRECATED);

// 出力タイプを HTML に設定
header("Content-Type: text/html; charset=utf-8");

// グローバル変数
$disable_log_file = "./assets/posts/disable_post.json";
$logfile = "./assets/posts/data.json";
$log_text = "";
$nextPageUrl = "";
$prevPageUrl = "";

// ページ情報郡
$version = "ThisPageVersion: " . "4.27.4";
$dualTransmissionMsg = "ボタンが連続で一回以上押されためシステム保護のため処理を終了しました、申し訳なく存じますが最初からやり直してください。/The button was pressed more than once in quick succession. For system protection, the process has been terminated. We apologize for the inconvenience, but please start again from the beginning.";

$title = "プシューサービス - プシューIPS/PusyuuIPS";
$description = "PIPS(PusyuuIPS)はPusyuuImpressionsPostService.の略で名前通り感想を書いたり見たりして楽しむサービスで、ミニブログとしてでも、掲示板としてでも、そしてチャットとしても利用可能です！、操作感は某SNSプラットフォームのような使い心地です。/PIPS (PusyuuIPS) is an abbreviation for PusyuuImpressionsPostService., and as the name suggests, it is a service that allows you to write and enjoy your impressions, and can be used as a miniblog, bulletin board, or chat! The operation feels like a certain SNS platform.";
$image = "https://pips.pusyuuwanko.com/assets/images/pips_logo.png";

// POSTする値のすべてのHTML 要素を無効にする
foreach ($_POST as $key => $value) {
  $_POST[$key] = htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function genelateSession() {
  $_SESSION["server_token"] = bin2hex(random_bytes(32));
}

function pipsBarsDayCounter() {
  $pipsBarsDay = [2023, 07, 20]; // 年、月、日
  $barsDayStr = implode("-", $pipsBarsDay);
  $barsDay = new DateTime($barsDayStr);
  $currentDay = new DateTime();
  $diff = $currentDay->diff($barsDay);
  $yearsDiff = ($diff->days / 365.25);
  
  return $yearsDiff;
}

function generateSitemapXML($posts) {
  $currentDate = date('Y-m-d');
  $jsonFilePath = './assets/documents/sitemap_generation_date.json';
  $sitemapFilePath = './sitemap.xml';

  if (file_exists($jsonFilePath)) {
    $jsonData = file_get_contents($jsonFilePath);
    $lastGenerated = json_decode($jsonData, true);
    if ($lastGenerated && isset($lastGenerated['last_generated'])) {
      if ($lastGenerated['last_generated'] === $currentDate) {
        //echo "Sitemapは既に今日生成されています。\n";
        return;
      }
    }
  }

  $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  $sitemap .= '<!-- プシューの手作りサイトマップ、最終、書き込みが行われたのは' . $currentDate . 'です。 -->' . "\n";
  $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
  
  function generateSitemapEntry($postId) {
    $url = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?id_one_post=' . $postId;
    $entry = "  <url>\n";
    $entry .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
    $entry .= "    <priority>0.8</priority>\n";  // Priorityは任意で設定
    $entry .= "  </url>\n";
    return $entry;
  }
    
  foreach ($posts["item"] as $post) {
    if (isset($post["id"])) {
      $sitemap .= generateSitemapEntry($post["id"]);
    }
    if (isset($post["replies"])) {
      foreach ($post["replies"] as $reply) {
        if (isset($reply["id"])) {
          $sitemap .= generateSitemapEntry($reply["id"]);
        }
      }
    }
  }
    
  $sitemap .= '</urlset>';
    
  file_put_contents($sitemapFilePath, $sitemap);
  echo "Sitemapが生成されました。\n";

  $dateData = json_encode(['last_generated' => $currentDate], JSON_PRETTY_PRINT);
  file_put_contents($jsonFilePath, $dateData);

  return $sitemap;
}

generateSitemapXML(json_decode(@file_get_contents($logfile), true));

function p_decimalPointCheck($num) {
  $stringNum = (string)$num;
  for ($i = 0; $i < strlen($stringNum); $i++) {
    if ($stringNum[$i] === ".") {
      return true;
    }
  }
  return false;
}

function p_count($array_num) {
  $counter = 0;
  if (isset($array_num) && is_array($array_num)) {
    foreach ($array_num as $reply) {
      $counter++;
    }
  }
  return $counter;
}

function utf8_substr($str, $start, $length = null) {
  $array = preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);

  if ($start >= p_count($array)) {
    return "";
  }

  if ($length === null) {
    $length = p_count($array) - $start;
  }

  return implode("", array_slice($array, $start, $length));
}

function pageOverCheck($pageArray, $pageSize) {
  $overNum = p_count($pageArray) / $pageSize;
  $decimal_pos = strpos((string)$overNum, ".");
  if ($decimal_pos === false) {
    return $overNum;
  }
  $oneDecimalValue = substr($overNum, 0 , $decimal_pos + 2);
              
  return p_decimalPointCheck($oneDecimalValue) && floor($oneDecimalValue) != $oneDecimalValue ? $oneDecimalValue+1 : $oneDecimalValue;
}

// データの書き込み処理
function post_data($media, $dangerous, $sensitive) {
  global $logfile;
  // データを一括読み込み
  $log_text = @file_get_contents($logfile);
  $json = json_decode($log_text);
  // 空のファイルかまたは、JSON データでは無い場合
  if ($json === null) {
    // JSON 用クラス作成
    $json = new stdClass;
    // 行データを格納する配列を作成
    $json->item = [];
  }
  
  // 改行コードを \n のみ(1バイト)にする
  $_POST["text"] = str_replace("\r", "", $_POST["text"]);
  
  // 新しい投稿用のクラス作成
  $board_data = new stdClass;

  // idをセット
  $board_data->id = uniqid() . bin2hex(random_bytes(8));
  // text プロパティに 入力された本文をセット
  $board_data->text = $_POST["text"];
  // media プロパティにメディアファイルをセット
  $board_data->media = $media; 
  // subject プロパティに 入力されたタイトルをセット
  $board_data->subject = isset($_POST["subject"]) && !empty($_POST["subject"]) ? $_POST["subject"] : "🐸本文がタイトルだよーん";
  // name プロパティに 入力された名前をセット
  $board_data->name = isset($_POST["name"]) && !empty($_POST["name"]) ? $_POST["name"] : "Unknown Pusyuu User";
  // contact プロパティに 入力された連絡先をセット
  $board_data->contact = isset($_POST["contact"]) && !empty($_POST["contact"]) ? $_POST["contact"] : "連絡先設定なし";
  // subject プロパティに 入力されたタイトルをセット
  $board_data->datetime = $_POST["datetime"];
  // dangerous プロパティに判定結果をセット
  $board_data->dangerous = $dangerous;
  // sensitive プロパティに判定結果をセット
  $board_data->sensitive = $sensitive;
  // 返信の場合をチェック
  if (!empty($_POST["reply_to"])) {
    // 返信先の投稿を探す
    $replyToId = $_POST["reply_to"];
    $replyToPost = findPostById($json->item, $replyToId);
    
    if ($replyToPost !== null) {
      // 返信のための新しいクラス作成
      $reply_data = new stdClass;
      // IDをセット
      $reply_data->id = uniqid() . "_" . bin2hex(random_bytes(8));
      // 本文をセット
      $reply_data->text = $_POST["text"];
      // media プロパティにメディアファイルをセット
      $reply_data->media = $media; 
      // subject プロパティに 入力されたタイトルをセット
      $reply_data->subject = isset($_POST["subject"]) && !empty($_POST["subject"]) ? "RE：" . $_POST["subject"] : "RE：No Thread Name";
      // ユーザー名をセット
      $reply_data->name = isset($_POST["name"]) && !empty($_POST["name"]) ? $_POST["name"] : "Unknown Pusyuu User";
      // contact プロパティに 入力された連絡先をセット
      $reply_data->contact = isset($_POST["contact"]) && !empty($_POST["contact"]) ? $_POST["contact"] : "連絡先設定なし";
      // 日時をセット
      $reply_data->datetime = $_POST["datetime"];
      // dangerous プロパティに判定結果をセット
      $reply_data->dangerous = $dangerous;
      // sensitive プロパティに判定結果をセット
      $reply_data->sensitive = $sensitive;

      $replyToPost->replies[] = $reply_data;
    } else {
    
    }
  } else {
    array_unshift($json->item, $board_data);
  }
  
  // JSONデータを時刻でソート
  usort($json->item, function($a, $b) {
    return strtotime($b->datetime) - strtotime($a->datetime);
  });
  
  // JSONとして一括書き込み
  file_put_contents($logfile, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

  // reply のセッションを空に初期化
  $_SESSION["reply_id"] = "";
  $_SESSION["reply_submit"] = "";

  $_SESSION["post_complete_msg"] = "正常に投稿されました！！/The post was successfully submitted!!";

  genelateSession();

  // GET メソッドで再表示します
  if (isset($_SESSION["query_param"])) {
    header("Location: {$_SESSION["query_param"]}");
  } else {
    header("Location: ./");
  }

  exit();
}

if (isset($_POST["reply"])) {
  if ($_POST["server_token"] == $_SESSION["server_token"]) {
    $_SESSION["reply_id"] = $_POST["reply_id"];
    $_SESSION["reply_submit"] = $_POST["reply_submit"];
    genelateSession();
    if (isset($_SESSION["query_param"])) {
      header("Location: {$_SESSION["query_param"]}#modal-1");
    } else {
      header("Location: ./#modal-1");
    }
  } else {
    $_SESSION["post_complete_msg"] = $dualTransmissionMsg;
  }
}

if (isset($_POST["disablePost_button"])) {
  if ($_POST["server_token"] == $_SESSION["server_token"]) {
  
    $jsonData = @file_get_contents($disable_log_file);
    $data = json_decode($jsonData, true);
    if ($data === null) {
      $data["disablePost"] = [];
    }

    $data["disablePost"][] = $_POST["disablePost_button"];
    $newJsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    file_put_contents($disable_log_file, $newJsonData);
  
    $_POST["disablePost_button"] = "";
    $_SESSION["post_complete_msg"] = "ポストの無効化をリクエストしました。/A request to disable the post has been made.";
    genelateSession();
    // GET メソッドで再表示します
    if (isset($_SESSION["query_param"])) {
      header("Location: {$_SESSION["query_param"]}");
    } else {
      header("Location: ./");
    }
    exit();
  } else {
    $_SESSION["post_complete_msg"] = $dualTransmissionMsg;
  }
}

if (isset($_POST["like_button"])) {
  if ($_POST["server_token"] == $_SESSION["server_token"]) {
    //$result = isset($_COOKIE["saveData"]) ? json_decode($_COOKIE["saveData"], true) : [];
    $result = isset($_SESSION["saveUserData"]) ? json_decode($_SESSION["saveUserData"], true) : [];
    $like = $_POST["like_button"];
    if (in_array($like, $result)) {
      $_SESSION["post_complete_msg"] = "この項目はすでに保存されています。/This item is already saved.";
    } else {
      $result[] .= $like;
      $_SESSION["saveUserData"] = json_encode($result);
      //setcookie("saveData", json_encode($result), time()+3600, "/");
      $_SESSION["post_complete_msg"] = "お気に入り登録しました。/Added to favorites.";
    }
    genelateSession();
    // GET メソッドで再表示します
    if (isset($_SESSION["query_param"])) {
      header("Location: {$_SESSION["query_param"]}");
    } else {
      header("Location: ./");
    }
    exit();
  } else {
    $_SESSION["post_complete_msg"] = $dualTransmissionMsg;
  }
}

if (isset($_POST["viewPost_button"])) {
  if ($_POST["server_token"] == $_SESSION["server_token"]) {
    genelateSession();
    header("location: {$_POST["viewPost_button"]}");
    exit();
  } else {
    $_SESSION["post_complete_msg"] = $dualTransmissionMsg;
  }
}

function usersSaveContent() {
  //$data = isset($_COOKIE["saveData"]) ? json_decode($_COOKIE["saveData"], true) : [];
  $data = isset($_SESSION["saveUserData"]) ? json_decode($_SESSION["saveUserData"], true) : [];
  $outputData = !empty($data) ? "" : "まだ、保存したブックマークがありません。/You don't have any saved bookmarks yet.";
  foreach ($data as $resultData) {
    $outputData .= '<li><a href="' . $resultData . '" target="_blank">' . $resultData . '</a></li>';
  }
  return $outputData;
}

function findPostById($posts, $postId, $isOneOnlyPost = false) {
  if ($isOneOnlyPost) {
    foreach ($posts as $post) {
      if (isset($post->id) && $post->id === $postId) {
        return $post;
      }
      if (isset($post->replies)) {
        foreach ($post->replies as $reply) {
          if ($reply->id === $postId) {
            return $reply;
          }
        }
      }
    }
    return null;
  } else {
    foreach ($posts as $post) {
      if (isset($post->id) && $post->id === $postId) {
        return $post;
      }
      // 返信がある場合は、replies配列を探索
      if (isset($post->replies)) {
        foreach ($post->replies as $reply) {
          if ($reply->id === $postId) {
            return $post;
          }
        }
      }
    }
    return null;
  }
}

// リンクの仕様変換
function convert_Links($text) {
  $pattern = "/(https?:\/\/[^\s<>'\'()]+)/u";
  $replacement = function ($match) {
    $url = $match[1];
    $imageExtensions = ["jpg", "jpeg", "png", "gif", "webp", "svg"];
    $audioExtensions = ["mp3", "ogg", "wav"];
    $videoExtensions = ["mp4", "webm", "3gp"];

    $fileExtension = pathinfo($url, PATHINFO_EXTENSION);

    if (in_array(strtolower($fileExtension), $imageExtensions)) {
      return '<a href="' . $url . '" target="_blank"><img style="width: 100%; height: auto;" src="' . $url . '" alt="' . $url . '" /></a>';
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

    if (in_array(strtolower($fileExtension), $audioExtensions)) {
      return '<audio controls style="width: 100%; height: auto;"><source src="' . $url . '" type="audio/' . $fileExtension . '">Your browser does not support the audio element.</audio>';
    }

    if (in_array(strtolower($fileExtension), $videoExtensions)) {
      return '<video controls style="width: 100%; height: auto;"><source src="' . $url . '" type="video/' . $fileExtension . '">Your browser does not support the video element.</video>';
    }

    return '<a href="' . $url . '" target="_blank">' . $url . '</a>';
  };
  return preg_replace_callback($pattern, $replacement, $text);
}

function bese64MediaDecoder($bese64Media, $isTagOrData = false) {
  $base64_header = substr($bese64Media, 0, 64);

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime_type = $finfo->buffer(base64_decode($bese64Media));

  if ($bese64Media !== "") {
    if (strpos($mime_type, "image/") === 0) {
      $data_tag = '<img src="data: ' . $mime_type . ';base64,' . $bese64Media . '" alt="投稿された画像/Images that have been posted." style="width: 100%; height: auto;" />';
      $data_info = 'data:' . $mime_type . ';base64,' . $bese64Media;
      return $isTagOrData ? $data_info : $data_tag;
    } else if (strpos($mime_type, 'video/') === 0) {
      $data_tag = '<video controls style="width: 100%; height: auto;"><source src="data:' . $mime_type . ';base64,' . $bese64Media . '" type="' . $mime_type . '">Your browser does not support the video element.</video>';
      $data_info = 'data:' . $mime_type . ';base64,' . $bese64Media;
      return $isTagOrData ? $data_info : $data_tag;
    } else {
      return '<p>メディアファイルが正常に処理されなかったか、壊れているため表示できません。/The media file could not be processed correctly or is corrupted, so it cannot be displayed.</p>';
    }
  } else {
    return "";
  }
}

function disp_data() {
  // 埋め込み用データを global 宣言
  global $logfile, $log_text, $title, $description, $image, $nextPageUrl, $prevPageUrl, $dangerounsMsg;

  $dangerounsMsg = "危険なポストを検知しました、申し訳なく存じますがあなたの投稿は表示できません。危険でないと管理者に伝えたい際は、＋ボタン（もっと多くの機能）内のお問い合わせ欄から、再審査して欲しいという旨をお伝えください。/We've detected a dangerous post, we're sorry, but we can't see your post. If you want to tell the administrator that it is not dangerous, please tell them that you would like to be re-examined from the inquiry field in the + button (more functions).";
	
  // ページのサイズを設定
  $pageSize = 15;

  // URLから現在のページ番号を取得し、デフォルトは1
  $pageNum_N = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
  
  // URLから現在のページ番号を取得し、デフォルトは1
  $pageNum_I = isset($_GET["id"]) && isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;

  $id = isset($_GET["id"]) ? $_GET["id"] : null;

  $id_one_post = isset($_GET["id_one_post"]) ? $_GET["id_one_post"] : null;

  // 全データを取得
  $log_data = @file_get_contents($logfile);

  // ファイルが存在しない場合やJSONデータではない場合の処理
  if ($log_data === false) {
    $log_text .= '<div class="center">';
    $log_text .= '<p>ここに投稿データが表示されます。/Post data will be displayed here.</p>';
    $log_text .= '</div>';
  } else {
    $json = json_decode($log_data);
    if ($json === null || !isset($json->item)) {
      $log_text .= '<div class="center">';
      $log_text .= '<p>投稿データが破損しています、管理者にこの事をいち早くお伝えください。お問い合わせは<a href="#modal-2">こちらのもっと多くの機能</a>内のあ問い合わせフォームからお伝えください。/The post data is corrupted, please inform the administrator of this as soon as possible. If you have any questions, please contact us using the contact form in <a href="#modal-2">More features here</a>.</p>';
      $log_text .= '</div>';
    } else {
      function contentHtmlLogic($item) {
        global $dangerounsMsg;
        return '<div class="content" data-sensitivity="' . (isset($item->sensitive) && $item->sensitive ? "true" : "false") . '">' . (isset($item->dangerous) && $item->dangerous ? $dangerounsMsg : $item->text . '<div class="media">' . (isset($item->media) ? bese64MediaDecoder($item->media, false) : "") . '</div>') . '</div>';
      }
      if ($id !== null) {
        $targetPost = findPostById($json->item, $id);
        if ($targetPost !== null) {
          if ($pageNum_I <= 1) {
            $title = "プシューIPS/PusyuuIPS - " . utf8_substr($targetPost->subject, 0, 20);
            $description = utf8_substr($targetPost->text, 0, 20);
            if (isset($targetPost->media) && strpos(bese64MediaDecoder($targetPost->media, false), "<img") !== false) {
              $imagePath = "image";
              if (isset($_GET[$imagePath])) {
                $decodedData = base64_decode($targetPost->media);
                header('Content-Type: image/jpeg');
                echo $decodedData;
                exit;
              }
              $image = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "&" . $imagePath;
            } else {
              $image = $image;
            }

            // 本文の改行は br 要素で表現します
            $targetPost->text = str_replace("\n", "<br>\n", $targetPost->text);
            // リンク変換
            $targetPost->text = convert_Links($targetPost->text);

            $log_text .= '<div class="post">'; // 外側の <div> タグ開始
            $log_text .= '<div class="wrre wrapper">';
            $log_text .= '<div class="title">【' . $targetPost->subject . '】(' . $targetPost->name . ' : ' . $targetPost->datetime . ') <span>'  . (isset($targetPost->contact) ? $targetPost->contact : "設定なし") . '</span></div>';
            $log_text .= contentHtmlLogic($targetPost);
            $log_text .= '</div>';
            $shareLink = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?id_one_post=" . $targetPost->id;
            $log_text .= '<div class="buttons">';
            $log_text .= '<form onsubmit="return false;"><button onclick="copyToClipboard(\'' . $shareLink . '\')" disabled>Share<?xml version="1.0" encoding="UTF-8"?><svg width="24px" height="24px" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="#000000"><path d="M18 22C19.6569 22 21 20.6569 21 19C21 17.3431 19.6569 16 18 16C16.3431 16 15 17.3431 15 19C15 20.6569 16.3431 22 18 22Z" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M18 8C19.6569 8 21 6.65685 21 5C21 3.34315 19.6569 2 18 2C16.3431 2 15 3.34315 15 5C15 6.65685 16.3431 8 18 8Z" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M6 15C7.65685 15 9 13.6569 9 12C9 10.3431 7.65685 9 6 9C4.34315 9 3 10.3431 3 12C3 13.6569 4.34315 15 6 15Z" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M15.5 6.5L8.5 10.5" stroke="#000000" stroke-width="1.5"></path><path d="M8.5 13.5L15.5 17.5" stroke="#000000" stroke-width="1.5"></path></svg></button></form>';
            $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '" /><button type="submit" name="viewPost_button" value="' . $shareLink . '">viewPost</button></form>';
            $log_text .= '<form method="post" onsubmit="return false;"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '" /><input type="hidden" name="reply_id" value="' . $targetPost->id . '" /><input type="hidden" name="reply_submit" value="' .$targetPost->subject . '" /><button name="reply" onclick="setReplyInfo(\'' . $targetPost->id . '\', \'' .$targetPost->subject . '\'); location.hash=\'modal-1\'">Reply<span class="replyCD">' . ($count = isset($targetPost->replies) ? p_count($targetPost->replies) : 0) . '</span></button></form>';
            $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '"><button type="submit" name="disablePost_button" value="' . $targetPost->id . '">disablePost</button></form>';
            $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '"><button type="submit" name="like_button" value="' . $shareLink . '">Like</button></form>';
            $log_text .= '</div>';
            $log_text .= '</div>';
          }
          if (isset($targetPost->replies)) {
            $pageOver = pageOverCheck($targetPost->replies, $pageSize);
            $pageSize = isset($_GET["page"]) && $_GET["page"] > 1 ? 15 : 14;
            $pageNum_N = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
            $start = ($pageNum_N - 1) * $pageSize;
            $end = $start + $pageSize;
            $carryover = $pageNum_N > 1 ? 1 : 0;
            $start -= $carryover;
            $end -= $carryover;
            $repliesToShow = array_slice($targetPost->replies, $start, $pageSize);
            if (isset($_GET["page"]) && !empty($_GET["page"])) {
              if ($_GET["page"] < $pageOver) {
                $nextPageUrl = "./?id=" . $_GET["id"] . "&page=" . $_GET["page"]+1;
              }
              if ($_GET["page"] > 1) {
                $prevPageUrl = "./?id=" . $_GET["id"] . "&page=" . $_GET["page"]-1;
              } else {
                $prevPageUrl = "./?page=1";
              }
            } else {
              $nextPageUrl = "./?id=" . $_GET["id"] . "&page=2";
              $prevPageUrl = "./?id=" . $_GET["id"] . "&page=1";
            }
            foreach ($repliesToShow as $reply) {
              $reply->text = str_replace("\n", "<br>\n", $reply->text);
              $reply->text = convert_Links($reply->text);
              $log_text .= '<div class="post">'; // 返信の場合はクラスを追加
              $log_text .= '<div class="wrre wrapper">';
              $log_text .= '<div class="title">【' . $reply->subject . '】(' . $reply->name . ' : ' . $reply->datetime . ') <span>'  . (isset($reply->contact) ? $reply->contact : "設定なし") . '</span></div>';
              $log_text .= contentHtmlLogic($reply);
              $log_text .= '</div>';
              $shareLink = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?id_one_post=" . $reply->id;
              $log_text .= '<div class="buttons">';
              $log_text .= '<form onsubmit="return false;"><button onclick="copyToClipboard(\'' . $shareLink . '\')" disabled>Share</button></form>';
              $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '" /><button type="submit" name="viewPost_button" value="' . $shareLink . '">viewPost</button></form>';
              $log_text .= '<form method="post" onsubmit="return false;"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '" /><input type="hidden" name="reply_id" value="' . $reply->id . '" /><input type="hidden" name="reply_submit" value="' .$reply->subject . '" /><button name="reply" onclick="setReplyInfo(\'' . $reply->id . '\', \'' .$reply->subject . '\'); location.hash=\'modal-1\'">Reply</button></form>';
              $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '"><button type="submit" name="disablePost_button" value="' . $reply->id . '">disablePost</button></form>';
              $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '"><button type="submit" name="like_button" value="' . $shareLink . '">Like</button></form>';
              $log_text .= '</div>';
              $log_text .= '</div>';
            }

            if (isset($_GET["page"]) && $_GET["page"] > $pageOver && $_GET["page"] !== $pageOver) {
              $log_text = "表示できるページ数を超えました。/The number of pages that can be displayed has been exceeded.";
            }
          } else {
            $log_text .= '<div class="post">';
            $log_text .= '<div class="wrre wrapper">';
            $log_text .= '<div class="title">システム通知/System Notifications</div>';
            $log_text .= '<div class="content">ここにあなたのポストやスレッド（返信）が表示されます。/Here you will see the threads (replies) of your post.</div>';
            $log_text .= '</div>';
            $log_text .= '</div>';
          }
        } else {
          header("HTTP/1.1 404 Not Found");
          $prevPageUrl = "./?page=1";
          $log_text .= '<div class="center">';
          $log_text .= '<p>指定されたIDの投稿が見つかりませんでした。/The post with the specified ID was not found.</p>';
          $log_text .= '</div>';
        }
      } else if ($id_one_post !== null) {
        $onePost = findPostById($json->item, $id_one_post, true);
        if (isset($onePost)) {
          $title = "プシューIPS/PusyuuIPS - " . utf8_substr($onePost->subject, 0, 20);
          $description = utf8_substr($onePost->text, 0, 20);
          if (isset($onePost->media) && strpos(bese64MediaDecoder($onePost->media, false), "<img") !== false) {
            $imagePath = "image";
            if (isset($_GET[$imagePath])) {
              $decodedData = base64_decode($onePost->media);
              header('Content-Type: image/jpeg');
              echo $decodedData;
              exit;
            }
            $image = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "&" . $imagePath;
          } else {
            $image = $image;
          }
          $onePost->text = str_replace("\n", "<br>\n", $onePost->text);
          $onePost->text = convert_Links($onePost->text);
          $log_text .= '<div class="one_post">';
          $log_text .= '<div class="title">Matching ID: ' . $onePost->id . "<hr>" . $onePost->subject . '<span>'  . (isset($onePost->contact) ? $onePost->contact : "設定なし") . '</span></div>';
          $log_text .= contentHtmlLogic($onePost);
          $shareLink = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?id_one_post=" . $onePost->id;
          $log_text .= '<div class="buttons">';
          $log_text .= '<form onsubmit="return false;"><button onclick="copyToClipboard(\'' . $shareLink . '\')" disabled>Share</button></form>';
          $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '"><button type="submit" name="like_button" value="' . $shareLink . '">Like</button></form>';
          $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '"><button type="submit" name="disablePost_button" value="' . $onePost->id . '">disablePost</button></form>';
          $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '" /><button type="submit" name="viewPost_button" value="' . './?id=' . $onePost->id . '">backToReplies</button></form>';
          $log_text .= '</div>';
          $log_text .= '</div>';
        } else {
          header("HTTP/1.1 404 Not Found");
          $log_text .= '<div class="center">';
          $log_text .= '<p>指定されたIDの投稿が見つかりませんでした。/The post with the specified ID was not found.</p>';
          $log_text .= '</div>';
        }      
      } else {
        $pageOver = pageOverCheck($json->item, $pageSize);
        // ページに合わせて表示すべきデータを抽出
        $start = ($pageNum_N - 1) * $pageSize;
        $end = $start + $pageSize;
        $pagedData = array_slice($json->item, $start, $pageSize);
        if (isset($_GET["page"]) && !empty($_GET["page"])) {
          if ($_GET["page"] < $pageOver) {
            $nextPageUrl = "./?page=" . $_GET["page"]+1;
          }
          if ($_GET["page"] > 1) {
            $prevPageUrl = "./?page=" . $_GET["page"]-1;
          }
        } else {
          $nextPageUrl = "./?page=2";
          $prevPageUrl = "./?page=1";
        }
        // 表示用の埋め込みに使用される文字列変数
        foreach ($pagedData as $postList) {
          // 本文の改行は br 要素で表現します
          $postList->text = str_replace("\n", "<br>\n", $postList->text);
          // リンク変換
          $postList->text = convert_Links($postList->text);
          $log_text .= '<div class="post">'; // 外側の <div> タグ開始
          $log_text .= '<div onclick="window.location.href = \'?id=' . $postList->id . '\'" class="wrapper">';
          $log_text .= '<div class="title">【' . $postList->subject . '】(' . $postList->name . ' : '. $postList->datetime . ') <span>'  . (isset($postList->contact) ? $postList->contact : "設定なし") . '</span></div>';
          $log_text .= contentHtmlLogic($postList);
          $log_text .= '</div>';
          $shareLink = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?id_one_post=" . $postList->id;
          $log_text .= '<div class="buttons">';
          $log_text .= '<form onsubmit="return false;"><button onclick="copyToClipboard(\'' . $shareLink . '\')" disabled>Share<i class="share-android"></i></button></form>';
          $log_text .= '<form method="post" onsubmit="return false;"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '" /><input type="hidden" name="reply_id" value="' . $postList->id . '" /><input type="hidden" name="reply_submit" value="' .$postList->subject . '" /><button name="reply" onclick="setReplyInfo(\'' . $postList->id . '\', \'' .$postList->subject . '\'); location.hash=\'modal-1\'">Reply<span class="replyCD">' . ($count = isset($postList->replies) ? p_count($postList->replies) : 0) . '</span></button></form>';
          $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '" /><button type="submit" name="disablePost_button" value="' . $postList->id . '">disablePost</button></form>';
          $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '" /><button type="submit" name="like_button" value="' . $shareLink . '">Like</button></form>';
          $log_text .= '<form method="post"><input type="hidden" name="server_token" value="' . $_SESSION["server_token"] . '" /><button type="submit" name="viewPost_button" value="' . "./?id=" . $postList->id . '">viewPost</button></form>';
          $log_text .= '</div>';
          $log_text .= '</div>';
        }
        
        if (isset($_GET["page"]) && $_GET["page"] > $pageOver) {
          $log_text = "表示できるページ数を超えました。/The number of pages that can be displayed has been exceeded.";
        }
      }
    }
  }
}

if (isset($_GET["time"])) {
  echo '
    <!DOCTYPE html>
    <html lang="ja">
    <head>
      <meta charset="UTF-8" />
      <meta name="robots" content="noindex,nofollow" />
      <meta name="viewport" content="width=device-width, user-scalable=yes, maximum-scale=6.0, minimum-scale=1.0" />
      <link rel="shortcut icon" href="https://pusyuuwanko.com/pusyuusystem/icons/favicon.ico" />
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
  exit;
}

if (!isset($_SESSION["server_token"])) {
  genelateSession();
}

// フォームの送信とテキストの入力チェック
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // トークンの検証
  if ($_POST["server_token"] === $_SESSION["server_token"]) {
    // クエリーパラメーター保存
    foreach ($_GET as $paramName => $paramValue) {
      if (!isset($result)) {
        $result = "?" . $paramName . "=" . $paramValue;
      } else {
        $result .= "&" . $paramName . "=" . $paramValue;
      }
      $_SESSION["query_param"] = $result;
    }

    // 禁止する言葉リスト
    $common_dangerous = [
      "ポア",
      "殺す",
      "死ね",
      "児ポ",
      "児童ポルノ",
      "殺しに行く",
      "ヤドン爆",
      "荒らし共栄圏",
      "[url=",
      "[/url]"
    ];

    $common_sensitive = [
      "マンコ",
      "まんこ",
      "ちんこ",
      "チンコ",
      "ちんちん",
      "チンチン",
      "性器",
      "性病",
      "金玉",
      "キン玉",
      "きん玉",
      "きんたま",
      "キンタマ",
      "性行為",
      "セックス",
      "せっくす",
      "SEX",
      "ちんぽ",
      "チンポ",
      "エロ",
      "えろ",
      "マンカス",
      "まんかす",
      "マンゲ",
      "まんげ",
      "マン毛",
      "チンゲ",
      "ちんげ",
      "チン毛"
    ];

    $dangerous = null;
    $sensitive = null;
    //ベータ版不正な投稿安全変換機！！
    foreach ($common_dangerous as $string) {
      if (stripos($_POST["text"], $string) !== false) {
        $dangerous = true;
        break; // 一つでも見つかればループを抜ける
      } else {
        $dangerous = false;
      }
    }

    //ベータ版不正な投稿安全変換機！！
    foreach ($common_sensitive as $string) {
      if (stripos($_POST["text"], $string) !== false || isset($_POST["sensitive"]) ) {
        $sensitive = true;
        break; // 一つでも見つかればループを抜ける
      } else {
        $sensitive = false;
      }
    }

    if (isset($_POST["text"]) && !empty($_POST["text"])) {
      // テキストデータの前後の空白を削除
      $_POST["text"] = preg_replace("/^[　\s]+/u", "", $_POST["text"]);
      $_POST["text"] = preg_replace("/[　\s]+$/u", "", $_POST["text"]);
      $mediaFile = $_FILES["media"];
      $mediaFileError = $mediaFile["error"];
      if (isset($mediaFile) && $mediaFileError !== UPLOAD_ERR_NO_FILE) {
        if ($mediaFileError === UPLOAD_ERR_OK) {
          if ($mediaFile["size"] <= 1048576) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $mediaFile["tmp_name"]);
            $extension = strtolower(pathinfo($mediaFile["name"], PATHINFO_EXTENSION));
            if ($mime == "video/mp4" || $mime == "image/jpeg" || $extension == "mp4" || $extension == "jpg") {
              post_data(base64_encode(file_get_contents($mediaFile["tmp_name"])), $dangerous, $sensitive);
            } else {
              $_SESSION["post_complete_msg"] = "メディアファイルが”mp4”または”jpg”ではありません。拡張子をご確認ください。/The media file is not in ”mp4” or ”jpg” format. Please check the file extension.";
            }
          } else {
            $_SESSION["post_complete_msg"] = "メディアファイルが上限の1MB以上です。圧縮または小さいメディアファイルをアップロードしてください。/The media file exceeds the 1MB limit. Please compress the file or upload a smaller media file.";
          }
        } else {
          switch ($mediaFileError) {
            case UPLOAD_ERR_INI_SIZE:
              $_SESSION["post_complete_msg"] = "メディアファイルのサイズがサーバーにアップロードできない大きさです。/The size of the media file is too large to upload to the server.";
            break;
            case UPLOAD_ERR_FORM_SIZE:
              $_SESSION["post_complete_msg"] = "メディアファイルのサイズがPIPS上でアップロードできない大きさです。/The size of the media file is too large to upload on PIPS.";
            break;
            default:
              $_SESSION["post_complete_msg"] = "メディアファイルのアップロード中に想定していないエラーが発生しました。エラー番号：" . $mediaFileError . "番です。/An unexpected error occurred while uploading a media file. Error number: " . $mediaFileError . "It's your turn.";
            break;
          }
        }
      } else {
        post_data("", $dangerous, $sensitive);
      }
    } else {
      $_SESSION["post_complete_msg"] = "フォームにコメントを入力してから送信してください。/Please enter Comment in the form before submitting.";
    }
  } else {
    $_SESSION["post_complete_msg"] = $dualTransmissionMsg;
    exit();
  }
}
disp_data();

function productLinks() {
  $product_disp = "<p>表示できるプロダクトはないようです。</p>";
  $product = json_decode(file_get_contents("https://pusyuuwanko.com/pusyuusystem/documents/futures.json"), true);
  if ($product) {
    $product_disp = '<ul style="height: 100%; overflow: auto; list-style: decimal-leading-zero;">';
    foreach ($product["links"] as $productLink) {
      if ($productLink["url"] === $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"]) {
        continue;
      }
      $product_disp .= '<li><a href="' . $productLink["url"] . '">' . $productLink["title"] . '</a></li>';
    }
    $product_disp .= "</ul>";
  } else {
    $product_disp = '<p style="color: #f00;">表示することができませんでした、開発者の設定ミスが原因な可能性がございます。</p>';
  }
  
  return $product_disp;
}
?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <title><?php echo($title); ?></title>
    <meta content="width=device-width minimum-scale=1.0 maximum-scale=6.0 user-scalable=yes" name="viewport" />
    <meta name="description" content="<?php echo($description); ?>" />
    <meta name="viewport" content="width=device-width, user-scalable=yes, maximum-scale=6.0, minimum-scale=1.0" />
    <meta name="copyright" content="© 2020-2024 created by PusyuuWanko/" />
    <meta name="keywords" content="プシューサービス,PIPS,プシューIPS,PusyuuIPS,プシュー,Pusyuu,PusyuuWanko/,プシューわんこ/,掲示板,感想を投稿するサイト,自由な投稿,PushyuuService,PIPS,PusyuuIPS,PusyuuIPS,Pusyuu,Bulletin Board,Site to post impressions,Free Submission" />
    <meta name="twitter:card" content="Summary_large_Image" />
    <meta name="twitter:site" content="@PusyuuWanko" />
    <meta name="twitter:title" content="<?php echo($title); ?>" />
    <meta name="twitter:description" content="<?php echo($description); ?>" />
    <meta name="twitter:image" content="<?php echo($image); ?>" />
    <meta name="fetchinfo" content="<?php echo $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/" . $logfile; ?>" />
    <link rel="manifest" href="./assets/documents/manifest.json" />
    <link rel="shortcut icon" href="https://pusyuuwanko.com/pusyuusystem/images/favicon.ico" />
    <link href="./assets/styles/style.css" rel="stylesheet" />
    <link href="./assets/styles/ads.css" rel="stylesheet" />
    <script src="./assets/scripts/script.js" rel="script/javascript"></script>
    <script src="./assets/scripts/ads.js" rel="script/javascript"></script>
    <script src="https://pusyuuwanko.com/pusyuusystem/scripts/touch-device-tool-tip.js"></script>
    <!--
        *----------------------------------
        |  <?php echo $version; ?>      |
        |  © 2021-2024 By PusyuuWanko/  |
        |  LastUpdate: 2024-12-25       |
        |  License: Apache-2.0 license     |
        |  PusyuuIPS....                |
      ----------------------------------*
    -->
  </head>
  <body>
    <div class="modal" id="loading">
      <div>
        <div>
          <span>Pusyuu System</span>
          <div style="height: auto;">
            <div><i>🎄</i><span>Now Loading</span>メリクリー★</div>
          </div>
        </div>
      </div>
    </div>
    <header class="header">
      <h1>プシューIPS/PusyuuIPS（☆彡メリークリスマス🎄）</h1>
      <p>投稿はすべて”PusyuuImpressionsPostService”のサービス名通り、感想を投稿して共有できるサービスであり、信憑性や信頼性の低い内容を見て投稿して楽しむサービスです、内容を過信せずこのような考えもあるんだなー程度で楽しみましょう、そしてそれがこのサービスの目的です！！<span>お約束：投稿者名は飽くまでも仮のものであり誰でも同じ名前を使用することが可能でありその個人へのメッセージのやり取りは大変危険ですので行う際は別の手段を講じてください。</span></p>
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
                <form method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>" enctype="multipart/form-data">
                  <input type="text" name="subject" id="subject" value="<?php echo isset($_SESSION["reply_submit"]) ? $_SESSION["reply_submit"] : ""; ?>" placeholder="Thread Name" />
                  <input type="text" name="name" placeholder="User Name" />
                  <input type="text" name="contact" placeholder="Contact Address OR Url" />
                  <textarea name="text" placeholder="Comment"></textarea>
                  <input type="file" name="media" id="media" accept=".mp4,.jpg" />
                  <input type="hidden" name="datetime" id="datetime" />
                  <div style="display: flex; justify-content: center;"><label style="color: #fff; margin-right: 10px;">センシティブな投稿/Sensitive post</label><input type="checkbox" name="sensitive"></div>
                  <input type="hidden" name="server_token" value="<?php echo $_SESSION["server_token"]; ?>" />
                  <input type="hidden" name="reply_to" id="reply_to" value="<?php echo isset($_SESSION["reply_id"]) ? $_SESSION["reply_id"] : ""; ?>" />
                  <button onclick="location.hash='#sent;" type="submit" name="send">送信/Sent</button>
                </form>
              </div>
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
              <h3>ヘルプボタン/Help Button</h3>
              <a class="button" style="cursor: help;" href="#modal-3">ヘルプ/Help</a>
              <h3>自分のコンテンツボタン/My own content button</h3>
              <a class="button" href="#modal-4">自分のコンテンツ/My content</a>
              <h3>システムの再起動/Syatem Reboot</h3>
              <button href="#reload" id="reyomikomi">再読込/Reload</button>
              <h3>タップ音の有無/Presence of tap sound</h3>
              <input type="checkbox" switch id="tapsoundswitch">
              <h3>壁紙の変更/change wallpaper</h3>
              <p>※ブラウザキャッシュを削除すると設定が初期化されるのでご注意ください。/*Please note that deleting your browser cache will initialize the settings.</p>
              <h5>あなたの好きな画像を壁紙を追加↓/Add your favorite image as wallpaper↓</h5>
              <input style="width: 100%;" type="file" id="upload-input">
              <h5>壁紙を選択↓/Select wallpaper↓</h5>
              <select id="background-select">
              </select>
              <h3>フィードバックやお問い合わせ/Feedback and inquiries</h3>
              <div class="form_style-1">
                <form method="post" name="form" onsubmit="return validate()" action="https://pusyuuwanko.com/pusyuusystem/pages/contact/confirm.php">
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
                  <button onclick="location.href='#modal-2'" type="submit" name="send">送信/Sent</button>
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
              <p>Shareボタン（ボタンを押すことによりポストのリンクをコピーできます。）Replyボタン（ボタンを押すことによりそのポストに対し返信を行うことができます。）DisablePostボタン（ボタンを押すことによりポストを通報することができます。）Like（ボタンを押すことによりポストをユーザーページのお気に入りに入りに追加できます。）viewPost（ボタンを押すことにより、ポスト一覧「スレッド一覧」ではJavaScriptが無効になっていてもポストを閲覧することができ、返信「投稿一覧」内のviewPostでは拡大かつ単品で投稿を表示できます。）/Share button (Press the button to copy the post link.)Reply button (Press the button to reply to that post.)DisablePost button (Press the button to report the post.)Like (Press the button to add the post to your favorites on your user page.)viewPost (Press the button to view posts in the post list "Thread List" even if JavaScript is disabled, and to display an expanded individual post in viewPost in the reply "Post List.")backToReplies (This button returns to the reply "Post List" when viewing a single post from viewPost.)</p>
              <h3>お願い/request</h3>
              <p>書き込みを行うのはとても自由なのですが、内容が刺激的な場合はセンシティブなポストにチェックを入れてからポストをお願いします。/You are very free to write, but if the content is exciting, please check the sensitive post before posting.</p>
              <h3>エラーの対処法について/What to do about the error</h3>
              <p>エラーの対処はとても多いいですし、あなたも対処法はわかりやすいほうが良いでしょう、そのためQ&Aを<a href="https://pips.pusyuuwanko.com/lp#p2" tatget="_blank">プシューIPSの紹介ページ</a>に掲載していますのでそちらをご覧ください。/There are a lot of ways to deal with errors, and you should be able to understand how to deal with them, so please see the Q&A on the <a href="https://pips.pusyuuwanko.com/lp#p2" tatget="_blank">introduction page of PusyuuIPS</a>.</p>
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
                  <?php echo usersSaveContent() ?>
                </ol>
              </div>
            </div>
          </div>
        </div>
      </div>
      <a class="top-right_button" title="次へ/next" href="<?php echo $nextPageUrl; ?>" onclick="nextPage()"><img class="image_iconsize" width="auto" height="auto" src="./assets/images/mark_arrow_right.png" alt="button" oncontextmenu="return false;" onselectstart="return false;" onmousedown="return false;"></img></a>
      <a class="top-left_button" title="戻る/previous" href="<?php echo $prevPageUrl; ?>" onclick="prevPage()"><img class="image_iconsize" width="auto" height="auto" src="./assets/images/mark_arrow_left.png" alt="button" oncontextmenu="return false;" onselectstart="return false;" onmousedown="return false;"></img></a>
      <a href="#modal-1" title="書き込み/write" onclick="setReplyInfo('','');" class="bottom-right_button"><img class="image_iconsize" width="auto" height="auto" src="./assets/images/seizu_pen.png" alt="button" oncontextmenu="return false;" onselectstart="return false;" onmousedown="return false;"></img></a>
      <a href="#modal-2" title="もっと多くの機能/more features" class="bottom-left_button"><img class="image_iconsize" width="auto" height="auto" src="./assets/images/math_mark01_plus.png" alt="button" oncontextmenu="return false;" onselectstart="return false;" onmousedown="return false;"></img></a>
      <div class="action_msg">
        <?php
          if (isset($_SESSION["post_complete_msg"])) {
            echo $_SESSION["post_complete_msg"];
            $_SESSION["post_complete_msg"] = "";
          }
        ?>
      </div>
      <div class="bbs_content">
        <?= $log_text ?>
      </div>
      <section class="adDisp">
      </section>
    </main>
    <footer class="footer">
      <div>
        <h3>ページ概要/Page Summary</h3>
        <p><?php echo $version; ?></p>
        <p>PIPSとはPusyuuImpressionsPostService.の略です。/PIPS stands for Pusyuu Impressions Post Service.</p>
        <p>PIPSは現在、約<?php echo pipsBarsDayCounter(); ?>周年なんだとか、ちなみになぜ小数点以降の数値までも表示させるかというと日にちも含めて、 現在がおおよそ何数年かわかったほうがユニークさがあるかな〜とね。よくある何周年立ったかは一の位の値をみれば一発です。/PIPS is currently about <?php echo pipsBarsDayCounter(); ?> anniversary, and by the way, the reason why the number after the decimal point is displayed is that it would be more unique to know how many years the present is approximately, including the date. The most common number of years is one shot if you look at the value of the first place.</p>
        <p>PIPSについてもっと詳しく<a href="./lp">紹介を見る</a>/Learn more about PIPS<a href="./lp">See introduction</a></p>
        <p><a href="./?time" target="_blank">隠しページ/hidden page</a></p>
      </div>
      <div>
        <h3>プシューのその他のプロダクト一覧/List of other Pushu products</h3>
        <?php echo productLinks(); ?>
      </div>
      <div>
        <h3>唐突のブラウザ情報/Abrupt browser information</h3>
        <?php echo($_SERVER["HTTP_USER_AGENT"]); ?>
      </div>
      <div>
        <h3>規約等/Terms & Conditions</h3>
        <li><a href="https://pusyuuwanko.com/pusyuusystem/pages/terms/term" target="_blank">利用規約</a></li>
        <li><a href="https://pusyuuwanko.com/pusyuusystem/pages/terms/privacypolicy" target="_blank">プライバシーポリシー</a></li>
        <smoll>&copy; 2020-2024 Created By PusyuuWanko/</smoll>
      </div>
    </footer>
  </body>
</html>