<?php
namespace app\common;

class NibPayout
{
    private $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    /**
     * 网关支付
     */
    public function gatewayPay($data)
    {
        $post_data = [];
        $post_data['service'] = 'App.Payout.Payout'; // 接口名称
        $post_data['receive_type'] = 'bank'; // 收款方式
        $post_data['amount'] = $data['amount']*100; // 金额
        $post_data['receive_currency'] = 'BRL'; // 收款币种
        $post_data['receive_country'] = 'BRA'; // 收款国家
        $post_data['nonce'] = $this->getNonce(); // 随机字符串
        $post_data['merchant_id'] = $data['merchant_id']; // 商户号
        $post_data['out_order_id'] = $data['out_order_id']; // 订单号
        $post_data['amount'] = $data['amount']; // 金额 单位：分
        $post_data['first_name'] = $data['first_name']; // 收款人名字
        $post_data['last_name'] = $data['last_name']; // 收款人姓氏
        $post_data['sex'] = $data['sex']; // 收款人性别
        $post_data['city'] = $data['city']; // 收款人所在城市
        $post_data['address'] = $data['address']; // 收款人联系地址
        $post_data['mobile_area'] = $data['mobile_area']; // 收款人电话区号
        $post_data['mobile'] = $data['mobile']; // 收款人电话
        $post_data['id_type'] = $data['id_type']; // 收款人证件类型
        $post_data['id_number'] = $data['id_number']; // 收款人证件号码
        $post_data['cpf_number'] = $data['cpf_number']; // 收款人CPF号码
        $post_data['id_issue_date'] = $data['id_issue_date']; // 收款人证件签发日期
        $post_data['id_expire_date'] = $data['id_expire_date']; // 收款人证件有效日期
        $post_data['birth_date'] = $data['birth_date']; // 收款人生日
        $post_data['location_id'] = $data['location_id']; // 收款银行识别号
        $post_data['bank_id'] = $data['bank_id']; // 收款银行编码
        $post_data['bank_name'] = $data['bank_name']; // 收款银行网点
        $post_data['bank_account_number'] = $data['bank_account_number']; // 收款银行账户
        $post_data['bank_branch_name'] = $data['bank_branch_name']; // 收款分行名称
        $post_data['bank_code'] = $data['bank_code']; // 分行代码
        if (isset($data['email'])) {
            $post_data['email'] = $data['email']; // 邮箱
        }
        $post_data['notify_url'] = $data['notify_url']; // 回调地址
        if (isset($data['attach'])) {
            $post_data['attach'] = $data['attach']; // 自定义参数
        }

        $post_data['timestamp'] = time(); // 时间戳
        $post_data['sign'] = $this->sign($post_data, $data['secret']);

        $rs = $this->post($post_data);

        return $rs;
    }

    /**
     * 生成签名
     */
    public function sign($values, $secret)
    {
        //签名步骤一：按字典序排序参数
        ksort($values);
        $buff = '';
        foreach ($values as $k => $v)
        {
            if($k != "sign" && !is_array($v)){
                $buff .= $k . '=' . $v . '&';
            }
        }  
        $string = trim($buff, '&');

        //签名步骤二：在string后加入KEY
        $string = $string . '&secret=' . $secret;
        //签名步骤三：MD5加密
        $string = md5($string);

        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);

        return $result;
    }

    private function post($post_data, $timeout = 10){
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $this->url);
        if(!empty($post_data)){
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        if (parse_url($this->url, PHP_URL_SCHEME) == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $output  = curl_exec($ch);
        curl_close($ch);

        return json_decode($output , true);
    }

    function http_post_json($url, $jsonStr)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            )
        );
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $response;
    }

    /**
     * 生成随机数
     */
    private function getNonce($length = 32) 
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {  
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
        }
        
        return $str;
    }
}

?>