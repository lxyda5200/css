
var httpUrl = base;

function ajaxPost(url, param) {
    return new Promise(function(resolve, reject){
        $.ajax({
            url: httpUrl + url,
            type: 'POST',
            dataType: 'JSON',
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

let userAgent = window.navigator.userAgent;
let isPage = userAgent.indexOf('app_chaoshensu') >= 0 ? "app" : "webApp"; // 判断在app中还是在webapp中
// 跳转推荐店铺
function linkStoreFn(_this) {
    let store_id = $(_this).attr("id");
    window.location.href = 'chaoshensu://chao.shen.com/store?store_id='+store_id

}
// 跳转推荐商品
function linkProductFn(_this){
    let product_id = $(_this).attr("id");
    window.location.href = 'chaoshensu://chao.shen.com/product?product_id='+product_id

}
// 跳转更多回复
function moreReplyFn(_this) {
    let comment_id = $(_this).attr("id");
    window.location.href = 'chaoshensu://chao.shen.com/moreReply?comment_type=1&detail_id='+detailsId+'&comment_id='+comment_id

}
// 跳转更多评论
function moreCommentFn() {
    window.location.href = 'chaoshensu://chao.shen.com/moreComment?comment_type=1&detail_id='+detailsId

}

// 跳转分享
function shareFn() {
    if (token) {
        window.location.href = 'chaoshensu://chao.shen.com/share';
        ajaxPost('/user_v6/dynamic/NewTrendShare', {
            user_id: user_id,
            token: token,
            id: detailsId
        }).then(res => {}).catch(err => {})
    } else {
        // 登录页
        window.location.href = 'chaoshensu://chao.shen.com/login'
    }
}

// 收藏
function collectFn() {
    if (isPage == "app") {
        if(token) {
            ajaxPost('/user_v6/dynamic/NewTrendColletOrCancel',{
                user_id: user_id,
                token: token,
                status: detailsDatainfo.is_collect ==0 ? 1 : -1,
                new_trend_id: detailsId
            }).then(res => {
                getData(detailsId, false);
            }).catch(err => {})
        } else {
            // 登录页
            window.location.href = 'chaoshensu://chao.shen.com/login'
        }
    } else {
        // 跳转至App
        window.location.href = 'chaoshensu://chao.shen.com'
    }
}
// 点赞
function linkFn() {
    if (isPage == "app") {
       if(token) {
           ajaxPost('/user_v6/dynamic/NewTrendDianzanOrCancel',{
               user_id: user_id,
               token: token,
               status: detailsDatainfo.is_dianzan ==0 ? 1 : -1,
               new_trend_id: detailsId
           }).then(res => {
               getData(detailsId, false);
           }).catch(err => {})
       } else {
           // 登录页
           window.location.href = 'chaoshensu://chao.shen.com/login'
       }
    } else {
        // 跳转至App
        window.location.href = 'chaoshensu://chao.shen.com'
    }
}
$(function(){
    // 评论
    $(document).keyup(function(event){
        if(event.keyCode == 13){
            if (isPage == "app") {
                if (token) {
                    let con = $("input[name=comment]").val();
                    if (con == '') {
                        alert("请输入评论内容");
                        return false
                    }
                    ajaxPost('/user_v6/dynamic/NewTrendComment',{
                        user_id: user_id,
                        token: token,
                        new_trend_id: detailsId,
                        content: con
                    }).then(res => {
                        $("input[name=comment]").val("");
                        getData(detailsId, false);
                    }).catch(err => {})
                } else {
                    // 登录页
                    window.location.href = 'chaoshensu://chao.shen.com/login'
                }
            } else {
                // 跳转至App
                window.location.href = 'chaoshensu://chao.shen.com'
            }
        }
    });
});