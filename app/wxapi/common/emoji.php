
<?php


//对emoji表情转义
function emoji_encode($content){

    $strEncode = '';

    $length = mb_strlen($content,'utf-8');

    for ($i=0; $i < $length; $i++) {
        $_tmpStr = mb_substr($content,$i,1,'utf-8');
        if(strlen($_tmpStr) >= 4){
            $strEncode .= '[[EMOJI:'.rawurlencode($_tmpStr).']]';
        }else{
            $strEncode .= $_tmpStr;
        }
    }

    return $strEncode;
}
//对emoji表情转反义
function emoji_decode($str){
    $strDecode = preg_replace_callback('|\[\[EMOJI:(.*?)\]\]|', function($matches){  
        return rawurldecode($matches[1]);
    }, $str);

    return $strDecode;
}
