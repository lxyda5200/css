<?php


namespace app\admin\controller\api;


use app\admin\controller\ApiBase;
use app\admin\model\Dynamic;
use think\Exception;
use think\Validate;

class DynamicApi extends ApiBase
{

    /**
     *
     * @param Validate $validate
     * @return \think\response\Json
     */
    public function editDynamicSelect(Validate $validate){
        try{
            $rule = [
                'dynamic_id|动态id' => 'require|number|>=:0',
                'is_select' => 'require|number|>=:0|<=:1'
            ];
            $res = $validate->rule($rule)->check(input());
            if(!$res)throw new Exception($validate->getError());
            #逻辑
            $res = Dynamic::editIsSelect();
            if(!is_bool($res))throw new Exception($res);

            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0, $e->getMessage()));
        }
    }

}