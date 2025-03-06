<?php

namespace Codewiser\Storage;

use Exception;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        } catch (Exception $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        Gate::authorize('view', $storage->owner());

        try {
            return $storage->files()->sole(fn(File $file) => $file->filename() == $filename);
        } catch (ItemNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        } catch (MultipleItemsFoundException $exception) {
            // highly improbable but okay
            throw new ConflictHttpException($exception->getMessage());
        }
    }
}
