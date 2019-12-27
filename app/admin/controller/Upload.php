<?php


namespace app\admin\controller;


use app\admin\model\UploadConfig;
use sourceUpload\UploadVideo;
use think\Config;
use think\Db;

class Upload extends Admin
{

    public function index(){

        return $this->fetch();

    }

    public function upload(){

        $file = $_FILES['file'];

        $file_path = $file['tmp_name'];

        $res = UploadVideo::uploadLocalVideo($file_path);

        print_r($res);
    }


    /***
     * 上传配置
     */
    public function edit(){
        if($this->request->isPost()){
            $ids = $this->request->post('ids');
            $local_url = $this->request->post('local_url');
            $aliyun_url = $this->request->post('aliyun_url');
            $type = $this->request->post('type');
            $upload_type = $this->request->post('upload_type');
            if(empty($ids) || $ids !=1){
                return $this->error('id不正确');
            }
            $data = [];
            if(!empty($local_url)){
                $data['local_url'] = $local_url;
            }
            if(!empty($aliyun_url)){
                $data['aliyun_url'] = $aliyun_url;
            }
            if(!empty($type) && in_array($type,[1,2])){
                $data['type'] = $type;
            }
            if(!empty($upload_type) && in_array($type,[1,2,3])){
                $data['upload_type'] = $upload_type;
            }
            if(empty($data)){
                return $this->error('修改参数不正确');
            }
            $UploadConfig = new UploadConfig();
            $res = $UploadConfig->allowField(true)->save($data,['id'=>$ids]);
            if($res){
                return $this->success('修改成功');
            }
            return $this->error('修改失败');
        }else{
            $data = Db::table('upload_config')->where(['id'=>1])->find();
            $this->assign('data',$data);
            return $this->fetch();
        }
    }

}