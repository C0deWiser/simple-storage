<?php

namespace Codewiser\Storage;

interface StorageContract extends \Illuminate\Contracts\Support\Arrayable
{
    /**
     * Switch bucket on-a-fly.
     */
    public function bucket(string $bucket): static;

    /**
     * Get storage name (aka bucket).
     */
    public function name(): ?string;

    /**
     * Switch disk on-a-fly.
     */
    public function onDisk(string|\Illuminate\Contracts\Filesystem\Filesystem $disk): static;

    /**
     * Get storage filesystem.
     */
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem;

    /**
     * Get storage owner.
     */
    public function owner(): \Illuminate\Database\Eloquent\Model&Attachmentable;

    /**
     * Get storage mount point (relative to a disk).
     */
    public function path(): string;

    /**
     * Unlink file(s) with given keys.
     *
     * @param  string|string[]  $keys
     */
    public function delete(string|array $keys): static;

    /**
     * Upload a new file(s).
     *
     * @param  \Psr\Http\Message\StreamInterface|\Illuminate\Http\File|\Illuminate\Http\UploadedFile|string|resource|array  $content
     */
    public function upload(mixed $content): null|File|FileCollection;

    /**
     * Put single file to a storage with a given name.
     */
    public function put(mixed $content, string $filename): null|File;

    /**
     * Remove all files.
     */
    public function flush(): void;

    /**
     * Get all files.
     */
    public function files(): FileCollection;
}