<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Block\BlockTypeRegistry;
use App\Entity\PageBlock;
use App\Form\BlockSettingsType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Edits one block's settings. Blocks are added, ordered and listed on the page's
 * Layout screen (PageCrudController::layout()), so this controller has no list or
 * "new" page of its own.
 *
 * @extends AbstractCrudController<PageBlock>
 */
class PageBlockCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly BlockTypeRegistry $blockTypes,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PageBlock::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('block.label')
            ->setEntityLabelInPlural('block.label_plural')
            ->setPageTitle(Crud::PAGE_EDIT, fn (PageBlock $block): string => $this->blockTitle($block));
    }

    public function configureActions(Actions $actions): Actions
    {
        $back = Action::new('backToLayout', 'page.back_to_layout', 'fa fa-arrow-left')
            ->linkToUrl(fn (PageBlock $block): string => $this->layoutUrl($block));

        return $actions
            ->disable(Action::NEW, Action::DETAIL, Action::BATCH_DELETE)
            ->add(Crud::PAGE_EDIT, $back)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, static fn (Action $a) => $a->setLabel('page.save_block'));
    }

    public function index(AdminContext $context): Response
    {
        return $this->redirect($this->urls->unsetAll()->setController(PageCrudController::class)->setAction(Action::INDEX)->generateUrl());
    }

    public function configureFields(string $pageName): iterable
    {
        $block = $this->getContext()?->getEntity()->getInstance();
        $type = $block instanceof PageBlock ? $this->blockTypes->get($block->getType()) : null;

        if (null !== $type) {
            yield Field::new('settings', false)
                ->setFormType(BlockSettingsType::class)
                ->setFormTypeOption('block_type', $type)
                ->onlyOnForms();
        }
        yield TextField::new('adminLabel', 'block.admin_label')
            ->setRequired(false)
            ->setHelp('block.admin_label_help')
            ->onlyOnForms();
        yield BooleanField::new('enabled', 'block.enabled')->onlyOnForms();
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $block = $context->getEntity()->getInstance();

        return $block instanceof PageBlock ? $this->redirect($this->layoutUrl($block)) : parent::getRedirectResponseAfterSave($context, $action);
    }

    #[AdminRoute('/{id}/toggle', name: 'toggle', options: ['methods' => ['POST']])]
    public function toggle(PageBlock $block, Request $request): RedirectResponse
    {
        $this->checkCsrf($request);
        $block->setEnabled(!$block->isEnabled());
        $this->em->flush();

        return $this->redirect($this->layoutUrl($block));
    }

    #[AdminRoute('/{id}/duplicate', name: 'duplicate', options: ['methods' => ['POST']])]
    public function duplicate(PageBlock $block, Request $request): RedirectResponse
    {
        $this->checkCsrf($request);
        $copy = (new PageBlock($block->getType(), $block->getSettings()))
            ->setAdminLabel($block->getAdminLabel())
            ->setEnabled(false)
            ->setPosition($block->getPosition());
        $block->getPage()?->addBlock($copy);
        // Shift the blocks after the original down by one, so the copy sits right below it.
        foreach ($block->getPage()?->getBlocks() ?? [] as $other) {
            if ($other !== $copy && $other->getPosition() > $block->getPosition()) {
                $other->setPosition($other->getPosition() + 1);
            }
        }
        $copy->setPosition($block->getPosition() + 1);
        $this->em->flush();

        return $this->redirect($this->layoutUrl($block));
    }

    /** Moves a block one place up or down (the no-JavaScript / small-screen alternative to dragging). */
    #[AdminRoute('/{id}/move/{direction}', name: 'move', options: ['methods' => ['POST'], 'requirements' => ['direction' => 'up|down']])]
    public function move(PageBlock $block, string $direction, Request $request): RedirectResponse
    {
        $this->checkCsrf($request);
        $blocks = $block->getPage()?->getBlocks()->toArray() ?? [];
        usort($blocks, static fn (PageBlock $a, PageBlock $b): int => [$a->getPosition(), $a->getId()] <=> [$b->getPosition(), $b->getId()]);
        $index = array_search($block, $blocks, true);
        $swap = 'up' === $direction ? $index - 1 : $index + 1;
        if (false !== $index && isset($blocks[$swap])) {
            [$blocks[$index], $blocks[$swap]] = [$blocks[$swap], $blocks[$index]];
        }
        foreach ($blocks as $position => $b) {
            $b->setPosition($position);
        }
        $this->em->flush();

        return $this->redirect($this->layoutUrl($block));
    }

    #[AdminRoute('/{id}/remove', name: 'remove', options: ['methods' => ['POST']])]
    public function remove(PageBlock $block, Request $request): RedirectResponse
    {
        $this->checkCsrf($request);
        $url = $this->layoutUrl($block);
        $block->getPage()?->removeBlock($block);
        $this->em->remove($block);
        $this->em->flush();

        return $this->redirect($url);
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid(PageCrudController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function layoutUrl(PageBlock $block): string
    {
        return $this->urls->unsetAll()
            ->setController(PageCrudController::class)
            ->setAction('layout')
            ->setEntityId($block->getPage()?->getId())
            ->generateUrl();
    }

    private function blockTitle(PageBlock $block): string
    {
        $type = $this->blockTypes->get($block->getType());
        $label = null !== $type ? $this->container->get('translator')->trans($type->getLabel(), [], 'admin') : $block->getType();

        return $label.($block->getAdminLabel() ? ' — '.$block->getAdminLabel() : '');
    }

    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), ['translator' => '?'.TranslatorInterface::class]);
    }
}
