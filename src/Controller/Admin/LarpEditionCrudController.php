<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Repository\LarpRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Editions of LARPs, each edited as an event of its own: dates, place,
 * recruitment, Facebook event, description and cover image (texts and image
 * fall back to the LARP's). Reached from a LARP ("Edycje"), filtered to it.
 *
 * @extends AbstractCrudController<LarpEdition>
 */
class LarpEditionCrudController extends AbstractCrudController
{
    public const UPLOAD_DIR = 'public/uploads/editions';
    public const BASE_PATH = 'uploads/editions';
    public const LARP_FILTER = 'larp';

    public function __construct(
        private readonly AdminUrlGenerator $urls,
        private readonly RequestStack $requests,
        private readonly LarpRepository $larps,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return LarpEdition::class;
    }

    /** List of one LARP's editions (or its "new edition" form with $action = NEW). */
    public static function larpListUrl(AdminUrlGenerator $urls, Larp $larp, string $action = Action::INDEX): string
    {
        return $urls->unsetAll()
            ->setController(self::class)
            ->setAction($action)
            ->set('filters', [self::LARP_FILTER => ['comparison' => '=', 'value' => $larp->getId()]])
            ->generateUrl();
    }

    public function configureCrud(Crud $crud): Crud
    {
        $larp = $this->currentLarp();

        return $crud
            ->setEntityLabelInSingular('edition.label')
            ->setEntityLabelInPlural('edition.label_plural')
            ->setPageTitle(Crud::PAGE_INDEX, null !== $larp ? new TranslatableMessage('edition.list_for', ['%larp%' => $larp->getName()], 'admin') : 'edition.label_plural')
            ->setPageTitle(Crud::PAGE_NEW, null !== $larp ? new TranslatableMessage('edition.new_for', ['%larp%' => $larp->getName()], 'admin') : 'edition.new')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (LarpEdition $e): string => $e->getTitle())
            ->setDefaultSort(['startDate' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields(['name', 'location', 'larp.name']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $larp = $this->currentLarp();
        $view = Action::new('viewOnSite', 'larp.view_on_site', 'fa fa-arrow-up-right-from-square')
            ->linkToRoute('app_edition_show', static fn (LarpEdition $e): array => ['slug' => $e->getLarp()?->getSlug(), 'edition' => $e->getSlug()])
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (LarpEdition $e): bool => null !== $e->getSlug() && true === $e->getLarp()?->isPublished());
        $characters = Action::new('characters', 'larp.characters', 'fa fa-masks-theater')
            ->linkToUrl(fn (LarpEdition $e): string => null === $e->getLarp() ? '#' : CharacterCrudController::larpListUrl($this->urls, $e->getLarp()));

        return $actions
            ->add(Crud::PAGE_INDEX, $characters)
            ->add(Crud::PAGE_INDEX, $view)
            ->add(Crud::PAGE_EDIT, $view)
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            // Keep the LARP when adding an edition from its list.
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action): Action => null === $larp
                ? $action->setLabel('edition.add')
                : $action->linkToUrl(self::larpListUrl($this->urls, $larp, Action::NEW))->setLabel('edition.add'));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(self::LARP_FILTER)->add('recruitment');
    }

    public function createEntity(string $entityFqcn): LarpEdition
    {
        $edition = new LarpEdition();
        $this->currentLarp()?->addEdition($edition);

        return $edition;
    }

    public function configureFields(string $pageName): iterable
    {
        yield ImageField::new('coverImage', 'edition.cover_image')->setBasePath(self::BASE_PATH)->onlyOnIndex();
        yield FormField::addTab('edition.tab.basics', 'fa fa-calendar-days')->onlyOnForms();
        yield AssociationField::new('larp', 'edition.larp')->setColumns(4);
        yield TextField::new('name', 'edition.name')->setHelp('edition.name_help')->setColumns(4);
        yield TextField::new('slug', 'edition.slug')->setRequired(false)->setHelp('edition.slug_help')->setColumns(4)->hideOnIndex();
        yield FormField::addRow();
        // A date range picker (assets/admin.js): picking the start opens the end on
        // the same month, with earlier days disabled.
        yield DateField::new('startDate', 'edition.start_date')
            ->renderAsText()
            ->setFormTypeOption('format', 'yyyy-MM-dd')
            ->setFormTypeOption('attr', ['data-date-range' => 'start', 'placeholder' => 'rrrr-mm-dd', 'autocomplete' => 'off'])
            ->setColumns(4);
        yield DateField::new('endDate', 'edition.end_date')
            ->renderAsText()
            ->setFormTypeOption('format', 'yyyy-MM-dd')
            ->setFormTypeOption('attr', ['data-date-range' => 'end', 'placeholder' => 'rrrr-mm-dd', 'autocomplete' => 'off'])
            ->setHelp('edition.end_date_help')
            ->setColumns(4)
            ->hideOnIndex();
        yield FormField::addRow();
        yield TextField::new('location', 'edition.location')->setRequired(false)->setColumns(6);
        yield UrlField::new('locationUrl', 'edition.location_url')->setRequired(false)->setColumns(6)->hideOnIndex();
        yield FormField::addRow();
        yield ChoiceField::new('recruitment', 'edition.recruitment')->setColumns(4)->renderAsBadges([
            'not_started' => 'secondary',
            'open' => 'success',
            'closed' => 'light',
        ]);
        yield UrlField::new('registrationUrl', 'edition.registration_url')
            ->setRequired(false)
            ->setHelp('edition.registration_url_help')
            ->setColumns(8)
            ->hideOnIndex();
        yield FormField::addRow();
        yield UrlField::new('facebookEventUrl', 'edition.facebook_event_url')
            ->setRequired(false)
            ->setHelp('edition.facebook_event_url_help')
            ->setColumns(12)
            ->hideOnIndex();
        yield IntegerField::new('characterCount', 'larp.characters')->onlyOnIndex();

        yield FormField::addTab('edition.tab.description', 'fa fa-align-left')->onlyOnForms();
        yield TextareaField::new('teaser', 'larp.teaser')->setRequired(false)->setHelp('edition.teaser_help')->onlyOnForms();
        yield TextEditorField::new('description', 'larp.description')->setRequired(false)->setHelp('edition.description_help')->onlyOnForms();
        yield TextField::new('playerCount', 'larp.player_count')->setRequired(false)->setHelp('edition.fallback_help')->setColumns(6)->onlyOnForms();
        yield TextField::new('price', 'larp.price')->setRequired(false)->setHelp('edition.fallback_help')->setColumns(6)->onlyOnForms();

        yield FormField::addTab('edition.tab.media', 'fa fa-image')->onlyOnForms();
        yield ImageField::new('coverImage', 'edition.cover_image')
            ->setBasePath(self::BASE_PATH)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFileConstraints([new Image(maxSize: '8M')])
            ->setRequired(false)
            ->setHelp('edition.cover_image_help')
            ->onlyOnForms();
        yield TextField::new('coverImageAlt', 'larp.cover_image_alt')->setRequired(false)->setHelp('larp.cover_image_alt_help')->onlyOnForms();
    }

    /** The LARP whose editions are listed: from the edited edition, or from the "LARP" filter in the URL. */
    private function currentLarp(): ?Larp
    {
        $edition = $this->getContext()?->getEntity()->getInstance();
        if ($edition instanceof LarpEdition && null !== $edition->getLarp()) {
            return $edition->getLarp();
        }
        $larpId = $this->requests->getCurrentRequest()?->query->all('filters')[self::LARP_FILTER]['value'] ?? null;

        return is_numeric($larpId) ? $this->larps->find((int) $larpId) : null;
    }
}
