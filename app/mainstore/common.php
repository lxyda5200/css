<?php
/*
 浏览器打开时设置header头
 $type excel版本类型 Excel5---Excel2003, Excel2007
 $filename 输出的文件名
*/
function browser_excel($type,$filename){
    if($type=="Excel5"){
        header('Content-Type: application/vnd.ms-excel');//告诉浏览器将要输出excel03文件
    }else{
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');//告诉浏览器数据excel07文件
    }
    header('Content-Disposition: attachment;filename="'.$filename.'"');//告诉浏览器将输出文件的名称，要是没有设置，会把当前文件名设置为名称
    header('Cache-Control: max-age=0');//禁止缓存
}
