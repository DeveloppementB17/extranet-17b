<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DocumentBatchUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'Titre',
            'mapped' => false,
            'required' => true,
            'help' => 'Pour plusieurs fichiers, le nom du fichier est ajouté après le titre pour les distinguer.',
            'constraints' => [
                new NotBlank(message: 'Le titre est requis.'),
                new Length(max: 255),
            ],
        ]);

        $builder->add('client', EntityType::class, [
            'class' => User::class,
            'choices' => $options['client_choices'],
            'choice_label' => 'email',
            'label' => 'Client destinataire',
            'mapped' => false,
            'required' => true,
            'placeholder' => '— Choisir un client —',
            'constraints' => [
                new NotBlank(message: 'Le client destinataire est requis.'),
            ],
        ]);

        $builder->add('category', ChoiceType::class, [
            'label' => 'Catégorie',
            'mapped' => false,
            'required' => false,
            'choices' => $options['category_choices'],
            'placeholder' => '— Choisir —',
        ]);

        $builder->add('files', FileType::class, [
            'label' => 'Fichiers',
            'mapped' => false,
            'required' => true,
            'multiple' => true,
            'help' => '20 Mo max par fichier. Sélectionne un ou plusieurs fichiers (Ctrl/Cmd + clic).',
            'constraints' => [
                new Count(min: 1, minMessage: 'Ajoute au moins un fichier.'),
                new All(constraints: [
                    new File(maxSize: '20M'),
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'category_choices' => [],
            'client_choices' => [],
        ]);

        $resolver->setAllowedTypes('category_choices', 'array');
        $resolver->setAllowedTypes('client_choices', 'array');
    }
}
