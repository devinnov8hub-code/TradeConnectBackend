<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'phone_number',
    'state',
    'lga',
    'address',
    'avatar_path',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::created(
            function (User $user): void {
                if ($user->account_code) {
                    return;
                }

                /*
                 * If role was omitted and the database default
                 * supplied "user", the model instance may not yet
                 * contain that database default.
                 *
                 * In that case the safe default prefix is BYR.
                 */
                $prefix =
                    $user->role === UserRole::Admin
                        ? 'ADM'
                        : 'BYR';

                $user->forceFill([
                    'account_code' =>
                        $prefix
                        .'-'
                        .str_pad(
                            (string) $user->id,
                            6,
                            '0',
                            STR_PAD_LEFT
                        ),
                ])->saveQuietly();
            }
        );
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' =>
                $this->role->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' =>
                'datetime',

            'password' =>
                'hashed',

            'role' =>
                UserRole::class,

            'status' =>
                UserStatus::class,
        ];
    }
}