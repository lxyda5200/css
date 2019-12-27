<?php


namespace app\admin\controller;


use app\admin\repository\implementses\RBrandReview;
use app\admin\repository\implementses\RStoreCompanyInfo;
use app\admin\tool\Tool;
use app\common\controller\AliSMS;
use think\Db;
use think\Exception;

class CompanyReview extends ApiBase
{
    private $type = [
        1 => '实体商家业务',
        2 => '会员商城业务'
    ];
    /**
     * 审核列表
     * @param RStoreCompanyInfo $companyInfo
     * @return \think\response\Json
     */
    public function reviewList(RStoreCompanyInfo $companyInfo) {
        $params = input('post.');
        $where = ['status' => 0];
        if(isset($params['key']) && !empty($params['key']))
            $where['license_name'] = ['like', "%{$params['key']}%"];
        if(isset($params['license_no']) && !empty($params['license_no']))
            $where['license_no'] = ['like', "%{$params['license_no']}%"];
        if(isset($params['time']) && !empty($params['time'])) {
            $time = explode('-', $params['time']);
            $where['create_time'] = ['egt', strtotime($time[0])];
            $where['create_time'] = ['elt', strtotime($time[1])];
        }

        $per_page = isset($params['per_page'])?$params['per_page']:10;
        $data = $companyInfo->reviewList($where, $per_page);
        if(!$data)
            return json(self::callback(0, '暂无数据'));

        foreach ($data['data'] as $k => $v)
            $data['data'][$k]['type'] = $this->type[$v['type']];

        return json(self::callback(1, 'success', $data));
    }


    /**
     * 审核详情
     * @param RStoreCompanyInfo $companyInfo
     * @return \think\response\Json
     */
    public function reviewDetail(RStoreCompanyInfo $companyInfo) {
        $id = input('post.id', 0, 'intval');
        if(!$id)
            return json(self::callback(0, '参数缺失'));

        $detail = $companyInfo->reviewDetail($id);
        $detail['card_img'] = empty($detail['card_img'])?[]:explode(',', $detail['card_img']);
        $detail['license_img'] = empty($detail['license_img'])?[]:explode(',', $detail['license_img']);
        if(!$detail)
            return json(self::callback(0, '暂无数据'));

        $detail['type'] = $detail['type'] == 1?'实体商家业务':'会员商城业务';
        return json(self::callback(1, 'success', $detail));
    }


    /**
     * 审核
     * @param RStoreCompanyInfo $companyInfo
     * @return \think\response\Json
     */
    public function review(RStoreCompanyInfo $companyInfo, RBrandReview $brandReview) {
        $params = input('post.');
        # 数据验证
        $validate = new \app\admin\validate\CompanyReview();
        if(!$validate->scene('review')->check($params))
            return json(self::callback(0, $validate->getError()));

        Db::startTrans();
        # 店铺id
        $store_id = $companyInfo->getStoreId(intval($params['id']));
        try{
            if($params['status'] == 1) {

                # 查看品牌审核状态
                $brand_status = $brandReview->reviewStatus($store_id);
                if($brand_status && $brand_status == 1) {
                    $res = Db::table('user_and_store')->insert([
                        'store_id' => $store_id,
                        'create_time' => time()
                    ]);
                    if(!$res)
                        throw new Exception('操作失败');

                    # 写入日志
                    $res1 = $companyInfo->writeLog($store_id, 2);
                    if(!$res1)
                        throw new Exception('日志记录失败');

                    # 写入合作期限
                    $res2 = \app\admin\model\Store::update([
                        'start_time' => time(),
                        'end_time' => strtotime('+1 year', time())
                    ], ['id' => $store_id]);
                    if($res2 === false)
                        throw new Exception('合作期限写入失败');

                    # 修改店铺状态
                    $res3 = \app\admin\model\Store::where(['id' => $store_id])->update([
                        'sh_status' => 1,
                        'status' => 1,
                        'store_status' => 1
                    ]);
                    if($res3 === false)
                        throw new Exception('store状态修改失败');


                    # 发送邮件
                    $data = Db::table('business')
                        ->where(['store_id' => $store_id, 'main_id' => $store_id, 'group_id' => 0])
                        ->field('email, mobile')->find();
                    if($data['email'])
                        Tool::ton_email($data['email'], '您好！您提交的入驻资质已经审核通过');

                    # 发送短息
                    AliSMS::sendEntryStatus($data['mobile'], 'send_success');
                }

                ## 无品牌入驻只需要审核店铺资质
                if(!$brand_status) {
                    $res = Db::table('user_and_store')->insert([
                        'store_id' => $store_id,
                        'create_time' => time()
                    ]);
                    if(!$res)
                        throw new Exception('操作失败');

                    # 写入日志
                    $res1 = $companyInfo->writeLog($store_id, 2);
                    if(!$res1)
                        throw new Exception('日志记录失败');

                    # 写入合作期限
                    $res2 = \app\admin\model\Store::update([
                        'start_time' => time(),
                        'end_time' => strtotime('+1 year', time())
                    ], ['id' => $store_id]);
                    if($res2 === false)
                        throw new Exception('合作期限写入失败');


                    # 修改店铺状态
                    $res3 = \app\admin\model\Store::where(['id' => $store_id])->update([
                        'sh_status' => 1,
                        'status' => 1,
                        'store_status' => 1
                    ]);
                    if($res3 === false)
                        throw new Exception('store状态修改失败');


                    # 发送邮件
                    $data = Db::table('business')
                        ->where(['store_id' => $store_id, 'main_id' => $store_id, 'group_id' => 0])
                        ->field('email, mobile')->find();
                    if($data['email'])
                        Tool::ton_email($data['email'], '您好！您提交的入驻资质已经审核通过');

                    # 发送短息
                    AliSMS::sendEntryStatus($data['mobile'], 'send_success');
                }
            }else {

                # 修改店铺状态
                $res3 = \app\admin\model\Store::where(['id' => $store_id])->update([
                    'sh_status' => -1,
                    'status' => 2,
                    'store_status' => 0,
                    'reason' => trimStr(isset($params['review_note'])?$params['review_note']:'')
                ]);
                if($res3 === false)
                    throw new Exception('store状态修改失败');


                # 写入日志
                $res1 = $companyInfo->writeLog($store_id, 6);
                if(!$res1)
                    throw new Exception('日志记录失败!');


                # 发送邮件
                $data = Db::table('business')
                    ->where(['store_id' => $store_id, 'main_id' => $store_id, 'group_id' => 0])
                    ->field('email, mobile')->find();
                if($data['email'])
                    Tool::ton_email($data['email'], '您好！您提交的入驻资质审核未通过');

                # 发送短息
                AliSMS::sendEntryStatus($data['mobile'], 'send_fail');
            }

            $res = $companyInfo->review(intval($params['id']), intval($params['status']), trimStr(isset($params['review_note'])?$params['review_note']:''));
            if($res === false)
                throw new Exception('操作失败啦');

            Db::commit();
            return json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            Db::rollback();
            return json(self::callback(0, $exception->getMessage()));
        }
    }
}