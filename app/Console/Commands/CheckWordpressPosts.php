<?php

namespace App\Console\Commands;

use App\Mail\NewPostNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CheckWordpressPosts extends Command
{
    protected $signature = 'wordpress:check-posts';

    protected $description = 'Check WordPress websites for new posts';

    protected $websites = [
        'instantgrabber' => 'https://instantgrabber.com/wp-json/wp/v2/posts',
        'saveautoglass' => 'https://saveautoglass.net/wp-json/wp/v2/posts',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Checking WordPress websites for new posts...');

        Storage::append('wordpress-check.log', '[' . now() . '] Check started');

        foreach ($this->websites as $siteName => $endpoint) {
            $this->checkSite($siteName, $endpoint);
        }

        Storage::append('wordpress-check.log', '[' . now() . '] Check completed');

        $this->info('Done checking websites.');
    }

    protected function checkSite($siteName, $endpoint)
    {
        $this->info("Checking {$siteName}...");

        try {
            // Get last checked post ID from storage
            $lastPostIdFile = "last_post_{$siteName}.txt";
            $lastPostId = Storage::exists($lastPostIdFile)
                ? (int) Storage::get($lastPostIdFile)
                : 0;

            // Fetch latest posts (limit to 5 to minimize data usage)
            $response = Http::get($endpoint, [
                'per_page' => 5,
                'orderby' => 'date',
                'order' => 'desc',
            ]);

            if ($response->successful()) {
                $posts = $response->json();

                if (empty($posts)) {
                    $this->info("No posts found for {$siteName}");

                    return;
                }

                $latestPost = $posts[0];
                $latestPostId = $latestPost['id'];

                if ($latestPostId > $lastPostId) {
                    // We found a new post!
                    $newPosts = array_filter($posts, function ($post) use ($lastPostId) {
                        return $post['id'] > $lastPostId;
                    });

                    $this->notifyNewPosts($siteName, $newPosts);

                    // Update the last post ID
                    Storage::put($lastPostIdFile, $latestPostId);
                    $this->info("Updated last post ID for {$siteName} to {$latestPostId}");
                } else {
                    $this->info("No new posts for {$siteName}");
                }
            } else {
                $this->error("Failed to fetch posts from {$siteName}: ".$response->status());
                Log::error("WordPress API error for {$siteName}: ".$response->body());
            }
        } catch (\Exception $e) {
            $this->error("Error checking {$siteName}: ".$e->getMessage());
            Log::error("Error checking WordPress site {$siteName}: ".$e->getMessage());
        }
    }

    protected function notifyNewPosts($siteName, $newPosts)
    {
        $this->info('Found '.count($newPosts)." new post(s) for {$siteName}!");
        Storage::append('wordpress-check.log', '[' . now() . '] Found ' . count($newPosts) . ' new post(s) for ' . $siteName);

        foreach ($newPosts as $post) {
            $this->info('New post: '.$post['title']['rendered']);

            // Send email notification
            try {
                Mail::to(config('wordpress.notification_email'))->send(
                    new NewPostNotification($siteName, $post)
                );
                $this->info('Email notification sent for post ID: '.$post['id']);
            } catch (\Exception $e) {
                $this->error('Failed to send email notification: '.$e->getMessage());
                Log::error('Email notification failed: '.$e->getMessage());
            }
        }
    }
}
