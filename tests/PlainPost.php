<?php

namespace Tests;

use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\AttachmentableObserver;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner without a bucket of its own: `storage` accepts the argument, but every
 * file lands in the default storage.
 */
class PlainPost extends Model implements Attachmentable
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
        return 'plain_post';
    }

    public function storage($bucket = null): StorageContract
    {
        return Storage::make($this, $this->disk())->mute();
    }
}
