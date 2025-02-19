<?php

namespace Codewiser\Storage;

use Illuminate\Contracts\Support\Arrayable;

class Pool implements Arrayable
{
    /**
     * @var \Illuminate\Support\Collection<string,SingularContract>
     */
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

    public function getBucket(string|\BackedEnum $name = null): StorageContract
    {
        if ($name instanceof \BackedEnum) {
            $name = $name->value;
        }

        return $this->buckets->sole(fn(StorageContract $bucket) => $bucket->name() === $name);
    }

    public function getBuckets(): \Illuminate\Support\Collection
    {
        return $this->buckets;
    }

    /**
     * Get array of buckets with their files.
     *
     * @return array<int,array{"bucket":null|string, "file":null|array, "files":null|array}>
     */
    public function toArray(): array
    {
        return $this->buckets
            ->map(
                fn(StorageContract $bucket) => [
                    'bucket'      => $bucket->name(),
                    $bucket instanceof Singular
                        ? 'file'
                        : 'files' => $bucket->toArray()
                ]
            )
            ->values()
            ->toArray();
    }
}