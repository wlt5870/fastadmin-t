<?php

namespace addons\ucloud;

use fast\Http;
use think\Addons;

/**
 * UCloud插件
 */
class Ucloud extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 加载配置
     */
    public function uploadConfigInit(&$upload)
    {
        $config = $this->getConfig();
        if ($config['uploadmode'] === 'client') {
            $upload = [
                'cdnurl'    => $config['cdnurl'],
                'uploadurl' => $config['uploadurl'],
                'bucket'    => $config['bucket'],
                'maxsize'   => $config['maxsize'],
                'mimetype'  => $config['mimetype'],
                'multipart' => [],
                'multiple'  => $config['multiple'] ? true : false,
                'storage'   => 'ucloud'
            ];
        } else {
            $upload = array_merge($upload, [
                'cdnurl'    => $config['cdnurl'],
                'uploadurl' => addon_url('ucloud/index/upload'),
                'maxsize'   => $config['maxsize'],
                'mimetype'  => $config['mimetype'],
                'multiple'  => $config['multiple'] ? true : false,
            ]);
        }
    }

    /**
     * 附件删除后
     */
    public function uploadDelete($attachment)
    {
        $config = $this->getConfig();
        if ($attachment['storage'] == 'ucloud' && isset($config['syncdelete']) && $config['syncdelete']) {
            $url = $config['uploadurl'] . $attachment->url;
            $auth = new \addons\ucloud\library\Auth($config['public_key'], $config['private_key']);
            $token = $auth->token('DELETE', $config['bucket'], ltrim($attachment->url, '/'), '', 'application/x-www-form-urlencoded');
            $authorization = $token;
            //删除云储存文件
            $ret = Http::sendRequest($url, [], 'DELETE', [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_HTTPHEADER => ['Authorization: ' . $authorization]]);
        }
        return true;
    }

}
