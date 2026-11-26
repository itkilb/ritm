<?php

namespace App\Containers\AppSection\Authorization\Tests\Unit\Data\Seeders;

use App\Containers\AppSection\Authorization\Data\Seeders\AuthorizationSeeder_1;
use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Authorization\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuthorizationSeeder_1::class)]
final class AuthorizationSeederTest extends UnitTestCase
{
    public function testCanSeed(): void
    {
        // The seeder creates one super-admin role per configured guard, and the
        // product adds a sanctum guard alongside web/api.
        $this->assertDatabaseCount('roles', count(config('auth.guards')));
        foreach (config('auth.guards') as $name => $value) {
            $this->assertDatabaseHas('roles', [
                'name' => Role::SUPER_ADMIN,
                'guard_name' => $name,
            ]);
        }
    }
}
