// function getSelectValue() {   //获取省市县/区在area.js配置的地区编码
//     var province = document.getElementById("province").value;
//     var city = document.getElementById("city").value;
//     var area = document.getElementById("area").value;
//
//
//     alert(province.split('_', 1));
//     alert(city.split('_', 1));
//     alert(area);
// }
//初始数据
let areaData = Area;
let formArea;
$(function(){
    layui.use(['form'], function() {
        formArea = layui.form;
        loadProvince();
    });
});
//加载省数据
function loadProvince() {
    var proHtml = '';
    for (var i = 0; i < areaData.length; i++) {
        proHtml += '<option value="' + areaData[i].provinceCode + '_' + areaData[i].mallCityList.length + '_' + i +'">' + areaData[i].provinceName + '</option>';
    }
    //初始化省数据
    $('select[name=province]').append(proHtml);
    formArea.render();
    formArea.on('select(province)', function(data) {
        $('select[name=area]').html('<option value="">请选择县/区</option>').parent().hide();
        var value = data.value;
        var d = value.split('_');
        var code = d[0];
        var count = d[1];
        var index = d[2];
        if (count > 0) {
            loadCity(areaData[index].mallCityList);
        } else {
            $('select[name=city]').parent().hide();
        }
    });
}
//加载市数据
function loadCity(citys) {
    var cityHtml = '';
    for (var i = 0; i < citys.length; i++) {
        cityHtml += '<option value="' + citys[i].cityCode + '_' + citys[i].mallAreaList.length + '_' + i +'">' + citys[i].cityName + '</option>';
    }
    $('select[name=city]').html(cityHtml).parent().show();
    formArea.render();
    formArea.on('select(city)', function(data) {
        var value = data.value;
        var d = value.split('_');
        var code = d[0];
        var count = d[1];
        var index = d[2];
        if (count > 0) {
            // loadArea(citys[index].mallAreaList);
        } else {
            $('select[name=area]').parent().hide();

        }
    });
}
//加载县/区数据
function loadArea(areas) {
    var areaHtml = '';
    for (var i = 0; i < areas.length; i++) {
        areaHtml += '<option value="' + areas[i].areaCode + '">' + areas[i].areaName + '</option>';
    }
    $('select[name=area]').html(areaHtml).parent().show();
    formArea.render();
    formArea.on('select(area)', function (data) {
    });
}