<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminAccessTest extends WebTestCase
{
    use RollbackTransactionTrait;

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testPublicHomePageShowsOrganization(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.site-brand', 'Las Tajemnic');
        self::assertSelectorTextContains('h1', 'Przeżyj prawdziwą przygodę');
    }

    public function testLoginWithFormShowsDashboard(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form([
            '_username' => AppFixtures::ADMIN_EMAIL,
            '_password' => AppFixtures::PASSWORD,
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.dashboard-grid');
    }

    public function testWrongPasswordIsRejected(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('form')->form([
            '_username' => AppFixtures::ADMIN_EMAIL,
            '_password' => 'wrong-password',
        ]));
        $client->followRedirect();

        self::assertRouteSame('app_login');
        self::assertSelectorExists('.alert-danger');
    }

    public function testAdminCanManageUsersAndSettings(): void
    {
        $client = $this->loginAs(AppFixtures::ADMIN_EMAIL);

        $client->request('GET', '/admin/user');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::EDITOR_EMAIL);

        $client->request('GET', '/admin/site-settings');
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testAdminCanSaveSettings(): void
    {
        $client = $this->loginAs(AppFixtures::ADMIN_EMAIL);
        $client->request('GET', '/admin/site-settings');
        $crawler = $client->followRedirect();

        $form = $crawler->filter('form[name="SiteSettings"]')->form();
        $form['SiteSettings[tagline]'] = 'Nowe hasło';
        $client->submit($form);
        $client->followRedirect();

        $client->request('GET', '/');
        self::assertSelectorExists('meta[name="description"]');
        self::assertStringContainsString('Nowe hasło', (string) $client->getResponse()->getContent());
    }

    public function testSettingsRejectNonHttpsSocialLinks(): void
    {
        $client = $this->loginAs(AppFixtures::ADMIN_EMAIL);
        $client->request('GET', '/admin/site-settings');
        $crawler = $client->followRedirect();

        $form = $crawler->filter('form[name="SiteSettings"]')->form();
        $form['SiteSettings[facebookUrl]'] = 'http://facebook.com/insecure';
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAdminCanCreateUserWithPassword(): void
    {
        $client = $this->loginAs(AppFixtures::ADMIN_EMAIL);
        $crawler = $client->request('GET', '/admin/user/new');

        $form = $crawler->filter('form[name="User"]')->form();
        $form['User[email]'] = 'new.editor@example.com';
        $form['User[roles]'][0]->tick();
        $form['User[plainPassword][first]'] = 'editor-password';
        $form['User[plainPassword][second]'] = 'editor-password';
        $client->submit($form);
        self::assertResponseRedirects();

        $client->request('GET', '/logout');
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('form')->form([
            '_username' => 'new.editor@example.com',
            '_password' => 'editor-password',
        ]));
        $client->followRedirect();
        self::assertRouteSame('admin');
    }

    public function testEditorCannotManageUsers(): void
    {
        $client = $this->loginAs(AppFixtures::EDITOR_EMAIL);

        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/user');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDarkModeSwitcherIsAvailable(): void
    {
        $client = $this->loginAs(AppFixtures::ADMIN_EMAIL);
        $crawler = $client->request('GET', '/admin');

        self::assertSelectorExists('[data-ea-color-scheme]');
        self::assertCount(0, $crawler->filter('html[data-ea-dark-scheme-is-enabled="false"]'));
    }

    private function loginAs(string $email): KernelBrowser
    {
        $client = self::createClient();
        $this->startTransaction($client);
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        $client->loginUser($user);

        return $client;
    }
}
