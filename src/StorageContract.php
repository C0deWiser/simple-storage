<?php

namespace Codewiser\Storage;

interface StorageContract extends \Illuminate\Contracts\Support\Arrayable
{
    /**
     * Get storage name (aka bucket).
     */
    public function name(): ?string;

    /**
     * Get disk name.
     */
    public function disk(): string;

    /**
     * Get storage filesystem.
     */
    public function filesystem(): \Illuminate\Contracts\Filesystem\Filesystem;

    /**
     * Get storage owner.
     */
    public function owner(): \Illuminate\Database\Eloquent\Model&Attachmentable;

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
     * Remove all files.
     */
    public function flush(): void;

    /**
     * Get all files.
     */
    public function files(): FileCollection;
}