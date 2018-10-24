<?php
/**
 * Created by PhpStorm.
 * User: 39096
 * Date: 2017/10/26
 * Time: 17:23
 */

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\UserWechat;
use App\Models\WechatAutoReceive;
use App\Models\WechatMessage;
use EasyWeChat\Kernel\Messages\Image;
use EasyWeChat\Kernel\Messages\Message;
use EasyWeChat\OfficialAccount\Application;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPHtmlParser\Dom;
use PHPQRCode\Constants;
use Shelwei\QRCode;

class WechatController extends Controller
{
    public function serve(Application $wechat)
    {
        Log::debug('receive start ----');
        /**
         * @var Application $wechat
         */
        $wechat->server->push(function ($message) use ($wechat) {
            Log::info('wechat message', [collect($message)->toArray()]);
            // $message
            //  ToUserName
            //  FromUserName
            //  CreateTime
            //  MsgType
            //  Content
            //  MsgId
            $msg = collect($message);
            $this->register($msg);
            if ($msg->get('MsgType') == 'text') {
                if ($msg->get('Content') == 'm') {
                    $imgs = $this->getMeiziImgs();
                    // media_id
                    // url
                    Log::debug('img', [$imgs]);
//                    $client = new Client(['verify' => false]);  //忽略SSL错误
//                    $client->get($img, ['save_to' => $savePath]);  //保存远程url到文件
//                    $uploadRes = $wechat->material->uploadImage($savePath);
//                    Log::debug('$uploadRes', [$uploadRes]);
//                    return new Image('FJggVYI2YxOOv8gvHG7R6O01ON1ZngXARg1TblkzA-P2rhK8G2KQ58BV24nP3s8m');
//                    return $img;
                    $res = '';
                    foreach ($imgs as $key => $img) {
                        $res .= "<a href=\"$img\">图片 {$key}</a>  ";
                    }
                    return $res;
                }

                $res = WechatAutoReceive::where('receive', $msg->get('Content'))->inRandomOrder()->value('send');
                if (!$res) $res = WechatAutoReceive::where('receive', 'default')->inRandomOrder()->value('send');
                if (!$res) $res = '上传带文字的图片, 将会识别图片中文字.';
                return $res;
            }
            if ($msg->get('MsgType') == 'event') {
                return WechatAutoReceive::where('receive', 'default')->inRandomOrder()->value('send');
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


    private function register(Collection $msg)
    {
        /** @var UserWechat $userWechat */
        if (!($userWechat = UserWechat::where('user_name', $msg->get('FromUserName'))->first())) {
            $userWechat = User::create(['name' => $msg->get('FromUserName')])
                ->userWechat()
                ->create(
                    [
                        'user_name' => $msg->get('FromUserName'),
                        'subscribed_at' => $msg->get('CreateTime')
                    ]
                );
        }

        // save message
        WechatMessage::create([
            'user_id' => $userWechat->user_id,
            'from_user_name' => $userWechat->user_name,
            'to_user_name' => $msg->get('ToUserName'),
            'msg_type' => $msg->get('MsgType'),
            'type' => 'receive',
            'data' => $msg
        ]);
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

    private function getMeiziImgs()
    {
        $randomPage = random_int(1, 34);

        $dom = $this->getMeiziHtml($randomPage);
        $comments = $dom->find('.post-grid');
        $imgs = [];
        foreach ($comments as $comment) {
            $img = $comment->find('img', 0)->getAttribute('src');
            $img = str_replace('-548x300', '', $img);
            $imgs[] = $img;
        }
        // get random page
        return $imgs;
    }

    private function getMeiziHtml($page = 1)
    {
        $dom = new Dom();
        $dom->loadFromUrl("https://qingbuyaohaixiu.com/page/$page");
        return $dom;
    }
}