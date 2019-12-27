 //域名
 const base = 'http://wx.supersg.cn'
 //设置自适应 b
 resize()
 window.onresize = function () {
     resize()
 }

 function resize() {
     var docEl = document.documentElement
     var clientWidth = window.innerWidth
     if (clientWidth >= 750) {
         docEl.style.fontSize = '100px'
     } else {
         docEl.style.fontSize = 100 * (clientWidth / 750) + 'px'
     }
 }

 let mySwiper = new Swiper('#swiper1', {
     pagination: {
         el: '.swiper-pagination',
         bulletClass: 'point-css',
         bulletActiveClass: 'point-active-css'
     }
 })
 setTimeout(function () {
     $('.main-site').remove()
     $('.wz-wrap').css('opacity', '1')
     mySwiper.update()
 }, 300)
 //获取参数
function getQueryString(name) {
    let reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
    let r = decodeURI(window.location.search).substr(1).match(reg);
    if (r != null)
        return unescape(r[2]);
    return null;
}
let _id = getQueryString("id")
 //商圈头部的数据
 $.post(base + "/user_v6/dynamic/BusinessCircle", {
     id: _id
 }, function (resp) {
     let res = resp.data
     $('.store-address p').html(res.circle_name)
     $('.store-address span').html(res.address)
     $('.guanZhu span').html(res.visit_number)
     let html = ''
     let imgs = res.images
     for (let i = 0, j = imgs.length; i < j; i++) {
         html += ` <div class="swiper-slide">
       <img src="${base + imgs[i]}" class="swiperImg">
   </div>`
     }
     $('.swiper-wrapper').html(html)
 })
 //商圈各店铺的数据
 $.post(base + "/user_v6/dynamic/BusinessCircleDynamicList", {
     id: _id,
     page: 1,
     size: 5
 }, function (resp) {
     let list = resp.data.data
     let html = ''
    for (let i = 0, j = list.length; i < j; i++) {
         let tag1 = ` <div class="item">
                 <div class="store-logo">
                     <div class="logo-img">
                         <img src="${base + list[i].store_logo}" alt="">
                     </div>
                     <dl>
                         <dt>${list[i].store_name}</dt>
                         <dd>
                             <img src="./img3.png" alt="">
                             <span class="shengLve">${list[i].address}</span>
                         </dd>
                     </dl>
                 </div>`
         let tag2 = ''
         for (let i2 = 0, j2 = list[i].brand_story; i2 < j2; i2++) {
             if (list[i].brand_story[i2][0].is_show == 1 && list[i].brand_story[i2][1].is_show == 1) {
                 tag2 = `<div class="tags">
                            <span>#${list[i].brand_story[i2][0].title}</span>
                            <span>#${list[i].brand_story[i2][1].title}</span>
                        </div>`
             } else if (list[i].brand_story[i2][1].is_show == 1 && list[i].brand_story[i2][1].is_show == 0) {
                 tag2 = `<div class="tags">
                                    <span>#${list[i].brand_story[i2][0].title}</span>
                                </div>`
             } else if (list[i].brand_story[i2][1].is_show == 0 && list[i].brand_story[i2][1].is_show == 1) {
                 tag2 = `<div class="tags">
                                    <span>#${list[i].brand_story[i2][1].title}</span>
                                </div>`
             }

         }
         let tag3 = ''
         for (let i3 = 0, j3 = list[i].user_look_list.length; i3 < j3; i3++) {
             if (i3 <= 2) {
                 tag3 += `<div class="guanzhu-people">
                     <img src="${list[i].user_look_list[i3].avatar}" alt="">
                 </div>`
             }
         }
         
         switch (list[i].dynamic_images.length) {
             case 1:
                 let imgss = ''
                 if (list[i].dynamic_images[0].type == 1) {
                     if(list[i].topic_title != null){
                        imgss = `<div class="vedio-box">
                        <img src="${base + list[i].dynamic_images[0].img_url}" alt="" class="videoImg">
                    </div>
                    <div class="cont-box">
                        <span>#${list[i].topic_title}#</span>
                        <p>${list[i].description}</p>
                    </div>`
                     }else{
                        imgss = `<div class="vedio-box">
                        <img src="${base + list[i].dynamic_images[0].img_url}" alt="" class="videoImg">
                    </div>
                    <div class="cont-box">
                        <p>${list[i].description}</p>
                    </div>`
                     }
                    

                 } else {
                    if(list[i].topic_title != null){
                        imgss = `<div class="vedio-box">
                        <img src="${list[i].dynamic_images[0].cover}" alt="" class="videoImg">
                        <img src="./img12.png" alt="" class="openVideoImg">
                        <img src="./img13.png" alt="" class="shengYinImg">
                    </div>
                    <div class="cont-box">
                        <span>#${list[i].topic_title}#</span>
                        <p>${list[i].description}</p>
                    </div>`
                    }else{
                        imgss = `<div class="vedio-box">
                        <img src="${list[i].dynamic_images[0].cover}" alt="" class="videoImg">
                        <img src="./img12.png" alt="" class="openVideoImg">
                        <img src="./img13.png" alt="" class="shengYinImg">
                    </div>
                    <div class="cont-box">
                        <p>${list[i].description}</p>
                    </div>`
                    }
                    
                 }
                 html += tag1 + imgss + tag2 + `
                        <div class="footer">
                            <div class="guanzhu-num">` + tag3 + `<span>${list[i].look_number}</span></div>
                            <div class="guanzhu">
                                <img src="./img4.png" alt="">
                                <span>${list[i].like_number}</span>
                            </div>
                            <div class="share">
                                <img src="./img5.png" alt="">
                                <span>分享</span>
                            </div>
                        </div>
                    </div>`
                 break;
             case 2:
                 let imgTwo = list[i].dynamic_images.reduce((result, item) => {
                     result.push(base + item.img_url)
                     return result
                 }, [])
                 html += tag1 +
                     `<div class="chuXing-img">
                            <img src="${imgTwo[0]}">
                            <img src="${imgTwo[1]}">
                        </div>` +
                     tag2 + `
                        <div class="footer">
                            <div class="guanzhu-num">` + tag3 + `<span>${list[i].look_number}</span></div>
                            <div class="guanzhu">
                                <img src="./img4.png" alt="">
                                <span>${list[i].like_number}</span>
                            </div>
                            <div class="share">
                                <img src="./img5.png" alt="">
                                <span>分享</span>
                            </div>
                        </div>
                    </div>`
                 break;
             case 3:
                 let imgThree = list[i].dynamic_images.reduce((result, item) => {
                     result.push(base + item.img_url)
                     return result
                 }, [])
                 html += tag1 +
                     `  <div class="show-img">
                        <div class="show-img-L">
                            <img src="${imgThree[0]}" alt="">
                        </div>
                        <div class="show-img-R">
                            <img src="${imgThree[1]}" alt="">
                            <img src="${imgThree[2]}" alt="">
                        </div>
                    </div>` +
                     tag2 +
                     `<div class="footer">
                            <div class="guanzhu-num">` + tag3 + `<span>${list[i].look_number}</span></div>
                            <div class="guanzhu">
                                <img src="./img4.png" alt="">
                                <span>${list[i].like_number}</span>
                            </div>
                            <div class="share">
                                <img src="./img5.png" alt="">
                                <span>分享</span>
                            </div>
                        </div>
                    </div>`
                 break;
             case 4:
                 let imgFour = list[i].dynamic_images.reduce((result, item) => {
                     result.push(base + item.img_url)
                     return result
                 }, [])
                 html += tag1 +
                     `  <div class="yue-hui-img">
                            <img src="${imgFour[0]}" class="mr01 mb01">
                            <img src="${imgFour[1]}" class="mb01">
                            <img src="${imgFour[2]}" class="mr01">
                            <img src="${imgFour[2]}">
                            <span>${imgFour.length}</span>
                        </div>` +
                     tag2 +
                     `<div class="footer">
                                <div class="guanzhu-num">` + tag3 + `<span>${list[i].look_number}</span></div>
                                <div class="guanzhu">
                                    <img src="./img4.png" alt="">
                                    <span>${list[i].like_number}</span>
                                </div>
                                <div class="share">
                                    <img src="./img5.png" alt="">
                                    <span>分享</span>
                                </div>
                            </div>
                        </div>`
                 break;
         }
     }
     $('.wzf-wrap').html(html)
 })

 $('body').on('click','.store-address,.guanZhu,.store-logo,.vedio-box,.chuXing-img,.show-img,.yue-hui-img,.guanzhu-people,.guanzhu,.share,.loadMore',function(){
    $('.w-model').show()
})