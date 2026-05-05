<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TimeCreditInterventionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('occurredAt', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de l’intervention',
                'data' => new \DateTimeImmutable(),
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
            ])
            ->add('durationValue', NumberType::class, [
                'label' => 'Durée',
                'mapped' => false,
                'scale' => 2,
                'constraints' => [new GreaterThanOrEqual(0.01)],
                'attr' => [
                    'class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary',
                    'min' => 0.01,
                    'step' => 0.25,
                ],
            ])
            ->add('durationUnit', HiddenType::class, [
                'label' => false,
                'mapped' => false,
                'data' => 'minutes',
                'attr' => ['data-duration-unit-field' => '1'],
            ])
            ->add('durationMinutes', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'constraints' => [new NotBlank()],
                'attr' => [
                    'rows' => 3,
                    'class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary',
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            $rawValue = $data['durationValue'] ?? null;
            $value = is_numeric($rawValue) ? (float) $rawValue : 0.0;
            $unit = (string) ($data['durationUnit'] ?? 'minutes');
            $minutes = $unit === 'hours'
                ? (int) round($value * 60)
                : (int) round($value);

            $data['durationMinutes'] = max(0, $minutes);
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
