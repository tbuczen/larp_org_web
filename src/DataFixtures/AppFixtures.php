<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\SiteSettings;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo data for local development. Never load this on production:
 * it creates accounts with well-known passwords.
 */
class AppFixtures extends Fixture
{
    public const ADMIN_EMAIL = 'admin@example.com';
    public const EDITOR_EMAIL = 'editor@example.com';
    public const PASSWORD = 'admin1234';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $settings = (new SiteSettings())
            ->setOrganizationName('Stowarzyszenie Larpowe „Las Tajemnic”')
            ->setTagline('Przeżyj prawdziwą przygodę')
            ->setAbout('<p>Dołącz do naszej społeczności i śledź postępy nad naszymi projektami.</p>')
            ->setContactEmail('stowarzyszenie@lastajemnic.org')
            ->setFacebookUrl('https://www.facebook.com/StowarzyszenieLasTajemnic')
            ->setDiscordUrl('https://discord.gg/Zjf2mZN5jV');
        $manager->persist($settings);

        foreach ([self::ADMIN_EMAIL => User::ROLE_ADMIN, self::EDITOR_EMAIL => User::ROLE_EDITOR] as $email => $role) {
            $user = (new User())->setEmail($email)->setRoles([$role]);
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
            $manager->persist($user);
        }

        $manager->flush();
    }
}
