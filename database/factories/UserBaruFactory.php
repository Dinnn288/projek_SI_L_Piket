<?php

namespace Database\Factories;

use App\Models\UserBaru;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserBaru>
 */
class UserBaruFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['admin', 'siswa', 'guru', 'kepala_sekolah']),
            'status' => fake()->randomElement(['aktif', 'nonaktif']),
        ];
    }
}