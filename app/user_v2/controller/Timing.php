<?php
@file_get_contents(SERVICE_FX.'/user/Task/cancelLongOrder'); //自动取消长租订单
@file_get_contents(SERVICE_FX.'/user/Task/cancelShortOrder'); //自动取消短租订单
@file_get_contents(SERVICE_FX.'/user/Task/cancelProductOrder'); //自动取消商品订单
@file_get_contents(SERVICE_FX.'/user/Task/xiajiaPorudct'); //自动下架预购商品
@file_get_contents(SERVICE_FX.'/user/Task/cancelPtOrder'); //拼团失败自动取消拼团订单
@file_get_contents(SERVICE_FX.'/user/Task/cancelChaodaPtOrder'); //拼团失败自动取消拼团订单
@file_get_contents(SERVICE_FX.'/user/Task/confirmOrder'); //自动确认收货
@file_get_contents(SERVICE_FX.'/user/Task/shopping_cart'); //
?>