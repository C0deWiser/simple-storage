<?php

namespace Codewiser\Storage;

use BackedEnum;
use Illuminate\Support\Collection;

class Pool
{
    /**
     * @var Collection<string,StorageContract>
     */
    protected Collection $buckets;

    public static function make(): static
    {
        return new static();
    }

    public function __construct()
    {
        $this->buckets = collect();
    }

    public function addBucket(StorageContract $bucket): static
    {
        $this->buckets->put($bucket->name() ?? '0', $bucket);

        return $this;
    }

    public function getBucket(string|BackedEnum $name = null): StorageContract
    {
        if ($name instanceof BackedEnum) {
            $name = $name->value;
        }

        return $this->buckets->sole(fn(StorageContract $bucket) => $bucket->name() === $name);
    }

    public function getBuckets(): Collection
    {
        return $this->buckets;
    }
}