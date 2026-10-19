<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->userName().Str::random(3),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => 'active',
        ];
    }

    // Suspended for a week by a system sanction
    public function banned(): static
    {
        return $this->state(fn () => ['status' => 'suspended'])->afterCreating(
            fn ($user) => $user->sanctions()->create(['type' => 'suspension', 'reason' => 'Test suspension', 'expires_at' => now()->addWeek()])
        );
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
