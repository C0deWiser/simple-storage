<?php

namespace Codewiser\Storage;

class Pool
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
        $this->buckets->put($bucket->name() ?? '0', $bucket);

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
}