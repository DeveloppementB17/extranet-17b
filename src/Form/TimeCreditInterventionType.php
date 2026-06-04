<?php

namespace App\Form;

use App\Entity\TimeCredit;
use App\Entity\TimeCreditMovement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TimeCreditInterventionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['show_credit_selector']) {
            $builder->add('timeCredit', EntityType::class, [
                'class' => TimeCredit::class,
                'choices' => $options['time_credit_choices'],
                'data' => $options['preselected_time_credit'],
                'choice_label' => static function (TimeCredit $credit): string {
                    $remainingMinutes = $credit->getRemainingMinutes();
                    $display = sprintf('%s min', $remainingMinutes);
                    $alternate = sprintf('%.2f h', $remainingMinutes / 60);

                    if ($remainingMinutes > 180) {
                        $display = sprintf('%.2f h', $remainingMinutes / 60);
                        $alternate = sprintf('%s min', $remainingMinutes);
                    }

                    return sprintf(
                        '%s — %s restantes (%s)',
                        $credit->getTitle(),
                        $display,
                        $alternate
                    );
                },
                'label' => 'Crédit temps',
                'placeholder' => '— Choisir un crédit —',
                'mapped' => false,
                'constraints' => [new NotBlank(message: 'Choisis un crédit temps.')],
                'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
            ]);
        } elseif ($options['fixed_time_credit'] instanceof TimeCredit) {
            $builder->add('timeCreditId', HiddenType::class, [
                'mapped' => false,
                'data' => $options['fixed_time_credit']->getId(),
            ]);
        }

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

        if ($options['return_to'] !== null) {
            $builder->add('returnTo', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'data' => $options['return_to'],
            ]);
        }

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

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($options): void {
            $credit = $options['time_credit_for_validation'];
            if (!$credit instanceof TimeCredit) {
                return;
            }

            $form = $event->getForm();
            if (!$form->isSubmitted()) {
                return;
            }

            $duration = (int) $form->get('durationMinutes')->getData();
            $remaining = $credit->getRemainingMinutes();
            if ($options['initial_movement'] instanceof TimeCreditMovement) {
                $remaining += abs($options['initial_movement']->getDeltaMinutes());
            }
            if ($duration > $remaining) {
                $form->get('durationValue')->addError(new FormError(sprintf(
                    'La durée saisie (%d min) dépasse le solde disponible (%d min). Réduisez la durée ou ajustez le total du crédit.',
                    $duration,
                    $remaining,
                )));
            }
        });

        if ($options['initial_movement'] instanceof TimeCreditMovement) {
            $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event) use ($options): void {
                $movement = $options['initial_movement'];
                if ($movement->getType() !== TimeCreditMovement::TYPE_INTERVENTION) {
                    return;
                }

                $minutes = abs($movement->getDeltaMinutes());
                $form = $event->getForm();
                $form->get('occurredAt')->setData($movement->getOccurredAt());
                $form->get('durationValue')->setData((float) $minutes);
                $form->get('durationUnit')->setData('minutes');
                $form->get('description')->setData($movement->getDescription() ?? '');
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'show_credit_selector' => false,
            'time_credit_choices' => [],
            'preselected_time_credit' => null,
            'fixed_time_credit' => null,
            'initial_movement' => null,
            'return_to' => null,
            'time_credit_for_validation' => null,
        ]);
        $resolver->setAllowedTypes('show_credit_selector', 'bool');
        $resolver->setAllowedTypes('time_credit_choices', 'array');
        $resolver->setAllowedTypes('preselected_time_credit', ['null', TimeCredit::class]);
        $resolver->setAllowedTypes('fixed_time_credit', ['null', TimeCredit::class]);
        $resolver->setAllowedTypes('initial_movement', ['null', TimeCreditMovement::class]);
        $resolver->setAllowedTypes('return_to', ['null', 'string']);
        $resolver->setAllowedTypes('time_credit_for_validation', ['null', TimeCredit::class]);
    }
}
