<?php

namespace App\Form;

use App\Entity\Entreprise;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DocumentBatchUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'Titre',
            'mapped' => false,
            'required' => true,
            'help' => 'Pour plusieurs fichiers, le nom du fichier est ajouté après le titre pour les distinguer.',
            'constraints' => [
                new NotBlank(message: 'Le titre est requis.'),
                new Length(max: 255),
            ],
        ]);

        $builder->add('entreprise', EntityType::class, [
            'class' => Entreprise::class,
            'choices' => $options['entreprise_choices'],
            'choice_label' => 'name',
            'label' => 'Entreprise',
            'mapped' => false,
            'required' => true,
            'placeholder' => $options['lock_entreprise'] ? false : '— Choisir une entreprise —',
            'data' => $options['preselected_entreprise'],
            'disabled' => $options['lock_entreprise'],
            'constraints' => [
                new NotBlank(message: 'L’entreprise est requise.'),
            ],
        ]);

        $builder->add('category', ChoiceType::class, [
            'label' => 'Catégorie',
            'mapped' => false,
            'required' => false,
            'choices' => $options['category_choices'],
            'placeholder' => '— Choisir —',
        ]);

        $builder->add('files', FileType::class, [
            'label' => 'Fichiers',
            'mapped' => false,
            'required' => false,
            'multiple' => true,
            'help' => '20 Mo max par fichier. Sélectionne un ou plusieurs fichiers (Ctrl/Cmd + clic), ou renseigne une URL externe.',
            'constraints' => [
                new Count(min: 0),
                new All(constraints: [
                    new File(maxSize: '20M'),
                ]),
            ],
        ]);

        $builder->add('externalUrl', UrlType::class, [
            'label' => 'URL externe (optionnel)',
            'mapped' => false,
            'required' => false,
            'default_protocol' => 'https',
            'attr' => [
                'placeholder' => 'https://exemple.com/document.pdf',
            ],
            'constraints' => [
                new Length(max: 2048),
            ],
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            if (!$form->isSubmitted()) {
                return;
            }

            $files = $form->get('files')->getData();
            if (!\is_array($files)) {
                $files = [];
            }
            $validFiles = array_values(array_filter(
                $files,
                static fn (mixed $file): bool => $file instanceof UploadedFile && $file->getError() === \UPLOAD_ERR_OK,
            ));

            $urlRaw = $form->get('externalUrl')->getData();
            $url = \is_string($urlRaw) ? trim($urlRaw) : '';

            if ($validFiles !== [] && $url !== '') {
                $form->addError(new FormError('Indique soit des fichiers, soit une URL externe, pas les deux.'));

                return;
            }

            if ($validFiles === [] && $url === '') {
                $form->addError(new FormError('Ajoute au moins un fichier ou une URL externe.'));

                return;
            }

            if ($url !== '') {
                if (!filter_var($url, \FILTER_VALIDATE_URL)) {
                    $form->get('externalUrl')->addError(new FormError('URL invalide.'));

                    return;
                }

                $parsed = parse_url($url);
                $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : '';
                if (!\in_array($scheme, ['http', 'https'], true)) {
                    $form->get('externalUrl')->addError(new FormError('L’URL doit commencer par http:// ou https://.'));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'category_choices' => [],
            'entreprise_choices' => [],
            'preselected_entreprise' => null,
            'lock_entreprise' => false,
        ]);

        $resolver->setAllowedTypes('category_choices', 'array');
        $resolver->setAllowedTypes('entreprise_choices', 'array');
        $resolver->setAllowedTypes('preselected_entreprise', ['null', Entreprise::class]);
        $resolver->setAllowedTypes('lock_entreprise', 'bool');
    }
}
