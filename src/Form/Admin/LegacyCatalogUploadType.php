<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class LegacyCatalogUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('entreprisesFile', FileType::class, [
                'label' => 'Catalogue entreprises (JSON)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['application/json', 'text/plain', 'text/json', 'application/octet-stream'],
                        mimeTypesMessage: 'Le fichier entreprises doit être un JSON.',
                    ),
                ],
                'attr' => ['accept' => '.json,application/json'],
            ])
            ->add('timeCreditsFile', FileType::class, [
                'label' => 'Catalogue crédits temps (JSON)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '8M',
                        mimeTypes: ['application/json', 'text/plain', 'text/json', 'application/octet-stream'],
                        mimeTypesMessage: 'Le fichier crédits doit être un JSON.',
                    ),
                ],
                'attr' => ['accept' => '.json,application/json'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
