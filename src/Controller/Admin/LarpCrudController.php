<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Larp;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Validator\Constraints\Image;

/**
 * @extends AbstractCrudController<Larp>
 */
class LarpCrudController extends AbstractCrudController
{
    public const UPLOAD_DIR = 'public/uploads/larps';
    public const BASE_PATH = 'uploads/larps';

    public function __construct(private readonly AdminUrlGenerator $urls)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Larp::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('larp.label')
            ->setEntityLabelInPlural('larp.label_plural')
            ->setDefaultSort(['status' => 'ASC', 'position' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'format', 'setting', 'teaser']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $characters = Action::new('characters', 'larp.characters', 'fa fa-masks-theater')
            ->linkToUrl(fn (Larp $larp): string => CharacterCrudController::larpListUrl($this->urls, $larp))
            ->displayIf(static fn (Larp $larp): bool => !$larp->isInitiative());
        $addCharacter = Action::new('addCharacter', 'character.add', 'fa fa-plus')
            ->linkToUrl(fn (Larp $larp): string => CharacterCrudController::larpListUrl($this->urls, $larp, Action::NEW))
            ->displayIf(static fn (Larp $larp): bool => !$larp->isInitiative() && !$larp->getEditions()->isEmpty());

        $view = Action::new('viewOnSite', 'larp.view_on_site', 'fa fa-arrow-up-right-from-square')
            ->linkToRoute('app_larp_show', static fn (Larp $larp): array => ['slug' => $larp->getSlug()])
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Larp $larp): bool => $larp->isPublished());

        return $actions
            ->add(Crud::PAGE_INDEX, $characters)
            ->add(Crud::PAGE_INDEX, $addCharacter)
            ->add(Crud::PAGE_INDEX, $view)
            ->add(Crud::PAGE_EDIT, $characters)
            ->add(Crud::PAGE_EDIT, $view);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status')->add('projectType')->add('featured');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('larp.tab.basics', 'fa fa-circle-info');
        yield ImageField::new('coverImage', 'larp.cover_image')
            ->setBasePath(self::BASE_PATH)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFileConstraints([new Image(maxSize: '8M')])
            ->setRequired(false)
            ->setHelp('larp.cover_image_help')
            ->onlyOnIndex();
        yield TextField::new('name', 'larp.name')->setColumns(8);
        yield SlugField::new('slug', 'larp.slug')
            ->setTargetFieldName('name')
            ->setColumns(4)
            ->hideOnIndex();
        yield ChoiceField::new('projectType', 'larp.project_type')->setColumns(4)->hideOnIndex();
        yield ChoiceField::new('status', 'larp.status')->setColumns(4)->renderAsBadges([
            'draft' => 'secondary',
            'current' => 'success',
            'past' => 'info',
        ]);
        yield BooleanField::new('featured', 'larp.featured')
            ->setColumns(4)
            ->setHelp('larp.featured_help');
        yield TextField::new('format', 'larp.format')
            ->setRequired(false)
            ->setHelp('larp.format_help')
            ->setColumns(6);
        yield TextField::new('setting', 'larp.setting')
            ->setRequired(false)
            ->setHelp('larp.setting_help')
            ->setColumns(6);
        yield TextField::new('playerCount', 'larp.player_count')->setRequired(false)->setColumns(6)->hideOnIndex();
        yield TextField::new('price', 'larp.price')->setRequired(false)->setColumns(6)->hideOnIndex();
        yield IntegerField::new('position', 'common.position')
            ->setHelp('common.position_help')
            ->setColumns(4)
            ->hideOnIndex();

        yield FormField::addTab('larp.tab.description', 'fa fa-align-left');
        yield TextareaField::new('teaser', 'larp.teaser')
            ->setRequired(false)
            ->setHelp('larp.teaser_help')
            ->hideOnIndex();
        yield TextEditorField::new('description', 'larp.description')
            ->setRequired(false)
            ->hideOnIndex();

        yield FormField::addTab('larp.tab.media', 'fa fa-image');
        yield ImageField::new('coverImage', 'larp.cover_image')
            ->setBasePath(self::BASE_PATH)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFileConstraints([new Image(maxSize: '8M')])
            ->setRequired(false)
            ->setHelp('larp.cover_image_help')
            ->onlyOnForms();
        yield TextField::new('coverImageAlt', 'larp.cover_image_alt')
            ->setRequired(false)
            ->setHelp('larp.cover_image_alt_help')
            ->onlyOnForms();

        yield FormField::addTab('larp.tab.editions', 'fa fa-calendar-days');
        yield CollectionField::new('editions', 'larp.editions')
            ->useEntryCrudForm(LarpEditionCrudController::class)
            ->setHelp('larp.editions_help')
            ->setFormTypeOption('by_reference', false)
            ->setEntryIsComplex()
            ->renderExpanded(false)
            ->onlyOnForms();
        yield IntegerField::new('editionCount', 'larp.editions')->onlyOnIndex();
    }
}
