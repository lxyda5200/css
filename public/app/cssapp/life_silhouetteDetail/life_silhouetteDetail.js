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

let mySwiper = new Swiper ('#swiper1', {
    on: {
        slideChangeTransitionEnd: function(){
           $('.swiper-point li').removeClass('activePoint')
           $('.swiper-point li').eq(this.activeIndex).addClass('activePoint')
        },
    }
})
setTimeout(function(){
    $('.main-site').remove()
    $('.wz-wrap').css('opacity','1')
    mySwiper.update()
},300)

function getQueryString(name) {
    let reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
    let r = decodeURI(window.location.search).substr(1).match(reg);
    if (r != null)
        return unescape(r[2]);
    return null;
}
let _id = getQueryString("group_id")

let dynamicId = getQueryString("dynamic_id")
//得到当前页面数据
$.post(base + "/user_v6/dynamic/LifeSilhouetteDetails", {
    group_id: _id
}, function (resp) {
    let listData = resp.data.list.filter(item => item.id == dynamicId)[0]
    let html = ''
    if(listData.dynamic_img.length == 1){
        if(listData.dynamic_img[0].type == 1){
            html = `<div class="swiper-slide">
            <img src="${base + listData.dynamic_img[0].img_url}" class="swiperImg">
        </div>`
        $('.swiper-point').html('<li class="activePoint"></li>')
        }else{
            html = ` <div class="vedio-box">
            <img src="${listData.dynamic_img[0].cover}" alt="" class="videoImg">
            <img src="./img12.png" alt="" class="openVideoImg">
            <img src="./img13.png" alt="" class="shengYinImg">
        </div>`
            let _html = ''
            for(let i=0,j=listData.dynamic_img.length;i<j;i++){
                _html += '<li></li>'
            }
            $('.swiper-point').html(_html)
            $('.swiper-point li').eq(0).addClass('activePoint')
        }
    }else{
        let imgs = listData.dynamic_img.reduce((result,item) =>{
            result.push(item.img_url)
            return result
        },[])
        for(let i=0,j=imgs.length;i<j;i++){
            html+= ` <div class="swiper-slide">
            <img src="${imgs[i]}" class="swiperImg">
        </div>`
        }
    }
    $('.swiper-wrapper').html(html)
    $('.logo-img').attr('src',base + listData.store_logo)
    $('.shouCang span').html(listData.collect_number)
    $('.pingLun span').html(listData.comment_number)
    $('.dianZan span').html(listData.like_number)
    $('.fenXiang span').html(listData.share_number)
    $('.car span').html(listData.dynamic_product + '件')
    let html2 = ''
    if(listData.dynamic_style.length !=0){
        for(let i=0,j=listData.dynamic_style.length;i<j;i++){
            html2+= `<li>${listData.dynamic_style[i]}</li>`
        }
        $('.tags').html(html2)
    }else{
        $('.tags').remove()
    }
    if(listData.topic == '' || listData.topic == null){
        $('.cont-title').remove()
    }else{
        $('.cont-title').html('#'+listData.topic+'#')
    }
    $('.cont-dec').html(listData.description)
})

$('body').on('click',
'.via .shouCang,.pingLun,.dianZan,.vedio-box,.fenXiang ,.car,.tags>li',function(){
    $('.w-model').show()
})