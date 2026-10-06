<?php

namespace Tests;

use Codewiser\Storage\Attachmentable;
use Codewiser\Storage\AttachmentableObserver;
use Codewiser\Storage\Storage;
use Codewiser\Storage\StorageContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner written against the original contract: `storage` takes no argument,
 * so a bucket handed over by the controller has nowhere to go.
 */
class NoParamPost extends Model implements Attachmentable
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
        return 'no_param_post';
    }

    public function storage(): StorageContract
    {
        return Storage::make($this, $this->disk())->mute();
    }
}
