<?php

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Index(name: 'bucket_name_version_idx', columns: ['bucket_id', 'name', 'version'])]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'binary_text', length: 1024)]
    private ?string $name = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $size = null;

    #[ORM\Column]
    private ?\DateTime $ctime = null;

    #[ORM\Column]
    private ?\DateTime $mtime = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $nctime = null;

    #[ORM\Column]
    private ?string $etag = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bucket $bucket = null;

    /** @var Collection<int, Filepart> */
    #[ORM\OneToMany(targetEntity: Filepart::class, mappedBy: 'file', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $fileparts;

    #[ORM\Column(nullable: true)]
    private ?string $version = null;

    #[ORM\Column]
    private bool $currentVersion = false;

    #[ORM\Column]
    private int $newerNoncurrentVersions = 0;

    #[ORM\Column(length: 255)]
    private ?string $contentType = null;

    #[ORM\Column(nullable: true)]
    private ?string $multipartUploadId = null;

    #[ORM\Column]
    private bool $deleteMarker = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $encryptionKey = null;
    // no field definition for $decryptedKey, not stored in database
    private ?string $decryptedKey = null;

    public function __construct(Bucket $bucket, string $name, ?string $version = null, string $contentType = '', int $size = 0, ?\DateTime $ctime = new \DateTime(), string $etag = '')
    {
        $this->fileparts = new ArrayCollection();

        $this->bucket = $bucket;
        $this->name = $name;
        $this->version = $version;
        $this->contentType = $contentType;
        $this->size = $size;
        $this->ctime = $ctime;
        $this->mtime = $ctime;
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

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

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

    public function getMtime(): ?\DateTime
    {
        return $this->mtime;
    }

    public function setMtime(\DateTime $mtime): static
    {
        $this->mtime = $mtime;

        return $this;
    }

    public function getNctime(): ?\DateTime
    {
        return $this->nctime;
    }

    public function setNctime(\DateTime $nctime): static
    {
        $this->nctime = $nctime;

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

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function isCurrentVersion(): bool
    {
        return $this->currentVersion;
    }

    public function setCurrentVersion(bool $currentVersion): void
    {
        $this->currentVersion = $currentVersion;
    }

    public function getNewerNoncurrentVersions(): int
    {
        return $this->newerNoncurrentVersions;
    }

    public function setNewerNoncurrentVersions(int $newerNoncurrentVersions): void
    {
        $this->newerNoncurrentVersions = $newerNoncurrentVersions;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): static
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getMultipartUploadId(): ?string
    {
        return $this->multipartUploadId;
    }

    public function setMultipartUploadId(?string $multipartUploadId): void
    {
        $this->multipartUploadId = $multipartUploadId;
    }

    public function isDeleteMarker(): bool
    {
        return $this->deleteMarker;
    }

    public function setDeleteMarker(bool $deleteMarker): static
    {
        $this->deleteMarker = $deleteMarker;

        return $this;
    }

    public function getEncryptionKey(): ?string
    {
        return $this->encryptionKey;
    }

    public function setEncryptionKey(?string $encryptionKey): void
    {
        $this->encryptionKey = $encryptionKey;
    }

    public function getDecryptedKey(): ?string
    {
        return $this->decryptedKey;
    }

    public function setDecryptedKey(?string $decryptedKey): void
    {
        $this->decryptedKey = $decryptedKey;
    }
}
