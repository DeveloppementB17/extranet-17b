<?php

declare(strict_types=1);

namespace App\Form\Account;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Service\DurationFormatter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TimeCreditInterventionBulkRowType extends AbstractType
{
    public function __construct(
        private readonly DurationFormatter $durationFormatter,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $durationFormatter = $this->durationFormatter;

        $builder
            ->add('entreprise', EntityType::class, [
                'class' => Entreprise::class,
                'choices' => $options['entreprise_choices'],
                'choice_label' => 'name',
                'label' => 'Client',
                'placeholder' => '— Client —',
                'constraints' => [new NotBlank(message: 'Choisissez un client.')],
                'attr' => [
                    'data-bulk-entreprise' => '1',
                    'data-bulk-entreprise-searchable' => '1',
                    'data-placeholder' => 'Rechercher un client…',
                ],
            ])
            ->add('timeCredit', EntityType::class, [
                'class' => TimeCredit::class,
                'choices' => $options['time_credit_choices'],
                'choice_label' => static function (TimeCredit $credit) use ($durationFormatter): string {
                    $dossier = trim((string) $credit->getDossierNumber());
                    $remaining = $credit->getRemainingMinutes();

                    return sprintf(
                        '%s%s — %s restantes',
                        $dossier !== '' ? $dossier.' · ' : '',
                        $credit->getTitle(),
                        $durationFormatter->primary($remaining),
                    );
                },
                'choice_attr' => static function (TimeCredit $credit): array {
                    return [
                        'data-entreprise-id' => (string) ($credit->getEntreprise()?->getId() ?? ''),
                    ];
                },
                'label' => 'Crédit temps',
                'placeholder' => '— Crédit —',
                'constraints' => [new NotBlank(message: 'Choisissez un crédit temps.')],
                'attr' => [
                    'data-bulk-credit' => '1',
                ],
            ])
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => new \DateTimeImmutable('today'),
                'constraints' => [new NotBlank(message: 'Indiquez la date.')],
            ])
            ->add('duree', TextType::class, [
                'label' => 'Durée',
                'constraints' => [new NotBlank(message: 'Indiquez la durée.')],
                'attr' => [
                    'placeholder' => '90 ou 1h30',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('descriptif', TextType::class, [
                'label' => 'Descriptif',
                'constraints' => [new NotBlank(message: 'Indiquez le descriptif.')],
                'attr' => [
                    'placeholder' => 'Description de l’intervention',
                    'autocomplete' => 'off',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'entreprise_choices' => [],
            'time_credit_choices' => [],
        ]);
        $resolver->setAllowedTypes('entreprise_choices', 'array');
        $resolver->setAllowedTypes('time_credit_choices', 'array');
    }
}
