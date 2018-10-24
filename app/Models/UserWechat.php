<?php
/**
 * Created by PhpStorm.
 * User: zhan
 * Date: 2018/10/24
 * Time: 7:30
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserWechat extends Model
{
    protected $table = 'user_wechat';

    protected $fillable = ['user_name', 'subscribed_at', 'unsubscribed_at', 'user_id'];

    protected $casts = [
        'subscribed_at' => 'date',
        'unsubscribed_at' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}