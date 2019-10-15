<?php

namespace App\Console\Commands;

use App\Jobs\DownloadMeiziImage;
use Illuminate\Console\Command;

class DownloadMeiziImageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meizi:download';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        for ($i = 2; $i < 144; $i++) {
            DownloadMeiziImage::dispatchNow($i);
        }
    }
}
