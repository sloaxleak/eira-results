<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Solo cuentas DEMO: no hay estudios precargados. Los estudios se registran al recibir
 * el resultado, con los datos transcritos del formato.
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'demo-eira-2026';

    public function run(): void
    {
        foreach ([
            ['Operadora DEMO', 'operador@eira.example', User::ROLE_OPERATOR],
            ['Publicadora DEMO', 'publicador@eira.example', User::ROLE_PUBLISHER],
            ['Administración DEMO', 'admin@eira.example', User::ROLE_ADMIN],
        ] as [$name, $email, $role]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => self::DEMO_PASSWORD,
                'role' => $role,
            ]);
        }
    }
}
