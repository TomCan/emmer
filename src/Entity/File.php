<?php

namespace App\Entity;

use App\Repository\FileRepository;
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

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column]
    private ?int $size = null;

    #[ORM\Column]
    private ?\DateTime $mtime = null;

    #[ORM\Column]
    private ?string $etag = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bucket $bucket = null;

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

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

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
}
