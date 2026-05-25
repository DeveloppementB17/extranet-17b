<?php

declare(strict_types=1);

namespace App\Controller\Recette;

use App\Recette\RecetteChecklistDefinition;
use App\Recette\RecetteSessionStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Outil de recette parallèle au site — accessible sans connexion extranet.
 */
#[Route('/recette')]
final class RecetteChecklistController extends AbstractController
{
    public function __construct(
        private readonly RecetteChecklistDefinition $definition,
        private readonly RecetteSessionStorage $storage,
    ) {
    }

    #[Route('', name: 'recette_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('recette/index.html.twig', [
            'definition' => $this->definition->getDefinition(),
            'roles' => $this->definition->getRoles(),
            'sessions' => $this->storage->listSessions(),
        ]);
    }

    #[Route('/nouvelle', name: 'recette_new', methods: ['GET', 'POST'])]
    public function newSession(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $testerFirstName = (string) $request->request->get('tester_first_name', '');
            $roleId = (string) $request->request->get('role', '');

            try {
                $session = $this->storage->create($testerFirstName, $roleId);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->render('recette/new.html.twig', [
                    'roles' => $this->definition->getRoles(),
                    'tester_first_name' => $testerFirstName,
                    'role' => $roleId,
                ]);
            }

            return $this->redirectToRoute('recette_session', ['id' => $session['id']]);
        }

        return $this->render('recette/new.html.twig', [
            'roles' => $this->definition->getRoles(),
            'tester_first_name' => '',
            'role' => '',
        ]);
    }

    #[Route('/session/{id}', name: 'recette_session', methods: ['GET'])]
    public function session(string $id): Response
    {
        $session = $this->storage->find($id);
        if ($session === null) {
            throw $this->createNotFoundException('Session de recette introuvable.');
        }

        $roleId = (string) $session['role'];

        return $this->render('recette/session.html.twig', [
            'session' => $session,
            'definition' => $this->definition->getDefinition(),
            'sections' => $this->definition->getSectionsForRole($roleId),
            'statuses' => $this->definition->getStatuses(),
            'roles' => $this->definition->getRoles(),
            'role_label' => $this->roleLabel($roleId),
        ]);
    }

    #[Route('/session/{id}/enregistrer', name: 'recette_session_save', methods: ['POST'])]
    public function saveSession(string $id, Request $request): Response
    {
        $session = $this->storage->find($id);
        if ($session === null) {
            throw $this->createNotFoundException('Session de recette introuvable.');
        }

        $responses = $this->extractResponsesFromRequest($request);

        try {
            $session = $this->storage->update(
                $id,
                $responses,
                $request->request->has('tester_first_name')
                    ? (string) $request->request->get('tester_first_name')
                    : null,
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('recette_session', ['id' => $id]);
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'ok' => true,
                'updated_at' => $session['updated_at'],
                'progress' => $this->computeProgress($session),
            ]);
        }

        $this->addFlash('success', 'Checklist enregistrée.');

        return $this->redirectToRoute('recette_session', ['id' => $id]);
    }

    #[Route('/session/{id}/supprimer', name: 'recette_session_delete', methods: ['POST'])]
    public function deleteSession(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('recette_delete_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->storage->delete($id);
        $this->addFlash('success', 'Session de recette supprimée.');

        return $this->redirectToRoute('recette_index');
    }

    #[Route('/session/{id}/export', name: 'recette_session_export', methods: ['GET'])]
    public function exportSession(string $id): Response
    {
        $session = $this->storage->find($id);
        if ($session === null) {
            throw $this->createNotFoundException('Session de recette introuvable.');
        }

        $json = json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $filename = sprintf(
            'recette-%s-%s.json',
            preg_replace('/[^a-z0-9\-]+/i', '-', (string) $session['tester_first_name']),
            substr($id, 0, 8),
        );

        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * @return array<string, array{status: string, comment: string}>
     */
    private function extractResponsesFromRequest(Request $request): array
    {
        $responses = [];
        $rawItems = $request->request->all('items');
        if (!is_array($rawItems)) {
            return $responses;
        }

        foreach ($rawItems as $itemId => $data) {
            if (!is_string($itemId) || !is_array($data)) {
                continue;
            }

            $responses[$itemId] = [
                'status' => (string) ($data['status'] ?? ''),
                'comment' => (string) ($data['comment'] ?? ''),
            ];
        }

        return $responses;
    }

    private function roleLabel(string $roleId): string
    {
        foreach ($this->definition->getRoles() as $role) {
            if (($role['id'] ?? '') === $roleId) {
                return (string) ($role['label'] ?? $roleId);
            }
        }

        return $roleId;
    }

    /**
     * @param array<string, mixed> $session
     *
     * @return array{total: int, valide: int, en_cours: int, non_verifie: int, percent: int}
     */
    private function computeProgress(array $session): array
    {
        $responses = $session['responses'] ?? [];
        if (!is_array($responses)) {
            return ['total' => 0, 'valide' => 0, 'en_cours' => 0, 'non_verifie' => 0, 'percent' => 0];
        }

        $total = count($responses);
        $valide = 0;
        $enCours = 0;
        $nonVerifie = 0;

        foreach ($responses as $response) {
            if (!is_array($response)) {
                continue;
            }

            match ($response['status'] ?? 'non_verifie') {
                'valide' => ++$valide,
                'en_cours' => ++$enCours,
                default => ++$nonVerifie,
            };
        }

        $percent = $total > 0 ? (int) round(($valide / $total) * 100) : 0;

        return [
            'total' => $total,
            'valide' => $valide,
            'en_cours' => $enCours,
            'non_verifie' => $nonVerifie,
            'percent' => $percent,
        ];
    }
}
