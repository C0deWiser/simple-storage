<?php

namespace Codewiser\Storage;

interface StorageContract extends \Illuminate\Contracts\Support\Arrayable
{
    /**
     * Switch bucket on-the-fly.
     */
    public function bucket(string|\BackedEnum $bucket): static;

    /**
     * Get storage scalar name (aka bucket).
     */
    public function name(): ?string;

    /**
     * Switch disk on-the-fly.
     */
    public function onDisk(string|\Illuminate\Contracts\Filesystem\Filesystem $disk): static;

    /**
     * Get storage filesystem.
     */
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem;

    /**
     * Get a storage owner.
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
     * Alias for store method.
     *
     * @param  File|FileCollection|\Symfony\Component\HttpFoundation\File\UploadedFile|array|string  $content  A stored file, an upload, a local path, a remote URL, or a list of them.
     *
     * @deprecated use store()
     */
    public function upload(mixed $content): null|File|FileCollection;

    /**
     * Upload a new file(s).
     *
     * @param  File|FileCollection|\Symfony\Component\HttpFoundation\File\UploadedFile|array|string  $content  A stored file, an upload, a local path, a remote URL, or a list of them.
     *
     * @throws \InvalidArgumentException  When the content cannot be stored.
     * @throws \LogicException  When the owner model is not persisted yet.
     */
    public function store(mixed $content): null|File|FileCollection;

    /**
     * Put a single file to a storage with a given name.
     *
     * @throws \LogicException  When the owner model is not persisted yet.
     */
    public function put(mixed $content, string $filename): ?File;

    /**
     * Remove all files.
     */
    public function flush(): void;

    /**
     * Get all files.
     */
    public function files(): FileCollection;

    public function isEmpty(): bool;

    public function isNotEmpty(): bool;
}