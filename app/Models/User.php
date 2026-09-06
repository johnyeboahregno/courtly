<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Mail\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'google_id',
        'facebook_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Send the verification email using a self-contained HTML mailable.
     * The default notification relies on compiled Blade views, which are not
     * available on the server.
     */
    public function sendEmailVerificationNotification(): void
    {
        Mail::to($this->getEmailForVerification())->send(new VerifyEmail($this));
    }

    /**
     * The circle this user administers (their personal circle, created at
     * registration).
     */
    public function personalCircle(): HasOne
    {
        return $this->hasOne(Circle::class, 'admin_id');
    }

    /**
     * Every circle the user belongs to (their own plus any they have joined).
     */
    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_members')->withTimestamps();
    }

    /**
     * The user's own player record inside their personal circle.
     */
    public function player(): HasOne
    {
        return $this->hasOne(Player::class, 'user_id')
            ->whereIn('circle_id', function ($query) {
                $query->select('id')
                    ->from('circles')
                    ->whereColumn('circles.admin_id', 'players.user_id');
            });
    }

    public function isOrganiser(): bool
    {
        return $this->role === UserRole::ORGANISER || $this->role === UserRole::ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }
}
