/*****************************************
  *----------------------------------
  |  ThisStyleVersion: 1.0.1      |
  |  © 2021-2024 By PusyuuWanko/  |
  |  LastUpdate: 2024-11-27       |
  |  License: Apache-2.0 license     |
  |  PIPS of LP...                |
----------------------------------*
******************************************/

window.addEventListener("DOMContentLoaded" ,()=> {
  const ele = [...document.getElementsByClassName("zoom")];
  const body = [...document.getElementsByTagName("body")][0];
  ele.forEach((item,index)=> {
    item.style.cursor = "pointer";
    item.addEventListener("click", ()=> {
      window.location.hash = "#zoom-img-" + index;
    });
    const zoomImageEle = document.createElement("div");
    const ext = item.src.split('.').pop().toLowerCase(); // 拡張子を取得
    const isMp4 = ext === 'mp4';
    const isImg = ext === 'jpg' || ext === 'png';
    zoomImageEle.innerHTML = `
      <div class="modal" id="zoom-img-${index}">
        <div>
          <a href="#mc"></a>
          <div>
            <span>拡大表示</span>
            <div>
              ${
                isMp4 
                ? `<video style="width: 1000px; height: auto;" autoplay muted loop>
                     <source src="${item.src}" type="video/mp4">
                   </video>` 
                : isImg 
                ? `<img style="width: 1000px; height: auto; object-fit: cover;" src="${item.src}" />`
                : `<p>Unsupported file format</p>`
              }
            </div>
          </div>
        </div>
      </div>
    `;
    body.appendChild(zoomImageEle);
  });
  
  window.addEventListener("hashchange", function() {
    if (window.location.hash.startsWith("#zoom-img-")) {
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "unset";
    }
  });
});
