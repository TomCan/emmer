<?php

namespace App\Domain\List;

use App\Entity\Bucket;

class BucketList
{
    private bool $truncated = false;
    /** @var Bucket[] */
    private array $buckets = [];
    private string $nextMarker = '';

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function setTruncated(bool $truncated): void
    {
        $this->truncated = $truncated;
    }

    /**
     * @return Bucket[]
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }

    public function addBucket(Bucket $bucket): void
    {
        $this->buckets[] = $bucket;
    }

    public function getNextMarker(): string
    {
        return $this->nextMarker;
    }

    public function setNextMarker(string $nextMarker): void
    {
        $this->nextMarker = $nextMarker;
    }
}
