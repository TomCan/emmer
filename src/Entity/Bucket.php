<?php

namespace App\Entity;

use App\Repository\BucketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BucketRepository::class)]
#[ORM\UniqueConstraint(name: 'bucket_name_idx', columns: ['name'])]
class Bucket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 1024)]
    private ?string $path = null;

    /**
     * @var Collection<int, Policy>
     */
    #[ORM\OneToMany(targetEntity: Policy::class, mappedBy: 'bucket', cascade: ['remove'], orphanRemoval: true)]
    private Collection $policies;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column]
    private ?\DateTime $ctime = null;

    #[ORM\Column]
    private bool $versioned = false;

    /**
     * @var Collection<int, LifecycleRules>
     */
    #[ORM\OneToMany(targetEntity: LifecycleRules::class, mappedBy: 'bucket', orphanRemoval: true)]
    private Collection $lifecycleRules;

    /**
     * @var Collection<int, CorsRule>
     */
    #[ORM\OneToMany(targetEntity: CorsRule::class, mappedBy: 'bucket', orphanRemoval: true)]
    private Collection $corsRules;

    public function __construct()
    {
        $this->policies = new ArrayCollection();
        $this->ctime = new \DateTime();
        $this->lifecycleRules = new ArrayCollection();
        $this->corsRules = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    /**
     * @return Collection<int, Policy>
     */
    public function getPolicies(): Collection
    {
        return $this->policies;
    }

    public function addPolicy(Policy $policy): static
    {
        if (!$this->policies->contains($policy)) {
            $this->policies->add($policy);
            $policy->setBucket($this);
        }

        return $this;
    }

    public function removePolicy(Policy $policy): static
    {
        if ($this->policies->removeElement($policy)) {
            // set the owning side to null (unless already changed)
            if ($policy->getBucket() === $this) {
                $policy->setBucket(null);
            }
        }

        return $this;
    }

    public function getIdentifier(): string
    {
        return 'emr:bucket:'.$this->name;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getCtime(): ?\DateTime
    {
        return $this->ctime;
    }

    public function setCtime(\DateTime $ctime): static
    {
        $this->ctime = $ctime;

        return $this;
    }

    public function isVersioned(): bool
    {
        return $this->versioned;
    }

    public function setVersioned(bool $versioned): void
    {
        $this->versioned = $versioned;
    }

    /**
     * @return Collection<int, LifecycleRules>
     */
    public function getLifecycleRules(): Collection
    {
        return $this->lifecycleRules;
    }

    public function addLifecycleRule(LifecycleRules $lifecycleRule): static
    {
        if (!$this->lifecycleRules->contains($lifecycleRule)) {
            $this->lifecycleRules->add($lifecycleRule);
            $lifecycleRule->setBucket($this);
        }

        return $this;
    }

    public function removeLifecycleRule(LifecycleRules $lifecycleRule): static
    {
        if ($this->lifecycleRules->removeElement($lifecycleRule)) {
            // set the owning side to null (unless already changed)
            if ($lifecycleRule->getBucket() === $this) {
                $lifecycleRule->setBucket(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CorsRule>
     */
    public function getCorsRules(): Collection
    {
        return $this->corsRules;
    }

    public function addCorsRule(CorsRule $corsRule): static
    {
        if (!$this->corsRules->contains($corsRule)) {
            $this->corsRules->add($corsRule);
            $corsRule->setBucket($this);
        }

        return $this;
    }

    public function removeCorsRule(CorsRule $corsRule): static
    {
        if ($this->corsRules->removeElement($corsRule)) {
            // set the owning side to null (unless already changed)
            if ($corsRule->getBucket() === $this) {
                $corsRule->setBucket(null);
            }
        }

        return $this;
    }
}
