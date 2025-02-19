<?php

namespace Tests;

use Codewiser\Storage\File;
use Codewiser\Storage\FileCollection;
use Codewiser\Storage\Pool;
use Codewiser\Storage\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    protected FilesystemAdapter $fs;

    protected function setUp(): void
    {
        parent::setUp();

        $root = __DIR__.'/../storage';
        $adapter = new LocalFilesystemAdapter($root);
        $filesystem = new Filesystem($adapter);
        $this->fs = new FilesystemAdapter($filesystem, $adapter, ['root' => $root]);
    }

    public function testOne()
    {
        $model = new Model();

        $pool = Pool::make()->addBucket(Storage::make($model, $this->fs)->mute()->singular());

        $file = $pool->getBucket()->upload([
            __DIR__ . '/test.png',
            __DIR__ . '/test2.png'
        ]);

        $this->assertTrue($file instanceof File);

        $this->assertEquals(1, $pool->getBucket()->files()->count());

        $pool->getBucket()->flush();

        $this->assertEquals(0, $pool->getBucket()->files()->count());
    }

    public function testMany()
    {
        $model = new Model();

        $pool = Pool::make()->addBucket(Storage::make($model, $this->fs)->mute());

        $files = $pool->getBucket()->upload([
            __DIR__ . '/test.png',
            __DIR__ . '/test2.png'
        ]);

        $this->assertTrue($files instanceof FileCollection);

        $this->assertEquals(2, $pool->getBucket()->files()->count());

        $pool->getBucket()->flush();

        $this->assertEquals(0, $pool->getBucket()->files()->count());
    }

    public function testPool()
    {
        $model = new Model();

        $pool = Pool::make()
            ->addBucket(Storage::make($model, $this->fs)->mute()->singular())
            ->addBucket(Storage::make($model, $this->fs, 'docs')->mute());

        $pool->getBucket()->upload(__DIR__ . '/test.png');
        $pool->getBucket('docs')->upload([
            __DIR__ . '/test.png',
            __DIR__ . '/test2.png'
        ]);

        $this->assertEquals(1, $pool->getBucket()->files()->count());
        $this->assertEquals(2, $pool->getBucket('docs')->files()->count());

        $this->assertNull($pool->toArray()[0]['bucket']);
        $this->assertEquals('docs', $pool->toArray()[1]['bucket']);
        $this->assertArrayHasKey('file', $pool->toArray()[0]);
        $this->assertArrayHasKey('files', $pool->toArray()[1]);
        $this->assertCount(2, $pool->toArray()[1]['files']);

        $pool->getBucket()->flush();
        $pool->getBucket('docs')->flush();

        $this->assertEquals(0, $pool->getBucket()->files()->count());
        $this->assertEquals(0, $pool->getBucket('docs')->files()->count());
    }
}
