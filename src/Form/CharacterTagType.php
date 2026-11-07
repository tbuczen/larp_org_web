<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CharacterTag;
use App\Entity\Larp;
use App\Entity\Tag;
use App\Enum\TagType;
use App\Repository\TagRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One row in a character's trigger or gameplay list: tag + percentage.
 *
 * @extends AbstractType<CharacterTag>
 */
final class CharacterTagType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var TagType $tagType */
        $tagType = $options['tag_type'];
        /** @var Larp|null $larp */
        $larp = $options['larp'];

        $builder
            ->add('tag', EntityType::class, [
                'class' => Tag::class,
                'label' => 'character_tag.tag',
                'placeholder' => 'character_tag.choose',
                'choice_label' => 'name',
                // Shared tags by category; the LARP's own tags in their own group.
                'group_by' => static fn (Tag $tag): ?string => $tag->isShared()
                    ? $tag->getCategory()?->getName()
                    : '★ '.$tag->getLarp()?->getName().($tag->getCategory() ? ' — '.$tag->getCategory()->getName() : ''),
                'query_builder' => static fn (TagRepository $repo): QueryBuilder => $repo->availableForQuery($tagType, $larp),
                'row_attr' => ['class' => 'character-tag-row__tag'],
            ])
            ->add('percentage', IntegerType::class, [
                'label' => 'character_tag.percentage',
                'attr' => ['min' => 1, 'max' => 100],
                'row_attr' => ['class' => 'character-tag-row__percentage'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => CharacterTag::class,
                'translation_domain' => 'admin',
                'attr' => ['class' => 'character-tag-row'],
            ])
            ->setRequired('tag_type')
            ->setAllowedTypes('tag_type', TagType::class)
            // The character's LARP: its own tags are offered next to the shared ones.
            ->setDefault('larp', null)
            ->setAllowedTypes('larp', ['null', Larp::class]);
    }
}
