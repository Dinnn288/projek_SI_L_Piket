<?php

namespace Database\Seeders;

use App\Models\UserBaru;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Membuat 1 akun Admin awal pada users_baru.
     *
     * Idempoten: aman dijalankan berkali-kali karena memakai
     * updateOrCreate berdasarkan username unik.
     */
    public function run(): void
    {
        $username = (string) config('admin-seeder.username', env('ADMIN_USERNAME', 'admin'));
        $password = (string) config('admin-seeder.password', env('ADMIN_PASSWORD', 'admin123'));

        if ($username === '' || $password === '') {
            $this->command->warn('AdminSeeder dilewati: ADMIN_USERNAME / ADMIN_PASSWORD belum diisi.');

            return;
        }

        UserBaru::updateOrCreate(
            ['username' => $username],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'aktif',
            ]
        );

        $this->command->info('Akun Admin awal tersedia di users_baru.');
    }
}
