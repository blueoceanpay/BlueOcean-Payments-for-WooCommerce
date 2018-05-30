<?php
/**
 * Created by PhpStorm.
 * User: Hua
 * Date: 2018/5/28
 * Time: 14:59
 */

/**
 * 以post方式提交请求
 * @param string $url
 * @param array|string $data
 * @return bool|mixed
 */
function httpPost($url, $data)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    if (is_array($data)) {
        foreach ($data as &$value) {
            if (is_string($value) && stripos($value, '@') === 0 && class_exists('CURLFile', false)) {
                $value = new CURLFile(realpath(trim($value, '@')));
            }
        }
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $data = curl_exec($ch);
    curl_close($ch);
    if ($data) {
        return $data;
    }
    return false;
}

/**
 * 数组数据签名
 * @param array $data 参数
 * @param string $key 密钥
 * @return string 签名
 */
function sign($data, $key)
{
    $ignoreKeys = ['sign', 'key'];
    ksort($data);
    $signString = '';
    foreach ($data as $k => $v) {
        if (in_array($k, $ignoreKeys)) {
            unset($data[$k]);
            continue;
        }
        $signString .= "{$k}={$v}&";
    }
    $signString .= "key={$key}";
    return strtoupper(md5($signString));
}

/**
 * 生成随机字符串
 * @param $length
 * @return null|string
 */
function getRandChar($length)
{
    $str    = null;
    $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
    $max    = strlen($strPol) - 1;

    for ($i = 0; $i < $length; $i++) {
        $str .= $strPol[rand(0, $max)];
    }
    return $str;
}

/**
 *
 * 通过 slack 查看临时调试信息
 *
 * @param mix $data
 * @return none
 */
function slack($data)
{
    if (empty($data)) {
        return;
    }

    $api     = 'https://hooks.slack.com/services/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';
    $payload = array(
        "channel"  => "#developer",
        "username" => "BlueOceanBot",
        "text"     => "slack webhook",
    );
    if (is_string($data)) {
        $payload['text'] = $data;
    } elseif (is_array($data)) {
        $payload = array_merge($payload, $data);
    } else {
        return;
    }
    $post = "payload=" . json_encode($payload);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}