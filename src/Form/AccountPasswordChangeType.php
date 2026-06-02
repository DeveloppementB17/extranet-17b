<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AccountPasswordChangeType extends AbstractType
{
    private const int MIN_PASSWORD_LENGTH = 10;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldAttr = [
            'class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary',
            'autocomplete' => 'off',
        ];

        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => array_merge($fieldAttr, ['autocomplete' => 'current-password']),
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe actuel est requis.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'help' => sprintf('Minimum %d caractères.', self::MIN_PASSWORD_LENGTH),
                    'attr' => array_merge($fieldAttr, ['autocomplete' => 'new-password']),
                    'constraints' => [
                        new NotBlank(message: 'Le nouveau mot de passe est requis.'),
                        new Length(
                            min: self::MIN_PASSWORD_LENGTH,
                            max: 4096,
                            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        ),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmation du nouveau mot de passe',
                    'attr' => array_merge($fieldAttr, ['autocomplete' => 'new-password']),
                    'constraints' => [
                        new NotBlank(message: 'La confirmation est requise.'),
                    ],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'account_password_change',
        ]);
    }
}
