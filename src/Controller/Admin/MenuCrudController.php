<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Menus and the "Items" screen where their links are ordered by drag and drop.
 *
 * @extends AbstractCrudController<Menu>
 */
class MenuCrudController extends AbstractCrudController
{
    public const CSRF_ID = 'menu-items';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Menu::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('menu_admin.label')
            ->setEntityLabelInPlural('menu_admin.label_plural')
            ->setDefaultSort(['menuKey' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $items = Action::new('items', 'menu_admin.items', 'fa fa-list')
            ->linkToCrudAction('items')
            ->setCssClass('btn btn-primary');

        return $actions->add(Crud::PAGE_INDEX, $items)->add(Crud::PAGE_EDIT, $items);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'menu_admin.name');
        yield TextField::new('menuKey', 'menu_admin.key')->setHelp('menu_admin.key_help');
        yield IntegerField::new('itemCount', 'menu_admin.items')->onlyOnIndex();
    }

    #[AdminRoute('/{id}/items', name: 'items')]
    public function items(Menu $menu): Response
    {
        return $this->render('admin/menu/items.html.twig', [
            'menu' => $menu,
            'csrfId' => self::CSRF_ID,
            'reorderUrl' => $this->urls->unsetAll()->setController(self::class)->setAction('reorder')->setEntityId($menu->getId())->generateUrl(),
            'newItemUrl' => $this->urls->unsetAll()->setController(MenuItemCrudController::class)->setAction(Action::NEW)->set('menu', $menu->getId())->generateUrl(),
        ]);
    }

    /** Saves the order of one list (top level or one submenu): {"ids": [3, 1, 2]}. */
    #[AdminRoute('/{id}/items/order', name: 'reorder', options: ['methods' => ['POST']])]
    public function reorder(Menu $menu, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $ids = $request->toArray()['ids'] ?? null;
        if (!\is_array($ids)) {
            throw new BadRequestHttpException('Expected {"ids": [...]}.');
        }

        $byId = [];
        foreach ($menu->getItems() as $item) {
            $byId[$item->getId()] = $item;
        }
        foreach (array_values($ids) as $position => $id) {
            if (($byId[(int) $id] ?? null) instanceof MenuItem) {
                $byId[(int) $id]->setPosition($position);
            }
        }
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
