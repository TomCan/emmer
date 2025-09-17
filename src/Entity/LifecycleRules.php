<?php

namespace App\Entity;

use App\Repository\LifecycleRulesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LifecycleRulesRepository::class)]
class LifecycleRules
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lifecycleRules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bucket $bucket = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $rules = null;

    public function __construct(?Bucket $bucket, string $rules)
    {
        $this->bucket = $bucket;
        $this->rules = $rules;
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

    public function getRules(): ?string
    {
        return $this->rules;
    }

    public function setRules(string $rules): static
    {
        $this->rules = $rules;

        return $this;
    }
}
