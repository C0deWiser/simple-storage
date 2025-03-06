# Simple lightweight storage

No database, only local filesystem.

Every model keeps its files isolated from each over. Path to files 
formed from model's morph name and its primary key.

> Do not forget to `enforceMorphMap` in `AppServiceProvider`.

## Define storage 

Implement `Attachmentable` contract on `Model`.

In the example below the model will keep files on default disk at `post/{id}`
path.

```php
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements Attachmentable
{
    public function storage(string|BackedEnum $bucket = null): StorageContract
    {
        return Storage::make($this, bucket: $bucket);
    }
}
```

### Uploading files

You may add files from `UploadedFile`, from local path or remote url.
You may upload multiple files at once.

```php
use Illuminate\Http\Request;

class Controller {
    public function attach(Request $request, Post $post) {

        return $post->storage()
            ->upload($request->allFiles())
            ->toArray(); 
    }
}
```

```json
[{
    "path": "post/1/test.png",
    "url": "/storage/post/1/test.png",
    "name": "test.png",
    "size": 6434,
    "hash": "d41d8cd98f00b204e9800998ecf8427e",
    "mime_type": "image/png",
    "last_modified": "2025-02-18T12:29:46+00:00"
}]
```

### Removing files

File `path` attribute is a relative path to a disk, e.g. `post/1/test.png`. 
Use `path` attribute to delete file. You may delete multiple files at once.

```php
use Illuminate\Http\Request;

class Controller {
    public function detach(Request $request, Post $post) {
        $post->storage()
            ->delete($request->input('unlink'));
            
        return response()->noContent(); 
    }
}
```

To remove all files call `flush` method on `Storage`:

```php
$files = $post->storage()->flush();
```

### List files

To get collection with all files call `files` method on `Storage`:

```php
$files = $post->storage()->files();

return $files->toArray();
```

`Storage` object is `Arrayable` too. It returns the same:

```php
$post->storage()->toArray();
// Is equivalent to
$post->storage()->files()->toArray();
```

### File object

File object has the same methods as Laravel Storage Facade: `exists`, `size`,
`lastModified`, `delete`, `checksum`, `url` etc.

Every stored file represented with such array:

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

`File` object implements `Responsable` and  `Attachable`, so you may use it as 
`Response` and in `Notification` or `Mailable`.

## Singular Storage

Sometimes we need the model to have only one file. We may create such a 
storage:

```php
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Codewiser\Storage\Singular;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements Attachmentable
{
    public function storage(string|BackedEnum $bucket = null): StorageContract|Singular
    {
        return Storage::make($this, disk: 'public', bucket: $bucket)->singular();
    }
}
```

When you upload next file to a storage, all previous files will be removed.

Singular storage has only one file, so `files` collection will contain only one 
element maximum. You may use `file` method instead. 

```php
$post->storage()->toArray();
// Is equivalent to
$post->storage()->file()->toArray();
```

## Storage Pool

The model may have few storages at the same time. Storages must have 
unique names (aka buckets).

```php
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Storage;
use Codewiser\Storage\Singular;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;
use BackedEnum;

class Post extends Model implements Attachmentable
{
    public function storage(string|BackedEnum $bucket = null): StorageContract|Singular
    {
        return match ($bucket)
            
            // One cover
            'cover' => Storage::make($this, bucket: $bucket)
                ->singular(),
                
            // Many docs
            'docs'  => Storage::make($this, bucket: $bucket),
            
            default => throw new \InvalidArgumentException("Bucket $bucket is not supported"),
        };
    }
}
```

Then we may get the exact bucket:

```php
$docs = $post->storage('docs')->files();
$cover = $post->storage('cover')->file();
```

### Default storage

It is allowed to have one default storage in a pool:

```php
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Storage;
use Codewiser\Storage\Singular;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;
use BackedEnum;

class Post extends Model implements Attachmentable
{
    public function storage(string|BackedEnum $bucket = null): StorageContract|Singular
    {
        return match ($bucket)
        
            // Named bucket
            'docs'  => Storage::make($this, bucket: $bucket),
            
            // Default bucket
            null => Storage::make($this)->singular(),
            
            default => throw new \InvalidArgumentException("Bucket $bucket is not supported"),
        };
    }
}
```

Call `storage` without bucket name to get the default one.

```php
$cover = $post->storage()->file();
$docs = $post->storage('docs')->files();
```

### Pool response

You may add a method to a model, that will return `Pool` object with all 
buckets defined:

```php
use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\Pool;
use Codewiser\Storage\Storage;
use Codewiser\Storage\Singular;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;
use BackedEnum;

class Post extends Model implements Attachmentable
{
    public function pool(): Pool
    {
        return Pool::make()
            ->addBucket(Storage::make($this)->singular())
            ->addBucket(Storage::make($this, bucket: 'docs'));        
    }

    public function storage(string|BackedEnum $bucket = null): StorageContract|Singular
    {
        return $this->pool()->getBucket($bucket);
    }
}
```

Then you may use this method in api resource:

```php
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            
            'files' => $this->pool()->toArray()
        ];
    }
}
```

Pool `toArray` method will return an array with every bucket and its file(s).
Singular storage provides `file` attribute, that may be `null` 
if no file were uploaded. Base storage provides `files` array, that may be 
empty.

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

The file is directly accessible only then published in public local 
filesystem. In other cases — private or cloud filesystem — application needs 
a controller to make files accessible to the users.

Let's say we have such private disk in `config/filesystems.php`:

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

If so, file url would be about `private/post/1/test.png` (for default bucket)
or `private/post/1/bucket/test.png` (for named bucket).

We suggest to use a controller `\Codewiser\Storage\StorageController`, that 
is looks so:

```php
use Codewiser\Storage\File;
use Codewiser\Storage\Storage;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StorageController
{
    public function __invoke(Request $request, string $model, string $id, string $bucket, string $filename = null): Responsable
    {
        if (is_null($filename)) {
            $filename = $bucket;
            $bucket = null;
        }

        $storage = Storage::resolve($model, $id, $bucket);

        Gate::authorize('view', $storage->owner());

        return $storage->files()->sole(
            fn(File $file) => $file->filename() == $filename
        );
    }
}
```

All you need is to declare a route:

```php
use Codewiser\Storage\StorageController;
use Illuminate\Support\Facades\Route;

Route::get('private/{model}/{id}/{bucket}/{filename?}', StorageController::class);
```

