<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmailIsNormalized(): void
    {
        self::assertSame('anna@example.com', (new User())->setEmail('  Anna@Example.COM ')->getEmail());
    }

    public function testEveryUserHasRoleUser(): void
    {
        $roles = (new User())->setRoles([User::ROLE_EDITOR])->getRoles();

        self::assertSame([User::ROLE_EDITOR, 'ROLE_USER'], $roles);
    }

    public function testEraseCredentialsClearsPlainPassword(): void
    {
        $user = (new User())->setPlainPassword('secret-password');
        $user->eraseCredentials();

        self::assertNull($user->getPlainPassword());
    }
}
