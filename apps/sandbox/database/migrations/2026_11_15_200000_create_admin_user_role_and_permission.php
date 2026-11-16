<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sandbox only: creates the passwordless "admin" user (see the login button at /login),
 * the "admin" role and the "entities.*" permission (every action on every entity and scope).
 */
return new class extends Migration
{
    private const EMAIL = 'admin@sandbox.test';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('entities.*', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo($permission);

        // The users table requires a password, but the sandbox login never asks for it.
        $user = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'admin', 'password' => Hash::make(Str::random(40))],
        );
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        User::query()->where('email', self::EMAIL)->delete();
        Role::query()->where('name', 'admin')->where('guard_name', 'web')->delete();
        Permission::query()->where('name', 'entities.*')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
