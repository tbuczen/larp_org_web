<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\SiteSettingsRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Single entry point of the admin panel. Every section of the site is reached
 * from the sidebar defined in configureMenuItems().
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly SiteSettingsRepository $settings,
        private readonly UserRepository $users,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'settings' => $this->settings->get(),
            'userCount' => $this->users->count([]),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->settings->get()->getOrganizationName())
            ->setFaviconPath('favicon.svg')
            ->setTranslationDomain('admin')
            ->setLocales(['pl' => 'Polski', 'en' => 'English'])
            // Light / dark / auto switcher in the user menu. The choice is kept in the browser.
            ->setDefaultColorScheme('auto')
            ->renderContentMaximized();
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('styles/admin.css');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $menu = parent::configureUserMenu($user);
        if ($user instanceof User) {
            $menu->setName((string) $user);
        }

        return $menu;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('menu.dashboard', 'fa fa-gauge');
        yield MenuItem::linkToRoute('menu.view_site', 'fa fa-arrow-up-right-from-square', 'app_home')
            ->setLinkTarget('_blank');

        yield MenuItem::section('menu.section.site');
        yield MenuItem::linkTo(SiteSettingsCrudController::class, 'menu.settings', 'fa fa-sliders')
            ->setAction('edit')
            ->setEntityId($this->settings->get()->getId());

        // Sections for LARPs, characters, tags, galleries, menus, page blocks and
        // themes are added here as the corresponding issues are implemented.

        yield MenuItem::section('menu.section.access')->setPermission(User::ROLE_ADMIN);
        yield MenuItem::linkTo(UserCrudController::class, 'menu.users', 'fa fa-users')
            ->setPermission(User::ROLE_ADMIN);
    }
}
