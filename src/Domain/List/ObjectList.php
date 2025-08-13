<?php

namespace App\Domain\List;

use App\Entity\File;

class ObjectList
{
    private bool $truncated = false;
    private array $files = [];
    private array $commonPrefixes = [];
    private string $nextMarker = '';

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function setTruncated(bool $truncated): void
    {
        $this->truncated = $truncated;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function addFile(File $file): void
    {
        $this->files[] = $file;
    }

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
}
