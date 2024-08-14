<?php
namespace app\common;

class Nibpay
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
        $post_data['service'] = 'App.Pay.GatewayPay'; // 接口名称
        $post_data['merchant_id'] = $data['merchant_id']; // 商户号
        $post_data['out_order_id'] = $data['out_order_id']; // 订单号
        $post_data['amount'] = $data['amount']*100; // 金额
        $post_data['pay_currency'] = $data['pay_currency']; // 支付币种
        $post_data['buy_currency'] = $data['buy_currency']; // 购买币种
        if (isset($data['email'])) {
            $post_data['email'] = $data['email']; // 邮箱
        }
        $post_data['notify_url'] = $data['notify_url']; // 回调地址
        if (isset($data['attach'])) {
            $post_data['attach'] = $data['attach']; // 自定义参数
        }
        $post_data['nonce'] = $this->getNonce(); // 随机字符串
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