<?php

namespace Tests;

use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\AttachmentableObserver;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner without a default bucket: `storage()` without arguments is not
 * resolvable, as in a `match` without a `default` case.
 */
class StrictPost extends Model implements Attachmentable
{
    use TestDisk;

    protected $table = 'posts';

    protected $guarded = [];

    public static function booted(): void
    {
        static::observe(AttachmentableObserver::class);
    }

    public function getMorphClass(): string
    {
        return 'strict_post';
    }

    public function storage(Bucket $bucket = null): StorageContract
    {
        if (is_null($bucket)) {
            throw new \InvalidArgumentException('Bucket is not supported');
        }

        return Storage::make($this, $this->disk(), $bucket)->mute();
    }
}
