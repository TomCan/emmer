<?php

namespace App\Domain\List;

use App\Entity\File;

class ObjectList
{
    private bool $truncated = false;
    /** @var File[] */
    private array $files = [];
    /** @var string[] */
    private array $commonPrefixes = [];
    private string $nextMarker = '';
    private string $nextVersionMarker = '';

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function setTruncated(bool $truncated): void
    {
        $this->truncated = $truncated;
    }

    /**
     * @return File[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    public function addFile(File $file): void
    {
        $this->files[] = $file;
    }

    /**
     * @return string[]
     */
    public function getCommonPrefixes(): array
    {
        return $this->commonPrefixes;
    }

    public function addCommonPrefix(string $commonPrefix): void
    {
        $this->commonPrefixes[] = $commonPrefix;
    }

    public function hasCommonPrefix(string $commonPrefix): bool
    {
        return in_array($commonPrefix, $this->commonPrefixes);
    }

    public function getNextMarker(): string
    {
        return $this->nextMarker;
    }

    public function setNextMarker(string $nextMarker): void
    {
        $this->nextMarker = $nextMarker;
    }

    public function getNextVersionMarker(): string
    {
        return $this->nextVersionMarker;
    }

    public function setNextVersionMarker(string $nextVersionMarker): void
    {
        $this->nextVersionMarker = $nextVersionMarker;
    }
}
