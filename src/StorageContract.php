<?php

namespace Codewiser\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;

interface StorageContract
{
    /**
     * Get storage name (aka bucket).
     */
    public function name(): ?string;

    /**
     * Get storage filesystem.
     */
    public function disk(): Filesystem;

    /**
     * Unlink files with given keys.
     *
     * @param  string|string[]  $keys
     */
    public function delete(string|array $keys): static;

    /**
     * Upload a new file.
     *
     * @param  StreamInterface|\Illuminate\Http\File|UploadedFile|string|resource  $content
     */
    public function upload(mixed $content): ?File;

    /**
     * Remove all files.
     */
    public function flush(): void;

    /**
     * Get all files.
     */
    public function files(): FileCollection;

    /**
     * Get single file.
     */
    public function single(): ?File;
}