<?php
/**
 * Created by PhpStorm.
 * User: 39096
 * Date: 2017/10/26
 * Time: 17:23
 */

namespace App\Http\Controllers;


use Illuminate\Support\Facades\Log;

class WechatController extends Controller
{
    public function serve()
    {
        Log::info('request arrived.'); # 注意：Log 为 Laravel 组件，所以它记的日志去 Laravel 日志看，而不是 EasyWeChat 日志

        $app = app('wechat.official_account');
        $app->server->push(function($message){
            return "欢迎关注 overtrue！";
        });

        return $app->server->serve();
    }
}