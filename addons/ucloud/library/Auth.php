<?php

namespace addons\ucloud\library;

final class Auth
{
    private $publicKey;
    private $privateKey;

    public function __construct($publicKey, $privateKey)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    public function getPublicKey()
    {
        return $this->publicKey;
    }

    public function getHeaders($headers)
    {
        $keys = array();
        foreach ($headers as $header) {
            $header = trim($header);
            $arr = explode(':', $header);
            if (count($arr) < 2) {
                continue;
            }
            list($k, $v) = $arr;
            $k = strtolower($k);
            if (strncasecmp($k, "x-ucloud") === 0) {
                $keys[] = $k;
            }
        }
        $c = '';
        sort($keys, SORT_STRING);
        foreach ($keys as $k) {
            $c .= $k . ":" . trim($headers[$v], " ") . "\n";
        }
        return $c;
    }

    public function sign($data)
    {
        $sign = base64_encode(hash_hmac('sha1', $data, $this->privateKey, true));
        return "UCloud " . $this->publicKey . ":" . $sign;
    }

    public function token($method, $bucket, $key, $content_md5, $content_type, $date = '')
    {
        $data = '';
        $data .= strtoupper($method) . "\n";
        $data .= $content_md5 . "\n";
        $data .= $content_type . "\n";
        $data .= $date . "\n";
        $data .= ("/" . $bucket . "/" . $key);
        return $this->sign($data);
    }
}
