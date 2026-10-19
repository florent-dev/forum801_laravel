<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SectionFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'visibility' => 'public',
            'position' => fake()->numberBetween(1, 50),
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['visibility' => 'private']);
    }
}
