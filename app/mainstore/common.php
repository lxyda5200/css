<?php
/*
 �������ʱ����headerͷ
 $type excel�汾���� Excel5---Excel2003, Excel2007
 $filename ������ļ���
*/
function browser_excel($type,$filename){
    if($type=="Excel5"){
        header('Content-Type: application/vnd.ms-excel');//�����������Ҫ���excel03�ļ�
    }else{
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');//�������������excel07�ļ�
    }
    header('Content-Disposition: attachment;filename="'.$filename.'"');//���������������ļ������ƣ�Ҫ��û�����ã���ѵ�ǰ�ļ�������Ϊ����
    header('Cache-Control: max-age=0');//��ֹ����
}
