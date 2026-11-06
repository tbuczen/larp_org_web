<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Edits one menu link. Items are listed and ordered on the menu's Items screen.
 *
 * @extends AbstractCrudController<MenuItem>
 */
class MenuItemCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MenuRepository $menus,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MenuItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('menu_item.label')
            ->setEntityLabelInPlural('menu_item.label_plural');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::DETAIL, Action::BATCH_DELETE);
    }

    public function index(AdminContext $context): Response
    {
        return $this->redirect($this->urls->unsetAll()->setController(MenuCrudController::class)->setAction(Action::INDEX)->generateUrl());
    }

    public function createEntity(string $entityFqcn): MenuItem
    {
        $item = new MenuItem();
        $menuId = $this->getContext()?->getRequest()->query->getInt('menu');
        $menu = $menuId ? $this->menus->find($menuId) : null;
        if ($menu instanceof Menu) {
            $item->setMenu($menu)->setPosition($menu->getItemCount());
        }

        return $item;
    }

    public function configureFields(string $pageName): iterable
    {
        $item = $this->getContext()?->getEntity()->getInstance();
        $menu = $item instanceof MenuItem ? $item->getMenu() : null;

        yield TextField::new('label', 'menu_item.label_field');
        yield AssociationField::new('menu', 'menu_admin.label');
        yield AssociationField::new('parent', 'menu_item.parent')
            ->setRequired(false)
            ->setHelp('menu_item.parent_help')
            ->setQueryBuilder(static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->andWhere('entity.parent IS NULL')
                ->andWhere(null === $menu ? 'entity.id IS NOT NULL' : 'entity.menu = :menu')
                ->setParameters(new ArrayCollection(null === $menu ? [] : [new Parameter('menu', $menu)]))
                ->orderBy('entity.position', 'ASC'));
        yield ChoiceField::new('target', 'menu_item.target')
            ->setHelp('menu_item.target_help')
            ->setFormTypeOption('attr', ['data-menu-target' => '1']);
        yield AssociationField::new('page', 'menu_item.page')->setRequired(false);
        yield AssociationField::new('larp', 'menu_item.larp')->setRequired(false);
        yield ChoiceField::new('route', 'menu_item.route')
            ->setChoices(array_flip(MenuItem::ROUTES))
            ->setFormTypeOption('choice_translation_domain', 'enums')
            ->setRequired(false);
        yield UrlField::new('url', 'menu_item.url')->setRequired(false);
        yield BooleanField::new('newTab', 'menu_item.new_tab')->renderAsSwitch(false);
        yield BooleanField::new('enabled', 'block.enabled')->renderAsSwitch(false);
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $item = $context->getEntity()->getInstance();
        if ($item instanceof MenuItem && null !== $item->getMenu()) {
            return $this->redirect($this->urls->unsetAll()->setController(MenuCrudController::class)->setAction('items')->setEntityId($item->getMenu()->getId())->generateUrl());
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }
}
