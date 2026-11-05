<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(User::ROLE_ADMIN)]
class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('user.label')
            ->setEntityLabelInPlural('user.label_plural')
            ->setDefaultSort(['email' => 'ASC'])
            ->setSearchFields(['email', 'displayName']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // "Add user" reads better than EasyAdmin's generic "Add <entity label>" in Polish.
        return $actions->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action) => $action->setLabel('user.add'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email', 'user.email');
        yield TextField::new('displayName', 'user.display_name')->setRequired(false);
        yield ChoiceField::new('roles', 'user.roles')
            ->setChoices([
                'user.role.editor' => User::ROLE_EDITOR,
                'user.role.admin' => User::ROLE_ADMIN,
            ])
            ->allowMultipleChoices()
            ->renderExpanded()
            ->renderAsBadges();
        yield BooleanField::new('enabled', 'user.enabled');
        yield TextField::new('plainPassword', 'user.password')
            ->onlyOnForms()
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'required' => Crud::PAGE_NEW === $pageName,
                'first_options' => ['label' => 'user.password'],
                'second_options' => ['label' => 'user.password_repeat'],
                'translation_domain' => 'admin',
            ])
            ->setHelp(Crud::PAGE_EDIT === $pageName ? 'user.password_keep' : '');
        yield DateTimeField::new('createdAt', 'user.created_at')->hideOnForm();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->applyPassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->applyPassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function applyPassword(mixed $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        $plain = $user->getPlainPassword();
        if (null !== $plain && '' !== $plain) {
            $user->setPassword($this->hasher->hashPassword($user, $plain));
            $user->eraseCredentials();
        }
    }
}
