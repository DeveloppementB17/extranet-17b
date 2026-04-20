<?php

namespace App\Form\Customer;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CustomerUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Email',
            'constraints' => [new NotBlank()],
            'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => true,
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
