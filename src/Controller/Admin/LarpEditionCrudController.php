<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\LarpEdition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

/**
 * Editions are edited inside the LARP form (CollectionField::useEntryCrudForm),
 * so this controller only describes the edition form and has no menu item.
 *
 * @extends AbstractCrudController<LarpEdition>
 */
class LarpEditionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LarpEdition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('edition.label')
            ->setEntityLabelInPlural('edition.label_plural');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addRow();
        yield TextField::new('name', 'edition.name')
            ->setHelp('edition.name_help')
            ->setColumns(4);
        yield DateField::new('startDate', 'edition.start_date')->setColumns(4);
        yield DateField::new('endDate', 'edition.end_date')->setColumns(4);
        yield FormField::addRow();
        yield TextField::new('location', 'edition.location')->setRequired(false)->setColumns(6);
        yield UrlField::new('locationUrl', 'edition.location_url')->setRequired(false)->setColumns(6);
        yield FormField::addRow();
        yield ChoiceField::new('recruitment', 'edition.recruitment')->setColumns(4);
        yield UrlField::new('registrationUrl', 'edition.registration_url')
            ->setRequired(false)
            ->setHelp('edition.registration_url_help')
            ->setColumns(8);
        yield FormField::addRow();
        yield UrlField::new('facebookEventUrl', 'edition.facebook_event_url')
            ->setRequired(false)
            ->setHelp('edition.facebook_event_url_help')
            ->setColumns(12);
    }
}
