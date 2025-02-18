<?php

namespace Tests;

use BackedEnum;
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Pool;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class Model extends \Illuminate\Database\Eloquent\Model implements Attachmentable
{
    public function getKey(): int
    {
        return 1;
    }

    public function getMorphClass(): string
    {
        return 'model';
    }

    public function storage(string|BackedEnum $bucket = null): StorageContract
    {
        $root = __DIR__.'/../storage';
        $adapter = new LocalFilesystemAdapter($root);
        $filesystem = new Filesystem($adapter);
        $fs = new FilesystemAdapter($filesystem, $adapter, ['root' => $root]);

        return Pool::make()
            ->addBucket(Storage::make($this, $fs)->mute()->singular())
            ->addBucket(Storage::make($this, $fs, 'docs')->mute())
            ->getBucket($bucket);
    }
}