<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'packing_calculation')]
#[ORM\Index(columns: ['input_hash', 'created_at'])]
class PackingCalculation
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $inputHash;

    #[ORM\Column(type: Types::TEXT)]
    private string $normalizedRequest;

    #[ORM\Column(type: Types::TEXT)]
    private string $normalizedResult;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $selectedBoxId;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $providerSource;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $refreshedAt;

    public function __construct(
        string $inputHash,
        string $normalizedRequest,
        string $normalizedResult,
        ?int $selectedBoxId,
        string $providerSource,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $refreshedAt,
    ) {
        $this->inputHash = $inputHash;
        $this->normalizedRequest = $normalizedRequest;
        $this->normalizedResult = $normalizedResult;
        $this->selectedBoxId = $selectedBoxId;
        $this->providerSource = $providerSource;
        $this->createdAt = $createdAt;
        $this->refreshedAt = $refreshedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInputHash(): string
    {
        return $this->inputHash;
    }

    public function getNormalizedRequest(): string
    {
        return $this->normalizedRequest;
    }

    public function getNormalizedResult(): string
    {
        return $this->normalizedResult;
    }

    public function getSelectedBoxId(): ?int
    {
        return $this->selectedBoxId;
    }

    public function getProviderSource(): string
    {
        return $this->providerSource;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRefreshedAt(): ?\DateTimeImmutable
    {
        return $this->refreshedAt;
    }
}
