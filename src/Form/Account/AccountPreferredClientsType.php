<?php

namespace App\Form\Account;

use App\Entity\Entreprise;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AccountPreferredClientsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('managedEntreprises', EntityType::class, [
            'class' => Entreprise::class,
            'choices' => $options['client_entreprise_choices'],
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'label' => 'Clients rattachés',
            'help' => 'Ces entreprises apparaissent en tête du sélecteur et dans le filtre « Mes clients ». Vous conservez l’accès à tous les clients.',
            'choice_label' => 'name',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'client_entreprise_choices' => [],
        ]);
        $resolver->setAllowedTypes('client_entreprise_choices', 'array');
    }
}
