<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use PHPHtmlParser\Dom;
use Intervention\Image\ImageManagerStatic as Image;


class DownloadMeiziImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $timeout = 600;

    public $page;

    /**
     * Create a new job instance.
     *
     * @param null $page
     * @throws \Exception
     */
    public function __construct($page = null)
    {
        $this->page = $page ? $page: random_int(2, 144);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $page = $this->page;
        try {
            if (!Storage::disk('public')->has("meizi/$page.jpg")) {
                $imageUrls = $this->getMeiziImgs($page);

                $imgMinWidth = 9999999;  // 最小宽度
                foreach ($imageUrls as $key => $img) {
                    \Log::debug("Downloading page: ($page) image $img...");
                    $image = Image::make($img);
                    $imgMinWidth = $image->width() < $imgMinWidth ? $image->width() : $imgMinWidth;
                    $images[] = $image;
                    \Log::debug("Loading success!");
                }
                $imgSumHeight = 0;
                foreach ($images as $key => &$image) {
                    /** @var \Intervention\Image\Image $image */
                    $image->widen($imgMinWidth);    // 调整宽度
                    $imgSumHeight += $image->height();
                }
                $nowHeight = 0;
                $newImg = Image::canvas($imgMinWidth, $imgSumHeight);
                foreach ($images as $image) {
                    $newImg->insert($image, 'top-left', 0, $nowHeight);
                    $nowHeight += $image->height();
                }
                $newImg->save(Storage::disk('public')->path("meizi/$page.jpg"));
            }
        } catch (\Exception $exception) {
            \Log::debug("Loading fail!");
            \Log::error($exception->getMessage());
        }
    }

    private function getMeiziImgs($page = null)
    {
        if (empty($page)) $page = random_int(1, 34);

        $dom = $this->getMeiziHtml($page);
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
