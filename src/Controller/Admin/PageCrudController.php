<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Block\BlockTypeRegistry;
use App\Entity\Page;
use App\Entity\PageBlock;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Pages and their block layout. The "Layout" screen (layout()) is where blocks are
 * added, reordered by drag and drop, hidden and removed.
 *
 * @extends AbstractCrudController<Page>
 */
class PageCrudController extends AbstractCrudController
{
    public const CSRF_ID = 'page-layout';

    public function __construct(
        private readonly BlockTypeRegistry $blockTypes,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('page.label')
            ->setEntityLabelInPlural('page.label_plural')
            ->setDefaultSort(['isHome' => 'DESC', 'title' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $layout = Action::new('layout', 'page.layout', 'fa fa-table-cells-large')
            ->linkToCrudAction('layout')
            ->setCssClass('btn btn-primary');
        $view = Action::new('viewOnSite', 'larp.view_on_site', 'fa fa-arrow-up-right-from-square')
            ->linkToUrl(fn (Page $page): string => $page->isHome()
                ? $this->generateUrl('app_home')
                : $this->generateUrl('app_page', ['slug' => $page->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank']);

        return $actions
            ->add(Crud::PAGE_INDEX, $layout)
            ->add(Crud::PAGE_EDIT, $layout)
            ->add(Crud::PAGE_INDEX, $view);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'page.title');
        yield SlugField::new('slug', 'page.slug')->setTargetFieldName('title')->setHelp('page.slug_help');
        yield BooleanField::new('isHome', 'page.is_home')->setHelp('page.is_home_help');
        yield BooleanField::new('published', 'page.published');
        yield TextareaField::new('metaDescription', 'page.meta_description')
            ->setRequired(false)
            ->setHelp('page.meta_description_help')
            ->hideOnIndex();
        yield IntegerField::new('blockCount', 'page.blocks')->onlyOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->keepSingleHome($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->keepSingleHome($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    #[AdminRoute('/{id}/layout', name: 'layout')]
    public function layout(Page $page): Response
    {
        return $this->render('admin/page/layout.html.twig', [
            'page' => $page,
            'blocks' => $page->getBlocks(),
            'blockTypes' => $this->blockTypes->all(),
            'csrfId' => self::CSRF_ID,
            'reorderUrl' => $this->adminUrl('reorder', $page),
            'viewUrl' => $page->isHome() ? $this->generateUrl('app_home') : $this->generateUrl('app_page', ['slug' => $page->getSlug()]),
        ]);
    }

    #[AdminRoute('/{id}/blocks/add/{type}', name: 'add_block', options: ['methods' => ['POST']])]
    public function addBlock(Page $page, string $type, Request $request): RedirectResponse
    {
        $this->checkCsrf($request);
        $blockType = $this->blockTypes->get($type) ?? throw new BadRequestHttpException('Unknown block type.');

        $block = (new PageBlock($type, $blockType->getDefaultSettings()))->setPosition($this->nextPosition($page));
        $page->addBlock($block);
        $this->em->flush();

        return $this->redirect($this->urls->unsetAll()
            ->setController(PageBlockCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($block->getId())
            ->generateUrl());
    }

    /** Saves the order sent by the drag-and-drop list: {"ids": [3, 1, 2]}. */
    #[AdminRoute('/{id}/blocks/order', name: 'reorder', options: ['methods' => ['POST']])]
    public function reorder(Page $page, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $ids = $request->toArray()['ids'] ?? null;
        if (!\is_array($ids)) {
            throw new BadRequestHttpException('Expected {"ids": [...]}.');
        }

        $byId = [];
        foreach ($page->getBlocks() as $block) {
            $byId[$block->getId()] = $block;
        }
        foreach (array_values($ids) as $position => $id) {
            if (isset($byId[(int) $id])) {
                $byId[(int) $id]->setPosition($position);
            }
        }
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    private function checkCsrf(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-Token') ?? $request->request->getString('_token');
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function nextPosition(Page $page): int
    {
        $max = -1;
        foreach ($page->getBlocks() as $block) {
            $max = max($max, $block->getPosition());
        }

        return $max + 1;
    }

    /** Only one page can be the front page. */
    private function keepSingleHome(mixed $page): void
    {
        if (!$page instanceof Page || !$page->isHome()) {
            return;
        }
        foreach ($this->em->getRepository(Page::class)->findBy(['isHome' => true]) as $other) {
            if ($other !== $page) {
                $other->setIsHome(false);
            }
        }
    }

    private function adminUrl(string $action, Page $page): string
    {
        return $this->urls->unsetAll()->setController(self::class)->setAction($action)->setEntityId($page->getId())->generateUrl();
    }
}
