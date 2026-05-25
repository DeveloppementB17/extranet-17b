<?php

declare(strict_types=1);

namespace App\Recette;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Persistance des sessions de recette en fichiers JSON (var/recette/sessions/).
 */
final class RecetteSessionStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/recette/sessions')]
        private readonly string $sessionsDir,
        private readonly RecetteChecklistDefinition $definition,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSessions(): array
    {
        $this->ensureDirectory();

        $sessions = [];
        foreach (glob($this->sessionsDir.'/*.json') ?: [] as $path) {
            $data = $this->readFile($path);
            if ($data !== null) {
                $sessions[] = $data;
            }
        }

        usort($sessions, static fn (array $a, array $b): int => strcmp(
            (string) ($b['updated_at'] ?? $b['created_at'] ?? ''),
            (string) ($a['updated_at'] ?? $a['created_at'] ?? ''),
        ));

        return $sessions;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $path = $this->pathForId($id);
        if (!is_file($path)) {
            return null;
        }

        return $this->readFile($path);
    }

    /**
     * @param array<string, array{status: string, comment: string}> $responses
     *
     * @return array<string, mixed>
     */
    public function create(string $testerFirstName, string $roleId, array $responses = []): array
    {
        if (!$this->definition->isValidRole($roleId)) {
            throw new \InvalidArgumentException('Rôle de recette invalide.');
        }

        $testerFirstName = trim($testerFirstName);
        if ($testerFirstName === '') {
            throw new \InvalidArgumentException('Le prénom du testeur est obligatoire.');
        }

        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $itemIds = $this->definition->getItemIdsForRole($roleId);
        $normalizedResponses = $this->normalizeResponses($itemIds, $responses);

        $session = [
            'id' => $this->generateId(),
            'created_at' => $now,
            'updated_at' => $now,
            'tester_first_name' => $testerFirstName,
            'role' => $roleId,
            'checklist_version' => (int) ($this->definition->getDefinition()['version'] ?? 1),
            'responses' => $normalizedResponses,
        ];

        $this->write($session);

        return $session;
    }

    /**
     * @param array<string, array{status: string, comment: string}> $responses
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $responses, ?string $testerFirstName = null): array
    {
        $session = $this->find($id);
        if ($session === null) {
            throw new \RuntimeException('Session de recette introuvable.');
        }

        $roleId = (string) $session['role'];
        $itemIds = $this->definition->getItemIdsForRole($roleId);
        $session['responses'] = $this->normalizeResponses($itemIds, $responses);

        if ($testerFirstName !== null) {
            $testerFirstName = trim($testerFirstName);
            if ($testerFirstName === '') {
                throw new \InvalidArgumentException('Le prénom du testeur est obligatoire.');
            }
            $session['tester_first_name'] = $testerFirstName;
        }

        $session['updated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->write($session);

        return $session;
    }

    public function delete(string $id): void
    {
        $path = $this->pathForId($id);
        if (is_file($path)) {
            $this->filesystem->remove($path);
        }
    }

    /**
     * @param list<string> $itemIds
     * @param array<string, array{status?: string, comment?: string}> $responses
     *
     * @return array<string, array{status: string, comment: string}>
     */
    private function normalizeResponses(array $itemIds, array $responses): array
    {
        $defaultStatus = $this->definition->getDefaultStatusId();
        $normalized = [];

        foreach ($itemIds as $itemId) {
            $raw = $responses[$itemId] ?? [];
            $status = (string) ($raw['status'] ?? $defaultStatus);
            if (!$this->definition->isValidStatus($status)) {
                $status = $defaultStatus;
            }

            $normalized[$itemId] = [
                'status' => $status,
                'comment' => trim((string) ($raw['comment'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function write(array $session): void
    {
        $this->ensureDirectory();
        $id = (string) $session['id'];
        $path = $this->pathForId($id);

        $json = json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($path, $json."\n", LOCK_EX);
    }

    private function generateId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function pathForId(string $id): string
    {
        if (!preg_match('/^[a-f0-9\-]{36}$/i', $id)) {
            throw new \InvalidArgumentException('Identifiant de session invalide.');
        }

        return $this->sessionsDir.'/'.$id.'.json';
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->sessionsDir)) {
            $this->filesystem->mkdir($this->sessionsDir);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readFile(string $path): ?array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
