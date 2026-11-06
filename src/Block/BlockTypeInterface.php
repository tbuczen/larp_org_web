<?php

declare(strict_types=1);

namespace App\Block;

use App\Entity\PageBlock;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A kind of page block (hero, text, LARP cards, ...).
 *
 * To add a new block type: create a class in src/Block/Type/ implementing this
 * interface (extend AbstractBlockType) and a template blocks/<key>.html.twig in
 * themes/base/templates/. Nothing else needs registering. Themes can override the
 * template.
 */
#[AutoconfigureTag(self::TAG)]
interface BlockTypeInterface
{
    public const TAG = 'app.block_type';

    /** Unique key stored in PageBlock::$type, e.g. "hero". */
    public static function getKey(): string;

    /** Translation key (domain "admin") for the admin, e.g. "block.hero.label". */
    public function getLabel(): string;

    /** FontAwesome icon class for the admin, e.g. "fa fa-image". */
    public function getIcon(): string;

    /** @return array<string, mixed> settings of a newly added block */
    public function getDefaultSettings(): array;

    /**
     * Adds this block's settings fields; the field names are the settings keys.
     *
     * @param FormBuilderInterface<array<string, mixed>|null> $builder
     */
    public function buildSettingsForm(FormBuilderInterface $builder): void;

    /** Template name, normally "blocks/<key>.html.twig". */
    public function getTemplate(): string;

    /**
     * Variables for the template (in addition to "block" and "settings"), e.g. LARPs to show.
     *
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function getContext(PageBlock $block, array $settings): array;
}
