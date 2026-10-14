<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Entreprise;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TimeCreditInterventionCsvImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('entreprise', EntityType::class, [
                'class' => Entreprise::class,
                'choices' => $options['entreprise_choices'],
                'choice_label' => 'name',
                'label' => 'Client',
                'placeholder' => '— Choisir un client —',
                'constraints' => [new NotBlank(message: 'Choisissez un client.')],
                'attr' => ['data-entreprise-searchable' => '1'],
            ])
            ->add('file', FileType::class, [
                'label' => 'Fichier CSV',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Choisissez un fichier CSV.'),
                    new File(
                        maxSize: '5M',
                        // Les CSV Excel / macOS arrivent souvent en octet-stream.
                        mimeTypes: [
                            'text/plain',
                            'text/csv',
                            'application/csv',
                            'text/comma-separated-values',
                            'application/vnd.ms-excel',
                            'application/octet-stream',
                        ],
                        mimeTypesMessage: 'Le fichier doit être un CSV.',
                    ),
                ],
            ])
            ->add('dryRun', CheckboxType::class, [
                'label' => 'Simulation uniquement (ne rien enregistrer)',
                'required' => false,
                'data' => true,
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
        ]);
        $resolver->setAllowedTypes('entreprise_choices', 'array');
    }
}
