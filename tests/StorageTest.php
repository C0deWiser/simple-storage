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

    public function testFlushDropsEmptyMountPoint()
    {
        $model = new Model();

        $storage = Storage::make($model, $this->fs)->mute();

        // Start from a clean mount point: buckets first, then the default one.
        Storage::make($model, $this->fs, 'docs')->mute()->flush();
        $storage->flush();

        $storage->put('file contents', 'file.txt');

        $this->assertDirectoryExists(__DIR__.'/../storage/model/1');

        $storage->flush();

        $this->assertDirectoryDoesNotExist(__DIR__.'/../storage/model/1');
    }

    public function testFlushKeepsMountPointHoldingBuckets()
    {
        $model = new Model();

        $default = Storage::make($model, $this->fs)->mute();
        $docs = Storage::make($model, $this->fs, 'docs')->mute();

        $default->flush();
        $docs->flush();

        $default->put('file contents', 'file.txt');
        $docs->put('file contents', 'file.txt');

        $default->flush();

        // Mount point still holds the `docs` bucket.
        $this->assertDirectoryExists(__DIR__.'/../storage/model/1');
        $this->assertEquals(1, $docs->files()->count());

        $docs->flush();

        $this->assertEquals(0, $docs->files()->count());
        $this->assertDirectoryExists(__DIR__.'/../storage/model/1');
    }

    public function testStoringOverAnExistingFileTriggersAWarning(): void
    {
        $storage = Storage::make(new Model(), $this->fs)->mute();

        $storage->put('first', 'file.txt');

        $warnings = [];
        $replaced = [];

        set_error_handler(function (int $level, string $message) use (&$warnings, &$replaced, $storage) {
            $warnings[] = $message;
            // The new file is already in place when the warning fires.
            $replaced[] = $storage->files()->one('file.txt')?->get();

            return true;
        }, E_USER_WARNING);

        try {
            // A fresh name stays silent.
            $storage->put('fresh', 'fresh.txt');
            $storage->store(__DIR__.'/test.png');

            $this->assertEmpty($warnings);

            $storage->put('second', 'file.txt');
            $storage->store(__DIR__.'/test.png');
        } finally {
            restore_error_handler();
        }

        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('model/1/file.txt', $warnings[0]);
        $this->assertStringContainsString('model/1/test.png', $warnings[1]);
        $this->assertSame(['second', 'second'], $replaced);
        $this->assertEquals('second', $storage->files()->one('file.txt')?->get());
        $this->assertEquals(3, $storage->files()->count());

        $storage->flush();
    }

    public function testStoreCopiesAFileFromAnotherDisk(): void
    {
        $root = __DIR__.'/../storage/cloud';

        // A cloud disk offers no local path, so the copy must not rely on it.
        $adapter = new LocalFilesystemAdapter($root);
        $cloud = new class(new Filesystem($adapter), $adapter, ['root' => $root]) extends FilesystemAdapter {
            public function path($path)
            {
                return 's3://bucket/'.$path;
            }
        };

        $cloud->put('remote/test.png', 'cloud contents');

        $storage = Storage::make(new Model(), $this->fs)->mute();

        $copy = $storage->store(new File($cloud, 'remote/test.png'));

        $this->assertInstanceOf(File::class, $copy);
        $this->assertEquals('cloud contents', $copy->get());

        $storage->flush();

        $cloud->deleteDirectory('remote');
        (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($root);
    }

    public function testStoreRejectsAMissingLocalPath(): void
    {
        $storage = Storage::make(new Model(), $this->fs)->mute();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $storage->store(__DIR__.'/missing.png');
    }

    public function testStoreFlattensListsAndSkipsEmptyItems(): void
    {
        $storage = Storage::make(new Model(), $this->fs)->mute();

        $files = $storage->store([
            null,
            __DIR__.'/test.png',
            new FileCollection([__DIR__.'/test2.png']),
        ]);

        $this->assertInstanceOf(FileCollection::class, $files);
        $this->assertCount(2, $files);
        $this->assertCount(2, $storage->files());

        $storage->flush();
    }

    public function testSingularTakesTheFirstOfANestedList(): void
    {
        $storage = Storage::make(new Model(), $this->fs)->mute()->singular();

        $file = $storage->store([
            [__DIR__.'/test.png', __DIR__.'/test2.png'],
        ]);

        $this->assertInstanceOf(File::class, $file);
        $this->assertEquals(1, $storage->files()->count());

        $storage->flush();
    }

    public function testSortedFileListsStillEncodeAsJsonArrays(): void
    {
        $storage = Storage::make(new Model(), $this->fs)->mute();
        $storage->flush();

        $storage->put('new', 'new.txt');
        $storage->put('old', 'old.txt');

        touch(__DIR__.'/../storage/model/1/old.txt', time() - 60);

        // The listing order and the modification order disagree, so sorting
        // reorders the items.
        $files = FileCollection::hydrate($this->fs, ['model/1/old.txt', 'model/1/new.txt'])->latest();

        $this->assertSame(['model/1/new.txt', 'model/1/old.txt'], $files->pluck('path')->all());
        $this->assertSame([0, 1], array_keys($files->toArray()));

        $this->assertSame([0, 1], array_keys($storage->files()->toArray()));
        $this->assertStringStartsWith('[', json_encode($storage->toArray()));

        $storage->flush();
    }

    public function testStorageRejectsFilenamesOutsideTheMountPoint(): void
    {
        $storage = Storage::make(new Model(), $this->fs)->mute();
        $storage->flush();

        foreach (['', '.', '..', 'sub/nested.txt', 'sub\\nested.txt', "bad\0name.txt"] as $filename) {
            try {
                $storage->put('payload', $filename);

                $this->fail(sprintf('Filename %s should have been rejected.', var_export($filename, true)));
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('plain, non-empty name', $exception->getMessage());
            }
        }

        // A rejected name never reaches the disk: the mount point is not
        // turned into a file of its own.
        $this->assertFileDoesNotExist(__DIR__.'/../storage/model/1');

        // store() resolves a path ending in `..` to a filename of `..`.
        try {
            $storage->store(__DIR__.'/..');

            $this->fail('A filename of `..` should have been rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('plain, non-empty name', $exception->getMessage());
        }

        // The storage keeps accepting plain names.
        $storage->put('payload', 'kept.txt');

        $this->assertSame('payload', $storage->files()->one('kept.txt')?->get());

        $storage->flush();
    }

    public function testPutKeepsContentThatLooksEmpty(): void
    {
        $storage = Storage::make(new Model(), $this->fs)->mute();
        $storage->flush();

        $this->assertNull($storage->put(null, 'null.txt'));
        $this->assertNull($storage->put(false, 'false.txt'));

        $this->assertSame('0', $storage->put('0', 'zero.txt')?->get());
        $this->assertSame('', $storage->put('', 'empty.txt')?->get());

        $this->assertSame(2, $storage->files()->count());

        // The same rule applies to sources: nothing to store stays null,
        // while '0' is looked up as a path.
        $this->assertNull($storage->store(''));

        try {
            $storage->store('0');

            $this->fail('A source of `0` should have been looked up.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('Local file "0"', $exception->getMessage());
        }

        $storage->flush();
    }

    public function testFilesAreFoundByTheirExactName(): void
    {
        $storage = Storage::make(new Model(), $this->fs)->mute();
        $storage->flush();

        $storage->put('zero', '01');

        // `1` must not resolve to `01`, even though the two are loosely equal.
        $this->assertNull($storage->files()->one('1'));

        $storage->put('one', '1');

        $this->assertSame('zero', $storage->files()->one('01')?->get());
        $this->assertSame('one', $storage->files()->one('1')?->get());

        $storage->flush();
    }

    public function testFileTypeGivesTheTopLevelOnly(): void
    {
        $storage = Storage::make(new Model(), $this->fs)->mute();

        $file = $storage->store(__DIR__.'/test.png');

        $this->assertSame('image/png', $file?->mimeType());
        $this->assertSame('image', $file?->type());
        $this->assertSame($file?->type(), $file?->mime());

        $storage->flush();
    }
}
