<?php

return [

    'uploads_path' => ROOT_PATH .'public'. DS .'uploads'. DS .'user'. DS,     //图片上传地址

    'img_path' => DS .'uploads'. DS .'user'. DS,      //图片保存地址

    'ali_oss' => [     //阿里

        'AccessKeyID' => 'LTAIFkbFHlk7uez1',   //access_key

        'AccessKeySecret' => 'jkqMlbeGlIX8jswn1pcOXxiekOtHZ1',   //access_key_secret

        'bucket' => 'outin-0c2782419caa11e9a95e00163e1c955c.oss-cn-beijing.aliyuncs.com',  //bucket

        'group' => [   //分组
            'video' => [
                'cms' => '1000070734'
            ]
        ],

        'msg_sign_name' => '神龟科技',

        'msg_template_code' => [

            'ac_clock' => 'SMS_175535253',

            'order_repay_90' => 'SMS_175580479',

            'order_repay_60' => 'SMS_175580343',

            'send_success' => 'SMS_180356577',

            'send_fail' => 'SMS_180341876'

        ],

    ],

    'video_type' => [  //视频格式

        'mp4','mov','m4v','3gp','avi','m3u8','webm'

    ]

];
