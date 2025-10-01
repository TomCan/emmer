<?php

namespace App\Entity;

use App\Repository\FilepartRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FilepartRepository::class)]
#[ORM\UniqueConstraint(name: 'filepart_name_idx', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'filepart_idx', columns: ['file_id', 'partnumber'])]
class Filepart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'binary', length: 1024)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $size = null;

    #[ORM\Column]
    private ?\DateTime $mtime = null;

    #[ORM\Column]
    private ?string $etag = null;

    #[ORM\ManyToOne(inversedBy: 'fileparts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?File $file = null;

    #[ORM\Column(name: 'partnumber')]
    private ?int $partNumber = null;

    public function __construct(File $file, int $partNumber, string $name, string $path, int $size = 0, \DateTime $mtime = new \DateTime(), string $etag = '')
    {
        $this->file = $file;
        $this->partNumber = $partNumber;
        $this->name = $name;
        $this->path = $path;
        $this->size = $size;
        $this->mtime = $mtime;
        $this->etag = $etag;
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

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getPartNumber(): ?int
    {
        return $this->partNumber;
    }

    public function setPartNumber(?int $partNumber): void
    {
        $this->partNumber = $partNumber;
    }
}
