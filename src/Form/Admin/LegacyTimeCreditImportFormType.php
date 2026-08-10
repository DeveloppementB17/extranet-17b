<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Entreprise;
use App\Entity\TimeCreditCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class LegacyTimeCreditImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du crédit',
                'constraints' => [
                    new NotBlank(message: 'Le titre est requis.'),
                    new Length(max: 255),
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => TimeCreditCategory::class,
                'choices' => $options['category_choices'],
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => '— Aucune —',
            ])
            ->add('dossierNumber', TextType::class, [
                'label' => 'N° de dossier',
                'required' => false,
                'constraints' => [new Length(max: 120)],
            ])
            ->add('siteUrl', TextType::class, [
                'label' => 'URL du site (monitor)',
                'required' => false,
                'constraints' => [new Length(max: 500)],
            ]);

        if ($options['require_entreprise_choice']) {
            $builder->add('entreprise', EntityType::class, [
                'class' => Entreprise::class,
                'choices' => $options['entreprise_choices'],
                'choice_label' => 'name',
                'label' => 'Entreprise cible',
                'placeholder' => '— Choisir —',
                'constraints' => [new NotBlank(message: 'Choisissez une entreprise cible.')],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'category_choices' => [],
            'entreprise_choices' => [],
            'require_entreprise_choice' => false,
        ]);
        $resolver->setAllowedTypes('category_choices', 'array');
        $resolver->setAllowedTypes('entreprise_choices', 'array');
        $resolver->setAllowedTypes('require_entreprise_choice', 'bool');
    }
}
