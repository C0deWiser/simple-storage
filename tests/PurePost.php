<?php

namespace Tests;

use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\AttachmentableObserver;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner declaring a pure enum bucket: it may not be enumerated.
 */
class PurePost extends Model implements Attachmentable
{
    use TestDisk;

    protected $table = 'posts';

    protected $guarded = [];

    /**
     * Set then the observer resolves a bucket argument.
     */
    public static bool $resolved = false;

    public static function booted(): void
    {
        static::observe(AttachmentableObserver::class);
    }

    public function getMorphClass(): string
    {
        return 'pure_post';
    }

    public function storage(PureBucket $bucket = null): StorageContract
    {
        if ($bucket) {
            static::$resolved = true;

            throw new \InvalidArgumentException('Pure enum bucket is not supported');
        }

        return Storage::make($this, $this->disk())->mute();
    }
}
