<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Plain, self-contained verification email. Deliberately uses a raw HTML body
 * rather than a Blade view so it works on servers where the compiled-view
 * directory is not writable.
 */
class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $url,
        public string $name,
    ) {
    }

    public function build(): self
    {
        $name = e($this->name);
        $url = e($this->url);

        return $this->subject('Verify your email address')
            ->html(
                '<p>Hi '.$name.',</p>'
                .'<p>Please verify your Courtly email address by opening this link:</p>'
                .'<p><a href="'.$url.'">'.$url.'</a></p>'
                .'<p>If you didn\'t create this account, you can ignore this email.</p>'
            );
    }
}
