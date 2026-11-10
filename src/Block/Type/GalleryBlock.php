<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use App\Entity\PageBlock;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Photos from LARPs, shown in one of several moving layouts:
 *  - drift:    two endless strips gliding in opposite directions (pause on hover)
 *  - scroll:   strips that slide sideways as the visitor scrolls the page
 *  - parallax: columns that move at different speeds while scrolling
 *  - grid:     a calm grid; photos unveil as they come into view
 * Every layout opens the photos in a full-screen viewer on click.
 */
final class GalleryBlock extends AbstractBlockType
{
    public const LAYOUTS = ['drift', 'scroll', 'parallax', 'grid'];

    public static function getKey(): string
    {
        return 'gallery';
    }

    public function getIcon(): string
    {
        return 'fa fa-images';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'intro' => '', 'images' => [], 'captions' => '', 'layout' => 'drift'];
    }

    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false])
            ->add('intro', TextareaType::class, ['label' => 'block.gallery.intro', 'required' => false]);
        $this->addImages($builder, 'images', 'block.gallery.images', 'block.gallery.images_help');
        $builder
            ->add('captions', TextareaType::class, [
                'label' => 'block.gallery.captions',
                'required' => false,
                'help' => 'block.gallery.captions_help',
                'attr' => ['rows' => 5],
            ])
            ->add('layout', ChoiceType::class, [
                'label' => 'block.gallery.layout',
                'choices' => array_combine(array_map(static fn (string $l): string => 'block.gallery.layout_'.$l, self::LAYOUTS), self::LAYOUTS),
                'help' => 'block.gallery.layout_help',
            ]);
    }

    public function getContext(PageBlock $block, array $settings): array
    {
        $files = array_values(array_filter(
            (array) ($settings['images'] ?? []),
            static fn (mixed $f): bool => \is_string($f) && '' !== $f,
        ));
        $captions = preg_split('/\R/', (string) ($settings['captions'] ?? '')) ?: [];

        $photos = [];
        foreach ($files as $i => $file) {
            $photos[] = ['index' => $i, 'file' => $file, 'caption' => trim($captions[$i] ?? '')];
        }
        $layout = \in_array($settings['layout'] ?? null, self::LAYOUTS, true) ? $settings['layout'] : 'drift';

        return ['photos' => $photos, 'layout' => $layout, 'rows' => self::split($photos, 'parallax' === $layout ? 3 : 2)];
    }

    /**
     * Deals the photos round-robin into $count rows (or columns), so each gets a
     * mix. With few photos every row gets them all, so the strips are never empty.
     *
     * @template T
     *
     * @param list<T> $photos
     *
     * @return list<list<T>>
     */
    public static function split(array $photos, int $count): array
    {
        if (\count($photos) < $count * 2) {
            return array_fill(0, $count, $photos);
        }
        $rows = array_fill(0, $count, []);
        foreach ($photos as $i => $photo) {
            $rows[$i % $count][] = $photo;
        }

        return $rows;
    }
}
