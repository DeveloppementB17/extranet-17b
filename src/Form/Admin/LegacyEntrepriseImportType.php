<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

final class LegacyEntrepriseImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Fichier JSON',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Choisissez un fichier JSON.'),
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'application/json',
                            'text/plain',
                            'text/json',
                            'application/octet-stream',
                        ],
                        mimeTypesMessage: 'Le fichier doit être un JSON.',
                    ),
                ],
                'attr' => ['accept' => '.json,application/json'],
            ])
            ->add('includeArchived', CheckboxType::class, [
                'label' => 'Inclure les entreprises marquées archivées dans le fichier',
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
