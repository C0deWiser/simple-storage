<?php

namespace Tests;

use Codewiser\Storage\AttachmentableObserver;
use Codewiser\Storage\FileWasStored;
use Codewiser\Storage\Storage;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class ObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = Container::getInstance();

        $capsule = new Capsule($container);
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->getConnection()->getSchemaBuilder()->create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
        });

        $dispatcher = new Dispatcher($container);

        $container->instance('events', $dispatcher);
        Model::setEventDispatcher($dispatcher);

        // Let every model re-register its observers on the fresh dispatcher.
        Model::clearBootedModels();

        $this->cleanStorage();
    }

    protected function tearDown(): void
    {
        $this->cleanStorage();

        parent::tearDown();
    }

    public function testOwnerDeleteFlushesEveryBucket(): void
    {
        $post = Post::create([]);

        $post->storage()->store(__DIR__.'/test.png');
        $post->storage(Bucket::docs)->store(__DIR__.'/test.png');

        $this->assertEquals(1, $post->storage()->files()->count());
        $this->assertEquals(1, $post->storage(Bucket::docs)->files()->count());

        $post->delete();

        $this->assertEquals(0, $post->storage()->files()->count());
        $this->assertEquals(0, $post->storage(Bucket::docs)->files()->count());

        // Buckets first, then the default bucket drops the emptied mount point.
        $this->assertDirectoryDoesNotExist(__DIR__.'/../storage/post/'.$post->getKey());
    }

    public function testOwnerWithoutDefaultBucketStillFlushesEnumBuckets(): void
    {
        $post = StrictPost::create([]);

        $post->storage(Bucket::docs)->store(__DIR__.'/test.png');

        $this->assertEquals(1, $post->storage(Bucket::docs)->files()->count());

        // `storage()` without arguments is not resolvable, but may not break it.
        $post->delete();

        $this->assertEquals(0, $post->storage(Bucket::docs)->files()->count());
    }

    public function testStringBucketsAreNotEnumerated(): void
    {
        $post = SoftPost::create([]);

        $post->storage()->store(__DIR__.'/test.png');
        $post->storage('docs')->store(__DIR__.'/test.png');

        $post->delete();
        $post->forceDelete();

        $this->assertEquals(0, $post->storage()->files()->count());

        // Plain string bucket is not declared as an enum case, so it survives.
        $this->assertEquals(1, $post->storage('docs')->files()->count());
    }

    public function testPureEnumBucketsAreNotEnumerated(): void
    {
        PurePost::$resolved = false;

        $post = PurePost::create([]);

        $post->storage()->store(__DIR__.'/test.png');

        $this->assertEquals(1, $post->storage()->files()->count());

        $post->delete();

        // A bucket without a backing value is never resolved...
        $this->assertFalse(PurePost::$resolved);

        // ...while the default bucket is flushed as usual.
        $this->assertEquals(0, $post->storage()->files()->count());
    }

    public function testOwnerWithoutBucketsIsFlushed(): void
    {
        $post = PlainPost::create([]);

        $post->storage()->store(__DIR__.'/test.png');

        $this->assertEquals(1, $post->storage()->files()->count());

        // The bucket argument is untyped, so there is nothing to enumerate.
        $post->delete();

        $this->assertEquals(0, $post->storage()->files()->count());
        $this->assertDirectoryDoesNotExist(__DIR__.'/../storage/plain_post/'.$post->getKey());
    }

    public function testSoftDeletedOwnerKeepsFilesUntilForced(): void
    {
        $post = SoftPost::create([]);

        $post->storage()->store(__DIR__.'/test.png');

        $post->delete();

        $this->assertEquals(1, $post->storage()->files()->count());

        $post->forceDelete();

        $this->assertEquals(0, $post->storage()->files()->count());
    }

    public function testUnsavedOwnerDoesNotPersistFiles(): void
    {
        $post = new Post();

        try {
            $post->storage()->store(__DIR__.'/test.png');

            $this->fail('Expected a LogicException for an unsaved owner');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('saved', $exception->getMessage());
        }

        try {
            $post->storage()->put('file contents', 'file.txt');

            $this->fail('Expected a LogicException for an unsaved owner');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('saved', $exception->getMessage());
        }

        try {
            $post->storage()->singular()->store(__DIR__.'/test.png');

            $this->fail('Expected a LogicException for an unsaved owner');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('saved', $exception->getMessage());
        }

        $this->assertEquals(0, $post->storage()->files()->count());

        // The shared `post/0` mount point was never created.
        $this->assertDirectoryDoesNotExist(__DIR__.'/../storage/post/0');
    }

    public function testBucket()
    {
        $post = new Post();

        $this->assertEquals('docs', $post->storage(Bucket::docs)->name());
    }

    public function testObserverIgnoresAnUnsavedOwner(): void
    {
        $post = new Post();

        (new AttachmentableObserver())->deleted($post);

        $this->assertEquals(0, $post->storage()->files()->count());
    }

    public function testStoredFileEventCarriesFullPath(): void
    {
        $post = Post::create([]);

        $event = null;
        app('events')->listen(FileWasStored::class, function (FileWasStored $stored) use (&$event) {
            $event = $stored;
        });

        $post->storage()->mute(false)->store(__DIR__.'/test.png');

        $this->assertInstanceOf(FileWasStored::class, $event);

        // The listener gets a path it can open right away.
        $this->assertStringEndsWith('/post/'.$post->getKey().'/test.png', $event->path);
        $this->assertFileExists($event->path);

        $this->assertTrue($event->owner->is($post));

        $post->storage()->flush();
    }

    public function testResolveRequiresAMorphAlias(): void
    {
        try {
            Storage::resolve('unknown_alias', 1);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('unknown_alias', $exception->getMessage());
        }
    }

    public function testResolveLooksUpTheOwner(): void
    {
        Relation::morphMap(['post' => Post::class]);

        $post = Post::create([]);

        $post->storage()->store(__DIR__.'/test.png');

        $storage = Storage::resolve('post', $post->getKey());

        $this->assertTrue($storage->owner()->is($post));
        $this->assertEquals(1, $storage->files()->count());

        $storage->flush();
    }

    public function testResolveConvertsTheBucketNameToTheEnumCase(): void
    {
        Relation::morphMap(['post' => Post::class]);

        $post = Post::create([]);

        $post->storage(Bucket::docs)->store(__DIR__.'/test.png');

        // The route carries a string, `storage()` declares a `Bucket` case.
        $storage = Storage::resolve('post', $post->getKey(), 'docs');

        $this->assertEquals('docs', $storage->name());
        $this->assertEquals(1, $storage->files()->count());

        $storage->flush();
    }

    public function testResolveRejectsAnUnknownBucketName(): void
    {
        Relation::morphMap(['post' => Post::class]);

        $post = Post::create([]);

        try {
            Storage::resolve('post', $post->getKey(), 'missing');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('missing', $exception->getMessage());
            $this->assertStringContainsString(Post::class, $exception->getMessage());
            $this->assertStringContainsString('"docs"', $exception->getMessage());
        }
    }

    public function testResolveRejectsABucketTheOwnerDoesNotAccept(): void
    {
        Relation::morphMap(['no_param_post' => NoParamPost::class]);

        $post = NoParamPost::create([]);

        try {
            Storage::resolve('no_param_post', $post->getKey(), 'docs');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('no bucket argument', $exception->getMessage());
            $this->assertStringContainsString(NoParamPost::class, $exception->getMessage());
        }
    }

    public function testResolveRejectsAPureEnumBucket(): void
    {
        Relation::morphMap(['pure_post' => PurePost::class]);

        $post = PurePost::create([]);

        try {
            Storage::resolve('pure_post', $post->getKey(), 'docs');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('backing value', $exception->getMessage());
            $this->assertStringContainsString(PureBucket::class, $exception->getMessage());
        }
    }

    public function testResolvePassesTheBucketNameToAnUntypedStorage(): void
    {
        Relation::morphMap(['plain_post' => PlainPost::class]);

        $post = PlainPost::create([]);

        // The raw name is handed over: the implementation narrows it itself.
        $storage = Storage::resolve('plain_post', $post->getKey(), 'docs');

        $this->assertTrue($storage->owner()->is($post));
    }

    /**
     * Remove files stored by these tests.
     */
    protected function cleanStorage(): void
    {
        $filesystem = new Filesystem();

        foreach (['post', 'soft_post', 'strict_post', 'plain_post', 'pure_post', 'no_param_post'] as $directory) {
            $path = __DIR__.'/../storage/'.$directory;

            if (is_dir($path)) {
                $filesystem->deleteDirectory($path);
            }
        }
    }
}
