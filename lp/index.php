<?php
$title = "プシューIPS/PusyuuIPS - PIPSの紹介";
$discreption = "";

function futureList() {
  $futureData = json_decode(file_get_contents("https://pusyuuwanko.com/pusyuusystem/documents/futures.json"), true);
  $link = "";
  if (isset($futureData)  && !empty($futureData)) {
    foreach ($futureData["links"] as $future) {
      $link .= '<li><a href="' . $future["url"] . '" target="_blank">' . $future["title"] . '</a></li>';
    }
  } else {
    $link = "エラー：開発者の設定ミスまたは一時的な読み込みに問題が発生しました。";
  }
  return $link;
}
?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, user-scalable=yes, maximum-scale=6.0, minimum-scale=1.0" />
    <title><?php echo $title; ?></title>
    <meta name="copyright" content="© 2020-2024 created by PusyuuWanko/" />
    <meta name="description" content="<?php echo $discription; ?>" />
    <meta name="copyright" content="© 2020-2024 created by PusyuuWanko/" />
    <meta name="keywords" content="プシューサービス,紹介ページ,プシュー掲示板の使い方,PIPSの使い方,PusyuuIPSの使い方,プシューIPSの使い方,PIPS,プシューIPS,PusyuuIPS,プシュー,Pusyuu,PusyuuWanko/,プシューわんこ/,掲示板,感想を投稿するサイト,自由な投稿,PushyuuService,PIPS,PusyuuIPS,PusyuuIPS,Pusyuu,Bulletin Board,Site to post impressions,Free Submission" />
    <meta name="twitter:card" content="Summary_large_Image" />
    <meta name="twitter:site" content="@PusyuuWanko" />
    <meta name="twitter:title" content="<?php echo $title; ?>" />
    <meta name="twitter:description" content="<?php echo $discription; ?>" />
    <meta name="twitter:image" content="https://pusyuuwanko.com/pusyuusystem/icons/img-23.png" />
    <link rel="shortcut icon" href="https://pusyuuwanko.com/pusyuusystem/images/favicon.ico" />
    <link rel="stylesheet" href="../assets/styles/lp-style.css" />
    <script src="../assets/scripts/lp-script.js"></script>
    <script src="https://pusyuuwanko.com/pusyuusystem/scripts/touch-device-tool-top.js"></script>
    <!--
      /*****************************************
        *----------------------------------
        |  ThisPageVersion: 1.0.2       |
        |  © 2021-2024 By PusyuuWanko/  |
        |  LastUpdate: 2024-10-02       |
        |  License: Apache-2.0 license     |
        |  PIPS of LP...                |
      ----------------------------------*
      ******************************************/
    -->
  </head>
  <body>
    <show>
      <div>
        <h2>PusyuuIPSの紹介へようこそ</h2>
        <p>プシューIPSを始めてから一年がたち様々な機能が追加されてきました。その使い方や、紹介がこの場で行われています。</p>
        <p>このページ(LandingPage)を作成を機にロゴが作成されたのは内緒の話です！！</p>
      </div>
      <img src="../assets/images/lp_image/showImage.png" alt="show image" />
    </show>
    <header>
      <h1><img src="../assets/images/pips_logo.png"></h1>
      <a href="#modal_ham_nav" class="modal_menu-btn">
      <div id="modal_ham_nav">
        <div>
          <a href="#mc"></a>
          <div>
            <nav>
              <ul>
                <li><a href="#p1">プシューIPSって何？</a></li>
                <li><a href="#p2">こんな時は？</a></li>
                <li><a href="#p3">プシューサービスのそのほかのサービス</a></li>
                <li><a href="#p4">お問い合わせをする！！</a></li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </header>
    <main>
      <section class="futures" id="p1">
        <h2>プシューIPSとは何か</h2>
        <article>
          <h3>NO　JSな仕様🏳️‍🌈 </h3>
          <p>JavaScriptが無効な場合でも、基本的な機能が使用できる設計になっています！！</p>
          <img class="zoom" src="../assets/images/lp_image/syoukai-9.png" />
        </article>
        <article>
          <h3>ほかにないほどのカスタマイズ性👍</h3>
          <p>プシュー（PIPSの開発者）が知る限り、背景変更や動作音の変更ができるのはPIPSだけでした！！、そしてPIPSではあなたの好きな壁紙を設定できます！！、他にもpipsでは一番最初に表示される画面のことを、スレッド一覧または投稿一覧またはポスト一覧と表記します、そしてそれらに紐付いてる投稿を返信一覧またはポスト一覧または投稿一覧といいます。なぜこれらの呼び方をするかというと、使い方によって変わるためです。掲示板として使うならスレッドと返信、ブログとして使うなら投稿一覧またはポスト一覧、それ以外の使い方呼び方も可能です。それがこのサービスの長所でもあります。</p>
          <img class="zoom" src="../assets/images/lp_image/syoukai-7.png" />
        </article>
        <article>
          <h3>シンプルなUI設計✨</h3>
          <p>このPIPSでは余計な機能がありません、最低限のメッセージツールと返信、ブックマークと通報機能ぐらいでごちゃごちゃしません！！</p>
          <img class="zoom" src="../assets/images/lp_image/syoukai-4.png" />
        </article>
        <article>
          <h3>宣伝OK📢</h3>
          <p>このPIPSでは宣伝が可能です、普通だとお断りなことが多いですがPIPSなら基本自由に宣伝できますが、秩序維持のためにも下記↓の制限はあります！！</p>
          <h4>宣伝する上での注意点</h4>
          <ol>
            <li>宣伝をする際は他の利用者の邪魔にならないようにすること</li>
            <li>宣伝する際は、プシューサービスの利用規約、日本法、プシューサービスの管理者が許可する範囲内の内容で宣伝を行うこと、やってはならない例”〇〇を◯し引き受けます”のような事柄等</li>
          </ol>
          <video class="zoom" src="../assets/videos/adsok.mp4" autoplay controles muted><span>oppes, you are browser not suport html5 video.</span></video>
        </article>
        <article>
          <h3>感想を見て楽しもう！！🚀</h3>
          <p>PusyuuIPSとは感想を投稿して共有できるサービスです。信憑性や信頼性の低い内容を見て投稿して楽しむサービスです。というぐらい感想を見て楽しむことを目的としています！！<span style="color: #ff0000;">※ですが<strong>あまりにもひどすぎる場合</strong>や<strong>センシティブ要素なのにもかかわらずセンシティブ設定をせずに投稿</strong>した際は安全なサービス運営継続のためにも、<strong>投稿の通報</strong>または<strong>管理者による削除または非表示</strong>が行われる可能性があります。</span></p>
          <img class="zoom" src="../assets/images/lp_image/syoukai-1.png" />
          <div class="center" style="height: 40vh; margin: 10px;">
            <div class="future_button">
              <a href="https://pips.pusyuuwanko.com">PIPSへ行く</a>
            </div>
          </div>
        </article>
      </section>
      <section id="p2">
        <h2>こんな時は？</h2>
        <p>エラーが発生して先に進めない😱。。。や使い方がわからい🤬といった悩みをQ＆A形式で書いてあるのでその方法を使いかい解決できます！！</p>
        <ul class="q_and_a">
          <li><strong>”ボタンが連続で一回以上押されためシステム保護のため処理を終了しました、申し訳なく存じますが最初からやり直してください。/The button was pressed more than once in quick succession. For system protection, the process has been terminated. We apologize for the inconvenience, but please start again from the beginning.”と表示され進めない</strong><span>ボタンは一回のみ押してください。連打や一度以上のクリックやタップを行うとシステムの保護のため表示されてしまいます。</span></li>
          <li><strong>”この項目はすでに保存されています。/This item is already saved.”と表示され保存が行われない。</strong><span>既に保存されていないか確認してみてください。</span></li>
          <li><strong>”メディアファイルが正常に処理されなかったか、壊れているため表示できません。/The media file could not be processed correctly or is corrupted, so it cannot be displayed.”と表示される。</strong><span>投稿者がメディアファイルの対応形式”MP4またはJPG以外のファイルで投稿したためPIPSでは表示できないことを意味しています。</span></li>
          <li><strong>危険なポストを検知しました、申し訳なく存じますがあなたの投稿は表示できません。危険でないと管理者に伝えたい際は、＋ボタン（もっと多くの機能）内のお問い合わせ欄から、再審査して欲しいという旨をお伝えください。/We've detected a dangerous post, we're sorry, but we can't see your post. If you want to tell the administrator that it is not dangerous, please tell them that you would like to be re-examined from the inquiry field in the + button (more functions).と表示されポストの閲覧ができない</strong><span>ポストが投稿される際に禁止用語や管理者により危険だと判断された際にポストの表示を非表示にしたことを意味しています。あなたが投稿者で表示を行ってほしい場合は再審査してほしい旨をPIPSの管理者にお伝えください。</span></li>
          <li><strong>”表示できるページ数を超えました。/The number of pages that can be displayed has been exceeded.”と表示された。</strong><span>ポストのページ数の最大を超えているため表示できるポストがないことを示しています。ポストが表示されるまで戻るボタンを押してください。</span></li>
          <li><strong>”指定されたIDの投稿が見つかりませんでした。/The post with the specified ID was not found.”と表示された。</strong><span>投稿が削除されたまたは投稿そのものが存在しないことを意味しています。指定したIDがあっているかご確認ください。</span></li>
          <li><strong>”メディアファイルが”mp4”または”jpg”ではありません。拡張子をご確認ください。/The media file is not in ”mp4” or ”jpg” format. Please check the file extension.”と表示され投稿ができない。</strong><span>アップロードしようとしてるメディアファイルがMP4またはJPG（大文字小文字区分なし）の
の拡張子になっているかご確認ください。</span></li>
          <li><strong>”メディアファイルが上限の1MB以上です。圧縮または小さいメディアファイルをアップロードしてください。/The media file exceeds the 1MB limit. Please compress the file or upload a smaller media file.”と表示されて投稿ができない。</strong><span>メディアファイルの容量が１MBを超えてしまっている事を示しています。圧縮ソフトでファイルサイズを小さくするか別の容量の小さいファイルをお試しください。おすすめのソフトは<a href="https://imguma.com" target="_blank">あっしゅくま</a>というツールです。ウェブ上でかつブラウザ上でおこなってくれるためプライバシーの心配もないです。</span></li>
          <li><strong> ”メディアファイルのサイズがサーバーにアップロードできない大きさです。/The size of the media file is too large to upload to the server.”と表示された。</strong><span>サーバーで受け付けられないほど大きなメディアファイルを投稿しようとしたためサーバーで拒否されました、あまりにも大きすぎるので小さいファイルをアップロードしてください。</span></li>
          <li><strong> ”メディアファイルのサイズがPIPS上でアップロードできない大きさです。/The size of the media file is too large to upload on PIPS.”と表示された。</strong><span>これはPIPS上で受け付けられるメディアファイルの大きさを超えた際に出るエラーです。そのため小さいファイルのアップロードをご検討ください。</span></li>
          <li><strong> ”メディアファイルのアップロード中に想定していないエラーが発生しました。エラー番号：”数字”番です。/An unexpected error occurred while uploading a media file. Error number: ”number”It's your turn.”と表示された。</strong><span>これはメディアファイルのアップロード処理で思いもよらぬエラーが発生した際に表示されます。もしこのエラーが発生した際は、別のファイルで試すか時間をおいてから試してください。それでもこのエラーが表示される際は、あなたに行えることは何もないので、PIPSの管理者へエラー番号とともにお送りください。</span></li>
          <li><strong>”投稿データが破損しています、管理者にこの事をいち早くお伝えください。お問い合わせはこちらのもっと多くの機能内のあ問い合わせフォームからお伝えください。/The post data is corrupted, please inform the administrator of this as soon as possible. If you have any questions, please contact us using the contact form in More features here.”と表示された。</strong><span>投稿データが破損してることを意味しています。管理者の設定ミスやシステムトラブルが原因な事が多いためあなたにはどうすることもできないため、お問い合わせフォームからそのことをお伝えください。</span></li>
          <li><strong>”⚠このポストはセンシティブな内容です。解除方法を見るにはこのはポストをクリックしてください。/⚠ This post is sensitive. Click on this post to see how to undo it.”と表示された。</strong><span>投稿者がセンシティブ設定を行うことにより表示されます。あなたの見る覚悟があればクリックをして解除方法を閲覧してください。</span></li>
          <li><strong>”JavaScriptが有効ではありません、一部機能が機能しない可能性がございますが、基本的になくても機能するように心がけておりますのでご安心ください。”と表示された。</strong><span>JavaScriptがお使いの環境では古すぎたり無効な可能性があります。ブラウザを最新の状態にするかJavaScriptが有効であるかをご確認ください。※基本的にはJavaScriptがなくてもPIPSの”基本”機能は機能する設計になっていますのでそのままでもお楽しみいただけます。</span></li>
          <li><strong>壁紙設定中に”The file size exceeds the maximum limit of 1MB.”と表示された。</strong><span>壁紙のファイルサイズが1MBであるかをご確認ください。</span></li>
          <li><strong>壁紙を選択したが表示されない</strong><span>誤ったファイル形式を選択していませんか？、ZIPやMP4などの画像以外のファイルはご利用いただけません、画像ファイル（お使いのブラウザが対応してる画像形式）を指定していることをご確認ください。</span></li>
          <li><strong>ヘルプを参照したい</strong><span>＋ボタン（もっと多くの機能）内のヘルプボタンを押すことによりご参照いただけます。</span></li>
        </ul>
      </section>
      <section id="p3">
        <h2>プシューサービスのそのほかのサービス</h2>
        <p>PusyuuIPS以外のサービスを確認することができます。</p>
        <ul>
          <?php echo futureList(); ?>
        </ul>
      </section>
      <section id="p4">
        <h2>お問い合わせ</h2>
        <p>お問い合わせフォームでは、ご感想、やご質問などをプシューに届けることができます。</p>
        <p>名前はハンドルネームや名無し等の好きな名前での送信が可能です。</p>
        <p>使用は、利用規約、プライバシーポリシーに同意し自己責任でよろしくお願いします。</p>
        <p><span style="color: #ff0000; margin-right: 5px;">このフォームの注意事情:</span>個人情報を他人に見られたくない方は個人情報入力をしなくても送信が可能です※必須の部分は空にできないので、上記で説明した内容で記述を行えば送ることができます。暗号化は行われますが、安全な回線を使って送信をよろしくお願いします。</p>
        <div class="center">
          <div class="form_design-1">
            <h3>お問い合わせ 内容入力</h3>
            <p>お問い合わせ内容をご入力の上、「確認画面へ」ボタンをクリックしてください。</p>
            <form class="form" method="post" action="https://pusyuuwanko.com/pusyuusystem/pages/contact/confirm.php" name="form">
              <label>NAME<span style="color: #ff0000; padding-left: 5px;">必須</span></label>
              <input type="text" name="name" placeholder="例）YAMADA-TAROU" value="" />
              <label>E-MAILE<span style="color: #00ff00; padding-left: 5px;">任意</span></label>
              <input type="text" name="email" placeholder="例）guest@example.com" value="" />
              <label>SEX<span style="color: #ff0000; padding-left: 5px;">必須</span></label>
              <div class="yokoori">
                <input type="radio" name="sex" value="男性" checked /><span>男性</span>
                <input type="radio" name="sex" value="女性" /><span>女性</span>
                <input type="radio" name="sex" value="その他" /><span>その他</span>
              </div>
              <label>SELECT INQUIRY<span style="color: #ff0000; padding-left: 5px;">必須</span></label>
              <select name="item">
                <option value="">お問い合わせ項目を選択</option>
                <option value="ご質問・お問い合わせ">ご質問・お問い合わせ</option>
                <option value="ご意見・ご感想">ご意見・ご感想</option>
              </select>
              <label>INQUIRYDETAIL<span style="color: #ff0000; padding-left: 5px;">必須</span></th></label>
              <textarea name="content" rows="5" placeholder="お問合せ内容を入力"></textarea></td>
              <div class="center">
                <button type="submit">確認画面へ</button>
              </div>
            </form>
          </div>
        </div>
      </section>
    </main>
    <footer>
      <smoll>&copy; 2020-2024 Created By PusyuuWanko/</smoll>
    </footer>
  </body>
</html>
