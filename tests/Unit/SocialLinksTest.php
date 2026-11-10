<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Larp;
use App\Entity\SiteSettings;
use App\Social\SocialLink;
use App\Social\SocialNetwork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;

final class SocialLinksTest extends TestCase
{
    /** @return iterable<string, array{SocialNetwork, string, bool}> */
    public static function urls(): iterable
    {
        yield 'facebook page' => [SocialNetwork::Facebook, 'https://www.facebook.com/pradawnezwoje', true];
        yield 'facebook group' => [SocialNetwork::Facebook, 'https://facebook.com/groups/287396224278365/', true];
        yield 'fb.me' => [SocialNetwork::Facebook, 'https://fb.me/abc', true];
        yield 'plain http' => [SocialNetwork::Facebook, 'http://www.facebook.com/x', false];
        yield 'look-alike host' => [SocialNetwork::Facebook, 'https://facebook.com.evil.example/x', false];
        yield 'other site as facebook' => [SocialNetwork::Facebook, 'https://www.instagram.com/x', false];
        yield 'discord invite' => [SocialNetwork::Discord, 'https://discord.gg/c5UZ7nK6cE', true];
        yield 'youtube short link' => [SocialNetwork::YouTube, 'https://youtu.be/xyz', true];
        yield 'any website' => [SocialNetwork::Website, 'https://larp.example.org/', true];
        yield 'website without https' => [SocialNetwork::Website, 'http://larp.example.org/', false];
        yield 'javascript' => [SocialNetwork::Website, 'javascript:alert(1)', false];
    }

    #[DataProvider('urls')]
    public function testNetworksAcceptOnlyHttpsLinksToTheirOwnSite(SocialNetwork $network, string $url, bool $ok): void
    {
        self::assertSame($ok, $network->accepts($url));
    }

    public function testRowsAreCleanedAndTurnedIntoButtons(): void
    {
        $larp = (new Larp())->setSocialLinks([
            ['network' => 'facebook', 'url' => ' https://www.facebook.com/pradawnezwoje '],
            ['network' => 'facebook', 'url' => 'https://www.facebook.com/groups/1/', 'label' => ' Grupa graczy '],
            ['network' => 'discord', 'url' => '   '],
            'junk',
        ]);

        self::assertSame([
            ['network' => 'facebook', 'url' => 'https://www.facebook.com/pradawnezwoje', 'label' => null],
            ['network' => 'facebook', 'url' => 'https://www.facebook.com/groups/1/', 'label' => 'Grupa graczy'],
        ], $larp->getSocialLinks());
        self::assertSame(['Facebook', 'Grupa graczy'], array_map(static fn (SocialLink $l): string => $l->getLabel(), $larp->getSocialButtons()));
        self::assertSame('simple-icons:facebook', $larp->getSocialButtons()[0]->getIcon());

        self::assertSame([], (new Larp())->setSocialLinks([])->getSocialButtons());
        self::assertSame([], SocialLink::fromRows([['network' => 'myspace', 'url' => 'https://myspace.com/x']]), 'unknown networks are skipped');
    }

    public function testLinksToTheWrongSiteAreRejected(): void
    {
        $larp = (new Larp())->setSocialLinks([
            ['network' => 'instagram', 'url' => 'https://www.facebook.com/oops'],
            ['network' => 'facebook', 'url' => 'https://www.facebook.com/ok'],
            ['network' => 'tiktok', 'url' => 'http://tiktok.com/@x'],
        ]);

        $violations = Validation::createValidator()->validate($larp, new Callback(
            static fn (Larp $l, ExecutionContextInterface $context) => $l->validateSocialLinks($context),
        ));

        self::assertSame(['socialLinks[0][url]', 'socialLinks[2][url]'], array_map(static fn ($v): string => $v->getPropertyPath(), iterator_to_array($violations)));
    }

    public function testOrganizationButtonsComeFromSiteSettings(): void
    {
        $settings = (new SiteSettings())->setFacebookUrl('https://www.facebook.com/StowarzyszenieLasTajemnic')->setDiscordUrl('https://discord.gg/x');

        self::assertSame(['Facebook', 'Discord'], array_map(static fn (SocialLink $l): string => $l->getLabel(), $settings->getSocialButtons()));
    }
}
