<?php

namespace Codewiser\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;

interface StorageContract extends Arrayable
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
     * Get storage owner.
     */
    public function owner(): Model;

    /**
     * Unlink file(s) with given keys.
     *
     * @param  string|string[]  $keys
     */
    public function delete(string|array $keys): static;

    /**
     * Upload a new file(s).
     *
     * @param  StreamInterface|\Illuminate\Http\File|UploadedFile|string|resource|array  $content
     */
    public function upload(mixed $content): null|File|FileCollection;

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