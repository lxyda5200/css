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
    watchSlidesProgress : true,
    onProgress: function(swiper, progress){
    for (var i = 0; i < swiper.slides.length; i++){
    var slide = swiper.slides[i];
    es = slide.style;
    es.webkitTransform = es.MsTransform = es.msTransform = es.MozTransform = es.OTransform = es.transform = 'rotate('+360*slide.progress+'deg)';
            }
        }
    })

 let mySwiper2 = new Swiper('#swiper2', {
     spaceBetween: 10,
     onSlideChangeEnd: function(swiper){
        $('.swiper-point li').removeClass('activePoint')
        $('.swiper-point li').eq(this.swiper).addClass('activePoint')
      }
 })
 setTimeout(function () {
     $('.main-site').remove()
     $('.w-wrap').css('opacity', '1')
     mySwiper.update()
     mySwiper2.update()
 }, 300)
 //获取参数
 function getQueryString(name) {
     let reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
     let r = decodeURI(window.location.search).substr(1).match(reg);
     if (r != null)
         return unescape(r[2]);
     return null;
 }
 let _id = getQueryString("group_id")
 //得到当前页面数据
 $.post(base + "/user_v6/dynamic/LifeSilhouetteDetails", {
     group_id: _id
 }, function (resp) {
     let res = resp.data
     let storeInfo = res.dynamic_circle
     let str = ''
     let html1 = ''
     let html3 = ''
     let aside = {
         shouChang: 0,
         dianZan: 0,
         share: 0
     }
     let listData = res.list
     for (let i = 0, j = listData.length; i < j; i++) {
         str = str + '+' + listData[i].scene //拿到拼接xx篇
         aside.shouChang += parseInt(listData[i].collect_number) //合计收藏
         aside.dianZan += parseInt(listData[i].like_number) //合计点赞
         aside.share += parseInt(listData[i].share_number) //合计分享
         html1 += `<div class="swiper-slide">
                    <img src="${base + listData[i].cover}" class="swiperImg">
                </div>` //拿到轮播图数据
         switch (listData[i].dynamic_img.length) {
             case 1:
                 let tags = ''
                 if (listData[i].dynamic_img[0].type == 1) {
                     tags = `<div class="vedio-box">
                                <img src="${base + listData[i].dynamic_img[0].img_url}" alt="" class="videoImg">
                            </div>`
                 } else {
                     tags = `<div class="vedio-box">
                                <img src="${listData[i].dynamic_img[0].cover}" alt="" class="videoImg">
                                <img src="./img12.png" alt="" class="openVideoImg">
                                <img src="./img13.png" alt="" class="shengYinImg">
                            </div>`
                 }
                 html3 += ` <div class="sport public-box imgOne">
                <div class="public-title">
                    <img src="${base + listData[i].scene_icon}">
                    <span class="spanOne">${listData[i].scene + '篇'}</span>
                    <span class="spanTwo">${listData[i].scene_desc}</span>
                </div>
                <div class="store-logo">
                    <img src="${base + listData[i].store_logo}" class="w-logo">
                    <div class="store-name-box">
                        <div class="store-name shengLve">${listData[i].store_name}</div>
                        <div class="store-dec shengLve">${listData[i].signature}</div>
                    </div>
                    <div class="dianZan">
                        <img src="./img6.png">
                        <span>${listData[i].hot}</span>
                    </div>
                </div>` +
                     tags +
                     ` <div class="public-dec">
                        <p>${(listData[i].description).substr(0,40)+'...'}</p>
                        <div>
                            <span>详情</span>
                            <img src="./img8.png">
                        </div>
                    </div>
                    <div class="public-showSomeThing">
                        <div>
                            <img src="./img9.png" alt="">
                            <span>${listData[i].dynamic_store}</span>
                        </div>
                        <div>
                            <img src="./img10.png" alt="">
                            <span>${listData[i].dynamic_product}</span>
                        </div>
                    </div>
            </div>`
                 break;
             case 2:
                 let imgs = listData[i].dynamic_img.reduce((result, item) => {
                     result.push(base + item.img_url)
                     return result
                 }, [])
                 html3 += ` <div class="public-box imgTwo">
                <div class="public-title">
                    <img src="${base + listData[i].scene_icon}">
                    <span class="spanOne">${listData[i].scene + '篇'}</span>
                    <span class="spanTwo">${listData[i].scene_desc}</span>
                </div>
                <div class="store-logo">
                    <img src="${base + listData[i].store_logo}" class="w-logo">
                    <div class="store-name-box">
                        <div class="store-name shengLve">${listData[i].store_name}</div>
                        <div class="store-dec shengLve">${listData[i].signature}</div>
                    </div>
                    <div class="dianZan">
                        <img src="./img6.png">
                        <span>${listData[i].hot}</span>
                    </div>
                </div>
                <div class="chuXing-img">
                    <img src="${imgs[0]}">
                    <img src="${imgs[1]}">
                </div>
                <div class="yue-hui-dec public-dec">
                    <p>${(listData[i].description).substr(0,40)+'...'}</p>
                    <div>
                        <span>详情</span>
                        <img src="./img8.png">
                    </div>
                </div>
                <div class="yue-hui-showSomeThing public-showSomeThing">
                    <div>
                        <img src="./img9.png" alt="">
                        <span>${listData[i].dynamic_store}</span>
                    </div>
                    <div>
                        <img src="./img10.png" alt="">
                        <span>${listData[i].dynamic_product}</span>
                    </div>
                </div>
            </div>`
                 break;
             case 3:
                 let imgsThree = listData[i].dynamic_img.reduce((result, item) => {
                     result.push(base + item.img_url)
                     return result
                 }, [])
                 html3 += `<div class="public-box imgThree">
                <div class="public-title">
                    <img src="${base + listData[i].scene_icon}">
                    <span class="spanOne">${listData[i].scene + '篇'}</span>
                    <span class="spanTwo">${listData[i].scene_desc}</span>
                </div>
                <div class="store-logo">
                    <img src="${base + listData[i].store_logo}" class="w-logo">
                    <div class="store-name-box">
                        <div class="store-name shengLve">${listData[i].store_name}</div>
                        <div class="store-dec shengLve">${listData[i].signature}</div>
                    </div>
                    <div class="dianZan">
                        <img src="./img6.png">
                        <span>${listData[i].hot}</span>
                    </div>
                </div>
                <div class="show-img">
                    <div class="show-img-L">
                        <img src="${imgsThree[0]}" alt="">
                    </div>
                    <div class="show-img-R">
                        <img src="${imgsThree[1]}" alt="">
                        <img src="${imgsThree[2]}" alt="">
                    </div>
                </div>
                <div class="yue-hui-dec public-dec">
                    <p>${(listData[i].description).substr(0,40)+'...'}</p>
                    <div>
                        <span>详情</span>
                        <img src="./img8.png">
                    </div>
                </div>
                <div class="yue-hui-showSomeThing public-showSomeThing">
                    <div>
                        <img src="./img9.png" alt="">
                        <span>${listData[i].dynamic_store}</span>
                    </div>
                    <div>
                        <img src="./img10.png" alt="">
                        <span>${listData[i].dynamic_product}</span>
                    </div>
                </div>
            </div>`
                 break;
             default:
                 let imgsFour = listData[i].dynamic_img.reduce((result, item) => {
                     result.push(base + item.img_url)
                     return result
                 }, [])
                 html3 += `  <div class="yue-hui-box imgFour">
                <div class="yue-hui-title public-title">
                    <img src="${base + listData[i].scene_icon}">
                    <span class="spanOne">${listData[i].scene + '篇'}</span>
                    <span class="spanTwo">${listData[i].scene_desc}</span>
                </div>
                <div class="store-logo">
                    <img src="${base + listData[i].store_logo}" class="w-logo">
                    <div class="store-name-box">
                        <div class="store-name shengLve">${listData[i].store_name}</div>
                        <div class="store-dec shengLve">${listData[i].signature}</div>
                    </div>
                    <div class="dianZan">
                        <img src="./img6.png">
                        <span>${listData[i].hot}</span>
                    </div>
                </div>
                <div class="yue-hui-img">
                    <img src="${imgsFour[0]}">
                    <img src="${imgsFour[1]}">
                    <img src="${imgsFour[2]}">
                    <img src="${imgsFour[2]}">
                    <span>${imgsFour.length}</span>
                </div>
                <div class="yue-hui-dec public-dec">
                    <p>${(listData[i].description).substr(0,40)+'...'}</p>
                    <div>
                        <span>详情</span>
                        <img src="./img8.png">
                    </div>
                </div>
                <div class="yue-hui-showSomeThing public-showSomeThing">
                    <div>
                        <img src="./img9.png" alt="">
                        <span>${listData[i].dynamic_store}</span>
                    </div>
                    <div>
                        <img src="./img10.png" alt="">
                        <span>${listData[i].dynamic_product}</span>
                    </div>
                </div>
            </div>`

         }
     }
     $('#swiper1 .swiper-wrapper').html(html1)
     $('.asideImg .asideImg-item').eq(0).find('span').html(aside.shouChang)
     $('.asideImg .asideImg-item').eq(1).find('span').html(aside.dianZan)
     $('.asideImg .asideImg-item').eq(2).find('span').html(aside.share)
     $(html3).appendTo('.wz-wrap')
     $('.str').html(str.substr(1) + '篇')
     let html2 = ''
     let business = res.dynamic_circle
     if (business.length != 0) {
         for (let i = 0, j = business.length; i < j; i++) {
             $('<li></li>').appendTo($('.swiper-point'))
             html2 += `<div class="swiper-slide">
                            <div class="store-dec-box">
                                <div class="store-img">
                                    <img src="${base + business[i].img_url}">
                                </div>
                                <div class="store-dec">
                                    <div class="dec-T shengLve">${business[i].circle_name}</div>
                                    <div class="dec-C">
                                        <img src="./img4.png">
                                        <span>${business[i].visit_number}</span>
                                    </div>
                                    <div class="dec-B">
                                        <img src="./img5.png">
                                        <span class="shengLve">${business[i].address}</span>
                                    </div>
                                </div>
                            </div>
                        </div>`
         }
         $('#swiper2 .swiper-wrapper').html(html2)
     } else {
         $('.correlation').remove()
     }
 })

 $('body').on('click',
     '.swiper-slide,.store-logo,.asideImg-item,.w-logo,.vedio-box,.public-dec div,.public-showSomeThing>div,.show-img,.yue-hui-img,.chuXing-img',
     function () {
        $('.w-model').show()
     })

