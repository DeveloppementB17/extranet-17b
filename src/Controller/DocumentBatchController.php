<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Form\DocumentBatchUploadType;
use App\Repository\DocumentCategoryRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\UserRepository;
use App\Tenant\ManagedClientContext;
use App\Storage\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents/ajouter')]
#[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
final class DocumentBatchController extends AbstractController
{
    #[Route('', name: 'document_batch_upload', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        UserRepository $userRepository,
        DocumentCategoryRepository $categoryRepository,
        EntrepriseRepository $entrepriseRepository,
        ManagedClientContext $managedClientContext,
        DocumentStorage $storage,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $forcedEntreprise = null;
        if ($user->is17bStaff()) {
            $forcedEntreprise = $managedClientContext->getSelectedManagedEntreprise($user);
            if ($user->is17bUser() && !$forcedEntreprise instanceof Entreprise) {
                $this->addFlash('error', 'Sélectionne d’abord un client depuis le tableau de bord.');

                return $this->redirectToRoute('app_home');
            }
        }

        $clients = $userRepository->findCustomerAccountsForStaffUpload($user);
        if ($forcedEntreprise instanceof Entreprise) {
            $forcedId = $forcedEntreprise->getId();
            $clients = array_values(array_filter(
                $clients,
                static fn (User $candidate): bool => $candidate->getEntreprise()?->getId() === $forcedId,
            ));
        }
        $hasClients = $clients !== [];

        $form = $this->createForm(DocumentBatchUploadType::class, options: [
            'category_choices' => $this->buildCategoryChoices($categoryRepository, $entrepriseRepository, $user, $forcedEntreprise),
            'client_choices' => $clients,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $titleBase = trim((string) $form->get('title')->getData());
            $category = $form->get('category')->getData();
            /** @var User $clientUser */
            $clientUser = $form->get('client')->getData();

            if (!$clientUser->isCustomerActor()) {
                $this->addFlash('error', 'Le destinataire doit être un compte client (CUSTOMER_ADMIN ou CUSTOMER_USER).');

                return $this->redirectToRoute('document_batch_upload');
            }

            $clientEntreprise = $clientUser->getEntreprise();
            if ($clientEntreprise === null || $clientEntreprise->isAgency()) {
                $this->addFlash('error', 'Le destinataire doit être rattaché à une entreprise cliente.');

                return $this->redirectToRoute('document_batch_upload');
            }

            if ($user->is17bUser() && !$user->managesEntreprise($clientEntreprise)) {
                throw $this->createAccessDeniedException();
            }

            if ($category instanceof DocumentCategory) {
                $catEnt = $category->getEntreprise();
                if ($catEnt === null || $catEnt->getId() !== $clientEntreprise->getId()) {
                    $this->addFlash('error', 'La catégorie doit appartenir à la même entreprise que le client destinataire.');

                    return $this->redirectToRoute('document_batch_upload');
                }
            }

            /** @var list<UploadedFile>|null $files */
            $files = $form->get('files')->getData();
            if (!\is_array($files)) {
                $files = [];
            }

            $validFiles = [];
            foreach ($files as $file) {
                if ($file instanceof UploadedFile && $file->getError() === \UPLOAD_ERR_OK) {
                    $validFiles[] = $file;
                }
            }

            $multi = \count($validFiles) > 1;
            $count = 0;
            foreach ($validFiles as $file) {
                $sizeBeforeMove = $file->getSize();
                $mimeBeforeMove = (string) ($file->getClientMimeType() ?: 'application/octet-stream');
                $stored = $storage->storeUploadedFile($file, $user);

                $doc = new Document();
                $doc->setEntreprise($clientEntreprise);
                $doc->setClient($clientUser);
                $doc->setUploadedBy($user);
                $doc->setTitle($multi ? $titleBase.' — '.$file->getClientOriginalName() : $titleBase);
                if ($category instanceof DocumentCategory) {
                    $doc->setCategory($category);
                }
                $doc->setOriginalName($file->getClientOriginalName());
                $doc->setStorageName($stored['storageName']);
                $doc->setStoragePath($stored['relativePath']);
                $doc->setMimeType($mimeBeforeMove);
                $doc->setSize((int) ($sizeBeforeMove ?: (is_file($stored['absolutePath']) ? filesize($stored['absolutePath']) : 0)));
                $doc->setExternalUrl(null);

                $entityManager->persist($doc);
                ++$count;
            }

            $entityManager->flush();

            if ($count === 0) {
                $this->addFlash('error', 'Aucun fichier valide n’a été reçu.');
            } else {
                $this->addFlash('success', sprintf('%d document(s) ajouté(s).', $count));
            }

            return $this->redirectToRoute('document_index');
        }

        return $this->render('document/batch.html.twig', [
            'form' => $form,
            'has_clients' => $hasClients,
        ]);
    }

    /**
     * @return array<string, DocumentCategory>
     */
    private function buildCategoryChoices(
        DocumentCategoryRepository $categoryRepository,
        EntrepriseRepository $entrepriseRepository,
        User $actor,
        ?Entreprise $forcedEntreprise = null,
    ): array {
        $allowed = $forcedEntreprise instanceof Entreprise
            ? [$forcedEntreprise]
            : $this->allowedClientEntreprises($actor, $entrepriseRepository);
        $choices = [];
        foreach ($allowed as $ent) {
            foreach ($categoryRepository->findRootsForEntreprise($ent) as $root) {
                $this->appendCategoryBranchChoices($root, $ent, '', $choices);
            }
        }

        return $choices;
    }

    /**
     * @return list<Entreprise>
     */
    private function allowedClientEntreprises(User $actor, EntrepriseRepository $entrepriseRepository): array
    {
        if ($actor->is17bAdmin()) {
            return $entrepriseRepository->findNonAgencyOrdered();
        }

        if ($actor->is17bUser()) {
            return $entrepriseRepository->findNonAgencyByIdsOrdered($actor->getManagedEntrepriseIds());
        }

        return [];
    }

    /**
     * @param array<string, DocumentCategory> $choices
     */
    private function appendCategoryBranchChoices(
        DocumentCategory $category,
        Entreprise $entreprise,
        string $path,
        array &$choices,
    ): void {
        $label = $entreprise->getName().' — '.$path.$category->getName();
        $choices[$label] = $category;
        $next = $path === '' ? $category->getName().' / ' : $path.$category->getName().' / ';
        foreach ($category->getChildren() as $child) {
            $this->appendCategoryBranchChoices($child, $entreprise, $next, $choices);
        }
    }
}
