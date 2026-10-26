<?php

declare(strict_types=1);

namespace App\Form\Account;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Valid;

final class TimeCreditInterventionBulkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rows', CollectionType::class, [
                'entry_type' => TimeCreditInterventionBulkRowType::class,
                'entry_options' => [
                    'label' => false,
                    'entreprise_choices' => $options['entreprise_choices'],
                    'time_credit_choices' => $options['time_credit_choices'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
                'constraints' => [
                    new Count(min: 1, minMessage: 'Ajoutez au moins une intervention.'),
                    new Valid(),
                ],
            ])
            ->add('allowOverdraft', CheckboxType::class, [
                'label' => 'Autoriser le dépassement du solde restant',
                'required' => false,
                'data' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entreprise_choices' => [],
            'time_credit_choices' => [],
        ]);
        $resolver->setAllowedTypes('entreprise_choices', 'array');
        $resolver->setAllowedTypes('time_credit_choices', 'array');
    }
}
