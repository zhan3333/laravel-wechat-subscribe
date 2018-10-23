<?php
/**
 * Created by PhpStorm.
 * User: 39096
 * Date: 2017/10/26
 * Time: 17:23
 */

namespace App\Http\Controllers;


use EasyWeChat\Kernel\Messages\Message;
use EasyWeChat\OfficialAccount\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WechatController extends Controller
{
    public function serve(Application $wechat)
    {
        Log::debug('receive start ----');
        /**
         * @var Application $wechat
         */
        $wechat->server->push(function ($message) {
            Log::info('wechat message', [collect($message)->toArray()]);
            // $message
            //  ToUserName
            //  FromUserName
            //  CreateTime
            //  MsgType
            //  Content
            //  MsgId
            $msg = collect($message);
            if ($msg->get('MsgType') == 'text') {
                return '上传带文字的图片, 将会识别图片中文字.';
            }
            if ($msg->get('MsgType') == 'event') {
                return '欢迎关注我的订阅号, 发送带文字的图片将识别图中文字.';
            }
            if ($msg->get('MsgType') == 'image') {
                // MediaId
                // PicUrl

                $base64Img = base64_encode(\Requests::get($msg->get('PicUrl'))->body);
                $queryRes = $this->getImgText($base64Img);
                Log::debug('base64', [$base64Img, $msg->get('PicUrl'), $queryRes]);
                $resStr = '';
                foreach ($queryRes->get('words_result') as $key => $item) {
                    if ($key) {
                        $resStr .= "\n{$item['words']}";
                    } else {
                        $resStr .= "{$item['words']}";
                    }
                }

                return $resStr;
            }
            return $msg->toJson();
        });

        $response = $wechat->server->serve();

        return $response;
    }

    /**
     * @param $base64Img
     * @return \Illuminate\Support\Collection
     * [
     *  'direction' => '', // 图像方向
     *  'log_id' => '',
     *  'words_result' => [
     *      [
     *          'words' => ''
     *      ]
     *   ], // 识别结果数组
     *  'words_result_num' => '', // 识别结果数，表示words_result的元素个数
     *  '+words' => '', // 识别结果字符串
     *  'probability' => [] //识别结果中每一行的置信度值
     * ]
     * @throws \Exception
     */
    private function getImgText($base64Img)
    {
        $url = config('baidu.api.accurate_basic');
        $access_token = $this->getBaiduToken();
        $res = \Requests::post("$url?access_token=$access_token", [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ], [
            'image' => $base64Img,
            'detect_direction' => 'false',
            'probability' => 'false'
        ]);
        return collect(json_decode($res->body, true));
    }

    private function getBaiduToken()
    {
        if (Cache::has('baidu_token')) {
            return Cache::get('baidu_token');
        }
        $url = config('baidu.token.url');
        $grant_type = config('baidu.token.grant_type');
        $client_id = config('baidu.token.client_id');
        $client_secret = config('baidu.token.client_secret');
        $response = \Requests::post("$url?grant_type=$grant_type&client_id=$client_id&client_secret=$client_secret");
        $res = collect(json_decode($response->body, true));
        if ($res->has('access_token')) {
            Cache::set('baidu_token', $res->get('access_token'), $res->get('expires_in', 0));
            return $res->get('access_token');
        } else {
            Log::error('get baidu token error', [$res->toArray()]);
            throw new \Exception("get baidu token error, {$res->toJson()}");
        }
    }
}