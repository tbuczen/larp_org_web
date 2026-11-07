<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Character;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Enum\TagType;
use App\Form\CharacterTagType;
use App\Repository\CharacterRepository;
use App\Repository\LarpRepository;
use App\Util\DateRangeFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FiltersFormType;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Characters are managed per LARP: the list, "Add character" and the edition
 * field are always scoped to one LARP, taken from the URL (the "LARP" filter).
 * Without a LARP the list shows a LARP picker first.
 *
 * @extends AbstractCrudController<Character>
 */
class CharacterCrudController extends AbstractCrudController
{
    public const UPLOAD_DIR = 'public/uploads/characters';
    public const BASE_PATH = 'uploads/characters';

    /** Query key of the "LARP" filter: EasyAdmin writes nested properties with ":" (edition.larp → edition:larp). */
    public const LARP_FILTER = 'edition'.FiltersFormType::EMBEDDED_PROPERTY_SEPARATOR.'larp';

    public function __construct(
        private readonly LarpRepository $larps,
        private readonly CharacterRepository $characters,
        private readonly RequestStack $requests,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Character::class;
    }

    /** URL of one LARP's character list (also used by the LARP admin's "Characters" action). */
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
            ->setEntityLabelInSingular('character.label')
            ->setEntityLabelInPlural('character.label_plural')
            ->setPageTitle(Crud::PAGE_INDEX, null !== $larp ? new TranslatableMessage('character.list_for', ['%larp%' => $larp->getName()], 'admin') : 'character.label_plural')
            ->setPageTitle(Crud::PAGE_NEW, null !== $larp ? new TranslatableMessage('character.new_for', ['%larp%' => $larp->getName()], 'admin') : 'character.label')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Character $c): string => $c->getName().' · '.$c->getLarp()?->getName())
            ->setDefaultSort(['edition' => 'DESC', 'position' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'teaser', 'edition.name', 'edition.larp.name'])
            ->setPaginatorPageSize(50)
            ->addFormTheme('admin/form/character_tags_theme.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $larp = $this->currentLarp();

        $actions
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            // Keep the LARP when adding a character from its list.
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action): Action => null === $larp
                ? $action
                : $action->linkToUrl(self::larpListUrl($this->urls, $larp, Action::NEW))->setLabel('character.add'));

        if (null !== $larp) {
            $actions
                ->add(Crud::PAGE_INDEX, Action::new('importCharacters', 'import.action', 'fa fa-file-csv')
                    ->linkToUrl(LarpCrudController::larpToolUrl($this->urls, 'importCharacters', $larp))
                    ->createAsGlobalAction()
                    ->setCssClass('btn btn-secondary'))
                ->add(Crud::PAGE_INDEX, Action::new('larpTags', 'larp_tags.action', 'fa fa-tags')
                    ->linkToUrl(LarpCrudController::larpToolUrl($this->urls, 'tags', $larp))
                    ->createAsGlobalAction()
                    ->setCssClass('btn btn-secondary'));
        }

        return $actions;
    }

    /** Without a LARP, the list asks which LARP's characters to manage. */
    public function index(AdminContext $context): KeyValueStore|Response
    {
        if (null !== $this->currentLarp()) {
            return parent::index($context);
        }

        $larps = array_values(array_filter(
            $this->larps->findBy([], ['status' => 'ASC', 'position' => 'ASC', 'name' => 'ASC']),
            static fn (Larp $l): bool => !$l->isInitiative(),
        ));
        $rows = [];
        foreach ($larps as $larp) {
            $rows[] = [
                'larp' => $larp,
                'count' => $this->characters->count(['edition' => $larp->getEditions()->toArray()]),
                'listUrl' => self::larpListUrl($this->urls, $larp),
                'newUrl' => self::larpListUrl($this->urls, $larp, Action::NEW),
                'editLarpUrl' => $this->urls->unsetAll()->setController(LarpCrudController::class)->setAction(Action::EDIT)->setEntityId($larp->getId())->generateUrl(),
                'importUrl' => LarpCrudController::larpToolUrl($this->urls, 'importCharacters', $larp),
                'tagsUrl' => LarpCrudController::larpToolUrl($this->urls, 'tags', $larp),
            ];
        }

        return $this->render('admin/character/choose_larp.html.twig', ['rows' => $rows]);
    }

    /** A character is always added to a LARP: without one, go to the picker. */
    public function new(AdminContext $context): KeyValueStore|Response
    {
        $larp = $this->currentLarp();
        if (null === $larp) {
            return $this->redirect($this->urls->unsetAll()->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }
        if ($larp->getEditions()->isEmpty()) {
            $this->addFlash('warning', new TranslatableMessage('character.needs_edition', ['%larp%' => $larp->getName()], 'admin'));

            return $this->redirect($this->urls->unsetAll()->setController(LarpCrudController::class)->setAction(Action::EDIT)->setEntityId($larp->getId())->generateUrl());
        }

        return parent::new($context);
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $character = $context->getEntity()->getInstance();
        $larp = $character instanceof Character ? $character->getLarp() : null;
        // EasyAdmin sends the pressed button as ea[newForm][btn] on both new and edit forms.
        $submit = $context->getRequest()->request->all()['ea']['newForm']['btn'] ?? null;

        if (null === $larp || Action::SAVE_AND_CONTINUE === $submit) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        // Back to this LARP's list (or a new character in it), not to the LARP picker.
        return $this->redirect(self::larpListUrl($this->urls, $larp, Action::SAVE_AND_ADD_ANOTHER === $submit ? Action::NEW : Action::INDEX));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('edition.larp', 'character.larp'))
            ->add(EntityFilter::new('edition', 'character.edition'))
            ->add('casting')
            ->add('availability');
    }

    public function createEntity(string $entityFqcn): Character
    {
        $character = new Character();

        // Start in the LARP's current edition (the upcoming one, else the latest).
        $character->setEdition($this->currentLarp()?->getCurrentEdition());

        return $character;
    }

    /**
     * The LARP whose characters are being managed: from the edited character, or from
     * the "LARP" filter in the URL.
     */
    private function currentLarp(): ?Larp
    {
        $character = $this->getContext()?->getEntity()->getInstance();
        if ($character instanceof Character && null !== $character->getLarp()) {
            return $character->getLarp();
        }

        $request = $this->requests->getCurrentRequest();
        $larpId = $request?->query->all('filters')[self::LARP_FILTER]['value'] ?? null;
        if (!is_numeric($larpId)) {
            return null;
        }
        $larp = $this->larps->find((int) $larpId);

        return null !== $larp && !$larp->isInitiative() ? $larp : null;
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('character.tab.basics', 'fa fa-user');
        yield ImageField::new('portrait', 'character.portrait')
            ->setBasePath(self::BASE_PATH)
            ->onlyOnIndex();
        yield TextField::new('name', 'character.name')->setColumns(6);
        yield SlugField::new('slug', 'character.slug')
            ->setTargetFieldName('name')
            ->setColumns(6)
            ->hideOnIndex();
        // Only this LARP's editions: a character can't be moved to another LARP by accident.
        $larp = $this->currentLarp();
        yield AssociationField::new('edition', 'character.edition')
            ->setQueryBuilder(static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->andWhere(null === $larp ? 'entity.id IS NOT NULL' : 'entity.larp = :larp')
                ->setParameters(new ArrayCollection(null === $larp ? [] : [new Parameter('larp', $larp)]))
                ->orderBy('entity.startDate', 'DESC'))
            ->setFormTypeOption('choice_label', static fn (LarpEdition $e): string => $e->getName().(($dates = DateRangeFormatter::format($e->getStartDate(), $e->getEndDate())) ? ' · '.$dates : ''))
            ->setHelp('character.edition_help')
            ->setColumns(6)
            ->hideOnIndex();
        yield TextField::new('edition.name', 'character.edition')->onlyOnIndex();
        yield ChoiceField::new('casting', 'character.casting')->setColumns(3);
        yield ChoiceField::new('availability', 'character.availability')->setColumns(3)->renderAsBadges([
            'available' => 'success',
            'taken' => 'warning',
            'hidden' => 'secondary',
        ]);
        yield IntegerField::new('position', 'common.position')
            ->setHelp('common.position_help')
            ->setColumns(3)
            ->hideOnIndex();

        yield FormField::addTab('character.tab.description', 'fa fa-align-left');
        yield TextareaField::new('teaser', 'character.teaser')
            ->setRequired(false)
            ->setHelp('character.teaser_help')
            ->hideOnIndex();
        yield TextEditorField::new('description', 'character.description')
            ->setRequired(false)
            ->hideOnIndex();
        yield ImageField::new('portrait', 'character.portrait')
            ->setBasePath(self::BASE_PATH)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFileConstraints([new Image(maxSize: '5M')])
            ->setRequired(false)
            ->onlyOnForms();

        yield FormField::addTab('character.tab.tags', 'fa fa-tags');
        yield $this->tagCollection('triggerTags', 'character.trigger_tags', TagType::Trigger, 'character.trigger_tags_help');
        yield $this->tagCollection('gameplayTags', 'character.gameplay_tags', TagType::Gameplay, 'character.gameplay_tags_help');
    }

    private function tagCollection(string $property, string $label, TagType $type, string $help): CollectionField
    {
        return CollectionField::new($property, $label)
            ->setEntryType(CharacterTagType::class)
            ->setFormTypeOptions([
                'by_reference' => false,
                'entry_options' => ['tag_type' => $type, 'larp' => $this->currentLarp(), 'label' => false],
                'error_bubbling' => false,
            ])
            ->setHelp($help)
            ->hideOnIndex();
    }
}
