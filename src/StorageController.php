<?php

namespace Codewiser\Storage;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StorageController
{
    public function __invoke(Request $request, string $model, string $id, string $bucket, string $filename = null): Responsable
    {
        if (is_null($filename)) {
            $filename = $bucket;
            $bucket = null;
        }

        try {
            $storage = Storage::resolve($model, $id, $bucket);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        Gate::authorize('view', $storage->owner());

        if ($storage instanceof Singular) {
            return $storage->file();
        } else {
            return $storage->files()->one($filename);
        }
    }
}
