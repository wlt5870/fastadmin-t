<?php

namespace addons\ucloud\controller;

use app\common\model\Attachment;
use think\addons\Controller;
use think\Config;

/**
 * Ucloud
 *
 */
class Index extends Controller
{

    public function index()
    {
        $this->error("当前插件暂无前台页面");
    }

    public function token()
    {
        Config::set('default_return_type', 'json');
        $config = get_addon_config('ucloud');
        $bucket = $config['bucket'];
        $method = $this->request->post('method');
        $name = $this->request->post('name');
        $md5 = $this->request->post('md5');
        $type = $this->request->post('type');
        $suffix = substr($name, stripos($name, '.') + 1);
        $search = ['{year}', '{mon}', '{month}', '{day}', '{filemd5}', '{suffix}', '{.suffix}'];
        $replace = [date("Y"), date("m"), date("m"), date("d"), $md5, $suffix, '.' . $suffix];
        $filename = ltrim(str_replace($search, $replace, $config['savekey']), '/');

        $auth = new \addons\ucloud\library\Auth($config['public_key'], $config['private_key']);
        $token = $auth->token($method, $bucket, $filename, $md5, $type);
        $this->success('', null, ['token' => $token, 'filename' => $filename]);
        return;
    }

    public function upload()
    {
        Config::set('default_return_type', 'json');
        if (!session('admin') && !$this->auth->id) {
            $this->error("请登录后再进行操作");
        }
        $config = get_addon_config('ucloud');

        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            $this->error("请上传有效的文件");
        }
        $fileInfo = $file->getInfo();

        $filePath = $file->getRealPath() ?: $file->getPathname();

        preg_match('/(\d+)(\w+)/', $config['maxsize'], $matches);
        $type = strtolower($matches[2]);
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size = (int)$config['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);

        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix = $suffix ? $suffix : 'file';

        $md5 = md5_file($filePath);
        $search = ['{year}', '{mon}', '{month}', '{day}', '{filemd5}', '{suffix}', '{.suffix}'];
        $replace = [date("Y"), date("m"), date("m"), date("d"), $md5, $suffix, '.' . $suffix];
        $object = ltrim(str_replace($search, $replace, $config['savekey']), '/');

        $mimetypeArr = explode(',', strtolower($config['mimetype']));
        $typeArr = explode('/', $fileInfo['type']);

        //检查文件大小
        if (!$file->checkSize($size)) {
            $this->error("起过最大可上传文件限制");
        }

        //验证文件后缀
        if ($config['mimetype'] !== '*' &&
            (
                !in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $config['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
            )
        ) {
            $this->error(__('上传格式限制'));
        }

        $savekey = '/' . $object;

        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName = substr($savekey, strripos($savekey, '/') + 1);
        //先上传到本地
        $splInfo = $file->move(ROOT_PATH . '/public' . $uploadDir, $fileName);
        if ($splInfo) {
            $extparam = $this->request->post();
            $filePath = $splInfo->getRealPath() ?: $splInfo->getPathname();

            $sha1 = sha1_file($filePath);
            $imagewidth = $imageheight = 0;
            if (in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf'])) {
                $imgInfo = getimagesize($splInfo->getPathname());
                $imagewidth = isset($imgInfo[0]) ? $imgInfo[0] : $imagewidth;
                $imageheight = isset($imgInfo[1]) ? $imgInfo[1] : $imageheight;
            }
            $params = array(
                'admin_id'    => session('admin.id'),
                'user_id'     => $this->auth->id,
                'filesize'    => $fileInfo['size'],
                'imagewidth'  => $imagewidth,
                'imageheight' => $imageheight,
                'imagetype'   => $suffix,
                'imageframes' => 0,
                'mimetype'    => $fileInfo['type'],
                'url'         => $uploadDir . $splInfo->getSaveName(),
                'uploadtime'  => time(),
                'storage'     => 'local',
                'sha1'        => $sha1,
                'extparam'    => json_encode($extparam),
            );
            $attachment = Attachment::create(array_filter($params), true);
            //上传到远程
            $auth = new \addons\ucloud\library\Auth($config['public_key'], $config['private_key']);
            $token = $auth->token('POST', $config['bucket'], ltrim($attachment->url, '/'), $md5, $attachment->mimetype);
            $multipart = [
                [
                    'name'     => 'FileName',
                    'contents' => ltrim($attachment->url, '/')
                ],
                [
                    'name'     => 'Authorization',
                    'contents' => $token,
                ],
                [
                    'name'     => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => $fileName,
                ]
            ];
            try {
                $client = new \GuzzleHttp\Client();
                $res = $client->request('POST', $config['uploadurl'], [
                    'multipart' => $multipart,
                    'headers'   => [
                        'Content-MD5' => $md5
                    ],
                ]);
                $code = $res->getStatusCode();
                //成功不做任何操作
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $attachment->delete();
                unlink($filePath);
                $this->error("上传失败");
            }
            $url = '/' . $object;

            //上传成功后将存储变更为alioss
            $attachment->storage = 'ucloud';
            $attachment->save();

            $this->success("上传成功", null, ['url' => $url]);
        } else {
            $this->error('上传失败');
        }
        return;
    }

    public function notify()
    {
        Config::set('default_return_type', 'json');
        $config = get_addon_config('ucloud');
        $bucket = $config['bucket'];
        $method = $this->request->post('method');
        $size = $this->request->post('size');
        $name = $this->request->post('name');
        $md5 = $this->request->post('md5');
        $type = $this->request->post('type');
        $token = $this->request->post('token');
        $url = $this->request->post('url');
        $filename = ltrim($url, '/');
        $suffix = substr($name, stripos($name, '.') + 1);
        $auth = new \addons\ucloud\library\Auth($config['public_key'], $config['private_key']);
        if ($token == $auth->token($method, $bucket, $filename, $md5, $type)) {
            $attachment = Attachment::getBySha1($md5);
            if (!$attachment) {
                $params = array(
                    'admin_id'    => (int)session('admin.id'),
                    'user_id'     => (int)cookie('uid'),
                    'filesize'    => $size,
                    'imagewidth'  => 0,
                    'imageheight' => 0,
                    'imagetype'   => $suffix,
                    'imageframes' => 0,
                    'mimetype'    => $type,
                    'url'         => $url,
                    'uploadtime'  => time(),
                    'storage'     => 'ucloud',
                    'sha1'        => $md5,
                );
                Attachment::create($params);
            }
            $this->success();
        } else {
            $this->error(__('You have no permission'));
        }
        return;
    }

}
