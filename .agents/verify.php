<?php

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../tests/bootstrap.php';

use Codewiser\Storage\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

$root = __DIR__.'/../storage/verify';
@mkdir($root, 0777, true);
$adapter = new LocalFilesystemAdapter($root);
$fs = new FilesystemAdapter(new Filesystem($adapter), $adapter, ['root' => $root]);

// 1. A sorted file list keeps its JSON array shape
$model = new Tests\Model();
$storage = Storage::make($model, $fs)->mute();
$storage->put('a', 'a.txt');
sleep(1);
$storage->put('b', 'b.txt');
$files = $storage->files(); // sorted latest first
echo "keys after latest(): ";
var_export(array_keys($files->toArray()));
echo PHP_EOL;
echo "json of storage toArray: ";
echo substr(json_encode($storage->toArray()), 0, 60), PHP_EOL;
$storage->flush();

// 2. resolve() error message when morph is unknown
try {
    Storage::resolve('unknown_morph', 1);
} catch (\Throwable $e) {
    echo "resolve error: ".$e->getMessage().PHP_EOL;
}

// 3. event path: relative or absolute?
use Codewiser\Storage\FileWasStored;

$container = Illuminate\Container\Container::getInstance();
$dispatcher = new Illuminate\Events\Dispatcher($container);
$container->instance('events', $dispatcher);
$seen = null;
$dispatcher->listen(FileWasStored::class, function (FileWasStored $event) use (&$seen) {
    $seen = $event->path;
});

$storage2 = Storage::make($model, $fs)->mute(false);
$storage2->put('c', 'c.txt');
echo "event path: ".var_export($seen, true).PHP_EOL;
echo "file->path property: ".var_export($storage2->files()->first()->path, true).PHP_EOL;
$storage2->flush();

// 4. store() array with a failing item
try {
    $bad = $storage->store(['/nonexistent/nowhere.png', __DIR__.'/../tests/test.png']);
    echo "store array result: ".get_class($bad).' count='.$bad->count().' items='.json_encode(array_map(fn($i) => gettype($i), $bad->all())).PHP_EOL;
} catch (\Throwable $e) {
    echo "store array with missing file: ".get_class($e).': '.$e->getMessage().PHP_EOL;
}
$storage->flush();

// 5. Attachmentable::storage() signature vs resolve() call
$r = new ReflectionMethod(Tests\Post::class, 'storage');
echo "Post::storage params: ".$r->getNumberOfParameters().PHP_EOL;
$r = new ReflectionMethod(Codewiser\Storage\Attachmentable::class, 'storage');
echo "Attachmentable::storage params: ".$r->getNumberOfParameters().PHP_EOL;

// 6. Store a File from another disk (cross-disk copy)
$other = new Codewiser\Storage\File($fs, 'verify/x.txt');
$fs->put('verify/x.txt', 'hello content');
$copy = $storage->store($other);
echo "cross-disk copy content: ".var_export($copy?->get(), true).PHP_EOL;
$storage->flush();

// 7. type(), its deprecated alias mime(), and the full mimeType()
$f = $storage->put('x', 'x.png');
echo "type(): ".var_export($f?->type(), true)
    ." mime(): ".var_export($f?->mime(), true)
    ." mimeType: ".var_export($f?->mimeType(), true).PHP_EOL;
$storage->flush();

// 8. BucketArgument: conversion and guards around the `storage` bucket argument
$cases = [
    'enum, known case'      => [new Tests\Post(), 'docs'],
    'enum, unknown case'    => [new Tests\Post(), 'missing'],
    'pure enum'             => [new Tests\PurePost(), 'docs'],
    'untyped argument'      => [new Tests\PlainPost(), 'docs'],
    'union with string'     => [new Tests\Model(), 'docs'],
    'no argument at all'    => [new Tests\NoParamPost(), 'docs'],
];

foreach ($cases as $label => [$owner, $name]) {
    try {
        $value = (new Codewiser\Storage\BucketArgument($owner))->cast($name);
        echo $label.': '.gettype($value).(is_object($value) ? ' '.get_class($value).'::'.var_export($value->value, true) : ' '.var_export($value, true)).PHP_EOL;
    } catch (Throwable $e) {
        echo $label.': '.get_class($e).': '.$e->getMessage().PHP_EOL;
    }
}

echo 'buckets declared by Post: '.json_encode(array_map(fn ($case) => $case->value, (new Codewiser\Storage\BucketArgument(new Tests\Post()))->buckets())).PHP_EOL;
echo 'buckets declared by PlainPost: '.json_encode(array_map(fn ($case) => $case->value, (new Codewiser\Storage\BucketArgument(new Tests\PlainPost()))->buckets())).PHP_EOL;

// Int-backed enums stay broken (round 4, decision 3: ignore).
eval('enum Level: int { case one = 1; }');
eval('class IntPost extends Illuminate\Database\Eloquent\Model implements Codewiser\Storage\Attachmentable {
    public function storage(Level $bucket = null): Codewiser\Storage\StorageContract
    {
        return Codewiser\Storage\Storage::make($this, bucket: $bucket);
    }
}');

try {
    (new Codewiser\Storage\BucketArgument(new IntPost()))->cast('nope');
    echo 'int-backed enum, unknown name: resolved'.PHP_EOL;
} catch (Throwable $e) {
    echo 'int-backed enum, unknown name: '.get_class($e).': '.$e->getMessage().PHP_EOL;
}

try {
    Codewiser\Storage\Pool::make()
        ->addBucket(Codewiser\Storage\Storage::make($model, $fs)->mute())
        ->addBucket(Codewiser\Storage\Storage::make($model, $fs, Level::one)->mute())
        ->getBucket(Level::one);
    echo 'int-backed enum in a pool: resolved'.PHP_EOL;
} catch (Throwable $e) {
    echo 'int-backed enum in a pool: '.get_class($e).': '.$e->getMessage().PHP_EOL;
}

// 9. Round 4: plain filenames, falsy content, warning order, exact matching
$storage = Storage::make($model, $fs)->mute();

foreach (['', '.', '..', 'sub/nested.txt', "bad\0name.txt"] as $bad) {
    try {
        $storage->put('payload', $bad);
        echo 'put('.var_export($bad, true).'): written anyway'.PHP_EOL;
    } catch (\InvalidArgumentException $e) {
        echo 'put('.var_export($bad, true).'): rejected, mount is still a file: '.var_export(is_file($root.'/model/1'), true).PHP_EOL;
    }
}

echo "put('0'): ".var_export($storage->put('0', 'zero.txt')?->get(), true).PHP_EOL;
echo "put(''): ".var_export($storage->put('', 'empty.txt')?->get(), true).PHP_EOL;
echo "put(null): ".var_export($storage->put(null, 'null.txt'), true).PHP_EOL;
echo "store(''): ".var_export($storage->store(''), true).PHP_EOL;

try {
    $storage->store('0');
    echo "store('0'): written anyway".PHP_EOL;
} catch (\InvalidArgumentException $e) {
    echo "store('0'): ".$e->getMessage().PHP_EOL;
}

$storage->put('zero', '01');
echo "one('1') while only '01' exists: ".var_export($storage->files()->one('1'), true).PHP_EOL;

$warnings = [];
$replaced = [];
set_error_handler(function (int $level, string $message) use (&$warnings, &$replaced, $storage) {
    $warnings[] = $message;
    $replaced[] = $storage->files()->one('zero.txt')?->get();

    return true;
}, E_USER_WARNING);
$storage->put('second', 'zero.txt');
restore_error_handler();
echo 'warning: '.$warnings[0].PHP_EOL;
echo 'content when warned: '.var_export($replaced[0], true).PHP_EOL;

$storage->flush();

@exec('rm -rf '.escapeshellarg($root));
