<?php

namespace Codewiser\Storage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileWasStored
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $path  Full path of the stored file, as the disk resolves it.
     * @param  Model&Attachmentable  $owner  Owner of the storage the file was stored to.
     * @param  null|string|\BackedEnum  $bucket  Bucket the file was stored to.
     */
    public function __construct(
        public string $path,
        public Model&Attachmentable $owner,
        public null|string|\BackedEnum $bucket
    ) {
        //
    }
}
