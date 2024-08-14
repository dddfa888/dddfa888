<?php
/** Created by GuZiMou 2022/6/2 16:35 */

namespace app\common\lib;


class Security
{
    public $iv = '!WFNZFU_{H%M(S|a';    //IV偏移量这个是固定的不需要去管
    public $url = 'https://payapi.soon-ex.com';  //接口地址 测试
    public $appId = '01105972';  //商户ID 测试  ---土耳其   41960470
    public $key = '20f3af23c2485cb7202668df04b636b6';  //商户的Keyd在商户后台可以获取 测试	   --土耳其 
    public $key16 = '20f3af23c2485cb7'; //   商户秘钥的前16位  测试	     ---土耳其 c2d74153c5639bf8
    public $chargeurl = 'https://pay.soon-ex.com/turkey/#/?orderId='; //充值收银台地址

    /**用户充值功能*/
    public function chargeforuser($amount, $thirdOrderNumber, $thirdUserId)
    {

        $url = $this->url . '/otc/api/getRechargeData';  //充值接口，这个接口post方式以json格式提交
        $data = [
            'amount' => $amount,
            'thirdOrderNumber' => $thirdOrderNumber,//商家自己平台的订单号
            'thirdUserId' => $thirdUserId,//商家自己平台的会员ID，如果没有可以用上面的订单号
        ];
        $ret = $this->curlhead($url, $data, 'POST');  //向充值接口发起充值请求 post方式以json格式提交
        return $ret;  //获取返回的订单信息json字符串
    }

    public function curlhead($url, $params, $m)
    {
        $curl = curl_init();
        $data_string = json_encode($params);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => $data_string,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic' . base64_encode($this->appId . ':' . $this->key . ''), //添加头，在name和pass处填写对应账号密码
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function queryorder($thirdOrderNumber)
    {
        date_default_timezone_set('Asia/Shanghai'); //设置时区
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000); //获取当前时间戳
        $nonce = date('Ymd') . substr(implode(NULL, array_map('gzm', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);   //生成10位以上的随机数

        $url = $this->url . '/otc/api/queryOrderStatus';  //订单查询接口，这个接口post方式以json格式提交
        $en = [
            'thirdOrderNumber' => $thirdOrderNumber,//商家自己平台的订单号
        ];
        $data = [
            'encryptedData' => $this->encode(json_encode($en)),   //openssl_encrypt进行aes对称加密
            'signaturePo' => [
                'apiId' => $this->appId,  //商家ID
                'nonce' => $nonce . '',
                'signature' => '',
                'timestamp' => $msectime . ''
            ],
        ];

        $signature = $this->sign([   //调用签名函数进行数据签名
            $data['signaturePo']['timestamp'] . '',
            $data['signaturePo']['nonce'] . '',
            $data['signaturePo']['apiId'], $this->key . '',
            json_encode($en),  //将数组转json格式的数据
        ]);
        $data['signaturePo']['signature'] = $signature;
        $ret = $this->curl($url, $data, 'POST');  //向订单查询接口发起查询请求 post方式以json格式提交
        return $ret;  //获取返回的订单信息json字符串
    }

    public function curl($url, $params, $m)
    {  //请求参数与URL post数据组装
        $curl = curl_init();
        $data_string = json_encode($params);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => $data_string,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string)
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function curlheadforwithdraw($url, $params, $m)
    {
        $curl = curl_init();
        $data_string = json_encode($params);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => $data_string,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    //注意：nonnce:生成10位以上的随机数(建议自己改改，拼接个公司简称的字母和随机数，此nonce方法生成的数，会出现比较大的概率重复，影响提现成功率)    thirdUserId  和  name 必须五位以上的字符长度
    public function widrawcash($amount, $thirdOrderNumber, $thirdUserId, $barcodeurl)
    {   //代付功能 $amount--代付金额，$thirdOrderNumber--三方商户平台的订单号，$thirdUserId---三方商户的用户ID，$barcodeurl－－收款二维码
        date_default_timezone_set('Asia/Shanghai'); //设置时区
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000); //获取当前时间戳
        $nonce = date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);  //生成10位以上的随机数(建议自己改改，拼接个公司简称的字母和随机数，此nonce方法生成的数，会出现比较大的概率重复，影响提现成功率)
        $url = $this->url . '/otc/api/issue';   //提现接口，这个接口post方式以json格式提交
        $en = [
            'amount' => $amount,    //paymentId，name，accountName，bankName，ifscCode
            'thirdOrderNumber' => $thirdOrderNumber,//uniqid(),商家自己平台的提现订单号
            'thirdUserId' => $thirdUserId, //商家自己平台的会员ID，如果没有可以用上面的订单号
            'issuePayPo' => [
                'accountName' => '2891101010856',  //Papara account
                'name' => 'Soumya Bharathi',  //提现用户姓名
                'paymentId' => '42' //收款方式ID这里以Papara为例,其他收款方式见文件，如果填错可能会影响订单代收
            ]
        ];
        $data = [
            'encryptedData' => $this->encode(json_encode($en)),   //提现数据加密
            'signaturePo' => [
                'apiId' => $this->appId,
                'nonce' => $nonce . '',
                'signature' => '',
                'timestamp' => $msectime . ''
            ],
        ];
        $signature = $this->sign([   //调用签名函数进行数据签名
            $data['signaturePo']['timestamp'] . '',
            $data['signaturePo']['nonce'] . '',
            $data['signaturePo']['apiId'], $this->key,
            json_encode($en), //将数组转json格式的数据
        ]);
        $data['signaturePo']['signature'] = $signature;
        $ret = $this->curlheadforwithdraw($url, $data, 'POST');   //数据提交
        return $ret;
    }

    public function encode($str)  //数据加密函数
    {
        $data = base64_encode(openssl_encrypt($str, 'AES-128-CBC', $this->key16, OPENSSL_RAW_DATA, $this->iv));

        return $data;
    }

    public function decode($secretData)
    {   //数据解密函数
        return openssl_decrypt(base64_decode($secretData), 'AES-128-CBC', $this->key16, OPENSSL_RAW_DATA, $this->iv);
    }

    public function sign($data)
    {   //数据签名
        sort($data, SORT_LOCALE_STRING);
        $str = $data[0] . $data[1] . $data[2] . $data[3] . $data[4];
        return strtoupper(sha1($str));
    }
}

//$re = new Security();
//$chargeurl = $re->chargeurl;

//echo '-------查询订单------';
//echo  $re->queryorder('TESLA1617291014298');  //传户商户自己平台的订单号
//echo '----调用充值功能888------';
#echo  $re->chargeforuser('1000',"48544_1231231_123123",'32558883');
//echo $re->chargeforuser('5000', date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8), '124875');//新版本基于 Basic Authentication验证
#echo '------获取订单号进行URL拼装，这块相对简单自行进行拼装'.$chargeurl.'订单号-----';

//echo '---调用提现功能---';
//echo $re->widrawcash('5000', date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8), '451111245', 'https://fm.wsbroker.me/img/back_logo.401cbe3e.png'); //提现接口没有
#echo '----如果成功则返回{"code":0,"message":"Succeed","success":true}';
#echo '----开始解密码------';
#echo  $re->decode('K1p6GTtMVGi3KaXuMtu/x4ovzxWGQxtx9H67h6tyrLgyH3yqIweHbOVGlYWoX+XyFZjRE+jQR6NC9klLVrcTQfBe1CWDVq5ZW7XcoEb4bNynHDt9udwlMtXYSL+f7TfkUkgOb+uwu4e10YhfBQo8vO2EaFRnamsCYgn0TdLkTkjai4M1A3xHywsX4+PAAPrie2AiMhCMeAI5K67iX4teeCJsBjlUmV4gFvcRlWFRMGnjHzxYIQ43yc8jJZUeCk3BRZ6aFChzvwtrSFT2ba4mIA');