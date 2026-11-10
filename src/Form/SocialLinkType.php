<?php

declare(strict_types=1);

namespace App\Form;

use App\Social\SocialNetwork;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One social link row {network, url, label}; Larp::$socialLinks is a list of these.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SocialLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach (SocialNetwork::cases() as $network) {
            $choices[$network->label()] = $network->value;
        }

        $builder
            ->add('network', ChoiceType::class, [
                'label' => 'social.network',
                'choices' => $choices,
                'choice_translation_domain' => false,
                'row_attr' => ['class' => 'social-link-row__network'],
            ])
            ->add('url', UrlType::class, [
                'label' => 'social.url',
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'https://'],
                'row_attr' => ['class' => 'social-link-row__url'],
            ])
            ->add('label', TextType::class, [
                'label' => 'social.label',
                'required' => false,
                'attr' => ['placeholder' => 'social.label_placeholder'],
                'row_attr' => ['class' => 'social-link-row__label'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'admin', 'attr' => ['class' => 'social-link-row']]);
    }
}
