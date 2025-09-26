<?php

namespace App\Entity;

use App\Repository\CorsRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CorsRuleRepository::class)]
class CorsRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'corsRules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bucket $bucket;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customId = null;

    /** @var string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY)]
    private array $allowedMethods = [];

    /** @var string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY)]
    private array $allowedOrigins = [];

    /** @var ?string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $allowedHeaders = null;

    /** @var ?string[] */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $exposeHeaders = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxAgeSeconds = null;

    /**
     * @param string[] $allowedMethods
     * @param string[] $allowedOrigins
     */
    public function __construct(?Bucket $bucket, array $allowedMethods, array $allowedOrigins)
    {
        $this->bucket = $bucket;
        $this->allowedMethods = $allowedMethods;
        $this->allowedOrigins = $allowedOrigins;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBucket(): ?Bucket
    {
        return $this->bucket;
    }

    public function setBucket(?Bucket $bucket): static
    {
        $this->bucket = $bucket;

        return $this;
    }

    public function getCustomId(): ?string
    {
        return $this->customId;
    }

    public function setCustomId(?string $customId): static
    {
        $this->customId = $customId;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /**
     * @param string[] $allowedMethods
     */
    public function setAllowedMethods(array $allowedMethods): static
    {
        $this->allowedMethods = $allowedMethods;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * @param string[] $allowedOrigins
     */
    public function setAllowedOrigins(array $allowedOrigins): static
    {
        $this->allowedOrigins = $allowedOrigins;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getAllowedHeaders(): ?array
    {
        return $this->allowedHeaders;
    }

    /**
     * @param string[]|null $allowedHeaders
     */
    public function setAllowedHeaders(?array $allowedHeaders): static
    {
        $this->allowedHeaders = $allowedHeaders;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getExposeHeaders(): ?array
    {
        return $this->exposeHeaders;
    }

    /**
     * @param string[]|null $exposeHeaders
     */
    public function setExposeHeaders(?array $exposeHeaders): static
    {
        $this->exposeHeaders = $exposeHeaders;

        return $this;
    }

    public function getMaxAgeSeconds(): ?int
    {
        return $this->maxAgeSeconds;
    }

    public function setMaxAgeSeconds(?int $maxAgeSeconds): static
    {
        $this->maxAgeSeconds = $maxAgeSeconds;

        return $this;
    }
}
