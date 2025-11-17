<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Opcional: si igual quieres datos fake, puedes dejarlos al final.
        $this->call(DevBootstrapSeeder::class);

        // Si quieres crear usuarios de prueba adicionales con factory,
        // recuerda que ahora users tiene role_id y username, así que define esos campos:
        // \App\Models\User::factory()->create([
        //     'role_id' => 2, // SUPERVISOR
        //     'username' => 'supervisor1',
        //     'name' => 'Supervisor Uno',
        //     'email' => 'sup1@example.com',
        // ]);
    }
}
