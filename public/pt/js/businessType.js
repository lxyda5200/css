"use strict";$(".w-header").load("./compontents/header.html"),$(".w-footer").load("./compontents/footer.html"),$(function(){var e=!1;$(".gouImg").click(function(){e=0==e?($(this).attr("src","./images/gou_bg.png"),!0):($(this).attr("src","./images/gou.png"),!1)}),$("#delModel").click(function(){$(".model").hide()}),$("#delModelVip").click(function(){$(".model").hide()}),$("#puLayerOpenBtn").click(function(){$("#puLayerOpen").show()}),$("#puLayerOpenVipBtn").click(function(){$("#vipLayerOpen").show()})});