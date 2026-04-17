<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use App\Tenant\EntrepriseOwnedInterface;
use App\Tenant\EntrepriseOwnedTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
class Document implements EntrepriseOwnedInterface
{
    use EntrepriseOwnedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $storageName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $storagePath = null;

    /**
     * Document hébergé ailleurs : pas de fichier en storage local.
     */
    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $externalUrl = null;

    #[ORM\ManyToOne(targetEntity: DocumentCategory::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DocumentCategory $category = null;

    /**
     * Utilisateur « client » auquel le document est destiné (espace documentaire du client).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?User $client = null;

    /**
     * Compte ayant effectué l’upload (admin ou super admin).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?User $uploadedBy = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getStorageName(): ?string
    {
        return $this->storageName;
    }

    public function setStorageName(?string $storageName): self
    {
        $this->storageName = $storageName;

        return $this;
    }

    /**
     * Chemin relatif au STORAGE_PATH (ex: "companies/17b/documents/abc.pdf"), null si lien externe.
     */
    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(?string $storagePath): self
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl !== null && $externalUrl !== '' ? $externalUrl : null;

        return $this;
    }

    public function isExternalLink(): bool
    {
        return $this->externalUrl !== null && $this->externalUrl !== '';
    }

    public function getCategory(): ?DocumentCategory
    {
        return $this->category;
    }

    public function setCategory(?DocumentCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): self
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
