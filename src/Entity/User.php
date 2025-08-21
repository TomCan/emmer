<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, AccessKey>
     */
    #[ORM\OneToMany(targetEntity: AccessKey::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $accessKeys;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    /**
     * @var Collection<int, Policy>
     */
    #[ORM\OneToMany(targetEntity: Policy::class, mappedBy: 'user')]
    private Collection $policies;

    public function __construct()
    {
        $this->accessKeys = new ArrayCollection();
        $this->policies = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, AccessKey>
     */
    public function getAccessKeys(): Collection
    {
        return $this->accessKeys;
    }

    public function addAccessKey(AccessKey $accessKey): static
    {
        if (!$this->accessKeys->contains($accessKey)) {
            $this->accessKeys->add($accessKey);
            $accessKey->setUser($this);
        }

        return $this;
    }

    public function removeAccessKey(AccessKey $accessKey): static
    {
        if ($this->accessKeys->removeElement($accessKey)) {
            // set the owning side to null (unless already changed)
            if ($accessKey->getUser() === $this) {
                $accessKey->setUser(null);
            }
        }

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
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
            $policy->setUser($this);
        }

        return $this;
    }

    public function removePolicy(Policy $policy): static
    {
        if ($this->policies->removeElement($policy)) {
            // set the owning side to null (unless already changed)
            if ($policy->getUser() === $this) {
                $policy->setUser(null);
            }
        }

        return $this;
    }

    public function getIdentifier(): string
    {
        return 'emr:user:'.$this->email;
    }
}
