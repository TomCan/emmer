<?php

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\UniqueConstraint(name: "name_idx", columns: ['name'])]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 1024)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $size = null;

    #[ORM\Column]
    private ?\DateTime $mtime = null;

    #[ORM\Column]
    private ?string $etag = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bucket $bucket = null;

    #[ORM\OneToMany(targetEntity: Filepart::class, mappedBy: 'file', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $fileparts;

    public function __construct()
    {
        $this->fileparts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getMtime(): ?\DateTime
    {
        return $this->mtime;
    }

    public function setMtime(\DateTime $mtime): static
    {
        $this->mtime = $mtime;

        return $this;
    }

    public function getEtag(): ?string
    {
        return $this->etag;
    }

    public function setEtag(?string $etag): void
    {
        $this->etag = $etag;
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

    /**
     * @return Collection<int, Filepart>
     */
    public function getFileparts(): Collection
    {
        return $this->fileparts;
    }

    public function addFilepart(Filepart $filepart): static
    {
        if (!$this->fileparts->contains($filepart)) {
            $this->fileparts->add($filepart);
            $filepart->setFile($this);
        }

        return $this;
    }

    public function removeFilepart(Filepart $filepart): static
    {
        if ($this->fileparts->removeElement($filepart)) {
            // set the owning side to null (unless already changed)
            if ($filepart->getFile() === $this) {
                $filepart->setFile(null);
            }
        }

        return $this;
    }
}
