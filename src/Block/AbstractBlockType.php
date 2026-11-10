<?php

declare(strict_types=1);

namespace App\Block;

use App\Entity\PageBlock;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractBlockType implements BlockTypeInterface
{
    public const UPLOAD_DIR = 'public/uploads/blocks';
    public const BASE_PATH = 'uploads/blocks';

    private string $projectDir = '';

    #[Required]
    public function setProjectDir(#[Autowire('%kernel.project_dir%')] string $projectDir): void
    {
        $this->projectDir = $projectDir;
    }

    public function getLabel(): string
    {
        return 'block.'.static::getKey().'.label';
    }

    public function getTemplate(): string
    {
        return 'blocks/'.static::getKey().'.html.twig';
    }

    public function getContext(PageBlock $block, array $settings): array
    {
        return [];
    }

    /**
     * Adds an image upload field stored in public/uploads/blocks/.
     *
     * @param FormBuilderInterface<array<string, mixed>|null> $builder
     */
    protected function addImage(FormBuilderInterface $builder, string $name, string $label, ?string $help = null): void
    {
        $builder->add($name, FileUploadType::class, [
            'label' => $label,
            'help' => $help,
            'required' => false,
            'upload_dir' => $this->projectDir.'/'.self::UPLOAD_DIR.'/',
            'upload_filename' => '[slug]-[contenthash].[extension]',
            'file_constraints' => [new Image(maxSize: '8M')],
        ]);
    }

    /**
     * Adds an upload field for several images (stored as a list of file names);
     * new uploads are added to the ones already there.
     *
     * @param FormBuilderInterface<array<string, mixed>|null> $builder
     */
    protected function addImages(FormBuilderInterface $builder, string $name, string $label, ?string $help = null): void
    {
        $builder->add($name, FileUploadType::class, [
            'label' => $label,
            'help' => $help,
            'required' => false,
            'multiple' => true,
            'upload_dir' => $this->projectDir.'/'.self::UPLOAD_DIR.'/',
            'upload_filename' => '[slug]-[contenthash].[extension]',
            'file_constraints' => [new Image(maxSize: '8M')],
        ]);
    }
}
