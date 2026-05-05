<?php

namespace App\Form;

use App\Entity\TimeCredit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TimeCreditQuickInterventionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('timeCredit', EntityType::class, [
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
                'constraints' => [new NotBlank(message: 'Choisis un crédit temps.')],
                'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
            ])
            ->add('occurredAt', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date',
                'data' => new \DateTimeImmutable(),
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label' => 'Durée (minutes)',
                'constraints' => [new GreaterThanOrEqual(1)],
                'attr' => [
                    'class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary',
                    'min' => 1,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'constraints' => [new NotBlank()],
                'attr' => [
                    'rows' => 2,
                    'class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary',
                ],
            ])
            ->add('returnTo', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'data' => $options['return_to'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'time_credit_choices' => [],
            'preselected_time_credit' => null,
            'return_to' => '/credits-temps',
            'csrf_token_id' => 'time_credit_quick_intervention',
        ]);
        $resolver->setAllowedTypes('time_credit_choices', 'array');
        $resolver->setAllowedTypes('preselected_time_credit', ['null', TimeCredit::class]);
        $resolver->setAllowedTypes('return_to', 'string');
    }
}
