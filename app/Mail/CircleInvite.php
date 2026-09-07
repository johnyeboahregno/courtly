<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Mail\Mailable;

class CircleInvite extends Mailable
{
    public function __construct(public Circle $circle, public User $inviter)
    {
    }

    /**
     * Build a self-contained HTML invitation email (no Blade views, since
     * compiled views are not available on the server).
     */
    public function build(): self
    {
        $circleName = htmlspecialchars((string) $this->circle->name, ENT_QUOTES, 'UTF-8');
        $inviterName = htmlspecialchars((string) $this->inviter->name, ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars((string) $this->circle->invite_code, ENT_QUOTES, 'UTF-8');
        $joinUrl = htmlspecialchars((string) config('app.url', '').'/circles', ENT_QUOTES, 'UTF-8');

        $html = '<p>Hi,</p>'
            .'<p>'.$inviterName.' invited you to join <strong>'.$circleName.'</strong> on Courtly.</p>'
            .'<p>Open Courtly and join with this invite code:</p>'
            .'<p style="font-size:22px;font-weight:bold;letter-spacing:3px;color:#7c5cff">'.$code.'</p>'
            .'<p><a href="'.$joinUrl.'" style="display:inline-block;padding:12px 20px;background:#7c5cff;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold">Open Circles</a></p>'
            .'<p>If this was not intended, you can safely ignore this email.</p>';

        return $this
            ->subject($inviterName.' invited you to '.$circleName)
            ->html($html);
    }
}
