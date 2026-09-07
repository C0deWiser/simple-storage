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
            ->addBucket(Storage::make($model, $this->fs, Bucket::docs)->mute());

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

    public function testStoreAcceptsFileCollection()
    {
        $model = new Model();

        $storage = Storage::make($model, $this->fs)->mute();

        $files = $storage->store(new FileCollection([
            __DIR__ . '/test.png',
            __DIR__ . '/test2.png'
        ]));

        $this->assertTrue($files instanceof FileCollection);
        $this->assertEquals(2, $files->count());
        $this->assertEquals(2, $storage->files()->count());

        $storage->flush();
    }

    public function testSingularReplacesPrevious()
    {
        $model = new Model();

        $storage = Storage::make($model, $this->fs)->mute()->singular();

        $storage->store(__DIR__ . '/test.png');
        $storage->store(__DIR__ . '/test2.png');

        $this->assertEquals(1, $storage->files()->count());

        $storage->flush();
    }

    public function testPutStoresToMount()
    {
        $model = new Model();

        $storage = Storage::make($model, $this->fs)->mute();

        $file = $storage->put('file contents', 'file.txt');

        $this->assertInstanceOf(File::class, $file);
        $this->assertStringStartsWith('model/1/', $file->path);
        $this->assertTrue($this->fs->fileExists($file->path));

        $storage->flush();
    }

    public function testDeleteByPath()
    {
        $model = new Model();

        $storage = Storage::make($model, $this->fs)->mute();

        $files = $storage->store([
            __DIR__ . '/test.png',
            __DIR__ . '/test2.png'
        ]);

        $storage->delete($files->first()->path);

        $this->assertEquals(1, $storage->files()->count());

        $storage->flush();
    }
}
