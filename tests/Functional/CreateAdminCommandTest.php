<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateAdminCommandTest extends KernelTestCase
{
    public function testCreatesAdminWithHashedPassword(): void
    {
        $tester = $this->tester();
        $tester->execute(['email' => 'New.Admin@Example.com', '--password' => 'long-password']);

        $tester->assertCommandIsSuccessful();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'new.admin@example.com']);
        self::assertNotNull($user);
        self::assertContains(User::ROLE_ADMIN, $user->getRoles());
        self::assertNotSame('long-password', $user->getPassword());
    }

    public function testRejectsShortPassword(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['email' => 'x@example.com', '--password' => 'short']));
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find('app:create-admin'));
    }
}
