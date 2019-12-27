/**
* Created by lovo_bdk on 2018-11-26.
* wang zhi hong
* 换算：100px/100 = 1rem
*/
//设置自适应  rem
resize();
window.onresize = function () {
    resize()
};

function resize() {
    var docEl = document.documentElement;
    var clientWidth = window.innerWidth;
    if (clientWidth >= 750) {
        docEl.style.fontSize = '100px'
    } else {
        docEl.style.fontSize = 100 * (clientWidth / 750) + 'px'
    }
}