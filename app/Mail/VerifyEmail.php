<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmail extends Mailable
{
    public function __construct(public User $user)
    {
    }

    /**
     * Build a self-contained HTML verification email (no Blade views, since
     * compiled views are not available on the server).
     */
    public function build(): self
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => (string) $this->user->getKey(),
                'hash' => sha1($this->user->getEmailForVerification()),
            ]
        );

        $name = htmlspecialchars((string) $this->user->name, ENT_QUOTES, 'UTF-8');
        $link = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        $html = '<p>Hi '.$name.',</p>'
            .'<p>Please verify your email address to start using Courtly. This link expires in 60 minutes.</p>'
            .'<p><a href="'.$link.'" style="display:inline-block;padding:12px 20px;background:#ff2d55;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold">Verify Email Address</a></p>'
            .'<p>If you did not create this account, you can safely ignore this email.</p>';

        return $this
            ->subject('Verify your email address')
            ->html($html);
    }
}
