/*==== 知名品牌--时尚动态相关js ====*/
var advert_now_page = 1; // 广告位 当前页
var infor_now_page = 1; // 资讯集 当前页
var dynamicElementTab = null;
$(function () {
    // 可拖拽的排序
    var subMovieUl = document.getElementById("subMovieUl"); // 影集图片
    Sortable.create(subMovieUl, {
        animation: 150
    });
    var newsImgBoxUl = document.getElementById("newsImgBoxUl"); //  news图片
    Sortable.create(newsImgBoxUl, {
        animation: 150
    });
    layui.use('element', function(){
        dynamicElementTab = layui.element;
        // 监听(banner图广告/视频广告) Tab切换
        dynamicElementTab.on('tab(bannerVideoTab)', function(data){
            // console.log(data.index)  0 banner图广告  1 视频广告
            // console.log(this.getAttribute('lay-id'));
            // dynamicElementTab.tabChange('bannerVideoTab', '111');
            $("input[name=addAdvertType]").val(data.index +1 );
        });
        // 监听 资讯集 Tab切换
        dynamicElementTab.on('tab(informTypeTab)', function(data){
            // console.log(data.index)  0 banner图广告  1 视频广告
            // console.log(this.getAttribute('lay-id'));
            // dynamicElementTab.tabChange('bannerVideoTab', '111');
            $("input[name=addInformType]").val(data.index);
        });
    });
    //
    layui.use(["upload", "form"], function(){
        let uploadFile = layui.upload;
        let formSubmit = layui.form;
        // 上传广告banner图 (module 有误)
        uploadFile.render({
            elem: '.addAdvertBanner' //绑定元素
            ,url: httpUrl + '/admin/api_base/upload' //上传接口
            ,data: {module:'pop_pro', use:'banner'}
            ,accept: 'images'
            ,done: function(res){
                if(res.status == 1){
                    $("#addEditAdvertisingLayer #bannerCover").show();
                    $("#addEditAdvertisingLayer #bannerCover").attr('src', httpUrl + res.data.src);
                    $("#addEditAdvertisingLayer #addBannerBtn").hide();
                    $("input[name=banner_cover]").val(res.data.src);
                } else {
                    layer.msg(res.msg)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        });
        // 上传视频缩略图 (module 有误)
        uploadFile.render({
            elem: '.addAdvertVideo' //绑定元素
            ,url: httpUrl + '/admin/api_base/upload' //上传接口
            ,data: {module:'pop_pro', use:'cover'}
            ,accept: 'images'
            ,done: function(res){
                if(res.status == 1){
                    $("#addEditAdvertisingLayer #videoCover").show();
                    $("#addEditAdvertisingLayer #videoCover").attr('src', httpUrl + res.data.src);
                    $("#addEditAdvertisingLayer #addVideoCoverBtn").hide();
                    $("input[name=video_cover]").val(res.data.src);
                } else {
                    layer.msg(res.msg)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        });
        // 上传视频 广告位
        uploadFile.render({
            elem: '.addAdvertVideoBtn' //绑定元素
            ,url: httpUrl + '/admin/api_base/uploadVideo' //上传接口
            ,accept: 'video'
            ,field: 'video'
            ,size: 51200 // = 50MB
            ,data: {}
            ,before: function(obj){
                layer.load(); //上传loading
            }
            ,done: function(res){
                layer.closeAll('loading'); //关闭loading
                if(res.status == 1){
                    $(".addAdvertVideoBtn").hide();
                    $("#advertVideoEdit").show();
                    $("#advertVideoCover").show();
                    $("#advertVideoCover").attr("src", res.data.cover_img);
                    $("#addEditAdvertisingLayer input[name=video_url]").val(res.data.video_url);
                    $("#addEditAdvertisingLayer input[name=video_cover]").val(res.data.cover_img);
                } else {
                    layer.msg(res.msg)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        });
        // 资讯集 - 封面
        uploadFile.render({
            elem: '.informCoverB' //绑定元素
            ,url: httpUrl + '/admin/api_base/upload' //上传接口
            ,data: {module:'pop_pro', use:'cover'}
            ,accept: 'images'
            ,done: function(res){
                if(res.status == 1){
                    $("#addEditInformLayer .informCoverImg").show();
                    $("#addEditInformLayer .informCoverImg").attr('src', httpUrl + res.data.src);
                    $("#addEditInformLayer input[name=cover]").val(res.data.src);
                } else {
                    layer.msg(res.msg)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        });
        // 上传视频 资讯集 - 视频
        uploadFile.render({
            elem: '.videoAddBtn' //绑定元素
            ,url: httpUrl + '/admin/api_base/uploadVideo' //上传接口
            ,accept: 'video'
            ,field: 'video'
            ,size: 51200 // = 50MB
            ,data: {}
            ,before: function(obj){
                layer.load(); //上传loading
            }
            ,done: function(res){
                layer.closeAll('loading'); //关闭loading
                if(res.status == 1){
                    $("#type_video #videoAddBtn_icon").hide();
                    $("#type_video #videoShowBox").show();
                    $("#type_video #videoShow").attr("src", res.data.video_url);
                    $("#type_video input[name=video_cover]").val(res.data.cover_img);
                    $("#type_video input[name=video_url]").val(res.data.video_url);
                    $("#type_video input[name=media_id]").val(res.data.video_id);
                } else {
                    layer.msg(res.msg)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        });
        // 上传影集图片
        let imgLeng = 0;
        uploadFile.render({
            elem: '#addImgBoxBtn' //绑定元素
            ,url: httpUrl + '/admin/api_base/upload' //上传接口
            ,data: {module:'pop_pro', use:'cover'}
            ,accept: 'images'
            ,multiple: true // 可多传
            ,number: 9 // 最多上传9张
            ,done: function(res, index){
                // console.log(index);
                if(res.status == 1){
                    imgLeng++;
                    let li = '<li class="">\n' +
                        '           <div class="left-img">\n' +
                        '               <div class="imgcover">\n' +
                        '                   <input type="hidden" name="movieCover" value="'+res.data.src+'">\n' +
                        '                   <img src="'+httpUrl+res.data.src+'" class="img">\n' +
                        '               </div>\n' +
                        '               <div class="cover-status">封面</div>\n' +
                        '               <div class="close-cover" onclick="removeMovieLi(this, true)"><i class="layui-icon">&#x1006;</i></div>\n' +
                        '               <div class="layui-btn layui-btn-primary layui-btn-xs set-cover" onclick="setCover(this)">设置封面</div>\n' +
                        '           </div>\n' +
                        '           <div class="right-desc">\n' +
                        '               <textarea name="desc" class="layui-textarea textarea-desc" placeholder="描述，2-200个字符"></textarea>\n' +
                        '           </div>\n' +
                        '       </li>';
                    $("#subMovieUl").append(li);
                    if (imgLeng >= 9){
                        $("#addImgBox").hide()
                    } else {
                        $("#addImgBox").show()
                    }
                } else {
                    layer.msg(res.msg)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        });
        // 上传news图片
        let newsImgLeng = 0;
        uploadFile.render({
            elem: '#addNewsImgBtn' //绑定元素
            ,url: httpUrl + '/admin/api_base/upload' //上传接口
            ,data: {module:'pop_pro', use:'cover'}
            ,accept: 'images'
            ,multiple: true // 可多传
            ,number: 6 // 最多上传6张
            ,done: function(res, index){
                // console.log(index);
                if(res.status == 1){
                    newsImgLeng++;
                    let li = '<li class="">\n' +
                        '            <input type="hidden" name="newsCover" value="'+res.data.src+'">\n' +
                        '            <div class="news-img">\n' +
                        '                <img src="'+httpUrl+res.data.src+'" >\n' +
                        '            </div>\n' +
                        '            <div class="cover-status">封面</div>\n' +
                        '            <div class="close-cover" onclick="removeMovieLi(this, false)"><i class="layui-icon">&#x1006;</i></div>\n' +
                        '            <div class="layui-btn layui-btn-primary layui-btn-xs set-cover" onclick="setCover(this)">设置封面</div>\n' +
                        '        </li>';
                    $("#newsImgBoxUl").prepend(li);
                    if (newsImgLeng >= 6){
                        $("#addNewsImgBtn").hide()
                    } else {
                        $("#addNewsImgBtn").show()
                    }
                } else {
                    layer.msg(res.msg)
                }
            }
            ,error: function(){
                //请求异常回调
            }
        });
        // 添加(编辑)广告位 banner submit
        formSubmit.on("submit(formAdvertBanner)", function(data){
            let param = data.field;
            let link_url = "";
            if (param.link_type == 1) {
                link_url = param.link_app
            } else if (param.link_type == 2) {
                link_url = param.link_h5
            }
            let brand_id = $("input[name=brand_id]").val();
            let brand_dynamic_id = $("input[name=brand_dynamic_id]").val();
            let editAdvertId = $("#addEditAdvertisingLayer input[name=editAdvertId]").val();
            let post_url = '/admin/brand_api/addBrandDynamicAds';
            let subParam = {
                brand_dynamic_id: brand_dynamic_id,
                title: param.title,
                type: 1,
                url: param.banner_cover,
                link_type: param.link_type,
                link_url: link_url
            };
            if (editAdvertId) {
                post_url = '/admin/brand_api/editBrandDynamicAds';
                subParam.id = editAdvertId;
            }
            ajaxPost(post_url, subParam).then(res => {
                layer.msg('操作成功', {time: 1500}, function(){
                    layer.close(advertLayerOpen);
                    advertList (brand_id, brand_dynamic_id);
                    clearAdvertForm();
                })
            }).catch(err => {});
            return false
        });
        // 添加(编辑)广告位 video submit
        formSubmit.on("submit(formAdvertVideo)", function(data){
            let param = data.field;
            let brand_id = $("input[name=brand_id]").val();
            let brand_dynamic_id = $("input[name=brand_dynamic_id]").val();
            let editAdvertId = $("#addEditAdvertisingLayer input[name=editAdvertId]").val();
            let post_url = '/admin/brand_api/addBrandDynamicAds';
            let subParam = {
                brand_dynamic_id: brand_dynamic_id,
                title: param.title,
                type: 2,
                url: param.video_url,
                cover: param.video_cover,
                link_type: 0,
                link_url: ""
            };
            if (editAdvertId) {
                post_url = '/admin/brand_api/editBrandDynamicAds';
                subParam.id = editAdvertId;
            }
            ajaxPost(post_url, subParam).then(res => {
                layer.msg('操作成功', {time: 1500}, function(){
                    layer.close(advertLayerOpen);
                    advertList (brand_id, brand_dynamic_id);
                    clearAdvertForm();
                })
            }).catch(err => {});
            return false
        });
        // 添加(编辑)资讯集 submit
        formSubmit.on("submit(formInformSubmit)", function(data){
            let brand_id = $("input[name=brand_id]").val();
            let brand_dynamic_id = $("input[name=brand_dynamic_id]").val();
            let types =  $("input[name=addInformType]").val();
            let param = data.field;
            let subParam = {};
            if (types == 0) {
                // 视频
                subParam = {
                    brand_dynamic_id: brand_dynamic_id,
                    title: param.title,
                    cover: param.cover,
                    type: 1,
                    video_url: param.video_url,
                    video_cover: param.video_cover,
                    media_id: param.media_id,
                    media_desc: param.media_desc
                };
            } else if (types == 1) {
                // 影集
                let imgs = [];
                $("#subMovieUl>li").each(function(i, item){
                    imgs.push({
                        url: $(this).find("input[name=movieCover]").val(),
                        desc: $(this).find("textarea[name=desc]").val(),
                        is_cover: $(this).hasClass('active') ? 1 : 0,
                        sort: i+1
                    })
                });
                subParam = {
                    brand_dynamic_id: brand_dynamic_id,
                    title: param.title,
                    cover: param.cover,
                    type: 2,
                    imgs: imgs
                };
            } else if(types = 2) {
                // news
                let imgs = [];
                $("#newsImgBoxUl>li").each(function(i, item){
                    imgs.push({
                        url: $(this).find("input[name=newsCover]").val(),
                        is_cover: $(this).hasClass('active') ? 1 : 0,
                        sort: i+1
                    })
                });
                subParam = {
                    brand_dynamic_id: brand_dynamic_id,
                    title: param.title,
                    cover: param.cover,
                    type: 3,
                    imgs: imgs,
                    content: param.newsContent
                };
            }
            let post_url = '/admin/brand_api/addBrandDynamicArticle';
            if (param.informEditId) {
                post_url = '/admin/brand_api/editBrandDynamicArticle';
                subParam.id = param.informEditId;
            }
            ajaxPost(post_url, JSON.stringify(subParam)).then(res => {
                layer.msg('操作成功', {time: 1500}, function(){
                    layer.close(informLayerOpen);
                    if (!param.informEditId) {// 新增
                        infor_now_page = 1;
                        informList (brand_id, brand_dynamic_id, 1, true);
                    } else {
                        // 编辑
                        informList (brand_id, brand_dynamic_id, infor_now_page, false);
                    }
                    clearInformForm();
                })
            }).catch(err => {})
            return false
        })
    });
    //
    $("#linkUlTab>li").click(function(){
        let index = $("#linkUlTab>li").index(this);
        $(this).addClass('active').siblings().removeClass('active');
        $("#linkInput>input.s-h").eq(index).show().siblings().hide();
        $("input[name=link_type]").val(index);
    })


});
// 时尚动态 layer
function brandDynamicLayer(brandId, brandDynamicId) {
    $("input[name=brand_id]").val(brandId);
    $("input[name=brand_dynamic_id]").val(brandDynamicId);
    $(".xuNum").text(brandDynamicId);
    infor_now_page = 1;
    advertList(brandId, brandDynamicId);
    informList(brandId, brandDynamicId, 1, true);

    setTimeout(() => {
        let open = layer.open({
            type: 1,
            content: $('#brandDynamic'),
            title: '时尚动态',
            area: ['95%', '90%'],
            cancel: function () {
                //右上角关闭回调
                //return false 开启该代码可禁止点击该按钮关闭
                $("input[name=brand_id]").val("");
                $("input[name=brand_dynamic_id]").val("");
            }
        })
    }, 100)
}
// 广告 列表
function advertList (brandId, brandDynamicId) {
    ajaxPost('/admin/brand_api/brandDynamicAdsList',{
        brand_id: brandId,
        brand_dynamic_id: brandDynamicId
    }).then(data => {
        let tr = '';
        if (data.length > 0) {
            for(let i in data){
                let types = data[i].type == 1 ? "Banner广告" : "视频广告";
                let link_type = '';
                if (data[i].link_type == 0) {
                    link_type = "不跳转"
                } else if (data[i].link_type == 1) {
                    link_type = "APP"
                } else if (data[i].link_type == 2) {
                    link_type = "H5"
                }
                let cover = data[i].type == 1 ? httpUrl+data[i].url : data[i].cover;
                tr += '<tr data-index="'+i+'" data-id="'+data[i].id+'" data-sort="'+data[i].sort+'" type="'+data[i].type+'" title="'+data[i].title+'" url="'+data[i].url+'" link_type="'+data[i].link_type+'" link-url="'+data[i].link_url+'" cover="'+data[i].cover+'">\n' +
                    '       <td>'+data[i].id+'</td>\n' +
                    '       <td>'+types+'</td>\n' +
                    '       <td>'+data[i].title+'</td>\n' +
                    '       <td><img src="'+cover+'" class="advert-cover"></td>\n' +
                    '       <td>'+link_type+'</td>\n' +
                    '       <td>'+data[i].link_url+'</td>\n' +
                    '       <td style="width: 200px;">\n' +
                    '           <button type="button" class="layui-btn layui-btn-sm magleft" onclick="editAdvertLayer(this)">\n' +
                    '               <i class="layui-icon">&#xe642;</i>编辑\n' +
                    '           </button>\n' +
                    '           <button type="button" class="layui-btn layui-btn-sm layui-btn-danger magleft" onclick="removeAdvert(this)">\n' +
                    '               <i class="layui-icon">&#xe640;</i>删除\n' +
                    '           </button>\n' +
                    '       </td>\n' +
                    '   </tr>'
            }
        } else {
            tr = '<td colspan="7" class="no-data">暂无数据</td>'
        }
        $("#advertisingTbody").html(tr);
        // 拖拽排序
        var list = document.getElementById("advertisingTbody");
        Sortable.create(list, {
            onUpdate: function(evt){
                // console.log(evt.item)
                let index = $(evt.item).attr('data-index');
                let id = $(evt.item).attr('data-id');
                let sort = $(evt.item).attr('data-sort');
                let jIndex = 0;
                for(let j = 0 ; j < $("#advertisingTbody>tr").length ; j++) {
                    if ($("#advertisingTbody>tr").eq(j).attr('data-index') == index) {
                        // console.log(j); // 当前元素已拖拽到了 这个下标位置
                        jIndex = j;
                        break
                    }
                }
                // console.log('拖拽前位置：', sort);
                // console.log('拖拽后位置：', dataList[jIndex].sort)
                ajaxPost('/admin/brand_api/sortBrandDynamicAds', {
                    id: id ,
                    sort: data[jIndex].sort
                }).then((res) => {

                }).catch((err) => {
                    console.log(err);
                })
            }
        }); // That's all.
    }).catch(err => {});
}
// 资讯集 列表
function informList(brandId, brandDynamicId, page, isPage) {
    ajaxPost('/admin/brand_api/brandDynamicArticleList',{
        page: page,
        brand_id: brandId,
        brand_dynamic_id: brandDynamicId
    }).then(res => {
        let list = res.data;
        let tr = '';
        if (list.length > 0) {
            for(let i in list) {
                let types = '';
                if (list[i].type == 1) {
                    types = "视频"
                } else if (list[i].type == 2) {
                    types = "影集"
                } else if (list[i].type == 3) {
                    types = "news"
                }
                let isChecked = list[i].status==1?"checked":"";
                tr += '<tr data-index="'+i+'" data-id="'+list[i].id+'" data-sort="'+list[i].sort+'">\n' +
                    '      <td>'+list[i].id+'</td>\n' +
                    '      <td>'+list[i].title+'</td>\n' +
                    '      <td>'+types+'</td>\n' +
                    '      <td>'+list[i].visit_number+'</td>\n' +
                    '      <td>\n' +
                    '          <form class="layui-form" action="" lay-filter="infoCheckboxForm">\n' +
                    '              <div class="layui-input-block" style="margin-left: 0;">\n' +
                    '                  <input type="checkbox" name="switch" lay-filter="infoShowHide" value="'+list[i].id+'" lay-skin="switch" '+isChecked+'>\n' +
                    '              </div>\n' +
                    '          </form>\n' +
                    '      </td>\n' +
                    '      <td style="width: 260px;">\n' +
                    '          <button type="button" class="layui-btn layui-btn-sm magleft" onclick="editInformLayer('+list[i].id+')">\n' +
                    '              <i class="layui-icon">&#xe642;</i>编辑\n' +
                    '          </button>\n' +
                    '          <button type="button" class="layui-btn layui-btn-primary layui-btn-sm magleft" onclick="dataTop('+list[i].id+','+brandId+','+brandDynamicId+')" >\n' +
                    '              <i class="layui-icon">&#xe604;</i> 置顶\n' +
                    '          </button>\n' +
                    '          <button type="button" class="layui-btn layui-btn-sm layui-btn-danger magleft" onclick="dataRemove('+list[i].id+','+brandId+','+brandDynamicId+')">\n' +
                    '              <i class="layui-icon">&#xe640;</i>删除\n' +
                    '          </button>\n' +
                    '      </td>\n' +
                    '  </tr>'
            }
            $("#informationPage").show();
            if (isPage) {
                layui.use('laypage', function(){
                    let pages = layui.laypage;
                    //执行一个laypage实例
                    pages.render({
                        elem: 'informationPage' //注意，这里的 test1 是 ID，不用加 # 号
                        ,count: res.total //数据总数，从服务端得到
                        ,groups: 5
                        ,limit: 5
                        ,theme: '#FF5722'
                        ,jump: function(obj, first) {
                            //首次不执行
                            if(!first){
                                infor_now_page = obj.curr;
                                informList(brandId, brandDynamicId, obj.curr, false)
                            }
                        }
                    });
                });
            }
        } else {
            $("#informationPage").hide();
            tr = '<td colspan="6" class="no-data">暂无数据</td>'
        }
        $("#informationTbody").html(tr);
        // checkbox
        form.render('checkbox', 'infoCheckboxForm');
        form.on('switch(infoShowHide)', function(data){
            // let load = layer.load()
            ajaxPost('/admin/brand_api/editBrandDynamicArticleStatus', {
                id: data.value,
                status: data.elem.checked ? 1 : 2
            }).then((res) => {
                // layer.close(load);
            }).catch((err) => {
                console.log(err);
            })
        });
        // 拖拽排序
        var listTbody = document.getElementById("informationTbody");
        Sortable.create(listTbody, {
            onUpdate: function(evt){
                // console.log(evt.item)
                let index = $(evt.item).attr('data-index');
                let id = $(evt.item).attr('data-id');
                let sort = $(evt.item).attr('data-sort');
                let jIndex = 0;
                for(let j = 0 ; j < $("#informationTbody>tr").length ; j++) {
                    if ($("#informationTbody>tr").eq(j).attr('data-index') == index) {
                        // console.log(j); // 当前元素已拖拽到了 这个下标位置
                        jIndex = j;
                        break
                    }
                }
                // console.log('拖拽前位置：', sort);
                // console.log('拖拽后位置：', dataList[jIndex].sort)
                ajaxPost('/admin/brand_api/sortBrandDynamicArticle', {
                    id: id ,
                    brand_dynamic_id: brandDynamicId,
                    sort: list[jIndex].sort
                }).then((res) => {

                }).catch((err) => {
                    console.log(err);
                })
            }
        }); // That's all.

    }).catch(err => {});
}
// 置顶资讯集
function dataTop(id, brandId, brandDynamicId) {
    ajaxPost('/admin/brand_api/topBrandDynamicArticle', {
        id: id,
        brand_dynamic_id: brandDynamicId
    }).then(res => {
        layer.msg('置顶成功', {time: 1500}, function(){
            informList(brandId, brandDynamicId, infor_now_page, false)
        })

    }).catch(err => {

    })
}
// 删除资讯集
function dataRemove(id, brandId, brandDynamicId) {
    layer.confirm('确定要删除该数据吗？', {title: '提示'}, function(){
        // 确认
        ajaxPost('/admin/brand_api/delBrandDynamicArticle', {
            id: id
        }).then((res) => {
            layer.msg('删除成功', {time: 1500}, function(){
                informList(brandId, brandDynamicId, infor_now_page, false)
            })
        }).catch((err) => {
            console.log(err);
        })

    },function(){
        // 取消
    })
}
// 广告位添加 layer
let advertLayerOpen = null;
function showAddAdvertLayer() {
    advertLayerOpen = layer.open({
        type: 1,
        content: $('#addEditAdvertisingLayer'),
        title: '添加广告位',
        area: ['800px', '700px'],
        cancel: function () {
            //右上角关闭回调
            //return false 开启该代码可禁止点击该按钮关闭
            clearAdvertForm()
        }
    })
}
// 编辑广告位
function editAdvertLayer(_this) {
    let id = $(_this).parents("tr").attr("data-id");
    let type = $(_this).parents("tr").attr("type");
    let title = $(_this).parents("tr").attr("title");
    let url = $(_this).parents("tr").attr("url");
    let link_type = parseInt($(_this).parents("tr").attr("link_type"));
    let link_url= $(_this).parents("tr").attr("link-url");
    let cover= $(_this).parents("tr").attr("cover");
    $("#addEditAdvertisingLayer input[name=editAdvertId]").val(id);
    $("#bannerTab input[name=addAdvertType]").val(type);
    if (type == 1) { // banner
        dynamicElementTab.tabChange('bannerVideoTab', 'banner');
        $("#bannerTab input[name=title]").val(title);
        // tu
        $("#addEditAdvertisingLayer #bannerCover").show();
        $("#addEditAdvertisingLayer #bannerCover").attr('src', httpUrl + url);
        $("#addEditAdvertisingLayer #addBannerBtn").hide();
        $("input[name=banner_cover]").val(url);
        //
        $("#linkUlTab>li").eq(link_type).addClass("active").siblings().removeClass("active");
        $("#linkInput>input.s-h").eq(link_type).show().siblings().hide();
        if (link_type == 1) {
            $("input[name=link_app]").val(link_url?link_url:"");
        } else if (link_type == 2) {
            $("input[name=link_h5]").val(link_url?link_url:"");
        }
    } else { // video
        dynamicElementTab.tabChange('bannerVideoTab', 'video');
        $("#videoTab input[name=title]").val(title);
        $(".addAdvertVideoBtn").hide();
        $("#advertVideoEdit").show();
        $("#advertVideoCover").show();
        $("#advertVideoCover").attr("src", cover);
        $("#addEditAdvertisingLayer input[name=video_url]").val(url);
        $("#addEditAdvertisingLayer input[name=video_cover]").val(cover);

    }
    advertLayerOpen = layer.open({
        type: 1,
        content: $('#addEditAdvertisingLayer'),
        title: '编辑广告位',
        area: ['800px', '700px'],
        cancel: function () {
            //右上角关闭回调
            //return false 开启该代码可禁止点击该按钮关闭
            clearAdvertForm()
        }
    })
}
// 删除 广告
function removeAdvert(_this) {
    let brand_id = $("input[name=brand_id]").val();
    let brand_dynamic_id = $("input[name=brand_dynamic_id]").val();
    let id = $(_this).parents("tr").attr("data-id");
    layer.confirm('是否要删除该广告位？', {title: '提示'}, function(){
        // 确认
        ajaxPost('/admin/brand_api/delBrandDynamicAds', {
            id: id
        }).then((res) => {
            layer.msg('删除成功', {time: 1500}, function(){
               advertList(brand_id, brand_dynamic_id)
            })
        }).catch((err) => {
            console.log(err);
        })

    },function(){
        // 取消
    })
}
// 清空广告位form data
function clearAdvertForm() {
    $("#addEditAdvertisingLayer input[name=editAdvertId]").val("");
    $("input[name=addAdvertType]").val("1");
    dynamicElementTab.tabChange('bannerVideoTab', 'banner');
    $("#addEditAdvertisingLayer input[type=text]").val("");
    // 图
    $("#addEditAdvertisingLayer #bannerCover").hide();
    $("#addEditAdvertisingLayer #bannerCover").attr('src', "");
    $("#addEditAdvertisingLayer #addBannerBtn").show();
    $("input[name=banner_cover]").val("");
    // 视频
    $(".addAdvertVideoBtn").show();
    $("#advertVideoEdit").hide();
    $("#advertVideoCover").hide();
    $("#advertVideoCover").attr("src", "");
    $("#addEditAdvertisingLayer input[name=video_url]").val("");
    $("#addEditAdvertisingLayer input[name=video_cover]").val("");
}
// 资讯集添加 layer
let informLayerOpen = null;
function showAddInformLayer() {
    informLayerOpen = layer.open({
        type: 1,
        content: $('#addEditInformLayer'),
        title: '添加资讯集',
        area: ['800px', '95%'],
        cancel: function () {
            //右上角关闭回调
            //return false 开启该代码可禁止点击该按钮关闭
            clearInformForm()
        }
    })
}
// 编辑资讯集 layer
function editInformLayer(id) {
    ajaxPost('/admin/brand_api/brandDynamicArticleInfo',{
        article_id: id
    }).then(res => {
        console.log(res);
        $("#addEditInformLayer input[name=informEditId]").val(res.id);
        $("#addEditInformLayer input[name=title]").val(res.title);
        $("#addEditInformLayer input[name=cover]").val(res.cover);
        $("#addEditInformLayer .informCoverImg").show();
        $("#addEditInformLayer .informCoverImg").attr('src', httpUrl+res.cover);
        if (res.type == 1) {
            dynamicElementTab.tabChange('informTypeTab', 'video');
            $("#type_video input[name=video_cover]").val(res.video_cover);
            $("#type_video input[name=video_url]").val(res.video_url);
            $("#type_video input[name=media_id]").val(res.media_id);
            $("#type_video #videoAddBtn_icon").hide();
            $("#type_video #videoShowBox").show();
            $("#type_video #videoShowBox #videoShow").attr('src', res.video_url);
            $("#type_video textarea[name=media_desc]").val(res.media_desc);
        } else if (res.type == 2) {
            dynamicElementTab.tabChange('informTypeTab', 'images');
            let li = '';
            for(let i in res.pictures) {
                let isActive = res.pictures[i].is_cover == 1 ? "active" : "";
                li += '<li class="'+isActive+'">\n' +
                    '           <div class="left-img">\n' +
                    '               <div class="imgcover">\n' +
                    '                   <input type="hidden" name="movieCover" value="'+res.pictures[i].url+'">\n' +
                    '                   <img src="'+httpUrl+res.pictures[i].url+'" class="img">\n' +
                    '               </div>\n' +
                    '               <div class="cover-status">封面</div>\n' +
                    '               <div class="close-cover" onclick="removeMovieLi(this, true)"><i class="layui-icon">&#x1006;</i></div>\n' +
                    '               <div class="layui-btn layui-btn-primary layui-btn-xs set-cover" onclick="setCover(this)">设置封面</div>\n' +
                    '           </div>\n' +
                    '           <div class="right-desc">\n' +
                    '               <textarea name="desc" class="layui-textarea textarea-desc" placeholder="描述，2-200个字符">'+res.pictures[i].desc+'</textarea>\n' +
                    '           </div>\n' +
                    '       </li>';
            }
            $("#subMovieUl").html(li);
            if (res.pictures.length >= 9) {
                $("#addImgBox").hide();
            } else {
                $("#addImgBox").show();
            }
        } else if (res.type == 3) {
            dynamicElementTab.tabChange('informTypeTab', 'news');
            for(let i in res.news_imgs) {
                let isActive = res.news_imgs[i].is_cover == 1 ? "active" : "";
                let li = '<li class="'+isActive+'">\n' +
                    '            <input type="hidden" name="newsCover" value="'+res.news_imgs[i].img+'">\n' +
                    '            <div class="news-img">\n' +
                    '                <img src="'+httpUrl+res.news_imgs[i].img+'" >\n' +
                    '            </div>\n' +
                    '            <div class="cover-status">封面</div>\n' +
                    '            <div class="close-cover" onclick="removeMovieLi(this, false)"><i class="layui-icon">&#x1006;</i></div>\n' +
                    '            <div class="layui-btn layui-btn-primary layui-btn-xs set-cover" onclick="setCover(this)">设置封面</div>\n' +
                    '        </li>';
                $("#newsImgBoxUl").prepend(li);
            }
            if (res.news_imgs.length >= 6){
                $("#addNewsImgBtn").hide()
            } else {
                $("#addNewsImgBtn").show()
            }
            $("#type_news textarea[name=newsContent]").val(res.content);
        }

        informLayerOpen = layer.open({
            type: 1,
            content: $('#addEditInformLayer'),
            title: '编辑资讯集',
            area: ['800px', '95%'],
            cancel: function () {
                //右上角关闭回调
                //return false 开启该代码可禁止点击该按钮关闭
                clearInformForm()
            }
        })
    }).catch(err => {});

}
// 清空资讯集form data
function clearInformForm() {
    dynamicElementTab.tabChange('informTypeTab', 'video');
    $("#addEditInformLayer input[name=addInformType]").val(0);
    $("#addEditInformLayer input.clearVal").val("");
    $("#addEditInformLayer .informCoverImg").hide();
    $("#addEditInformLayer .informCoverImg").attr("src", "");
    // 视频
    $("#addEditInformLayer .videoAddBtn").show();
    $("#addEditInformLayer #videoShowBox").hide();
    $("#addEditInformLayer #type_video textarea[name=media_desc]").val("");
    // 影集
    $("#subMovieUl").html("");
    $("#addEditInformLayer #type_imgs #addImgBox").show();
    // news
    $("#newsImgBoxUl>li").each(function(){
        $(this).remove()
    });
    $("#newsImgBoxUl #addNewsImgBtn").show();
    $("#type_news textarea[name=newsContent]").val("");
}
//  删除 li
function removeMovieLi(_this, types) {
    $(_this).parents("li").remove();
    if(types){
        // 影集
        $("#addImgBox").show();
    } else {
        // news
        $("#addNewsImgBtn").show();
    }
}
// 影集、news 设置封面
function setCover(_this) {
    $(_this).parents("li").addClass('active').siblings("li").removeClass('active')
}