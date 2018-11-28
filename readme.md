# install
1. run `composer install`
2. copy `.env.example` to `.env`
3. migrate database `php artisan migrate`
4. change `.env`
    - `APP_KEY`
    - `DB_*`
    - `CACHE_DRIVER` except file
    - `WECHAT_OFFICIAL_ACCOUNT_*` set wechat config
    - `BAIDU_TOKEN_*` if use baidu text recognition


# debug
1. debug log to /storage/logs/laravel.log
2. wechat debug log to /storage/logs/wechat.log