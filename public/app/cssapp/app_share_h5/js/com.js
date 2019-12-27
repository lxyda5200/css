let httpUrl = 'http://wx.supersg.cn';
function ajaxPost(url, param) {
    return new Promise(function(resolve, reject){
        $.ajax({
            url: httpUrl + url,
            type: 'POST',
            dataType: 'JSON',
            // headers: {
            //     'Content-Type': 'application/x-www-form-urlencoded'
            // },
            data: param,
            success: function(res) {
                resolve(res)
            },
            error: function(err) {
                reject(err)
            }
        })
    })
}
function getUrlParams(name) { // 不传name返回所有值，否则返回对应值
    var url = window.location.search;
    if (url.indexOf('?') == 1) { return false; }
    url = url.substr(1);
    url = url.split('&');
    var name = name || '';
    var nameres;
    // 获取全部参数及其值
    for(var i=0;i<url.length;i++) {
        var info = url[i].split('=');
        var obj = {};
        obj[info[0]] = decodeURI(info[1]);
        url[i] = obj;
    }
    // 如果传入一个参数名称，就匹配其值
    if (name) {
        for(var i=0;i<url.length;i++) {
            for (const key in url[i]) {
                if (key == name) {
                    nameres = url[i][key];
                }
            }
        }
    } else {
        nameres = url;
    }
    // 返回结果
    return nameres;
}
// 跳转app(带一个参数，值为id，或者不带参数)
// function goto(url) {
//     setTimeout(function () {
//         // 如没安装app
//         $('.w-model').show()
//     }, 500);
//     if (url.indexOf('?')>=0) {
//         url = url + '=' + id
//     }
//     // console.log('chaoshensu://chao.shen.com/'+url)
//     window.location = 'chaoshensu://chao.shen.com/'+url; //打开自己的应用
// }
// // 店铺动态详情跳转app
// function gotoStoreDetails() {
//     let url = 'dynamicDetail?id='+id+'&scene_id='+scene_id;
//     setTimeout(function () {
//         // 如没安装app
//         $('.w-model').show()
//     }, 500);
//     // console.log('chaoshensu://chao.shen.com/'+url)
//     window.location = 'chaoshensu://chao.shen.com/'+url; //打开自己的应用
// }
function openApp() {
    const u = navigator.userAgent;
    let ua = u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/) ? 'ios' : 'android'
    if (ua === 'ios') {
        window.location.href = 'itms-apps://itunes.apple.com/app/id1440450216?mt=8'
    }
    if (ua === 'android') {
        window.location.href = 'https://a.app.qq.com/o/simple.jsp?pkgname=com.bingfor.cxs'
    }
}
// banner swiper
function bannerSwiper() {
    new Swiper ('#swiper10', {
        initialSlide: 0,
        speed: 300,
        autoplay: true,
        grabCursor: true, // 手势
        autoHeight: true,
        pagination: {
            el: '.swiper-pagination',
            bulletClass: 'banner-bullet',
            bulletActiveClass: 'banner-bullet-active',
            clickable: true
        },
        on: {
            slideChange: function() {
                // let index = this.activeIndex
                // mySwiper.slideTo(index, 1000, false); // 切换到第一个slide，速度为1秒
                // $(".banner-bullet").eq(index).addClass('banner-bullet-active').siblings().removeClass('banner-bullet-active')
            }
        }

    })
}
// login ===============================
function showLogin() {
    $('.w-model').show()
}
$(function(){
    let timer = '';
    let canClick = true;
    let cansubmit = true;
    //点击取消模态框
    $('#hiddenModel').click(function () {
        $('.w-model').hide();
        clearTimeout(timer);
        $('.w-code').html("获取验证码")
    })
    //点击发送验证码
    $('.w-code').click(function () {
        if ($('#w-phone').val().trim() == '') {
            $('.w-hint').html('手机号不能为空');
            return
        } else {
            if (canClick == true) {
                canClick = false
                $.post(httpUrl + "/user_v6/user/getVerifyCodeNew", {
                    mobile: $('#w-phone').val()
                }, function (resp) {
                    if(resp.status==1){
                        let codeDom = $('.w-code');
                        let time = 60;
                        codeDom.html(time + 's后可再次获取');
                        timer = setInterval(() => {
                            if (time > 1) {
                                time--;
                                codeDom.html(time + "s后可再次获取")
                            } else {
                                clearInterval(timer);
                                canClick = true;
                                codeDom.html("获取验证码")
                            }
                        }, 1000);
                    } else {
                        $('.w-hint').html(resp.msg)
                    }
                })

            }
        }
    });
    //点击登录
    $('#loginBtn').click(function () {
        if ($('#w-phone').val().trim() == '') {
            $('.w-hint').html('手机号不能为空');
            return
        }
        if ($('#wCode').val().trim() == '') {

            $('.w-hint').html('验证码不能为空');
            return
        }
        if (cansubmit == true) {
            cansubmit = false
            setTimeout(() => {
                cansubmit = true
            }, 2000);
            let user_id = getUrlParams('user_id');
            $.post(httpUrl + "/user_v6/user/verifyCodeLogin", {
                mobile: $('#w-phone').val(),
                code: $('#wCode').val(),
                invitation_user_id: user_id,
                type: 1
            }, function (resp) {
                if (resp.status == 0) {
                    $('.w-hint').html(resp.msg)
                } else {
                    //登录成功
                    openApp()
                    // window.location.href = 'http://appwx.supersg.cn/app/download.html'
                }
            })
        }
    })

});
function add(m){return m<10?'0'+m:m }
function time(value){
    let time = new Date(value*1000);
    let year = time.getFullYear();
    let month = time.getMonth()+1;
    let date = time.getDate();
    let hours = time.getHours();
    let minutes = time.getMinutes();
    let seconds = time.getSeconds();
    return year+'.'+add(month)+'.'+add(date)+' '+add(hours)+':'+add(minutes);
    // return year+'-'+add(month)+'-'+add(date)+' '+add(hours)+':'+add(minutes)+':'+add(seconds);
};  //2017-05-08 10:31:27