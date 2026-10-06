<?php

namespace Codewiser\Storage;

use Illuminate\Contracts\Support\Arrayable;

class Pool implements Arrayable
{
    protected \Illuminate\Support\Collection $buckets;

    public static function make(): static
    {
        return new static();
    }

    public function __construct()
    {
        $this->buckets = new \Illuminate\Support\Collection();
    }

    public function addBucket(StorageContract $bucket): static
    {
        $this->buckets->add($bucket);

        return $this;
    }

    /**
     * @param null|string|\BackedEnum $name
     *
     * @return StorageContract
     */
    public function getBucket($name = null): StorageContract
    {
        if ($name instanceof \BackedEnum) {
            $name = $name->value;
        }

        return $this->buckets->sole(fn(StorageContract $bucket) => $bucket->name() === $name);
    }

    /**
     * @return \Illuminate\Support\Collection<array-key, SingularContract>
     */
    public function getBuckets(): \Illuminate\Support\Collection
    {
        return $this->buckets;
    }

    /**
     * Get an array of buckets with their files.
     *
     * A singular bucket provides a `file` entry, any other bucket provides a
     * `files` entry.
     *
     * @return array<int, array{bucket: null|string, file?: array, files?: array}>
     */
    public function toArray(): array
    {
        return $this->buckets
            ->map(
                fn(StorageContract $bucket) => [
                    'bucket'      => $bucket->name(),
                    $bucket instanceof SingularContract ? 'file' : 'files' => $bucket->toArray()
                ]
            )
            ->values()
            ->toArray();
    }
}