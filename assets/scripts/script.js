/*****************************************
  *----------------------------------
  |  ThisScriptVersion: 4.19.4    |
  |  © 2021-2024 By PusyuuWanko/  |
  |  LastUpdate: 2024-12-23       |
  |  License: Apache-2.0 license      |
  |  PusyuuIPS....                |
----------------------------------*
******************************************/

document.addEventListener("DOMContentLoaded", function() {
  var dateNow = new Date();
  var dateString = dateNow.getFullYear() + "年" + ("0" + (dateNow.getMonth() + 1)).slice(-2) + "月" + ("0" + dateNow.getDate()).slice(-2) + "日";
  var timeString = ("0" + dateNow.getHours()).slice(-2) + ":" + ("0" + dateNow.getMinutes()).slice(-2) + ":" + ("0" + dateNow.getSeconds()).slice(-2);
  var datetimeField = document.getElementById("datetime");
  datetimeField.value = dateString + " " + timeString;
  var subjectInput = document.querySelector('[name="subject"]');
  var nameInput = document.querySelector('[name="name"]');
  var textInput = document.querySelector('[name="text"]');
  var replyInput = document.querySelector('[name="reply_to"]');
  var sendButton = document.querySelector('[type="submit"]');

  subjectInput.addEventListener("input", function() {
    localStorage.setItem("subjectsave", subjectInput.value);
  });
  nameInput.addEventListener("input", function() {
    localStorage.setItem("namesave", nameInput.value);
  });
  textInput.addEventListener("input", function() {
    localStorage.setItem("textsave", textInput.value);
  });
  replyInput.addEventListener("input", function() {
    localStorage.setItem("replysave", replyInput.value);
  });
  subjectInput.value = localStorage.getItem("subjectsave");
  nameInput.value = localStorage.getItem("namesave");
  textInput.value = localStorage.getItem("textsave");
  replyInput.value = localStorage.getItem("replysave");
  sendButton.addEventListener("click", function(event) {
    localStorage.setItem("subjectsave", "");
    localStorage.setItem("textsave", "");
    localStorage.setItem("replysave", "");
    if (textInput.value.trim() === "") {
      event.preventDefault(); // フォーム送信をキャンセル
      alert("フォームに\nコメント\nを入力してから送信してください。\nPlease enter\nComment\nin the form before submitting.");
      location.href = "#modal-1";
    }
  });
  // 再読込ボタン
  let relButton = document.getElementById("reyomikomi");
  if (relButton) {
    relButton.onclick = function() {
      location.reload();
    };
  } else {
    console.error("Element with id 'reyomikomi' not found.");
  }
  // buttonsクラスの設定
  var buttons = document.querySelectorAll('.buttons button');
  buttons.forEach(function(button) {
    button.disabled = false;
  });
  let replyCountDisplay = document.getElementsByClassName("replyCD");
  for (let i = 0; i < replyCountDisplay.length; i++) {
    let result = replyCountDisplay[i];
    result.style.color = "#ff0000";
    if (result.innerHTML > 0) {
      result.style.color = "#0000ff";
      result.style.textShadow = "1px 1px #00aaff";
    }
  }
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
  // センシティブなカードの目隠し処理
  addPreloadedImages();
  var contentElements = document.querySelectorAll('.content');
  contentElements.forEach(function(contentElement) {
    var isSensitive = contentElement.getAttribute('data-sensitivity') === 'true';
    if (isSensitive) {
      contentElement.style.position = 'relative';
      contentElement.style.height = "100%";
      contentElement.style.overflow = "hidden";
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
      overlay.style.cursor = 'pointer';
      overlay.style.height = '100%';
      contentElement.appendChild(overlay);
      var urlParams = new URLSearchParams(window.location.search);
      var idParam = urlParams.get('id');
      var idOne_Param = urlParams.get('id_one_post');
      if (idOne_Param) {
        contentElement.style.height = "";
      }
      if (idParam || idOne_Param) {
        overlay.textContent = '⚠このポストはセンシティブな内容です。クリックすると閲覧できます。/⚠ This post is sensitive. Click to view.';
        contentElement.addEventListener('click', function() {
          contentElement.style.overflow = "";
          contentElement.style.height = "";
          contentElement.style.position = "";
          contentElement.removeChild(overlay);
        });
      }
    }
    
    // 右ボタンのhrefを"#"に設定
    document.querySelectorAll(".top-right_button").forEach(function(el) {
      el.href = "#";
    });

    // 左ボタンのhrefを"#"に設定
    document.querySelectorAll(".top-left_button").forEach(function(el) {
      el.href = "#";
    });
  });

  function modalStopper() {
    const ele = document.querySelector("body");
    if (window.location.hash === "#modal-1" || window.location.hash === "#modal-2" || window.location.hash === "#modal-3" || window.location.hash === "#modal-4" || window.location.hash === "#loading") {
      ele.style.overflow = "hidden";
    } else {
      ele.style.overflow = "unset";
    }
  }
  modalStopper();

  window.addEventListener("popstate", function() {
    modalStopper();
  });
  
  var checkSwitch = document.getElementById("tapsoundswitch");
  var inputEle = [...document.querySelectorAll("input")];
  var textareaEle = [...document.querySelectorAll("textarea")];
  var selectEle = [...document.querySelectorAll("select")];
  var buttonEle = [...document.querySelectorAll("button")];
  var ankerEle = [...document.querySelectorAll("a")];
  var audioFile = new Audio("./assets/audios/effect.mp3");
  var checkSwitchBool = "";

  checkSwitch.checked = localStorage.getItem("checkSwitchSave") === "true";

  function soundLogic() {
    if (checkSwitch.checked === true) {
      audioFile.currentTime = 0;
      audioFile.play();
      checkSwitchBool = true;
    } else {
      checkSwitchBool = false;
    }
    localStorage.setItem("checkSwitchSave", checkSwitchBool)
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

  document.getElementById("noJavaScriptMsg").innerHTML = "";

  function hideTooltip() {
    if (tooltipElement) {
      // フェードアウト効果
      tooltipElement.style.opacity = '0';

      // フェードアウト完了後に削除
      setTimeout(() => {
        if (tooltipElement) {
          tooltipElement.remove();
          tooltipElement = null;
        }
      }, 200); // フェードアウトに合わせたタイミング
    }
  }
  
/*
  var getFetchInfo = Array.prototype.slice.call(document.getElementsByTagName("meta"));
  var fetchUrl = null;
  getFetchInfo.forEach(function(metaNameInfo) {
    if (metaNameInfo.getAttribute("name") === "fetchinfo") { 
      fetchUrl = metaNameInfo.getAttribute("content");
    }
  });
  if (fetchUrl !== null) {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", fetchUrl, true);
    function postDataLogic(postData) {
      console.log(postData);
      localStorage.setItem("savePostNum", postData.item.length);
      setInterval(function() {
        if (parseInt(localStorage.getItem("savePostNum"), 10) < postData.item.length) {
          alert("誰か新しくポスト・スレッドを投稿しました。");
          setTimeout(function() {
            window.location.reload();
          }, 5000);
        }
      }, 500);
    }
    
    
    xhr.onreadystatechange = function() {
      if (xhr.readyState === 4) {
        if (xhr.status === 200) {
          var postData = JSON.parse(xhr.responseText);
          postDataLogic(postData);
        } else {
          console.error(xhr.statusText);
        }
      }
    };
    xhr.send();
  }
*/

var getFetchInfo = Array.prototype.slice.call(document.getElementsByTagName("meta"));
var fetchUrl = null;

getFetchInfo.forEach(function(metaNameInfo) {
  if (metaNameInfo.getAttribute("name") === "fetchinfo") { 
    fetchUrl = metaNameInfo.getAttribute("content");
  }
});

if (fetchUrl !== null) {
  // 古いデータを保持する変数
  let oldPostNum = 0;

  // 定期的にサーバーからデータを取得
  setInterval(function() {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", fetchUrl, true);
    
    xhr.onreadystatechange = function() {
      if (xhr.readyState === 4) {
        if (xhr.status === 200) {
          try {
            var postData = JSON.parse(xhr.responseText);
            
            // 初回データ保存
            if (oldPostNum === 0) {
              oldPostNum = postData.item.length;
              localStorage.setItem("savePostNum", oldPostNum);
            }
            
            // 新しい投稿があれば通知
            if (postData.item.length > oldPostNum) {
              alert("誰か新しくポスト・スレッドを投稿しました。");
              oldPostNum = postData.item.length; // 古いデータを更新
              localStorage.setItem("savePostNum", oldPostNum);

              // 5秒後にページをリロード
              setTimeout(function() {
                if (confirm("最新のポストを読み込むためにリロードしますか？")) {
                  window.location.reload();
                } else {
                  ;
                }
              }, 5000);
            }
          } catch (e) {
            console.error("JSONの解析に失敗しました:", e);
          }
        } else {
          console.error(xhr.statusText);
        }
      }
    };

    xhr.send(); // リクエスト送信
  }, 2000); // 5秒ごとにデータを取得
}

  var mediaFileImgEle = Array.prototype.slice.call(document.querySelectorAll(".post > .wrapper > .content > .media > img"));
  mediaFileImgEle.forEach(function(imgEle) {
    imgEle.addEventListener("click", function() {
      alert("画像の拡大は、viewPostボタンを押すと拡大されます。/The image can be enlarged by pressing the viewPost button.");
    });
  });
  
  loadingDisp();
});

function loadingDisp() {
  window.location.hash = "loading";
  setTimeout(function() {
    const info = "プシューPIPS/PusyuuPIPSへようこそ！！、このサービスでは感想を投稿して共有できるサービスです。信憑性や信頼性の低い内容を見て投稿して楽しむサービスです。ご利用には利用規約プライバシーポリシーへの同意が必要です。\n\nWelcome to Shu PIPS/Pusyuu PIPS!! This service allows you to post and share your impressions. It is a service that enjoys seeing and posting content that is not credible or unreliable. You must agree to the Terms of Use and Privacy Policy.";
    let visitedBefore = localStorage.getItem("MessageBefore");
    if (visitedBefore === null) {
      alert(info + "\n\n最近このサービスの使い方がわからないという問い合わせが急増してるため、次のダイアログでその有無を確認するようにしました。");
      if (confirm("ヘルプの参照方法の説明を表示しますか？\n\n注意：この画面は一度しか表示されません。")) {
        if (confirm("手順：画面左下にある＋ボタンを押し、その中にあるヘルプボタンを押してください、そうすることでヘルプ画面を表示することが可能です。\n\n今すぐヘルプ画面を表示することも可能ですが、今すぐヘルプを表示しますか？")) {
          window.location.hash = "modal-3";
        } else {
          window.location.hash = "";
        }
      } else {
        alert("注意点⚠️：ヘルプの参照方法を再度表示するには、ブラウザのキャッシュ（履歴）を削除し、その後にリロードすると再度メッセージを表示できます。");
        window.location.hash = "";
      }
      localStorage.setItem("MessageBefore", "true")
    } else {
      window.location.hash = "";
    }
  }, 3000);
}

function setReplyInfo(postId, postTitle) {
  var replyInput = document.getElementById("reply_to");
  var subjectInput = document.getElementById("subject");

  // 値を設定
  replyInput.value = postId;
  subjectInput.value = postTitle;

  // 手動でinputイベントを発火
  replyInput.dispatchEvent(new Event('input'));
  subjectInput.dispatchEvent(new Event('input'));
}

function nextPage() {
  var currentPage = parseInt(getParameterByName('page')) || 1;
  var idParam = getParameterByName('id');
  if (idParam !== null) {
    window.location.href = "?id=" + idParam + '&page=' + (currentPage + 1);
  } else {
    window.location.href = './?page=' + (currentPage + 1);
  }
}


function prevPage() {
  var currentPage = parseInt(getParameterByName('page'), 10) || 1;
  var idParam = getParameterByName('id');

  if (idParam !== null) {
    if (currentPage === 1) {
      window.location.href = sessionStorage.getItem("postUrl") ? sessionStorage.getItem("postUrl") : "./";
      sessionStorage.removeItem("postUrl");
    } else {
      window.location.href = "?id=" + encodeURIComponent(idParam) + '&page=' + (currentPage - 1);
    }
  } else {
    window.location.href = './?page=' + (currentPage - 1);
  }
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
  var textarea = document.createElement("textarea");
  textarea.value = text;
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand("copy");
  document.body.removeChild(textarea);
  alert("Link copied to clipboard: " + text);
}
// コンソールへの警告処理
console.log("%c 注意事項：あなた、あるいは他人の情報を盗み出そうとしている人のたくらみですので、コードの入力や開発者ツールの使用を要求されて開いている場合は危険ですので使用を行わないでください。\n\nNote: This is the work of someone who is trying to steal your or someone else's information, so if you are required to enter code or use developer tools, please do not use it, as it is dangerous.", "font-size: 25px; color: #ff0000;");

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
