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
use Illuminate\Support\Collection;

class WechatMessage extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'from_user_name', 'to_user_name', 'msg_type', 'data', 'type'];

    protected $casts = ['data' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}