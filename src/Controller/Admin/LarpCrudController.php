<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Enum\TagType;
use App\Form\SocialLinkType;
use App\Import\CharacterCsvImporter;
use App\Import\CsvImportException;
use App\Import\CsvReader;
use App\Import\ImportPlan;
use App\Repository\TagRepository;
use App\Tagging\TagResolver;
use App\Util\DateRangeFormatter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Larp>
 */
class LarpCrudController extends AbstractCrudController
{
    public const UPLOAD_DIR = 'public/uploads/larps';
    public const BASE_PATH = 'uploads/larps';

    public const CSRF_TAGS = 'larp-tags';
    public const CSRF_IMPORT = 'character-import';
    private const IMPORT_SESSION_KEY = 'character_import';

    public function __construct(
        private readonly AdminUrlGenerator $urls,
        private readonly TagRepository $tagRepository,
        private readonly TagResolver $tagResolver,
        private readonly CharacterCsvImporter $importer,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Larp::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('larp.label')
            ->setEntityLabelInPlural('larp.label_plural')
            ->setDefaultSort(['status' => 'ASC', 'position' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'format', 'setting', 'teaser'])
            ->addFormTheme('admin/form/social_links_theme.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $characters = Action::new('characters', 'larp.characters', 'fa fa-masks-theater')
            ->linkToUrl(fn (Larp $larp): string => CharacterCrudController::larpListUrl($this->urls, $larp))
            ->displayIf(static fn (Larp $larp): bool => !$larp->isInitiative());
        $addCharacter = Action::new('addCharacter', 'character.add', 'fa fa-plus')
            ->linkToUrl(fn (Larp $larp): string => CharacterCrudController::larpListUrl($this->urls, $larp, Action::NEW))
            ->displayIf(static fn (Larp $larp): bool => !$larp->isInitiative() && !$larp->getEditions()->isEmpty());

        $tags = Action::new('larpTags', 'larp_tags.action', 'fa fa-tags')
            ->linkToUrl(fn (Larp $larp): string => $this->larpUrl('tags', $larp))
            ->displayIf(static fn (Larp $larp): bool => !$larp->isInitiative());
        $import = Action::new('importCharacters', 'import.action', 'fa fa-file-csv')
            ->linkToUrl(fn (Larp $larp): string => $this->larpUrl('importCharacters', $larp))
            ->displayIf(static fn (Larp $larp): bool => !$larp->isInitiative() && !$larp->getEditions()->isEmpty());

        $galleries = Action::new('galleries', 'menu.galleries', 'fa fa-images')
            ->linkToUrl(fn (Larp $larp): string => $this->urls->unsetAll()
                ->setController(GalleryCrudController::class)
                ->setAction(Action::INDEX)
                ->set('filters', ['larp' => ['comparison' => '=', 'value' => $larp->getId()]])
                ->generateUrl());

        $view = Action::new('viewOnSite', 'larp.view_on_site', 'fa fa-arrow-up-right-from-square')
            ->linkToRoute('app_larp_show', static fn (Larp $larp): array => ['slug' => $larp->getSlug()])
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Larp $larp): bool => $larp->isPublished());

        return $actions
            ->add(Crud::PAGE_INDEX, $characters)
            ->add(Crud::PAGE_INDEX, $addCharacter)
            ->add(Crud::PAGE_INDEX, $import)
            ->add(Crud::PAGE_INDEX, $tags)
            ->add(Crud::PAGE_INDEX, $galleries)
            ->add(Crud::PAGE_INDEX, $view)
            ->add(Crud::PAGE_EDIT, $characters)
            ->add(Crud::PAGE_EDIT, $tags)
            ->add(Crud::PAGE_EDIT, $view);
    }

    /** URL of one of this controller's LARP screens (tags, importCharacters, ...). */
    public static function larpToolUrl(AdminUrlGenerator $urls, string $action, Larp $larp): string
    {
        return $urls->unsetAll()->setController(self::class)->setAction($action)->setEntityId($larp->getId())->generateUrl();
    }

    private function larpUrl(string $action, Larp $larp): string
    {
        return self::larpToolUrl($this->urls, $action, $larp);
    }

    // ---------------------------------------------------------------- LARP tags

    /** The LARP's own trigger and gameplay tags, added by pasting a list. */
    #[AdminRoute('/{id}/tags', name: 'tags')]
    public function tags(Larp $larp): Response
    {
        $this->denyInitiative($larp);

        $lists = [];
        foreach (TagType::cases() as $type) {
            $lists[$type->value] = [
                'type' => $type,
                'tags' => $this->tagRepository->findOwnedBy($larp, $type),
                'sharedCount' => $this->tagRepository->count(['type' => $type, 'larp' => null]),
            ];
        }

        return $this->render('admin/larp/tags.html.twig', [
            'larp' => $larp,
            'lists' => $lists,
            'usage' => $this->tagUsage($larp),
            'csrfId' => self::CSRF_TAGS,
            'charactersUrl' => CharacterCrudController::larpListUrl($this->urls, $larp),
            'sharedTagsUrl' => $this->urls->unsetAll()->setController(TagCrudController::class)->setAction(Action::INDEX)->generateUrl(),
        ]);
    }

    #[AdminRoute('/{id}/tags/add', name: 'add_tags', options: ['methods' => ['POST']])]
    public function addTags(Larp $larp, Request $request): RedirectResponse
    {
        $this->denyInitiative($larp);
        $this->checkCsrf(self::CSRF_TAGS, $request);
        $type = TagType::tryFrom($request->request->getString('type')) ?? throw new BadRequestHttpException();

        $result = $this->tagResolver->addList($larp, $type, $request->request->getString('tags'));
        $this->em->flush();

        if ([] !== $result['created']) {
            $this->addFlash('success', new TranslatableMessage('larp_tags.created', ['%tags%' => implode(', ', $result['created'])], 'admin'));
        }
        if ([] !== $result['existing']) {
            $this->addFlash('info', new TranslatableMessage('larp_tags.existing', ['%tags%' => implode(', ', $result['existing'])], 'admin'));
        }
        if ([] !== $result['skipped']) {
            $this->addFlash('warning', new TranslatableMessage('larp_tags.skipped', ['%tags%' => implode(', ', $result['skipped'])], 'admin'));
        }

        return $this->redirect($this->larpUrl('tags', $larp));
    }

    #[AdminRoute('/{id}/tags/{tag}/remove', name: 'remove_tag', options: ['methods' => ['POST'], 'requirements' => ['tag' => '\\d+']])]
    public function removeTag(Larp $larp, int $tag, Request $request): RedirectResponse
    {
        $this->checkCsrf(self::CSRF_TAGS, $request);
        $entity = $this->tagRepository->find($tag);
        // Only the LARP's own tags can be removed here (shared tags are edited under Tags).
        if (null !== $entity && $entity->getLarp() === $larp) {
            $this->em->remove($entity);
            $this->em->flush();
            $this->addFlash('success', new TranslatableMessage('larp_tags.removed', ['%tag%' => $entity->getName()], 'admin'));
        }

        return $this->redirect($this->larpUrl('tags', $larp));
    }

    // ------------------------------------------------------------ CSV import

    /** Step 1: upload a CSV and see a preview of what it will do. */
    #[AdminRoute('/{id}/characters/import', name: 'import_characters', options: ['methods' => ['GET', 'POST']])]
    public function importCharacters(Larp $larp, Request $request): Response
    {
        $this->denyInitiative($larp);
        if ($larp->getEditions()->isEmpty()) {
            $this->addFlash('warning', new TranslatableMessage('character.needs_edition', ['%larp%' => $larp->getName()], 'admin'));

            return $this->redirect($this->urls->unsetAll()->setController(self::class)->setAction(Action::EDIT)->setEntityId($larp->getId())->generateUrl());
        }

        $form = $this->importForm($larp);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('file')->getData();
            /** @var LarpEdition $edition */
            $edition = $form->get('edition')->getData();
            $csv = (string) file_get_contents($file->getPathname());
            $update = (bool) $form->get('updateExisting')->getData();
            $createTags = (bool) $form->get('createTags')->getData();

            try {
                $plan = $this->importer->prepare($csv, $edition, $update, $createTags);
            } catch (CsvImportException $e) {
                $form->get('file')->addError(new FormError($this->translator->trans($e->getMessage(), $e->parameters, 'admin')));

                return $this->renderImportForm($larp, $form);
            }

            // Kept until the preview is confirmed; re-checked then, as data may have changed.
            $token = bin2hex(random_bytes(16));
            $request->getSession()->set(self::IMPORT_SESSION_KEY, [
                'token' => $token, 'larp' => $larp->getId(), 'edition' => $edition->getId(),
                'csv' => base64_encode($csv), 'update' => $update, 'createTags' => $createTags,
                'filename' => $file->getClientOriginalName(),
            ]);

            return $this->renderPreview($larp, $plan, $token, $file->getClientOriginalName());
        }

        return $this->renderImportForm($larp, $form);
    }

    /** Step 2: save the previewed import (all rows or none). */
    #[AdminRoute('/{id}/characters/import/confirm', name: 'confirm_import', options: ['methods' => ['POST']])]
    public function confirmImport(Larp $larp, Request $request): Response
    {
        $this->checkCsrf(self::CSRF_IMPORT, $request);
        $stored = $request->getSession()->get(self::IMPORT_SESSION_KEY);
        if (!\is_array($stored) || ($stored['token'] ?? null) !== $request->request->getString('token') || $stored['larp'] !== $larp->getId()) {
            $this->addFlash('warning', new TranslatableMessage('import.expired', [], 'admin'));

            return $this->redirect($this->larpUrl('importCharacters', $larp));
        }

        $edition = $this->em->getRepository(LarpEdition::class)->find($stored['edition']);
        if (null === $edition || $edition->getLarp() !== $larp) {
            throw new BadRequestHttpException();
        }

        $plan = $this->importer->prepare((string) base64_decode((string) $stored['csv'], true), $edition, (bool) $stored['update'], (bool) $stored['createTags']);
        if ($plan->hasErrors()) {
            return $this->renderPreview($larp, $plan, (string) $stored['token'], (string) $stored['filename']);
        }

        $result = $this->importer->apply($plan);
        $request->getSession()->remove(self::IMPORT_SESSION_KEY);
        $this->addFlash('success', new TranslatableMessage('import.done', [
            '%created%' => $result['created'], '%updated%' => $result['updated'], '%tags%' => $result['tags'],
        ], 'admin'));

        return $this->redirect(CharacterCrudController::larpListUrl($this->urls, $larp));
    }

    /** A ready-to-fill CSV (UTF-8 with BOM, semicolons, so Excel opens it correctly). */
    #[AdminRoute('/{id}/characters/import/template', name: 'import_template')]
    public function importTemplate(Larp $larp): Response
    {
        $gameplay = array_slice($this->tagRepository->findAvailableFor(TagType::Gameplay, $larp), 0, 3);
        $triggers = array_slice($this->tagRepository->findAvailableFor(TagType::Trigger, $larp), 0, 2);
        $tagCell = static function (array $tags, array $percentages): string {
            $items = [];
            foreach (array_values($tags) as $i => $tag) {
                $items[] = $tag->getName().' '.($percentages[$i] ?? 50).'%';
            }

            return implode('; ', $items);
        };

        $rows = [
            ['nazwa', 'zajawka', 'opis', 'obsada', 'dostępność', 'kolejność', 'rozgrywka', 'triggery'],
            ['Przykładowa postać', 'Jedno zdanie o postaci.', 'Pełny opis. Pusta linia zaczyna nowy akapit.', 'dowolna', 'dostępna', '1', $tagCell($gameplay, [50, 30, 20]), $tagCell($triggers, [60, 30])],
            ['Druga postać', '', '', 'kobieca', 'zajęta', '2', $tagCell(array_reverse($gameplay), [70, 30]), ''],
        ];

        $out = fopen('php://temp', 'r+');
        if (false === $out) {
            throw new \RuntimeException('Cannot open a temporary stream.');
        }
        foreach ($rows as $row) {
            fputcsv($out, $row, ';', '"', '');
        }
        rewind($out);
        $csv = "\xEF\xBB\xBF".stream_get_contents($out);
        fclose($out);

        $response = new Response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8']);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'postacie-'.$larp->getSlug().'.csv',
        ));

        return $response;
    }

    /** @return FormInterface<mixed> */
    private function importForm(Larp $larp): FormInterface
    {
        return $this->createFormBuilder(null, ['translation_domain' => 'admin'])
            ->add('file', FileType::class, [
                'label' => 'import.file',
                'help' => 'import.file_help',
                // CSV files are usually detected as text/plain; anything that isn't a usable
                // table is rejected by CsvReader with a clear message anyway.
                'constraints' => [new NotNull(), new File(maxSize: CsvReader::MAX_BYTES, mimeTypes: [
                    'text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'text/x-comma-separated-values',
                    'text/tab-separated-values', 'application/vnd.ms-excel',
                ])],
                'attr' => ['accept' => '.csv,.txt,.tsv,text/csv'],
            ])
            ->add('edition', EntityType::class, [
                'label' => 'character.edition',
                'class' => LarpEdition::class,
                'choices' => array_reverse($larp->getSortedEditions()),
                'choice_label' => static fn (LarpEdition $e): string => $e->getName().(($d = DateRangeFormatter::format($e->getStartDate(), $e->getEndDate())) ? ' · '.$d : ''),
                'data' => $larp->getCurrentEdition(),
            ])
            ->add('updateExisting', CheckboxType::class, ['label' => 'import.update_existing', 'help' => 'import.update_existing_help', 'required' => false, 'data' => true])
            ->add('createTags', CheckboxType::class, ['label' => 'import.create_tags', 'help' => 'import.create_tags_help', 'required' => false, 'data' => true])
            ->getForm();
    }

    /** @param FormInterface<mixed> $form */
    private function renderImportForm(Larp $larp, FormInterface $form): Response
    {
        return $this->render('admin/character/import.html.twig', [
            'larp' => $larp,
            'form' => $form->createView(),
            'templateUrl' => $this->larpUrl('importTemplate', $larp),
            'tagsUrl' => $this->larpUrl('tags', $larp),
            'charactersUrl' => CharacterCrudController::larpListUrl($this->urls, $larp),
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    private function renderPreview(Larp $larp, ImportPlan $plan, string $token, string $filename): Response
    {
        return $this->render('admin/character/import_preview.html.twig', [
            'larp' => $larp,
            'plan' => $plan,
            'token' => $token,
            'filename' => $filename,
            'csrfId' => self::CSRF_IMPORT,
            'confirmUrl' => $this->larpUrl('confirmImport', $larp),
            'backUrl' => $this->larpUrl('importCharacters', $larp),
        ], new Response(null, $plan->hasErrors() ? 422 : 200));
    }

    /** @return array<int, int> tag id => number of this LARP's characters using it */
    private function tagUsage(Larp $larp): array
    {
        $usage = [];
        foreach ($larp->getEditions() as $edition) {
            foreach ($edition->getCharacters() as $character) {
                foreach ($character->getCharacterTags() as $characterTag) {
                    $id = (int) $characterTag->getTag()?->getId();
                    $usage[$id] = ($usage[$id] ?? 0) + 1;
                }
            }
        }

        return $usage;
    }

    private function denyInitiative(Larp $larp): void
    {
        if ($larp->isInitiative()) {
            throw $this->createNotFoundException('Initiatives have no characters.');
        }
    }

    private function checkCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status')->add('projectType')->add('featured');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('larp.tab.basics', 'fa fa-circle-info');
        yield ImageField::new('coverImage', 'larp.cover_image')
            ->setBasePath(self::BASE_PATH)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFileConstraints([new Image(maxSize: '8M')])
            ->setRequired(false)
            ->setHelp('larp.cover_image_help')
            ->onlyOnIndex();
        yield TextField::new('name', 'larp.name')->setColumns(8);
        yield SlugField::new('slug', 'larp.slug')
            ->setTargetFieldName('name')
            ->setColumns(4)
            ->hideOnIndex();
        yield ChoiceField::new('projectType', 'larp.project_type')->setColumns(4)->hideOnIndex();
        yield ChoiceField::new('status', 'larp.status')->setColumns(4)->renderAsBadges([
            'draft' => 'secondary',
            'current' => 'success',
            'past' => 'info',
        ]);
        yield BooleanField::new('featured', 'larp.featured')
            ->setColumns(4)
            ->setHelp('larp.featured_help');
        yield TextField::new('format', 'larp.format')
            ->setRequired(false)
            ->setHelp('larp.format_help')
            ->setColumns(6);
        yield TextField::new('setting', 'larp.setting')
            ->setRequired(false)
            ->setHelp('larp.setting_help')
            ->setColumns(6);
        yield TextField::new('playerCount', 'larp.player_count')->setRequired(false)->setColumns(6)->hideOnIndex();
        yield TextField::new('price', 'larp.price')->setRequired(false)->setColumns(6)->hideOnIndex();
        yield IntegerField::new('position', 'common.position')
            ->setHelp('common.position_help')
            ->setColumns(4)
            ->hideOnIndex();

        yield FormField::addTab('larp.tab.description', 'fa fa-align-left');
        yield TextareaField::new('teaser', 'larp.teaser')
            ->setRequired(false)
            ->setHelp('larp.teaser_help')
            ->hideOnIndex();
        yield TextEditorField::new('description', 'larp.description')
            ->setRequired(false)
            ->hideOnIndex();

        yield FormField::addTab('larp.tab.media', 'fa fa-image');
        yield ImageField::new('coverImage', 'larp.cover_image')
            ->setBasePath(self::BASE_PATH)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFileConstraints([new Image(maxSize: '8M')])
            ->setRequired(false)
            ->setHelp('larp.cover_image_help')
            ->onlyOnForms();
        yield TextField::new('coverImageAlt', 'larp.cover_image_alt')
            ->setRequired(false)
            ->setHelp('larp.cover_image_alt_help')
            ->onlyOnForms();

        yield FormField::addTab('larp.tab.links', 'fa fa-share-nodes');
        yield CollectionField::new('socialLinks', 'larp.social_links')
            ->setEntryType(SocialLinkType::class)
            ->setHelp('larp.social_links_help')
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex(false)
            ->setFormTypeOption('error_bubbling', false)
            ->onlyOnForms();

        yield FormField::addTab('larp.tab.editions', 'fa fa-calendar-days');
        yield CollectionField::new('editions', 'larp.editions')
            ->useEntryCrudForm(LarpEditionCrudController::class)
            ->setHelp('larp.editions_help')
            ->setFormTypeOption('by_reference', false)
            ->setEntryIsComplex()
            ->renderExpanded(false)
            ->onlyOnForms();
        yield IntegerField::new('editionCount', 'larp.editions')->onlyOnIndex();
    }
}
