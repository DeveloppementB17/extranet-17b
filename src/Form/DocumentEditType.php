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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
            ]);

        if (!$options['strategy_fields']) {
            $builder->add('documentDate', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'La date est requise.'),
                ],
            ]);
        }

        $builder
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
                'label' => $options['strategy_fields'] ? 'Dossier' : 'Type',
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
                ->add('month', ChoiceType::class, [
                    'label' => 'Mois',
                    'mapped' => false,
                    'required' => true,
                    'choices' => self::monthChoices(),
                    'placeholder' => '— Choisir —',
                    'constraints' => [
                        new NotBlank(message: 'Le mois est requis.'),
                        new Range(min: 1, max: 12),
                    ],
                ])
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

            $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
                $document = $event->getData();
                $form = $event->getForm();
                if (!$document instanceof Document || !$form->has('month')) {
                    return;
                }

                $date = $document->getDocumentDate();
                $form->get('month')->setData((int) $date->format('n'));
                if ($document->getYear() === null) {
                    $form->get('year')->setData((int) $date->format('Y'));
                }
            });

            $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
                $document = $event->getData();
                $form = $event->getForm();
                if (!$document instanceof Document || !$form->has('month') || !$form->has('year')) {
                    return;
                }

                $month = $form->get('month')->getData();
                $year = $form->get('year')->getData();
                if (!\is_int($month) || !\is_int($year)) {
                    return;
                }

                $document->setDocumentDate(new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)));
                $document->setYear($year);
            });
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

    /**
     * @return array<string, int>
     */
    public static function monthChoices(): array
    {
        return [
            'Janvier' => 1,
            'Février' => 2,
            'Mars' => 3,
            'Avril' => 4,
            'Mai' => 5,
            'Juin' => 6,
            'Juillet' => 7,
            'Août' => 8,
            'Septembre' => 9,
            'Octobre' => 10,
            'Novembre' => 11,
            'Décembre' => 12,
        ];
    }
}
