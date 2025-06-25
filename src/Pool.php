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

    public function getBucket(string|\UnitEnum $name = null): StorageContract
    {
        if ($name instanceof \BackedEnum) {
            $name = $name->value;
        }
        if ($name instanceof \UnitEnum) {
            $name = $name->name;
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
     * @return array<int, array{bucket:null|string, file:null|array, files:null|array}>
     */
    public function toArray(): array
    {
        return $this->buckets
            ->map(
                fn(StorageContract $bucket) => [
                    'bucket'      => $bucket->name(),
                    $bucket instanceof Singular ? 'file' : 'files' => $bucket->toArray()
                ]
            )
            ->values()
            ->toArray();
    }
}