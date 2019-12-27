<?php

namespace app\admin\tool;

use think\Config;
use think\Controller;
use app\common\controller\Email;

class Tool extends Controller
{
    /**
     * 审核结果通知邮件
     * @param $email //邮箱
     * @param $name //审核结果
     * @param string $title //邮件标题
     */
    public static function ton_email($email, $name, $title = '')
    {
        $config = Config::get('email_config');
        if (empty($email) || empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $html = '<!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="keywords" content="服饰,美妆,引流,电商,流量,购物,潮流,资讯" />
    <meta name="viewport" content="user-scalable=yes">
    <link rel="stylesheet" href="'.$config['mail_url'].'/static/email/emailModel.css">
    <meta name="description"
          content="四川神龟科技有限公司成立于2017年05月，旗下平台“超神宿APP”，着力于服饰与美妆领域，通过互联网连接实体门店与消费者，为品牌和用户提供优质服务。超神宿对用户进行精准分析，通过线上商品的展示和销售，有效地为实体商家实现线上至线下引流，以及品牌线上新品发布、活动促销和推广宣传等。旗下项目“流量小店”，有效整合各经销商、生产商的资源，以便利店的形式在全国范围内开设直营与加盟店。依托线上平台的庞大流量，将线上平台与线下门店相互结合，为用户提供更加便捷的购物体验。" />
    <title>四川神龟科技-商家平台-审核结果</title>
</head>
<body>
<div class="h-content">
    <p class="m1">尊敬的超神宿商家：</p>
    <div class="content-box">
        <div class="top">
            <p class="m2">'. $name . '</p>
            <p class="m2">详情请登录超神宿商家平台查看，</p>
            <p class="m2">商家平台地址：<a href="'.$config['mail_url'].'/check_result"><span class="color-link">'.$config['mail_url'].'/check_result</span></a></p>
        </div>
        <div class="btm">
            <img src="http://wx.supersg.cn/static/email/logo_two.png" class="logo-img">
            <p class="m3">官网：<a href="'.$config['mail_url'].'"><span class="color-link">'.$config['mail_url'].'</span></a></p>
            <p class="m3">地址：成都市高新区天府大道中段666号希顿国际广场B座702</p>
            <p class="m3">联系电话：028-85255310</p>
            <p class="sm">我们的使命是让实体品牌重塑品牌价值</p>
        </div>
    </div>
</div>
</body>
</html>';
        if (empty($title)) {
            $title = $name;
        }
        return self::setemail($email, $html, $title);
    }


    /**
     * 发送邮件
     * @param $email //邮箱
     * @param $html //内容
     * @param $title //标题
     * @return bool
     */
    public static function setemail($email, $html, $title)
    {
        $emails = new Email();
        $result = $emails->to($email)
            ->subject($title)
            ->message($html)
            ->send();
        return $result;
    }
}