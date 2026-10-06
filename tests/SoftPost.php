<?php

namespace Tests;

use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\AttachmentableObserver;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftPost extends Model implements Attachmentable
{
    use SoftDeletes;
    use TestDisk;

    protected $table = 'posts';

    protected $guarded = [];

    public static function booted(): void
    {
        static::observe(AttachmentableObserver::class);
    }

    public function getMorphClass(): string
    {
        return 'soft_post';
    }

    public function storage($bucket = null): StorageContract
    {
        return Storage::make($this, $this->disk(), $bucket)->mute();
    }
}
