<?php
/**
 * Created by PhpStorm.
 * User: 39096
 * Date: 2017/10/26
 * Time: 17:23
 */

namespace App\Http\Controllers;


use App\Exceptions\Handler;
use App\Jobs\DownloadMeiziImage;
use App\Models\User;
use App\Models\UserWechat;
use App\Models\WechatAutoReceive;
use App\Models\WechatMessage;
use EasyWeChat\OfficialAccount\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WechatController extends Controller
{
    public function serve(Application $wechat)
    {
        $wechat->server->push(function ($message) use ($wechat) {
            \Auth::setUser(User::where('name', $message['FromUserName'])->first());
            Log::info('wechat message', [$message]);
            // $message
            //  ToUserName
            //  FromUserName
            //  CreateTime
            //  MsgType
            //  Content
            //  MsgId
            $msg = collect($message);
            $this->register($msg);
            if ($msg->get('MsgType') === 'text') {
                if ($msg->get('Content') === 's') {
                    foreach (range(2, 144) as $page) {
                        if (!Storage::disk('public')->has("meizi/$page.jpg")) {
                            DownloadMeiziImage::dispatch($page);
                        }
                    }
                    return 'download...';
                }
                if ($msg->get('Content') === 'm') {
                    return $this->getRandomImg();
                }

                $res = WechatAutoReceive::where('receive', $msg->get('Content'))->inRandomOrder()->value('send');
                if (!$res) {
                    $res = WechatAutoReceive::where('receive', 'default')->inRandomOrder()->value('send');
                }
                if (!$res) {
                    $res = '上传带文字的图片, 将会识别图片中文字.';
                }
                return $res;
            }
            if ($msg->get('MsgType') === 'event') {
                return WechatAutoReceive::where('receive', 'default')->inRandomOrder()->value('send');
            }
            if ($msg->get('MsgType') === 'image') {
                try {
                    $base64Img = base64_encode(\Requests::get($msg->get('PicUrl'))->body);
                    $imgLength = (strlen($base64Img) - strlen($base64Img) / 4) / 1024 / 1024;
                    if ($imgLength > 4) return '😁 图片大小不可以超过4MB哦~';
                    Log::debug('base64 file length', [(strlen($base64Img) - strlen($base64Img) / 4) / 1024 / 1024 . ' MB']);
                    $queryRes = $this->getImgText($base64Img);
                    $resStr = '';
                    Log::debug('query res', [$msg->get('PicUrl'), $queryRes]);
                    if ($queryRes->get('error_code')) {
                        // error
                        $resStr = $queryRes->get('error_code') . PHP_EOL . $queryRes->get('error_msg') . PHP_EOL .
                            '识别发生了错误, 如果你有时间的话, 请联系微信号/QQ/手机: 13517210601 提交错误, 谢谢你啦 😝';
                        return $resStr;
                    }
                    foreach ($queryRes->get('words_result') as $key => $item) {
                        if ($key) {
                            $resStr .= "\n{$item['words']}";
                        } else {
                            $resStr .= $item['words'];
                        }
                    }
                    if (empty($resStr)) {
                        $resStr = '😥 可能未找到可识别的文字...';
                    }
                    return $resStr;
                } catch (\Exception $exception) {
                    app(Handler::class)->report($exception);
                    $resStr = $exception->getMessage() . PHP_EOL .
                        '程序发生了异常, 如果你有时间的话, 请联系微信号/QQ/手机: 13517210601 提交错误, 谢谢你啦 😝';
                    return $resStr;
                }

            }
            return $msg->toJson();
        });

        return $wechat->server->serve();
    }

    private function getRandomImg()
    {
        $images = Storage::disk('public')->files('meizi');
        if (count($images)) {
            $randomImgUrl = asset('storage/' . array_random($images));
            return "<a href='$randomImgUrl'>(。・∀・)ノ</a>";
        } else {
            return 'No img';
        }
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
        Log::debug('get baidu token', [$res]);
        if ($res->has('access_token')) {
            Cache::put('baidu_token', $res->get('access_token'), $res->get('expires_in') / 60);
            return $res->get('access_token');
        } else {
            Log::error('get baidu token error', [$res->toArray()]);
            throw new \Exception("get baidu token error, {$res->toJson()}");
        }
    }

}
