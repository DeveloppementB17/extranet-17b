<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DocumentUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'Titre',
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new NotBlank(message: 'Le titre est requis.'),
                new Length(max: 255),
            ],
        ]);

        $builder->add('client', EntityType::class, [
            'class' => User::class,
            'choices' => $options['client_choices'],
            'choice_label' => 'email',
            'label' => 'Client destinataire',
            'mapped' => false,
            'required' => true,
            'placeholder' => '— Choisir un client —',
            'constraints' => [
                new NotBlank(message: 'Le client destinataire est requis.'),
            ],
        ]);

        $builder->add('category', ChoiceType::class, [
            'label' => 'Catégorie',
            'mapped' => false,
            'required' => false,
            'choices' => $options['category_choices'],
            'placeholder' => '— Choisir —',
        ]);

        $builder->add('file', FileType::class, [
            'label' => 'Fichier (optionnel, 20 Mo max)',
            'mapped' => false,
            'required' => false,
            'constraints' => [
                new File(
                    maxSize: '20M',
                ),
            ],
        ]);

        $builder->add('externalUrl', UrlType::class, [
            'label' => 'URL externe (optionnel)',
            'mapped' => false,
            'required' => false,
            'default_protocol' => 'https',
            'attr' => [
                'placeholder' => 'https://exemple.com/document.pdf',
                'class' => 'mt-2 block w-full rounded bg-slate-100 px-3 py-2 text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-primary',
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

            $file = $form->get('file')->getData();
            $urlRaw = $form->get('externalUrl')->getData();
            $url = \is_string($urlRaw) ? trim($urlRaw) : '';

            $hasFile = $file instanceof UploadedFile && $file->getError() === \UPLOAD_ERR_OK;

            if ($hasFile && $url !== '') {
                $form->addError(new FormError('Indique soit un fichier, soit une URL externe, pas les deux.'));

                return;
            }

            if (!$hasFile && $url === '') {
                $form->addError(new FormError('Ajoute un fichier ou une URL externe.'));

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
            'client_choices' => [],
        ]);

        $resolver->setAllowedTypes('category_choices', 'array');
        $resolver->setAllowedTypes('client_choices', 'array');
    }
}
