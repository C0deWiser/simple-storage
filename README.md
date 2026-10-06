# Simple lightweight storage

No database, only the filesystem.

Every model keeps its files isolated from one another. The path to a file is
built from the model's `morph name` and its `primary key`.

> Remember to call `enforceMorphMap` in `AppServiceProvider`!

```
composer require codewiser/simple-files
```

## Define storage

Implement the `Attachmentable` contract on your model.

In the example below the model keeps its files on the `public` disk, under the
`post/{id}` path.

```php
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements Attachmentable
{
    public function storage(): StorageContract
    {
        return new Storage($this, disk: 'public');
    }
}
```

The `disk` argument is optional and defaults to the `filesystems.default`
config value.

## Uploading files

You may store an `UploadedFile`, a path to a local file, an instance of `File`
kept on any disk, or — when `allow_url_fopen` is enabled — a remote URL.
Several files may be uploaded at once.

```php
use Illuminate\Http\Request;

class Controller {
    public function attach(Request $request, Post $post) {

        return $post->storage()->store($request->allFiles()); 
    }
}
```

The owner must be saved first: storing files to an unsaved model throws a
`LogicException`, because such a model has no mount point of its own yet.

### Listening to uploads

Every stored file dispatches a `FileWasStored` event:

```php
use Codewiser\Storage\FileWasStored;

Event::listen(function (FileWasStored $event) {
    $event->path;    // Full path of the file, as the disk resolves it.
    $event->owner;   // The model the file belongs to.
    $event->bucket;  // null, a string or a backed enum case.
});
```

## Removing files

The `path` attribute of a file is its relative path on the disk, for example
`post/1/test.png`. Use it to delete files; you may delete several at once.

```php
use Illuminate\Http\Request;

class Controller {
    public function detach(Request $request, Post $post) {
        
        $post->storage()->delete($request->input('unlink'));
    }
}
```

To remove all files, call `flush()` on the storage. When the mount point gets
empty, it is dropped too:

```php
$post->storage()->flush();
```

## List files

To get a collection with all files, call `files()` on the storage:

```php
$files = $post->storage()->files();

return $files->toArray();
```

`Storage` is `Arrayable` too and returns the same thing:

```php
$post->storage()->toArray();
// Is equivalent to
$post->storage()->files()->toArray();
```

## File serialization

The `File` object mirrors the Laravel Storage facade: `exists`, `size`,
`lastModified`, `delete`, `checksum`, `url` and so on.

Every stored file is represented by such an array:

```json
{
  "path": "post/1/test.png",
  "url": "/storage/post/1/test.png",
  "name": "test.png",
  "size": 6434,
  "hash": "d41d8cd98f00b204e9800998ecf8427e",
  "mime_type": "image/png",
  "last_modified": "2025-02-18T12:29:46+00:00"
}
```

`File` implements `Responsable` and `Attachable`, so you may use it as a
`Response` and in a `Notification` or a `Mailable`.

## Singular storage

Sometimes a model needs to have only one file. We may create such a storage:

```php
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Codewiser\Storage\Singular;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements Attachmentable
{
    public function storage(): StorageContract
    {
        return Storage::make($this)->singular();
    }
}
```

When you upload the next file to this storage, all previous files are removed.

A singular storage holds at most one element in its `files` collection, so you
may prefer the `file()` method:

```php
$post->storage()->toArray();
// Is equivalent to
$post->storage()->file()->toArray();
```

## Storage pool

A model may have several storages at the same time. Storages must have unique
names, also known as buckets. A bucket keeps its files in a subdirectory.

```php
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Storage;
use Codewiser\Storage\Singular;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements Attachmentable
{
    public function storage($bucket = null): StorageContract
    {
        return match ($bucket)
            
            // One cover
            'cover' => Storage::make($this, disk: 'private', bucket: $bucket)
                ->singular(),
                
            // Many docs
            'docs'  => Storage::make($this, disk: 'private', bucket: $bucket),
            
            // Default bucket
            null => Storage::make($this),
            
            default => throw new \InvalidArgumentException("Bucket is not supported"),
        };
    }
}
```

Then we may ask for an exact bucket:

```php
$docs = $post->storage('docs')->files();
$cover = $post->storage('cover')->file();
$other_files = $post->storage()->files();
```

## Prune files with the owner

Register `AttachmentableObserver` on the owner model to unlink its files when
the owner is destroyed:

```php
use Codewiser\Storage\AttachmentableObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(AttachmentableObserver::class)]
class Post extends Model implements Attachmentable
{
    // ...
}
```

Files are unlinked on the `deleted` event. An owner with the `SoftDeletes`
trait keeps its files until `forceDelete` is called.

The observer flushes the default bucket and every bucket declared as a backed
enum case in the `storage` method argument:

```php
class Post extends Model implements Attachmentable
{
    public function storage(Bucket $bucket = null): StorageContract
    {
        return Storage::make($this, bucket: $bucket);
    }
}
```

The observer reflects that argument, resolves every `Bucket` case, and flushes
`post/1`, `post/1/cover`, `post/1/docs` and so on.

If buckets are defined as plain strings, the observer cannot enumerate them.

## Pool response

You may add a method to a model that returns a `Pool` object with all buckets
defined:

```php
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Pool;
use Codewiser\Storage\Storage;
use Codewiser\Storage\Singular;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements Attachmentable
{
    public function storagePool(): Pool
    {
        return Pool::make()
            ->addBucket(Storage::make($this)->singular())
            ->addBucket(Storage::make($this, bucket: 'docs'));        
    }

    public function storage($bucket = null): StorageContract
    {
        return $this->storagePool()->getBucket($bucket);
    }
}
```

Then you may use this method in an API resource:

```php
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            
            'files' => $this->storagePool()->toArray()
        ];
    }
}
```

The `Pool::toArray()` method returns an array with every bucket and its file
or files. A singular storage provides a `file` entry, which is an empty array
when no file has been uploaded; a base storage provides a `files` array, which
may be empty.

```json
[
    {
        "bucket": null,
        "file": {
          "path": "post/1/test.png",
          "url": "/storage/post/1/test.png",
          "name": "test.png",
          "size": 6434,
          "hash": "d41d8cd98f00b204e9800998ecf8427e",
          "mime_type": "image/png",
          "last_modified": "2025-02-18T12:29:46+00:00"
        }
    },
    {
        "bucket": "docs",
        "files": [
          {
            "path": "post/1/docs/test.png",
            "url": "/storage/post/1/docs/test.png",
            "name": "test.png",
            "size": 6434,
            "hash": "d41d8cd98f00b204e9800998ecf8427e",
            "mime_type": "image/png",
            "last_modified": "2025-02-18T12:29:46+00:00"
          }
        ]
    }
]
```

## Downloading files

A file is directly accessible only when it is published on a public local
filesystem. In every other case — a private or a cloud filesystem — the
application needs a controller that serves files to its users.

Let's say we have such a private disk in `config/filesystems.php`:

```php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'url' => env('APP_URL').'/private',
    'serve' => true,
    'throw' => false,
    'report' => false,
],
```

A file on this disk is addressed as `private/post/1/test.png` for the default
bucket and as `private/post/1/docs/test.png` for a named bucket.

The package ships an invokable `StorageController` for this purpose. All you
need is to declare a route:

```php
use Codewiser\Storage\StorageController;
use Illuminate\Support\Facades\Route;

Route::get('private/{model}/{id}/{bucket}/{filename?}', StorageController::class);
```

When a file belongs to the default bucket, the `{bucket}` segment carries the
filename, and the controller shifts the parameters for you.

The `{model}` segment must be a registered morph alias, so `enforceMorphMap()`
is required here as well: an unknown alias becomes a `BadRequest`.

The `{bucket}` segment is matched against the argument of your `storage()`
method: a plain string is handed over as it is, while an enum-typed one is
resolved to its case.

The controller resolves the storage from the route parameters, authorizes the
owner model with the `view` policy, and returns the matched file as a streamed
response. Resolution failures are turned into HTTP `BadRequest` exceptions, and
a missing file becomes `NotFound`.
