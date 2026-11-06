<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tag;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Tag>
 */
class TagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tag::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('tag.label')
            ->setEntityLabelInPlural('tag.label_plural')
            ->setDefaultSort(['type' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'description', 'category.name'])
            ->setPaginatorPageSize(50);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('type')->add('category');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'tag.name');
        yield ChoiceField::new('type', 'tag.type')->renderAsBadges();
        yield AssociationField::new('category', 'tag.category')
            ->setRequired(false)
            ->setHelp('tag.category_help');
        yield TextareaField::new('description', 'tag.description')
            ->setRequired(false)
            ->hideOnIndex();
    }
}
