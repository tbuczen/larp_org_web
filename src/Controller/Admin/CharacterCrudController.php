<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Character;
use App\Entity\LarpEdition;
use App\Enum\TagType;
use App\Form\CharacterTagType;
use App\Repository\LarpRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
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
use Symfony\Component\Validator\Constraints\Image;

/**
 * @extends AbstractCrudController<Character>
 */
class CharacterCrudController extends AbstractCrudController
{
    public const UPLOAD_DIR = 'public/uploads/characters';
    public const BASE_PATH = 'uploads/characters';

    /** Query key of the "LARP" filter: EasyAdmin writes nested properties with ":" (edition.larp → edition:larp). */
    public const LARP_FILTER = 'edition'.FiltersFormType::EMBEDDED_PROPERTY_SEPARATOR.'larp';

    public static function getEntityFqcn(): string
    {
        return Character::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('character.label')
            ->setEntityLabelInPlural('character.label_plural')
            ->setDefaultSort(['edition' => 'DESC', 'position' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'teaser', 'edition.name', 'edition.larp.name'])
            ->setPaginatorPageSize(50)
            ->addFormTheme('admin/form/character_tags_theme.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_EDIT, Action::INDEX);
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

        // Pre-select the edition when coming from a filtered list (the LARP's "Characters" action).
        $larpId = $this->getContext()?->getRequest()->query->all('filters')[self::LARP_FILTER]['value'] ?? null;
        if (null !== $larpId) {
            $character->setEdition($this->container->get(LarpRepository::class)->find($larpId)?->getCurrentEdition());
        }

        return $character;
    }

    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [LarpRepository::class]);
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
        yield AssociationField::new('edition', 'character.edition')
            ->setQueryBuilder(static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->addSelect('l')
                ->join('entity.larp', 'l')
                ->orderBy('l.name', 'ASC')
                ->addOrderBy('entity.startDate', 'DESC'))
            ->setFormTypeOption('group_by', static fn (LarpEdition $e): string => (string) $e->getLarp()?->getName())
            ->setFormTypeOption('choice_label', 'name')
            ->setColumns(6);
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
                'entry_options' => ['tag_type' => $type, 'label' => false],
                'error_bubbling' => false,
            ])
            ->setHelp($help)
            ->hideOnIndex();
    }
}
