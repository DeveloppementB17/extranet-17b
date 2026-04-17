<?php

namespace App\Form;

use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DocumentCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entrepriseChoices = $options['entreprise_choices'];
        if ($entrepriseChoices !== []) {
            $builder->add('entreprise', EntityType::class, [
                'class' => Entreprise::class,
                'choices' => $entrepriseChoices,
                'label' => 'Entreprise cliente',
                'required' => true,
                'placeholder' => '— Choisir —',
                'constraints' => [
                    new NotBlank(message: 'Choisis une entreprise cliente.'),
                ],
            ]);
        }

        $builder->add('name', TextType::class, [
            'label' => 'Nom du dossier',
            'constraints' => [
                new NotBlank(message: 'Le nom est requis.'),
                new Length(max: 255),
            ],
        ]);

        $builder->add('parent', EntityType::class, [
            'class' => DocumentCategory::class,
            'choices' => $options['parent_choices'],
            'required' => false,
            'placeholder' => '— Racine (niveau principal) —',
            'label' => 'Dossier parent',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentCategory::class,
            'parent_choices' => [],
            'entreprise_choices' => [],
        ]);

        $resolver->setAllowedTypes('parent_choices', 'array');
        $resolver->setAllowedTypes('entreprise_choices', 'array');
    }
}
