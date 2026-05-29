<?php

namespace App\Form;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Entity\TimeCreditCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
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
            ->add('totalValue', NumberType::class, [
                'label' => 'Total',
                'mapped' => false,
                'scale' => 2,
                'constraints' => [new GreaterThanOrEqual(value: 0.01, message: 'Le total doit être supérieur à 0.')],
                'disabled' => $options['lock_total_field'],
                'attr' => [
                    'class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary',
                    'min' => 0.01,
                    'step' => 0.25,
                ],
            ])
            ->add('totalUnit', HiddenType::class, [
                'label' => false,
                'mapped' => false,
                'data' => 'minutes',
            ])
            ->add('totalMinutes', HiddenType::class);

        if ($options['allow_archive_field']) {
            $builder->add('archived', CheckboxType::class, [
                'required' => false,
                'label' => 'Archivé',
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($options): void {
            if ($options['lock_total_field']) {
                return;
            }

            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            $rawValue = $data['totalValue'] ?? null;
            $value = is_numeric($rawValue) ? (float) $rawValue : 0.0;
            $unit = (string) ($data['totalUnit'] ?? 'minutes');
            $minutes = $unit === 'hours'
                ? (int) round($value * 60)
                : (int) round($value);

            $data['totalMinutes'] = max(0, $minutes);
            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
            $credit = $event->getData();
            if (!$credit instanceof TimeCredit) {
                return;
            }

            $minutes = $credit->getTotalMinutes();
            if ($minutes <= 0) {
                return;
            }

            $form = $event->getForm();
            $form->get('totalValue')->setData((float) $minutes);
            $form->get('totalUnit')->setData('minutes');
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeCredit::class,
            'entreprise_choices' => [],
            'category_choices' => [],
            'allow_archive_field' => true,
            'lock_total_field' => false,
            'preselected_entreprise' => null,
        ]);
        $resolver->setAllowedTypes('entreprise_choices', 'array');
        $resolver->setAllowedTypes('category_choices', 'array');
        $resolver->setAllowedTypes('allow_archive_field', 'bool');
        $resolver->setAllowedTypes('lock_total_field', 'bool');
        $resolver->setAllowedTypes('preselected_entreprise', ['null', Entreprise::class]);
    }
}
