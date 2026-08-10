<?php

namespace App\Form;

use App\Document\DocumentUploadPolicy;
use App\Document\ExternalDocumentUrlChecker;
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
    public function __construct(
        private readonly ExternalDocumentUrlChecker $externalDocumentUrlChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'Titre',
            'mapped' => false,
            'required' => true,
            'help' => 'Pour plusieurs fichiers, le nom du fichier est ajouté après le titre pour les distinguer.',
            'help_attr' => ['class' => 'mt-2 text-sm text-slate-600'],
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
            'help' => sprintf(
                '20 Mo max par fichier. Extensions autorisées : %s. Sélectionnez un ou plusieurs fichiers (Ctrl/Cmd + clic), ou renseignez une URL externe https.',
                DocumentUploadPolicy::extensionsLabel(),
            ),
            'help_attr' => ['class' => 'mt-2 text-sm text-slate-600'],
            'attr' => [
                'accept' => DocumentUploadPolicy::acceptAttribute(),
            ],
            'constraints' => [
                new Count(min: 0),
                new All(constraints: [
                    new File(
                        maxSize: '20M',
                        maxSizeMessage: 'Le fichier est trop volumineux ({{ size }} {{ suffix }}). La taille maximale autorisée est de {{ limit }} {{ suffix }}.',
                        extensions: DocumentUploadPolicy::extensions(),
                        extensionsMessage: 'Extension non autorisée. Formats acceptés : {{ extensions }}.',
                    ),
                ]),
            ],
        ]);

        $builder->add('externalUrl', UrlType::class, [
            'label' => 'URL externe https (optionnel)',
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

            $hasUploadErrors = false;
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                if ($file->getError() === \UPLOAD_ERR_INI_SIZE || $file->getError() === \UPLOAD_ERR_FORM_SIZE) {
                    $hasUploadErrors = true;
                    $message = 'Un ou plusieurs fichiers dépassent la taille maximale autorisée (20 Mo). Réduisez la taille puis réessayez.';
                    $form->addError(new FormError($message));
                    $form->get('files')->addError(new FormError($message));
                    break;
                }
                if ($file->getError() !== \UPLOAD_ERR_OK) {
                    $hasUploadErrors = true;
                    $message = 'Un ou plusieurs fichiers n’ont pas pu être téléversés correctement.';
                    $form->addError(new FormError($message));
                    $form->get('files')->addError(new FormError($message));
                    break;
                }
            }
            if ($hasUploadErrors) {
                return;
            }

            $validFiles = array_values(array_filter(
                $files,
                static fn (mixed $file): bool => $file instanceof UploadedFile && $file->getError() === \UPLOAD_ERR_OK,
            ));

            $urlRaw = $form->get('externalUrl')->getData();
            $url = \is_string($urlRaw) ? trim($urlRaw) : '';

            if ($validFiles !== [] && $url !== '') {
                $message = 'Indiquez soit des fichiers, soit une URL externe, pas les deux.';
                $form->addError(new FormError($message));
                $form->get('files')->addError(new FormError($message));
                $form->get('externalUrl')->addError(new FormError($message));

                return;
            }

            if ($validFiles === [] && $url === '') {
                $form->addError(new FormError('Ajoutez au moins un fichier ou une URL externe.'));

                return;
            }

            if ($url !== '') {
                $urlError = $this->externalDocumentUrlChecker->validate($url);
                if ($urlError !== null) {
                    $form->get('externalUrl')->addError(new FormError($urlError));
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
