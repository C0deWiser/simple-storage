<?php

namespace Codewiser\Storage;

use Illuminate\Database\Eloquent\Model;

class MountPoint
{
    public function handle(Model&Attachmentable $model, null|string|\BackedEnum $bucket = null): string
    {
        $mount = $model->getMorphClass().DIRECTORY_SEPARATOR.($model->getKey() ?? 0);

        if (! is_null($bucket)) {
            $bucket = is_string($bucket) ? $bucket : $bucket->value;

            $mount = $mount.DIRECTORY_SEPARATOR.$bucket;
        }

        return $mount;
    }
}