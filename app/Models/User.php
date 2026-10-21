<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * "role" and "admin_pin" are deliberately NOT mass-assignable. They are only ever
     * written with forceFill() (see UserController), so a stray
     * $user->update($request->all()) can never promote someone to admin or replace a PIN.
     *
     * "is_verified" stays here because OtpController creates the user with it.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_verified',
        'phone',
        'address',
        'notifications',
        'privacy',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'admin_pin',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'address' => 'array',
        'notifications' => 'array',
        'privacy' => 'array',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The one shape the frontend receives for the signed-in user
     * (login, OTP verification, /user and profile updates).
     * It never includes the password or the admin PIN.
     */
    public function toAuthPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_verified' => (bool) $this->is_verified,
            'phone' => $this->phone,
            'address' => $this->address,
            'notifications' => $this->notifications,
            'privacy' => $this->privacy,
        ];
    }

    /**
     * Deleting an account with SoftDeletes only hides the row, and the row would keep the
     * real email, which then blocks that person from ever signing up again.
     * So the personal data is wiped first. Orders and payments stay linked for your records.
     */
    public function anonymizeAndDelete(): void
    {
        $this->tokens()->delete();

        $this->forceFill([
            'name' => 'Deleted user',
            'email' => 'deleted-' . $this->id . '-' . Str::lower(Str::random(8)) . '@deleted.invalid',
            'phone' => null,
            'address' => [],
            'notifications' => [],
            'privacy' => [],
            'admin_pin' => null,
            'password' => Hash::make(Str::random(40)),
        ])->save();

        $this->delete();
    }
}
