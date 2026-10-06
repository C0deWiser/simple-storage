<?php

namespace Tests;

use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Keeps test files inside the package `storage` directory.
 */
trait TestDisk
{
    protected function disk(): FilesystemAdapter
    {
        $root = __DIR__.'/../storage';
        $adapter = new LocalFilesystemAdapter($root);
        $filesystem = new Filesystem($adapter);

        return new FilesystemAdapter($filesystem, $adapter, ['root' => $root]);
    }
}
