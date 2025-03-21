<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewPostNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $siteName;

    public $post;

    public function __construct($siteName, $post)
    {
        $this->siteName = $siteName;
        $this->post = $post;
    }

    public function build()
    {
        return $this->subject("New Post on {$this->siteName}")
            ->markdown('emails.new-post', [
                'siteName' => $this->siteName,
                'postTitle' => $this->post['title']['rendered'],
                'postUrl' => $this->post['link'],
                'postDate' => $this->post['date'],
                'postExcerpt' => isset($this->post['excerpt']['rendered'])
                    ? strip_tags($this->post['excerpt']['rendered'])
                    : '',
            ]);
    }
}
