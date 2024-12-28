/*****************************************
  *----------------------------------
  |  ThisScriptVersion: 1.0.1     |
  |  © 2021-2024 By PusyuuWanko/  |
  |  LastUpdate: 2024-10-04       |
  |  License: Apache-2.0 license     |
  |  PusyuuIPS....                |
----------------------------------*
******************************************/

window.addEventListener("DOMContentLoaded", function() {
  var adsEle = document.querySelectorAll(".adDisp");
  var resultAdsEle = Array.prototype.slice.call(adsEle);
  var content = 
    '<div class="ads">' +
      '<h3>広告欄</h3>' +
      '<p>広告はない方が良いのですが、サービスの存続のためにもご協力をお願いします。※ちなみにクリックしない限り有益にはなりません。<spna style="color: #ffaa00">広告が嫌いな人に朗報です、アドブロックなどを使わなくても、PIPSでは広告をある方法で消せます。まぁ私は教えませんが、その方法は知っている方は知っています。</span></p>' +
      '<div>' +
        '<!-- admax -->' +
          '<div class="admax-ads" data-admax-id="474467ec926fd6bf50a8b68aaa410b59" style="display:inline-block;"></div>' +
        '<!-- admax -->' +
        '<!-- admax -->' +
          '<div class="admax-ads" data-admax-id="4992660c8a7f34f29658a91d3c56d664" style="display:inline-block;"></div>' +
        '<!-- admax -->' +
        '<!-- admax -->' +
          '<div class="admax-ads" data-admax-id="446a1e4f02493f8a8dccb3cc93710e61" style="display:inline-block;"></div>' +
        '<!-- admax -->' +
        '</div>' +
    '</div>'
  ;

  resultAdsEle.forEach(function(ads) {
    ads.innerHTML = content;
    
    var admaxIds = [
      "474467ec926fd6bf50a8b68aaa410b59",
      "4992660c8a7f34f29658a91d3c56d664",
      "446a1e4f02493f8a8dccb3cc93710e61"
    ];
    
    admaxIds.forEach(function(id) {
      var script = document.createElement("script");
      script.type = "text/javascript";
      script.text = '(admaxads = window.admaxads || []).push({admax_id: "' + id + '",type: "banner"});';
      ads.appendChild(script);
    });
    
    var tScript = document.createElement("script");
    tScript.type = "text/javascript";
    tScript.src = "https://adm.shinobi.jp/st/t.js";
    tScript.async = true;
    ads.appendChild(tScript);
  });

  console.log("ads loaded");
});
