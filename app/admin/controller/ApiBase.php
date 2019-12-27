<?php


namespace app\admin\controller;


use sourceUpload\UploadVideo;
use think\Controller;
use think\Db;
use think\Exception;
use think\response\Json;
use think\Session;

class ApiBase extends Controller
{

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

    /**
     * 图片上传方法 返回id
     * @return [type] [description]
     */
    public function upload($module='admin',$use='admin_thumb')
    {
        if($this->request->file('file')){
            $file = $this->request->file('file');
        }else{
            $res['code']=1;
            $res['msg']='没有上传文件';
            return json($res);
        }
        $module = $this->request->has('module') ? $this->request->param('module') : $module;//模块
        $web_config = Db::name('webconfig')->where('web','web')->find();
        $info = $file->validate(['size'=>50*1024*1024,'ext'=>$web_config['file_type']])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);
        if($info) {
            //写入到附件表
            $data = [];
            $data['module'] = $module;
            $data['filename'] = $info->getFilename();//文件名
            $data['filepath'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();//文件路径
            $data['fileext'] = $info->getExtension();//文件后缀
            $data['filesize'] = $info->getSize();//文件大小
            $data['create_time'] = time();//时间
            $data['uploadip'] = $this->request->ip();//IP
            $data['user_id'] = Session::has('admin') ? Session::get('admin') : 0;
            if($data['module'] = 'admin') {
                //通过后台上传的文件直接审核通过
                $data['status'] = 1;
                $data['admin_id'] = $data['user_id'];
                $data['audit_time'] = time();
            }
            $data['use'] = $this->request->has('use') ? $this->request->param('use') : $use;//用处
            $res['id'] = Db::name('attachment')->insertGetId($data);
            $res['src'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();
            $res['code'] = 2;
//            addlog($res['id']);//记录日志
            return json(self::callback(1,'',$res));
        } else {
            // 上传失败获取错误信息
            return json(self::callback(0,$file->getError()));
        }
    }

    /**
     * 图片上传方法 返回id
     * @return [type] [description]
     */
    public function layUpload($module='admin',$use='admin_thumb')
    {
        if($this->request->file('file')){
            $file = $this->request->file('file');
        }else{
            $res['code']=1;
            $res['msg']='没有上传文件';
            return json($res);
        }
        $module = $this->request->has('module') ? $this->request->param('module') : $module;//模块
        $web_config = Db::name('webconfig')->where('web','web')->find();
        $info = $file->validate(['size'=>50*1024*1024,'ext'=>$web_config['file_type']])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);
        if($info) {
            //写入到附件表
            $data = [];
            $data['module'] = $module;
            $data['filename'] = $info->getFilename();//文件名
            $data['filepath'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();//文件路径
            $data['fileext'] = $info->getExtension();//文件后缀
            $data['filesize'] = $info->getSize();//文件大小
            $data['create_time'] = time();//时间
            $data['uploadip'] = $this->request->ip();//IP
            $data['user_id'] = Session::has('admin') ? Session::get('admin') : 0;
            if($data['module'] = 'admin') {
                //通过后台上传的文件直接审核通过
                $data['status'] = 1;
                $data['admin_id'] = $data['user_id'];
                $data['audit_time'] = time();
            }
            $data['use'] = $this->request->has('use') ? $this->request->param('use') : $use;//用处
//            $res['id'] = Db::name('attachment')->insertGetId($data);
            $res['data']['src'] = "http://" . $_SERVER['HTTP_HOST'] . DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();
            $res['msg'] = '上传成功';
            $res['code'] = 0;
//            addlog($res['id']);//记录日志
            return json_encode($res);
        } else {
            // 上传失败获取错误信息
            return json_encode(['code'=>1,'msg'=>'上传失败']);
        }
    }


    /**
     * 多张图片上传方法 返回id数组
     * @return [type] [description]
     */
    public function uploads($module='admin',$use='admin_thumb')
    {
        if($this->request->file('file')){
            $files = $this->request->file('file');
        }else{
            $res['code']=1;
            $res['msg']='没有上传文件';
            return json($res);
        }

        $module = $this->request->has('module') ? $this->request->param('module') : $module;//模块
        $web_config = Db::name('webconfig')->where('web','web')->find();

        foreach ($files as $key=>$file) {

            $info = $file->validate(['size'=>$web_config['file_size']*1024,'ext'=>$web_config['file_type']])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);
            if($info) {
                //写入到附件表
                $data = [];
                $data['module'] = $module;
                $data['filename'] = $info->getFilename();//文件名
                $data['filepath'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();//文件路径
                $data['fileext'] = $info->getExtension();//文件后缀
                $data['filesize'] = $info->getSize();//文件大小
                $data['create_time'] = time();//时间
                $data['uploadip'] = $this->request->ip();//IP
                $data['user_id'] = Session::has('admin') ? Session::get('admin') : 0;
                if($data['module'] = 'admin') {
                    //通过后台上传的文件直接审核通过
                    $data['status'] = 1;
                    $data['admin_id'] = $data['user_id'];
                    $data['audit_time'] = time();
                }
                $data['use'] = $this->request->has('use') ? $this->request->param('use') : $use;//用处
                $res['id'] = Db::name('attachment')->insertGetId($data);
                $res['src'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();
                $res['code'] = 2;
                addlog($res['id']);//记录日志

                $data[] = $res;

            } else {
                // 上传失败获取错误信息
                return $this->error('上传失败：'.$file->getError());
            }
        }

        #dump($data);die;
        return json($data);
    }

    /**
     * 上传视频
     * @return Json
     */
    public function uploadVideo(){
        try{
            ##验证
            if(!request()->has('video','file'))throw new Exception('上传文件缺失');

            $file = request()->file('video');
            if($file){
                $path = $file->getRealPath();
                $size = $file->getSize();
                $ext = explode('/',$file->getInfo('type'))[1];
                ##判断文件格式
                $right_ext = config('config_uploads.video_type');
                if(!in_array(strtolower($ext),$right_ext))throw new Exception('文件格式不支持');
                ##判断文件大小
                if($size > 40 * 1024 *1024)throw new Exception('文件大小不能超过40M');
                ##保存临时本地文件
                $file_name = "admin_" . time() . rand(10000,99999) . '.' . $ext;
                $data = file_get_contents($path);
                $path2 = "uploads/video_temp/{$file_name}";
                file_put_contents($path2,$data);
                if(file_exists($path2)){
                    $res = UploadVideo::uploadLocalVideo($path2,$file_name,2);
                    if(!$res)throw new Exception('上传失败');
                    $data = [
                        'video_url' => $res['path'],
                        'cover_img' => $res['path'] . "?x-oss-process=video/snapshot,t_1000,m_fast",
                        'video_id'  => $res['media_id']
                    ];
                    @unlink($path2);  //删除临时文件
                    return json(self::callback(1,'',$data));
                }
                throw new Exception('临时本地文件生成失败');
            }
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

}