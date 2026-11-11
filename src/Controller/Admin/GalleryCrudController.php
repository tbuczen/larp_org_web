<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Gallery;
use App\Entity\LarpEdition;
use App\Gallery\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Photo galleries. Usually a gallery is a link to an album on another site
 * (Google Photos, Facebook, ...) with a cover image. Photos can also be uploaded
 * here, on a separate screen (photos()): many at once (the browser sends them one
 * by one, shrunk to web size first), drag to reorder, captions and credits, delete.
 *
 * @extends AbstractCrudController<Gallery>
 */
class GalleryCrudController extends AbstractCrudController
{
    private const CSRF = 'gallery_photos';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $urls,
        private readonly PhotoStorage $storage,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        #[Autowire('%app.gallery_dir%')]
        private readonly string $galleryDir,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Gallery::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('gallery.label')
            ->setEntityLabelInPlural('gallery.label_plural')
            ->setPageTitle(Crud::PAGE_NEW, 'gallery.new_title')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['title', 'photographer', 'description']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $photos = Action::new('photos', 'gallery.photos', 'fa fa-images')
            ->linkToUrl(fn (Gallery $gallery): string => $this->photosUrl($gallery));
        $view = Action::new('viewOnSite', 'larp.view_on_site', 'fa fa-arrow-up-right-from-square')
            ->linkToRoute('app_gallery_show', static fn (Gallery $gallery): array => ['slug' => $gallery->getSlug()])
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Gallery $gallery): bool => $gallery->isVisible());

        return $actions
            ->add(Crud::PAGE_INDEX, $photos)
            ->add(Crud::PAGE_INDEX, $view)
            ->add(Crud::PAGE_EDIT, $photos)
            ->add(Crud::PAGE_EDIT, $view)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $a): Action => $a->setLabel('gallery.add'))
            ->reorder(Crud::PAGE_INDEX, ['photos', Action::EDIT, 'viewOnSite', Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('larp')->add('published');
    }

    public function configureFields(string $pageName): iterable
    {
        yield ImageField::new('coverPath', 'gallery.cover')
            ->setBasePath(PhotoStorage::BASE_PATH)
            ->onlyOnIndex();
        yield TextField::new('title', 'gallery.title')->setColumns(8);
        yield SlugField::new('slug', 'larp.slug')->setTargetFieldName('title')->setColumns(4)->hideOnIndex();
        yield UrlField::new('externalUrl', 'gallery.external_url')
            ->setRequired(false)
            ->setHelp('gallery.external_url_help')
            ->setColumns(8)
            ->hideOnIndex();
        yield TextField::new('externalHost', 'gallery.source')->onlyOnIndex();
        yield ImageField::new('coverImage', 'gallery.cover_image')
            ->setBasePath(PhotoStorage::BASE_PATH.'/covers')
            ->setUploadDir($this->coverUploadDir())
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFileConstraints([new Image(maxSize: '8M')])
            ->setRequired(false)
            ->setHelp('gallery.cover_image_help')
            ->setColumns(4)
            ->onlyOnForms();
        yield AssociationField::new('larp', 'gallery.larp')
            ->setRequired(false)
            ->setHelp('gallery.larp_help')
            ->setColumns(6);
        yield AssociationField::new('edition', 'gallery.edition')
            ->setRequired(false)
            ->setHelp('gallery.edition_help')
            ->setQueryBuilder(static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->join('entity.larp', 'larp')
                ->orderBy('larp.name', 'ASC')
                ->addOrderBy('entity.startDate', 'DESC'))
            ->setFormTypeOption('choice_label', static fn (LarpEdition $e): string => (string) $e)
            ->setColumns(6)
            ->hideOnIndex();
        yield TextField::new('photographer', 'gallery.photographer')
            ->setRequired(false)
            ->setHelp('gallery.photographer_help')
            ->setColumns(6);
        yield DateField::new('eventDate', 'gallery.event_date')
            ->renderAsText()
            ->setFormTypeOption('format', 'yyyy-MM-dd')
            ->setFormTypeOption('attr', ['data-date-picker' => '', 'placeholder' => 'rrrr-mm-dd', 'autocomplete' => 'off'])
            ->setRequired(false)
            ->setHelp('gallery.event_date_help')
            ->setColumns(6)
            ->hideOnIndex();
        yield TextEditorField::new('description', 'gallery.description')->setRequired(false)->hideOnIndex();
        yield IntegerField::new('photoCount', 'gallery.photo_count')->onlyOnIndex();
        yield BooleanField::new('published', 'gallery.published')->setHelp('gallery.published_help');
    }

    /** A new gallery without an album link goes straight to adding photos. */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $gallery = $context->getEntity()->getInstance();
        if (Action::NEW === $action && $gallery instanceof Gallery && !$gallery->isExternal()) {
            return $this->redirect($this->photosUrl($gallery));
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    public static function photosUrlFor(AdminUrlGenerator $urls, Gallery $gallery): string
    {
        return $urls->unsetAll()->setController(self::class)->setAction('photos')->setEntityId($gallery->getId())->generateUrl();
    }

    private function photosUrl(Gallery $gallery): string
    {
        return self::photosUrlFor($this->urls, $gallery);
    }

    // ------------------------------------------------------------------ photos

    #[AdminRoute('/{id}/photos', name: 'photos')]
    public function photos(Gallery $gallery): Response
    {
        return $this->render('admin/gallery/photos.html.twig', [
            'gallery' => $gallery,
            'photos' => $gallery->getPhotos()->toArray(),
            'csrfId' => self::CSRF,
            'maxBytes' => UploadedFile::getMaxFilesize(),
            'maxSize' => PhotoStorage::MAX_SIZE,
            'editUrl' => $this->urls->unsetAll()->setController(self::class)->setAction(Action::EDIT)->setEntityId($gallery->getId())->generateUrl(),
            'listUrl' => $this->urls->unsetAll()->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        ]);
    }

    /** Receives one photo (the page sends them one by one) and answers in JSON. */
    #[AdminRoute('/{id}/photos/upload', name: 'upload_photo', options: ['methods' => ['POST']])]
    public function uploadPhoto(Gallery $gallery, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::CSRF, (string) $request->headers->get('X-CSRF-Token', $request->request->getString('_token')))) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $message = $file instanceof UploadedFile && \in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                ? $this->translator->trans('gallery.upload_too_big', ['%max%' => $this->megabytes(UploadedFile::getMaxFilesize())], 'admin')
                : $this->translator->trans('gallery.upload_failed', [], 'admin');

            return new JsonResponse(['error' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $violations = $this->validator->validate($file, new Image(maxSize: '20M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif']));
        if (\count($violations) > 0) {
            return new JsonResponse(['error' => (string) $violations->get(0)->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $photo = $this->storage->store($file, $gallery);
        $gallery->addPhoto($photo);
        $this->em->flush();

        return new JsonResponse(['id' => $photo->getId(), 'thumbnail' => '/'.PhotoStorage::BASE_PATH.'/'.$photo->getThumbnailOrFile()], Response::HTTP_CREATED);
    }

    /**
     * Saves the photo list: order (as the photos come in the form), captions,
     * credits, and deletes the ticked photos.
     */
    #[AdminRoute('/{id}/photos/save', name: 'save_photos', options: ['methods' => ['POST']])]
    public function savePhotos(Gallery $gallery, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::CSRF, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $byId = [];
        foreach ($gallery->getPhotos() as $photo) {
            $byId[(int) $photo->getId()] = $photo;
        }

        /** @var array<array-key, mixed> $rows */
        $rows = $request->request->all('photos');
        $position = 0;
        $removed = 0;
        foreach ($rows as $id => $row) {
            $photo = $byId[(int) $id] ?? null;
            if (null === $photo || !\is_array($row)) {
                continue;
            }
            unset($byId[(int) $id]);
            if (isset($row['delete'])) {
                $gallery->removePhoto($photo);
                ++$removed;
                continue;
            }
            $photo->setCaption(\is_string($row['caption'] ?? null) ? mb_substr($row['caption'], 0, 500) : null)
                ->setPhotographer(\is_string($row['photographer'] ?? null) ? mb_substr($row['photographer'], 0, 160) : null)
                ->setPosition($position++);
        }
        // Photos missing from the form (e.g. uploaded meanwhile in another tab) go last.
        foreach ($byId as $photo) {
            $photo->setPosition($position++);
        }
        $this->em->flush();

        $this->addFlash('success', new TranslatableMessage(
            $removed > 0 ? 'gallery.saved_removed' : 'gallery.saved',
            ['%count%' => $removed],
            'admin',
        ));

        return $this->redirect($this->photosUrl($gallery));
    }

    /** Cover images: covers/ in the gallery folder, as a path relative to the project (what ImageField wants). */
    private function coverUploadDir(): string
    {
        return ltrim(substr($this->galleryDir, \strlen($this->projectDir)), '/').'/covers';
    }

    private function megabytes(int|float $bytes): string
    {
        return (string) round($bytes / 1024 / 1024, 1);
    }
}
