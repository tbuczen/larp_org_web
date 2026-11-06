<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TagCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<TagCategory>
 */
class TagCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TagCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('tag_category.label')
            ->setEntityLabelInPlural('tag_category.label_plural')
            ->setDefaultSort(['type' => 'ASC', 'position' => 'ASC', 'name' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('type');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'tag_category.name');
        yield ChoiceField::new('type', 'tag.type')->renderAsBadges();
        yield IntegerField::new('position', 'common.position')->setHelp('common.position_help');
    }
}
