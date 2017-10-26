<?php
/**
 * Created by PhpStorm.
 * User: 39096
 * Date: 2017/10/26
 * Time: 17:23
 */

namespace App\Http\Controllers;


use EasyWeChat\Foundation\Application;
use EasyWeChat\Support\Collection;
use Illuminate\Support\Facades\Log;

class WechatController extends Controller
{
    public function serve(Application $wechat)
    {
        Log::info('request arrived.'); # 注意：Log 为 Laravel 组件，所以它记的日志去 Laravel 日志看，而不是 EasyWeChat 日志

        // 获取wechat对象

        // 1. 从服务容器中直接取
        $wechat = app('wechat');

        // 2. 使用facede
//        $wechat = EasyWeChat::server();
        /**
         * @var Application $wechat
         */
        $wechat->server->setMessageHandler(function($message){
            // 打印微信发送过来的消息
            Log::info('wechat message', [$message]);
            $content = $message->Content;
            if ($content == 1) {
                return '1';
            } else {
                return "欢迎学习 easy wechat！";
            }
            // 实质上是返回给微信服务器的消息
        });

        Log::info('return response.');

        return $wechat->server->serve();
    }
}