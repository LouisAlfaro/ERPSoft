<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Cargar roles
        $this->call(RolesSeeder::class);

        // 2) Crear usuario admin completo (users base de Laravel requiere name + email)
        $userId = DB::table('users')->insertGetId([
            'role_id'    => 1, // ADMIN
            'username'   => 'admin',
            'name'       => 'Administrator',
            'email'      => 'admin@example.com',
            'password'   => Hash::make('admin123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3) Crear empresa
        $companyId = DB::table('companies')->insertGetId([
            'name'       => 'DemoCorp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4) Crear local
        $localId = DB::table('locals')->insertGetId([
            'name'       => 'Local Central',
            'company_id' => $companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5) Asignar admin al local
        DB::table('user_local')->insert([
            'user_id'    => $userId,
            'local_id'   => $localId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
