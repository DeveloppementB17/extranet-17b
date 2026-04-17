<?php

namespace App\Form\Admin;

use App\Entity\Entreprise;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Email',
            'constraints' => [new NotBlank()],
            'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
        ]);

        $builder->add('primaryRole', ChoiceType::class, [
            'mapped' => false,
            'label' => 'Rôle',
            'choices' => [
                'Administrateur 17b' => 'ROLE_17B_ADMIN',
                'Utilisateur 17b (périmètre limité)' => 'ROLE_17B_USER',
                'Administrateur client' => 'ROLE_CUSTOMER_ADMIN',
                'Utilisateur client' => 'ROLE_CUSTOMER_USER',
            ],
            'placeholder' => false,
            'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
        ]);

        $builder->add('entreprise', EntityType::class, [
            'class' => Entreprise::class,
            'choices' => $options['entreprise_choices'],
            'label' => 'Entreprise de rattachement',
            'placeholder' => '— Choisir —',
            'constraints' => [new NotBlank(message: 'Choisis une entreprise.')],
            'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
        ]);

        $builder->add('managedEntreprises', EntityType::class, [
            'mapped' => false,
            'class' => Entreprise::class,
            'choices' => $options['client_entreprise_choices'],
            'multiple' => true,
            'expanded' => false,
            'required' => false,
            'label' => 'Entreprises clientes gérées',
            'help' => 'Optionnel : pour un utilisateur 17b à périmètre limité, limite l’accès aux entreprises sélectionnées. Vide = aucun accès aux données clients tant qu’aucune entreprise n’est associée.',
            'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary', 'size' => 6],
        ]);

        $pwdConstraintsFirst = $options['require_password']
            ? [new NotBlank(message: 'Mot de passe requis.'), new Length(min: 8, max: 4096)]
            : [new Length(max: 4096)];

        $firstPwdField = [
            'label' => 'Mot de passe',
            'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
            'constraints' => $pwdConstraintsFirst,
        ];
        if (!$options['require_password']) {
            $firstPwdField['help'] = 'Laisser vide pour conserver le mot de passe actuel.';
        }

        $pwdOpts = [
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => $firstPwdField,
            'second_options' => [
                'label' => 'Confirmation',
                'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
                'constraints' => $options['require_password'] ? [new NotBlank()] : [],
            ],
            'invalid_message' => 'Les mots de passe doivent correspondre.',
        ];
        if (!$options['require_password']) {
            $pwdOpts['required'] = false;
        }
        $builder->add('plainPassword', RepeatedType::class, $pwdOpts);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $user = $event->getData();
            if (!$user instanceof User || null === $user->getId()) {
                return;
            }
            $form = $event->getForm();
            $form->get('primaryRole')->setData($user->getPrimaryStoredRole());
            $form->get('managedEntreprises')->setData($user->getManagedEntreprises()->toArray());
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => true,
            'entreprise_choices' => [],
            'client_entreprise_choices' => [],
        ]);
        $resolver->setAllowedTypes('entreprise_choices', 'array');
        $resolver->setAllowedTypes('client_entreprise_choices', 'array');
    }
}
