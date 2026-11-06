<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteSettings;
use App\Repository\SiteSettingsRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings are a single row, so only the "edit" page exists. Any other page redirects to it.
 *
 * @extends AbstractCrudController<SiteSettings>
 */
class SiteSettingsCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SiteSettingsRepository $settings,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SiteSettings::class;
    }

    public function index(AdminContext $context): Response
    {
        return $this->redirectToEdit();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('settings.label')
            ->setPageTitle(Crud::PAGE_EDIT, 'settings.title');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::DETAIL)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('settings.tab.organization', 'fa fa-people-group');
        yield TextField::new('organizationName', 'settings.organization_name');
        yield TextField::new('tagline', 'settings.tagline')->setRequired(false);
        yield TextEditorField::new('about', 'settings.about')->setRequired(false);

        yield FormField::addTab('settings.tab.contact', 'fa fa-share-nodes');
        yield EmailField::new('contactEmail', 'settings.contact_email')->setRequired(false);
        yield UrlField::new('facebookUrl', 'Facebook')->setRequired(false);
        yield UrlField::new('instagramUrl', 'Instagram')->setRequired(false);
        yield UrlField::new('discordUrl', 'Discord')->setRequired(false);

        yield FormField::addTab('settings.tab.appearance', 'fa fa-palette');
        yield TextField::new('theme', 'settings.theme')
            ->setHelp('settings.theme_help');
    }

    private function redirectToEdit(): RedirectResponse
    {
        return $this->redirect($this->urls
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($this->settings->get()->getId())
            ->generateUrl());
    }
}
