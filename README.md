# Simple lightweight storage

No database, only local filesystem.

Every model keeps its files isolated from each over. Path to files 
formed from model's morph name and its primary key.

Do not forget to `enforceMorphMap` in `AppServiceProvider`.

In the example below the model will keep files in `local` disk at `post/{id}`
path.

```php
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    public function storage(): StorageContract
    {
        return Storage::make(owner: $this, disk: 'local');
    }
}
```

You may add files from `UploadedFile`, from local path or remote url.

```php
use Illuminate\Http\Request;

class Controller {
    public function attach(Request $request, Post $post) {
        $file = $post->storage()
            ->upload($request->file('file'));
            
        return $file->toArray(); 
    }
}
```

Stored file represented with such array:

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

File `path` is a relative path to a disk. Use file `path` to unlink it. You 
may delete few files at once.

```php
use Illuminate\Http\Request;

class Controller {
    public function detach(Request $request, Post $post) {
        $file = $post->storage()
            ->delete($request->input('unlink'));
            
        return response()->noContent(); 
    }
}
```

To get collection with all files call `files` method on `Storage`:

```php
$files = $post->storage->files();

return $files->toArray();
```

To remove all files call `flush` method on `Storage`:

```php
$files = $post->storage->flush();
```

## File object

File object has the same methods as Laravel Storage Facade: `exists`, `size`,
`lastModified`, `delete`, `checksum`, `url` etc.

It implements `Responsable` and  `Attachable`, so you may use it as `Response` 
and in `Notification` or `Mailable`.


## Singular Storage

Sometimes we need the model to have only one file. We may create such a 
storage:

```php
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    public function storage(): StorageContract
    {
        return Storage::make(owner: $this, disk: 'local')
            ->singular();
    }
}
```

If you upload next file to a storage, all previous files will be removed.

## Storage Pool

We may combine few storages in a poll:

```php
use Codewiser\Storage\Pool;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;
use BackedEnum;

class Post extends Model
{
    public function storage(string|BackedEnum $bucket): StorageContract
    {
        return Pool::make()
            // Keep files at post/1/cover
            ->addBucket(Storage::make(owner: $this, disk: 'local', bucket: 'cover')
                ->singular())
            // Keep files at post/1/docs
            ->addBucket(Storage::make(owner: $this, disk: 'local', bucket: 'docs'))
            ->getBucket($bucket)
    }
}
```

Then we may call to required bucket:

```php
$docs = $post->storage('docs')->files();
$cover = $post->storage('cover')->single();
```

To get the first (or single) file from a storage use `single` method.

### Default storage

It is allowed to have one default storage in a pool:

```php
use Codewiser\Storage\Pool;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;
use BackedEnum;

class Post extends Model
{
    public function storage(string|BackedEnum $bucket = null): StorageContract
    {
        return Pool::make()
            // Default bucket keeps files at post/1
            ->addBucket(Storage::make(owner: $this, disk: 'local')
                ->singular())
            // Keep file at post/1/docs
            ->addBucket(Storage::make(owner: $this, disk: 'local', bucket: 'docs'))
            ->getBucket($bucket)
    }
}
```

Then we may call to required bucket:

```php
$cover = $post->storage()->single();
$docs = $post->storage('docs')->files();
```