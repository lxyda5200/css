<?php


namespace sourceUpload;

use think\Config;
use think\Exception;
use think\Loader;
use vod\Request\V20170321\DeleteMezzaninesRequest;
use vod\Request\V20170321\GetAIMediaAuditJobRequest;
use vod\Request\V20170321\GetPlayInfoRequest;
use vod\Request\V20170321\ListSnapshotsRequest;
use vod\Request\V20170321\SubmitAIMediaAuditJobRequest;

Loader::import('voduploadsdk.Autoloader');

class UploadVideo
{

    public static function uploadLocalVideo($filePath,$file_name,$type=1){
        try{
            $conf = Config::get('config_uploads.ali_oss');
            $uploader = new \AliyunVodUploader($conf['AccessKeyID'],$conf['AccessKeySecret']);

            $uploadVideoRequest = new \UploadVideoRequest($filePath, $file_name);
            $uploadVideoRequest->setCateId(1000071281);
            //$uploadVideoRequest->setCoverURL("http://xxxx.jpg");
            //$uploadVideoRequest->setTags('test1,test2');
            //$uploadVideoRequest->setStorageLocation('outin-xx.oss-cn-beijing.aliyuncs.com');
            //$uploadVideoRequest->setTemplateGroupId('6ae347b0140181ad371d197ebe289326');
            $web_path = Config::get('web_path');
            $userData = array(
                "MessageCallback"=> ["CallbackURL"=>"{$web_path}/wxapi_test/user/video_callback.shtml"],
                "Extend"=> ["type"=>$type, "test"=>"www"]
            );
            $uploadVideoRequest->setUserData(json_encode($userData,JSON_UNESCAPED_SLASHES));
            $res = $uploader->uploadLocalVideo($uploadVideoRequest);
            return $res;
        }catch(Exception $e){
            return $e->getMessage();
        }
    }

    public static function getPlayInfo($videoId) {
        $client = self::initVodClient();
        $request = new GetPlayInfoRequest();
        $request->setVideoId($videoId);
        $request->setAuthTimeout(3600*24);
        $request->setAcceptFormat('JSON');
        return $client->getAcsResponse($request);
    }

    /**
     * 查询截图数据
     */
    public static function listSnapshots($videoId) {
        $client = self::initVodClient();
        $request = new ListSnapshotsRequest();
        //视频ID
        $request->setVideoId($videoId);
        ///截图类型
        $request->setSnapshotType("CoverSnapshot");
        // 翻页参数
        $request->setPageNo("1");
        $request->setPageSize("1");
        return $client->getAcsResponse($request);
    }

    /**
     * 提交智能审核作业
     */
    public static function submitAIMediaAuditJob($videoId) {
        $client = self::initVodClient();
        $request = new SubmitAIMediaAuditJobRequest();
        // 设置视频ID
        $request->setMediaId($videoId);
        // 返回结果
        return $client->getAcsResponse($request);
    }

    /**
     * 查询智能审核作业
     */
    public static function getAIMediaAuditJob($videoId) {
        $client = self::initVodClient();
        $request = new GetAIMediaAuditJobRequest();
        // 设置作业ID
        $request->setJobId($videoId);
        // 返回结果
        return $client->getAcsResponse($request);
    }

    /**
     * 批量删除源文件函数
     */
    public static function deleteMezzanines($videoIds) {
        $client = self::initVodClient();
        $request = new DeleteMezzaninesRequest();
        $request->setVideoIds($videoIds);
        $request->setForce(true);
        return $client->getAcsResponse($request);
    }

    public static function initVodClient() {
        $conf = Config::get('config_uploads.ali_oss');
        $regionId = 'cn-shanghai';  // 点播服务接入区域
        $profile = \DefaultProfile::getProfile($regionId, $conf['AccessKeyID'],$conf['AccessKeySecret']);
        return new \DefaultAcsClient($profile);
    }

}