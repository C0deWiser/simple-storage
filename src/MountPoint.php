<?php

namespace Codewiser\Storage;

use Illuminate\Database\Eloquent\Model;

class MountPoint
{
    /**
     * Flysystem paths always use a forward slash, whatever the platform.
     */
    private const SEPARATOR = '/';

    public function handle(Model&Attachmentable $model, null|string|\BackedEnum $bucket = null): string
    {
        $mount = $model->getMorphClass().self::SEPARATOR.($model->getKey() ?? 0);

        if (! is_null($bucket)) {
            $bucket = is_string($bucket) ? $bucket : $bucket->value;

            $mount = $mount.self::SEPARATOR.$bucket;
        }

        return $mount;
    }
}