<?php

namespace App\Domain\Lifecycle;

class ParsedLifecycleRule
{
    private ?string $id = null;
    private ?string $status = null;
    private ?int $abortIncompleteMultipartUploadDays = null;
    private ?\DateTime $expirationDate = null;
    private ?int $expirationDays = null;
    private ?bool $expiredObjectDeleteMarker = null;
    private ?int $noncurrentVersionExpirationDays = null;
    private ?int $noncurrentVersionNewerVersions = null;
    /**
     * @var array<array{'NewerNoncurrentVersions'?: int, 'NoncurrentDays'?: int, 'StorageClass'?: string}>|null
     */
    private ?array $noncurrentVersionTransitions = null;
    /**
     * @var array<array{'Date'?: \DateTime, 'Days'?: int, 'StorageClass'?: string}>|null
     */
    private ?array $transitions = null;
    private ?string $filterPrefix = null;
    private ?int $filterSizeGreaterThan = null;
    private ?int $filterSizeLessThan = null;
    /**
     * @var array{'key': string, 'value': string}|null
     */
    private ?array $filterTag = null;
    private ?string $filterAndPrefix = null;
    private ?int $filterAndSizeGreaterThan = null;
    private ?int $filterAndSizeLessThan = null;
    /**
     * @var array<array{'key': string, 'value': string}>|null
     */
    private ?array $filterAndTags = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getAbortIncompleteMultipartUploadDays(): ?int
    {
        return $this->abortIncompleteMultipartUploadDays;
    }

    public function setAbortIncompleteMultipartUploadDays(?int $abortIncompleteMultipartUploadDays): void
    {
        $this->abortIncompleteMultipartUploadDays = $abortIncompleteMultipartUploadDays;
    }

    public function getExpirationDate(): ?\DateTime
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTime $expirationDate): void
    {
        $this->expirationDate = $expirationDate;
    }

    public function getExpirationDays(): ?int
    {
        return $this->expirationDays;
    }

    public function setExpirationDays(?int $expirationDays): void
    {
        $this->expirationDays = $expirationDays;
    }

    public function getExpiredObjectDeleteMarker(): ?bool
    {
        return $this->expiredObjectDeleteMarker;
    }

    public function setExpiredObjectDeleteMarker(?bool $expiredObjectDeleteMarker): void
    {
        $this->expiredObjectDeleteMarker = $expiredObjectDeleteMarker;
    }

    public function getNoncurrentVersionExpirationDays(): ?int
    {
        return $this->noncurrentVersionExpirationDays;
    }

    public function setNoncurrentVersionExpirationDays(?int $noncurrentVersionExpirationDays): void
    {
        $this->noncurrentVersionExpirationDays = $noncurrentVersionExpirationDays;
    }

    public function getNoncurrentVersionNewerVersions(): ?int
    {
        return $this->noncurrentVersionNewerVersions;
    }

    public function setNoncurrentVersionNewerVersions(?int $noncurrentVersionNewerVersions): void
    {
        $this->noncurrentVersionNewerVersions = $noncurrentVersionNewerVersions;
    }

    /**
     * @return array<array{'NewerNoncurrentVersions'?: int, 'NoncurrentDays'?: int, 'StorageClass'?: string}>|null
     */
    public function getNoncurrentVersionTransitions(): ?array
    {
        return $this->noncurrentVersionTransitions;
    }

    /**
     * @param array<array{'NewerNoncurrentVersions'?: int, 'NoncurrentDays'?: int, 'StorageClass'?: string}>|null $noncurrentVersionTransitions
     */
    public function setNoncurrentVersionTransitions(?array $noncurrentVersionTransitions): void
    {
        $this->noncurrentVersionTransitions = $noncurrentVersionTransitions;
    }

    /**
     * @return array<array{'Date'?: \DateTime, 'Days'?: int, 'StorageClass'?: string}>|null
     */
    public function getTransitions(): ?array
    {
        return $this->transitions;
    }

    /**
     * @param array<array{'Date'?: \DateTime, 'Days'?: int, 'StorageClass'?: string}>|null $transitions
     */
    public function setTransitions(?array $transitions): void
    {
        $this->transitions = $transitions;
    }

    public function getFilterPrefix(): ?string
    {
        return $this->filterPrefix;
    }

    public function setFilterPrefix(?string $filterPrefix): void
    {
        $this->filterPrefix = $filterPrefix;
    }

    public function getFilterSizeGreaterThan(): ?int
    {
        return $this->filterSizeGreaterThan;
    }

    public function setFilterSizeGreaterThan(?int $filterSizeGreaterThan): void
    {
        $this->filterSizeGreaterThan = $filterSizeGreaterThan;
    }

    public function getFilterSizeLessThan(): ?int
    {
        return $this->filterSizeLessThan;
    }

    public function setFilterSizeLessThan(?int $filterSizeLessThan): void
    {
        $this->filterSizeLessThan = $filterSizeLessThan;
    }

    /**
     * @return array{'key': string, 'value': string}|null
     */
    public function getFilterTag(): ?array
    {
        return $this->filterTag;
    }

    /**
     * @param array{'key': string, 'value': string}|null $filterTag
     */
    public function setFilterTag(?array $filterTag): void
    {
        $this->filterTag = $filterTag;
    }

    public function getFilterAndPrefix(): ?string
    {
        return $this->filterAndPrefix;
    }

    public function setFilterAndPrefix(?string $filterAndPrefix): void
    {
        $this->filterAndPrefix = $filterAndPrefix;
    }

    public function getFilterAndSizeGreaterThan(): ?int
    {
        return $this->filterAndSizeGreaterThan;
    }

    public function setFilterAndSizeGreaterThan(?int $filterAndSizeGreaterThan): void
    {
        $this->filterAndSizeGreaterThan = $filterAndSizeGreaterThan;
    }

    public function getFilterAndSizeLessThan(): ?int
    {
        return $this->filterAndSizeLessThan;
    }

    public function setFilterAndSizeLessThan(?int $filterAndSizeLessThan): void
    {
        $this->filterAndSizeLessThan = $filterAndSizeLessThan;
    }

    /**
     * @return array<array{'key': string, 'value': string}>|null
     */
    public function getFilterAndTags(): ?array
    {
        return $this->filterAndTags;
    }

    /**
     * @param array<array{'key': string, 'value': string}>|null $filterAndTags
     */
    public function setFilterAndTags(?array $filterAndTags): void
    {
        $this->filterAndTags = $filterAndTags;
    }

    public function hasFilter(): bool
    {
        return null !== $this->filterPrefix
            || null !== $this->filterSizeGreaterThan
            || null !== $this->filterSizeLessThan
            || null !== $this->filterTag
            || $this->hasAnd();
    }

    public function hasAnd(): bool
    {
        return null !== $this->filterAndPrefix
                || null !== $this->filterAndSizeGreaterThan
                || null !== $this->filterAndSizeLessThan
                || null !== $this->filterAndTags;
    }
}
