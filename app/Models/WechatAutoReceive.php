<?php
/**
 * Created by PhpStorm.
 * User: zhan
 * Date: 2018/10/24
 * Time: 7:30
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WechatAutoReceive extends Model
{
    use SoftDeletes;

    protected $table = 'wechat_auto_receives';

    protected $fillable = ['receive', 'send'];

    public function getSendAttribute($value)
    {
        return str_replace('\n', "\n", $value);
    }
}