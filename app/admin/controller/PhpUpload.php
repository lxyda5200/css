<?php


namespace app\admin\controller;


use sourceUpload\UploadVideo;
use think\Exception;

class PhpUpload
{

    public function index(){

        return $this->fetch();

    }

    public function upload(){
        try{
            $file = request()->file('video');
            if($file){
                $path = $file->getRealPath();
                $ext = explode('/',$file->getInfo('type'))[1];
                $data = file_get_contents($path);
                $path2 = "uploads/video_temp/test.{$ext}";
                file_put_contents($path2,$data);
                if(file_exists("uploads/video_temp/test.{$ext}")){
                    $res = UploadVideo::uploadLocalVideo($path2);
                    if(!$res)throw new Exception('上传失败');
                    $data = [
                        'video_url' => $res['path'],
                        'cover_img' => $res['path'] . "?x-oss-process=video/snapshot,t_4000,m_fast"
                    ];
//                    $video_id = $res['media_id'];
////                    ##获取封面
////                    $res2 = $this->listSnapshots($video_id);
                    return json(self::callback(1,'',$data));
                }
            }
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    public function getUploadInfo($video_id=0){
        if(!$video_id)$video_id = input('video_id','3ce278c4eba246779f955ba5bceed4dd');
        $playInfo = UploadVideo::getPlayInfo($video_id);
        if(!$playInfo)return input('?video_id')?json(self::callback(0,'',false)):false;
        $cover_img = $playInfo->VideoBase->CoverURL;
        $video_url = $playInfo->PlayInfoList->PlayInfo[0]->PlayURL;
        $data = compact('cover_img','video_url');
        return input('?video_id')?json(self::callback(1,'',$data)):$data;
    }

    public function listSnapshots($video_id){
        if(!$video_id)$video_id = input('video_id','','addslashes,strip_tags,trim');
        $res = UploadVideo::listSnapshots($video_id);
        print_r($res);
        if(!$res)return input('?video_id')?json(self::callback(0,'',false)):false;
        $cover_img = $res->MediaSnapshot->Snapshots->Snapshot[0]->Url;
        $data = compact('cover_img');
        return input('?video_id')?json(self::callback(1,'',$data)):$cover_img;
    }

    /**
     * 接口回调
     * @param $status
     * @param $msg
     * @param $data
     * @return array
     */
    public static function callback($status = 1,$msg = '',$data = 0,$flag=false){
        if ($data==0 && !$flag){
            $data = new \stdClass();
        }
        //正式阶段
        #return ['status'=>$status,'msg'=>$msg,'data'=>$data];

        //测试阶段
        return ['status'=>$status,'msg'=>$msg,'data'=>$data,'request'=>request()->post()];
    }

}