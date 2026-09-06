<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Circle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'admin_id',
        'invite_code',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'circle_members')->withTimestamps();
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function isAdmin(User $user): bool
    {
        return (int) $this->admin_id === (int) $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /**
     * Return a circle name that does not collide with any existing circle,
     * appending " 2", " 3", … as needed.
     */
    public static function uniqueName(string $desired): string
    {
        $desired = trim($desired) !== '' ? trim($desired) : 'Circle';
        $name = $desired;
        $suffix = 2;

        while (static::where('name', $name)->exists()) {
            $name = $desired.' '.$suffix;
            $suffix++;
        }

        return $name;
    }

    /**
     * Generate a unique, shareable invite code.
     */
    public static function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }
}
