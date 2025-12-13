<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrador',
                'email' => 'admin@missaonomeacao.com.br',
                'password' => Hash::make('admin123'),
            ],
            [
                'name' => 'Luis Pimenta',
                'email' => 'luis@missaonomeacao.com.br',
                'password' => Hash::make('senha123'),
            ],
            [
                'name' => 'Gestor',
                'email' => 'gestor@missaonomeacao.com.br',
                'password' => Hash::make('gestor123'),
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']], // Check by email
                $userData // Update or create with these values
            );
        }
    }
}
