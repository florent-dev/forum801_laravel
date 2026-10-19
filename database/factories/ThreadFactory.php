<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ThreadFactory extends Factory
{
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(4), '.');

        return [
            'section_id' => Section::factory(),
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'last_post_at' => now(),
        ];
    }
}
