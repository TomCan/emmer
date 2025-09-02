<?php

namespace App\Domain\List;

use App\Entity\Filepart;

class ObjectpartList
{
    private bool $truncated = false;
    /** @var Filepart[] */
    private array $fileparts = [];
    private int $nextMarker = 0;

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function setTruncated(bool $truncated): void
    {
        $this->truncated = $truncated;
    }

    /**
     * @return Filepart[]
     */
    public function getFileparts(): array
    {
        return $this->fileparts;
    }

    public function addFilepart(Filepart $filepart): void
    {
        $this->fileparts[] = $filepart;
    }

    public function getNextMarker(): int
    {
        return $this->nextMarker;
    }

    public function setNextMarker(int $nextMarker): void
    {
        $this->nextMarker = $nextMarker;
    }
}
