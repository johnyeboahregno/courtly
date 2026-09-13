<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete test/demo accounts from the database.
 *
 * Matches users by test email domains or obviously test-ish names, never
 * touches SUPER_ADMIN accounts, and defaults to a dry run so the operator
 * can review the exact list before adding --force.
 */
class PurgeTestUsers extends Command
{
    protected $signature = 'users:purge-test {--force : Actually delete (default is a dry run)}';

    protected $description = 'Delete test/demo users. Defaults to a dry run.';

    public function handle(): int
    {
        $users = User::query()
            ->where('role', '!=', UserRole::SUPER_ADMIN->value)
            ->where(function ($q) {
                foreach (['courtly.test', 'example.com', 'example.org', 'example.net'] as $domain) {
                    $q->orWhere('email', 'like', "%@{$domain}");
                }
                foreach (['test', 'smoke', 'tester', 'demo', 'dummy', 'factory', 'seed', 'example'] as $word) {
                    $q->orWhere('name', 'like', "%{$word}%");
                }
            })
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No test users found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Role'],
            $users->map(fn (User $u) => [$u->id, $u->name, $u->email, $u->role->value])->all()
        );

        if (! $this->option('force')) {
            $this->warn("Dry run — {$users->count()} users matched but were NOT deleted. Add --force to delete.");
            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm("Delete these {$users->count()} users and all their data? This cannot be undone.")) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $deleted = 0;
        DB::transaction(function () use ($users, &$deleted) {
            foreach ($users as $user) {
                $user->delete();
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} test users.");

        return self::SUCCESS;
    }
}
