<?php

namespace App\Form;

use App\Entity\Document;
use App\Entity\DocumentKind;
use App\Entity\DocumentTopic;
use App\Entity\Entreprise;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

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
            ->add('documentDate', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'La date est requise.'),
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
                'attr' => $options['lock_entreprise'] ? [] : ['data-entreprise-searchable' => '1'],
                'constraints' => [
                    new NotBlank(message: 'L’entreprise est requise.'),
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => $options['strategy_fields'] ? 'Dossier' : 'Catégorie',
                'required' => $options['strategy_fields'],
                'choices' => $options['category_choices'],
                'placeholder' => $options['strategy_fields'] ? '— Choisir —' : '— Aucune —',
                'constraints' => $options['strategy_fields']
                    ? [new NotBlank(message: 'Le dossier est requis.')]
                    : [],
            ]);

        if ($options['strategy_fields']) {
            $currentYear = (int) date('Y');
            $years = [];
            for ($y = $currentYear + 1; $y >= $currentYear - 10; --$y) {
                $years[(string) $y] = $y;
            }

            $builder
                ->add('year', ChoiceType::class, [
                    'label' => 'Année',
                    'required' => true,
                    'choices' => $years,
                    'placeholder' => '— Choisir —',
                    'constraints' => [
                        new NotBlank(message: 'L’année est requise.'),
                        new Range(min: 2000, max: 2100),
                    ],
                ])
                ->add('kind', EntityType::class, [
                    'class' => DocumentKind::class,
                    'choices' => $options['kind_choices'],
                    'choice_label' => 'name',
                    'label' => 'Type',
                    'required' => true,
                    'placeholder' => '— Choisir —',
                    'constraints' => [
                        new NotBlank(message: 'Le type est requis.'),
                    ],
                ])
                ->add('topic', EntityType::class, [
                    'class' => DocumentTopic::class,
                    'choices' => $options['topic_choices'],
                    'choice_label' => 'name',
                    'label' => 'Sujet',
                    'required' => true,
                    'placeholder' => '— Choisir —',
                    'constraints' => [
                        new NotBlank(message: 'Le sujet est requis.'),
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'category_choices' => [],
            'entreprise_choices' => [],
            'lock_entreprise' => false,
            'strategy_fields' => false,
            'kind_choices' => [],
            'topic_choices' => [],
        ]);

        $resolver->setAllowedTypes('category_choices', 'array');
        $resolver->setAllowedTypes('entreprise_choices', 'array');
        $resolver->setAllowedTypes('lock_entreprise', 'bool');
        $resolver->setAllowedTypes('strategy_fields', 'bool');
        $resolver->setAllowedTypes('kind_choices', 'array');
        $resolver->setAllowedTypes('topic_choices', 'array');
    }
}
