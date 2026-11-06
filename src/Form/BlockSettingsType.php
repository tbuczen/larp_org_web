<?php

declare(strict_types=1);

namespace App\Form;

use App\Block\BlockTypeInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Settings form of one page block; the fields come from its block type.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class BlockSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var BlockTypeInterface $type */
        $type = $options['block_type'];
        $type->buildSettingsForm($builder);

        // Blocks saved before a new setting was added still get its default.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($type): void {
            $data = $event->getData();
            $event->setData(array_replace($type->getDefaultSettings(), \is_array($data) ? $data : []));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['translation_domain' => 'admin', 'label' => false])
            // EasyAdmin treats the JSON "settings" column like a collection and passes
            // these options; they don't apply to a settings form, so accept and ignore them.
            ->setDefined(['allow_add', 'allow_delete', 'delete_empty', 'entry_options', 'entry_type'])
            ->setRequired('block_type')
            ->setAllowedTypes('block_type', BlockTypeInterface::class);
    }
}
