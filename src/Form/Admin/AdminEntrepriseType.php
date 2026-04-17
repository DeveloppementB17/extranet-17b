<?php

namespace App\Form\Admin;

use App\Entity\Entreprise;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class AdminEntrepriseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'Nom',
            'constraints' => [
                new NotBlank(message: 'Le nom est requis.'),
                new Length(max: 180),
            ],
            'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
        ]);

        $builder->add('slug', TextType::class, [
            'label' => 'Identifiant URL (slug)',
            'help' => 'Lettres minuscules, chiffres et tirets uniquement. Utilisé pour les dossiers de stockage des documents.',
            'constraints' => [
                new NotBlank(message: 'Le slug est requis.'),
                new Length(max: 64),
                new Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Slug invalide (ex. ma-entreprise).'),
            ],
            'attr' => ['class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary'],
        ]);

        $builder->add('agency', CheckboxType::class, [
            'label' => 'Agence 17b (pas une entreprise cliente)',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entreprise::class,
        ]);
    }
}
