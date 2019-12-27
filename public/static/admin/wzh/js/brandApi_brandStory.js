/*==== 知名品牌--品牌故事相关 js ====*/
$(function (){
    layui.use(["upload"], function(){
        let upFile = layui.upload;
        // 广告位 上传图片
        upFile.render({
            elem: '#addAdvertImg' //绑定元素
            ,url: httpUrl + '/admin/api_base/upload' //上传接口
            ,data: {module:'pop_pro', use:'bg'}
            ,accept: 'images'
            ,size: 1024
            ,done: function(res){
                if(res.status == 1){
                    let str = '<li class="video-img img" data-type="1" data-url="'+res.data.src+'">\n' +
                        '          <img src="'+httpUrl+res.data.src+'">\n' +
                        '          <div class="layui-btn close" onclick="removeImgVideo(this)"><i class="layui-icon">&#x1006;</i></div>\n' +
                        '      </li>';
                    $("#advertUl").append(str);
                    let length = $("#advertUl>li").length;
                    if (length >= 4) {
                        $("#addAdvertImg").hide();
                        $("#addAdvertVideo").hide();
                    }
                } else {
                    layer.msg(res.msg)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        });
        // 广告位 上传视频
        upFile.render({
            elem: '#addAdvertVideo' //绑定元素
            ,url: httpUrl + '/admin/api_base/uploadVideo' //上传接口
            ,data: {}
            ,accept: 'video'
            ,field: 'video'
            ,size: 5120
            ,before: function(){
                layer.load();
            }
            ,done: function(res){
                layer.closeAll('loading');
                if(res.status == 1){
                    // media_id 视频媒体id
                    let str = '<li class="video-img video" data-type="2" data-url="'+res.data.video_url+'" data-cover="'+res.data.cover_img+'" data-mediaid="'+res.data.video_id+'">\n' +
                        '          <img src="'+res.data.cover_img+'">\n' +
                        '          <i class="layui-icon video-icon">&#xe652;</i>\n' +
                        '          <div class="layui-btn close" onclick="removeImgVideo(this)"><i class="layui-icon">&#x1006;</i></div>\n' +
                        '      </li>';
                    $("#advertUl").append(str);
                    let length = $("#advertUl>li").length;
                    if (length >= 4) {
                        $("#addAdvertImg").hide();
                        $("#addAdvertVideo").hide();
                    }
                } else {
                    layer.msg(res.msg)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        })
    })
});
// 品牌故事
function brandStoryLayer(brandId, brandStoryId) {
    ajaxPost('/admin/brand_api/brandStoryInfo',{
        id: brandStoryId
    }).then(res => {
        console.log(res);
        // 广告位img video
        let banner_str = '';
        if (res.banner_list.length > 0) {
            for(let i in res.banner_list) {
                if (res.banner_list[i].type == 1) {
                    // 图片
                    banner_str += '<li class="video-img img" data-type="1" data-url="'+res.banner_list[i].url+'">\n' +
                        '              <img src="'+httpUrl+res.banner_list[i].url+'">\n' +
                        '              <div class="layui-btn close" onclick="removeImgVideo(this)"><i class="layui-icon">&#x1006;</i></div>\n' +
                        '          </li>';
                } else {
                    // 视频
                    banner_str += '<li class="video-img video" data-type="2" data-cover="'+res.banner_list[i].cover+'" data-url="'+res.banner_list[i].url+'" data-mediaid="'+res.banner_list[i].media_id+'">\n' +
                        '              <img src="'+res.banner_list[i].cover+'">\n' +
                        '              <i class="layui-icon video-icon">&#xe652;</i>\n' +
                        '              <div class="layui-btn close" onclick="removeImgVideo(this)"><i class="layui-icon">&#x1006;</i></div>\n' +
                        '          </li>';
                }
            }
            if (res.banner_list.length >= 4) {
                $("#addAdvertImg").hide();
                $("#addAdvertVideo").hide();
            } else {
                $("#addAdvertImg").show();
                $("#addAdvertVideo").show();
            }
        } else {
            $("#addAdvertImg").show();
            $("#addAdvertVideo").show();
        }
        $("#advertUl").html(banner_str);
        //
        $("textarea[name=history]").val(res.history?res.history:"");
        $("textarea[name=notion]").val(res.notion?res.notion:"");
        // 经典款
        let product_str = '';
        if (res.product_list) {
            for(let j in res.product_list){
                product_str += '<li id="'+res.product_list[j].product_id+'">\n' +
                    '              <div class="close" onclick="removeActiveGoods(this)">\n' +
                    '                   <button type="button" class="layui-btn closebtn">\n' +
                    '                       <i class="layui-icon">&#x1006;</i>\n' +
                    '                   </button>\n' +
                    '               </div>' +
                    '               <img src="'+httpUrl+res.product_list[j].specs.cover+'" class="goods-cover">\n' +
                    '               <div class="goods-desc">\n' +
                    '                   <div class="goods-name textSplit2">'+res.product_list[j].specs.product_name+'</div>\n' +
                    '                   <div class="goods-price">价格：<span class="price">￥'+res.product_list[j].specs.price+'</span></div>\n' +
                    '               </div>\n' +
                    '           </li>'
            }
            if (res.product_list.length >= 4) {
                $("#activeGoodsUl>div.add-goods-btn").hide()
            } else {
                $("#activeGoodsUl>div.add-goods-btn").show()
            }
        } else {
            $("#activeGoodsUl>div.add-goods-btn").show()
        }
        $("#activeGoodsUl").prepend(product_str);
        let open = layer.open({
            type: 1,
            content: $('#brandStory'),
            title: '品牌故事',
            btn: ["确认", "取消"],
            area: ['955px', '700px'],
            yes: function() {
                let banners = [];
                $("#advertUl>li").each(function(i ,item){
                    if (parseInt($(this).attr("data-type")) == 1) {
                        banners.push({
                            type: 1,
                            url: $(this).attr("data-url")
                        })
                    } else if(parseInt($(this).attr("data-type")) == 2) {
                        banners.push({
                            type: 2,
                            url: $(this).attr("data-url"),
                            cover: $(this).attr("data-cover"),
                            media_id: $(this).attr("data-mediaid"),
                        })
                    }
                });
                let products = [];
                $("#activeGoodsUl>li").each(function(i, item){
                    products.push({
                        product_id: $(this).attr("id")
                    })
                });
                if (banners.length <= 0) {
                    layer.msg('请上传图片或视频');
                    return
                }
                let history = $("textarea[name=history]").val();
                if (history.length > 500) {
                    layer.msg('品牌历史不能超出500个字符');
                    return
                }
                let notion = $("textarea[name=notion]").val();
                if (notion.length > 500) {
                    layer.msg('品牌理念不能超出500个字符');
                    return
                }
                let params = {
                    brand_id: brandId,
                    brand_story_id: brandStoryId,
                    banners: banners,
                    history: history,
                    notion: notion,
                    products: products
                };
                ajaxPost('/admin/brand_api/editBrandStory', JSON.stringify(params)).then(res => {
                    layer.msg('操作成功', {time: 1500}, function(){
                        layer.close(open);
                        clearBrandStoryForm();
                        brandList.getDataList(now_page, false)
                    })
                }).catch(erro => {})

            },
            btn2: function() {
                clearBrandStoryForm()
            },
            cancel: function () {
                clearBrandStoryForm()
                //右上角关闭回调
                //return false 开启该代码可禁止点击该按钮关闭
            }
        })
    }).catch(err => {})

}
//
function removeImgVideo(_this) {
    $(_this).parents("li").remove();
    $("#addAdvertImg").show();
    $("#addAdvertVideo").show();
}
// 清空品牌故事form的数据
function clearBrandStoryForm() {
    $("#advertUl").html("");
    $("#addAdvertImg").show();
    $("#addAdvertVideo").show();
    $("textarea[name=history]").val("");
    $("textarea[name=notion]").val("");
    $("#activeGoodsUl>li").each(function(i, item){
       $(this).remove()
    });
}
// show goods
function showGoodsListLayer() {
    $("input[name=keywords]").val("");
    getGuanGoodsList(1, "", true);
    let open = layer.open({
        type: 1,
        content: $('#goodsListLayer'),
        title: '商品列表',
        btn: ["确认", "取消"],
        area: ['90%', '75%'],
        yes: function() {
            let isData = false;
            let goods_str = ''
            $("#goodslistUl>li").each(function(i, item){
                if($(this).find('.radio-i').hasClass('active')) {
                    isData = true;
                    goods_str += '<li id="'+$(this).attr('id')+'">\n' +
                        '              <div class="close" onclick="removeActiveGoods(this)">\n' +
                        '                   <button type="button" class="layui-btn closebtn">\n' +
                        '                       <i class="layui-icon">&#x1006;</i>\n' +
                        '                   </button>\n' +
                        '               </div>' +
                        '               <img src="'+httpUrl+$(this).attr('cover')+'" class="goods-cover">\n' +
                        '               <div class="goods-desc">\n' +
                        '                   <div class="goods-name textSplit2">'+$(this).attr('product_name')+'</div>\n' +
                        '                   <div class="goods-price">价格：<span class="price">￥'+$(this).attr('price')+'</span></div>\n' +
                        '               </div>\n' +
                        '           </li>'
                }
            });
            if(isData){
                $("#activeGoodsUl").prepend(goods_str);
                if($("#activeGoodsUl>li").length >= 4){
                    $("#activeGoodsUl>div.add-goods-btn").hide()
                }
                layer.close(open)
            } else {
                layer.msg('请选择商品')
            }
        },
        btn2: function() {},
        cancel: function () {
            //右上角关闭回调
            //return false 开启该代码可禁止点击该按钮关闭
        }
    })

}
// get关联商品列表
function getGuanGoodsList(page, keywords, isPage) {
    ajaxPost('/admin/brand_api/productList', {
        page: page,
        keywords: keywords,
    }).then((res) => {
        let dataList = res.data;
        let liStr = '';
        if (dataList.length > 0) {
            for(let i = 0 ; i < dataList.length ; i++){
                liStr += '<li class="goodslistLi" cover="'+dataList[i].specs.cover+'" mobile="'+dataList[i].mobile+'" store_name="'+dataList[i].store_name+'" product_name="'+dataList[i].product_name+'" address="'+dataList[i].address+'" read_number="'+dataList[i].read_number+'" id="'+dataList[i].id+'" price="'+dataList[i].specs.price+'" onclick="selectedGoodsFn(this)">\n' +
                    '                <div class="gl-goods-item">\n' +
                    '                    <i class="layui-icon radio-i">&#xe63f;</i>\n' +
                    '                    <div class="imgbox">\n' +
                    '                        <img src="'+httpUrl+dataList[i].specs.cover+'" alt="">\n' +
                    '                        <span class="phone">\n' +
                    '                            <i class="fa fa-phone fa-lg"></i>\n' +
                    '                            '+dataList[i].mobile+'\n' +
                    '                        </span>\n' +
                    '                    </div>\n' +
                    '                    <div class="goodsinfo">\n' +
                    '                        <div class="goodsname textSplit2">'+dataList[i].store_name+'</div>\n' +
                    '                        <div class="goodsdesc textSplit2">\n' +
                    '                            '+dataList[i].product_name+'\n' +
                    '                        </div>\n' +
                    '                        <div class="goodsaddr textSplit1">\n' +
                    '                            <i class="fa fa-map-marker fa-lg"></i>\n' +
                    '                            '+dataList[i].address+'\n' +
                    '                        </div>\n' +
                    '                    </div>\n' +
                    '                </div>\n' +
                    '            </li>'
            }
            $("#goodsPage").show();
            if(isPage){
                layui.use('laypage', function(){
                    var laypage = layui.laypage;
                    //执行一个laypage实例
                    laypage.render({
                        elem: 'goodsPage' //注意，这里的 test1 是 ID，不用加 # 号
                        ,count: res.total //数据总数，从服务端得到
                        ,limit: 12
                        ,theme: '#FF5722'
                        ,jump: function(obj, first) {
                            //首次不执行
                            if(!first){
                                getGuanGoodsList(obj.curr, keywords, false)
                            }
                        }
                    });
                });
            }
        } else {
            liStr = '<li class="no-data" style="width: 100%">暂无数据</li>'
            $("#goodsPage").hide()
        }
        $("#goodslistUl").html(liStr);
        callback()
    }).catch((err) => {
        console.log(err);
    })
}
// 搜索商品
function searchGoodslist(){
    let keywords = $("input[name=keywords]").val();
    getGuanGoodsList(1, keywords, true);
}
// 选择商品
function selectedGoodsFn(_this) {
    let num = 0;
    let activeNum = parseInt($("#activeGoodsUl>li").length);
    $("#goodslistUl>li").each(function(i, item){
        if($(this).find('.radio-i').hasClass('active')) {
            num +=1
        }
    });
    if ($(_this).find('.radio-i').hasClass('active')) {
        $(_this).find('.radio-i').removeClass('active');
        $(_this).find('.radio-i').html('&#xe63f;');
    } else {
        if (num+activeNum >= 4) {
            layer.msg('最多设置4个商品');
            return
        }
        $(_this).find('.radio-i').addClass('active');
        $(_this).find('.radio-i').html('&#x1005;');
    }
}
// 删除 选中的商品
function removeActiveGoods(_this){
    $(_this).parents('ul.active-goods-ul ').find('div.add-goods-btn').show();
    $(_this).parents('li').remove();
}