<?php

namespace App\Form;

use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DocumentEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Le titre est requis.'),
                    new Length(max: 255),
                ],
            ])
            ->add('entreprise', EntityType::class, [
                'class' => Entreprise::class,
                'choices' => $options['entreprise_choices'],
                'choice_label' => 'name',
                'label' => 'Entreprise',
                'required' => true,
                'placeholder' => $options['lock_entreprise'] ? false : '— Choisir une entreprise —',
                'disabled' => $options['lock_entreprise'],
                'constraints' => [
                    new NotBlank(message: 'L’entreprise est requise.'),
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'choices' => $options['category_choices'],
                'placeholder' => '— Aucune —',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'category_choices' => [],
            'entreprise_choices' => [],
            'lock_entreprise' => false,
        ]);

        $resolver->setAllowedTypes('category_choices', 'array');
        $resolver->setAllowedTypes('entreprise_choices', 'array');
        $resolver->setAllowedTypes('lock_entreprise', 'bool');
    }
}
