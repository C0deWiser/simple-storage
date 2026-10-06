<?php

namespace Tests;

use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\AttachmentableObserver;
use Codewiser\Storage\Pool;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements Attachmentable
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
        return 'post';
    }

    public function pool(): Pool
    {
        return Pool::make()
            ->addBucket(Storage::make($this, $this->disk())->mute())
            ->addBucket(Storage::make($this, $this->disk(), Bucket::docs)->mute());
    }

    /**
     * Enum typed argument lets the observer enumerate every bucket of this
     * owner: the interface declares no bucket at all.
     */
    public function storage(Bucket $bucket = null): StorageContract
    {
        return $this->pool()->getBucket($bucket);
    }
}
