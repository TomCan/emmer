<?php

namespace App\Entity;

use App\Repository\PolicyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PolicyRepository::class)]
class Policy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'policies')]
    private ?Bucket $bucket = null;

    #[ORM\ManyToOne(inversedBy: 'policies')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $policy = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getPolicy(): ?string
    {
        return $this->policy;
    }

    public function setPolicy(string $policy): static
    {
        $this->policy = $policy;

        return $this;
    }
}
