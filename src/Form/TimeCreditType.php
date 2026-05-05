<?php

namespace App\Form;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Entity\TimeCreditCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TimeCreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entrepriseFieldOptions = [
            'class' => Entreprise::class,
            'choices' => $options['entreprise_choices'],
            'label' => 'Entreprise cliente',
            'placeholder' => '— Choisir —',
            'constraints' => [new NotBlank(message: 'Choisis une entreprise.')],
            'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
        ];
        if ($options['preselected_entreprise'] instanceof Entreprise) {
            $entrepriseFieldOptions['data'] = $options['preselected_entreprise'];
        }

        $builder
            ->add('entreprise', EntityType::class, $entrepriseFieldOptions)
            ->add('category', EntityType::class, [
                'class' => TimeCreditCategory::class,
                'choices' => $options['category_choices'],
                'required' => false,
                'label' => 'Catégorie',
                'placeholder' => '— Aucune —',
                'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
            ])
            ->add('dossierNumber', TextType::class, [
                'required' => false,
                'label' => 'Numéro de dossier',
                'attr' => [
                    'class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary',
                    'maxlength' => 120,
                ],
            ])
            ->add('totalMinutes', NumberType::class, [
                'label' => 'Total (heures)',
                'scale' => 2,
                'constraints' => [new GreaterThan(value: 0, message: 'Le total doit être supérieur à 0 heure.')],
                'attr' => [
                    'class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary',
                    'min' => 0.01,
                    'step' => 0.25,
                ],
            ]);

        if ($options['allow_archive_field']) {
            $builder->add('archived', CheckboxType::class, [
                'required' => false,
                'label' => 'Archivé',
            ]);
        }

        // L’UI saisit des heures, le modèle persiste des minutes.
        $builder->get('totalMinutes')->addModelTransformer(new CallbackTransformer(
            static fn (?int $minutes): float => $minutes === null ? 0.0 : round($minutes / 60, 2),
            static fn (mixed $hours): int => (int) round(((float) ($hours ?? 0)) * 60),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeCredit::class,
            'entreprise_choices' => [],
            'category_choices' => [],
            'allow_archive_field' => true,
            'preselected_entreprise' => null,
        ]);
        $resolver->setAllowedTypes('entreprise_choices', 'array');
        $resolver->setAllowedTypes('category_choices', 'array');
        $resolver->setAllowedTypes('allow_archive_field', 'bool');
        $resolver->setAllowedTypes('preselected_entreprise', ['null', Entreprise::class]);
    }
}
