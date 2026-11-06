<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CharacterTag;
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
 */
final class CharacterTagType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var TagType $tagType */
        $tagType = $options['tag_type'];

        $builder
            ->add('tag', EntityType::class, [
                'class' => Tag::class,
                'label' => 'character_tag.tag',
                'placeholder' => 'character_tag.choose',
                'choice_label' => 'name',
                'group_by' => static fn (Tag $tag): ?string => $tag->getCategory()?->getName(),
                'query_builder' => static fn (TagRepository $repo): QueryBuilder => $repo->createQueryBuilder('t')
                    ->leftJoin('t.category', 'c')
                    ->andWhere('t.type = :type')
                    ->setParameter('type', $tagType)
                    ->orderBy('c.position', 'ASC')
                    ->addOrderBy('c.name', 'ASC')
                    ->addOrderBy('t.name', 'ASC'),
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
            ->setAllowedTypes('tag_type', TagType::class);
    }
}
