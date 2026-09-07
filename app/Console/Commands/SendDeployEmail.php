<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDeployEmail extends Command
{
    protected $signature = 'mail:deploy-success {to? : Recipient email address (defaults to the configured from address)}';

    protected $description = 'Send a deployment-success notification email through the configured mailer';

    public function handle(): int
    {
        $to = $this->argument('to') ?: (string) config('mail.from.address');
        $version = (string) config('courtly.app.version', '1.0.0');

        try {
            Mail::raw(
                "Courtly was deployed successfully.\n\n"
                .'Version: '.$version."\n"
                .'Time: '.now()->toDateTimeString().' (UTC)'."\n"
                .'Host: '.(gethostname() ?: 'unknown'),
                function ($message) use ($to, $version) {
                    $message->to($to)
                        ->subject('Courtly v'.$version.' deployment successful');
                }
            );
        } catch (\Throwable $e) {
            $this->error('Mail failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Deployment email sent to {$to}");

        return self::SUCCESS;
    }
}
