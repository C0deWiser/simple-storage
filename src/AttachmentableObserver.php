<?php

namespace Codewiser\Storage;

use Illuminate\Database\Eloquent\Model;

/**
 * Flush owner files when the owner is destroyed.
 *
 * The observer unlinks the default bucket and every bucket declared as a
 * backed enum case in the owner `storage` method.
 */
class AttachmentableObserver
{
    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        // A soft deleted owner is not destroyed yet, so keep its files.
        if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
            return;
        }

        $this->flush($model);
    }

    /**
     * Handle the model "forceDeleted" event.
     */
    public function forceDeleted(Model $model): void
    {
        $this->flush($model);
    }

    /**
     * Unlink every file of the owner.
     *
     * Every storage is flushed on its own: a bucket that cannot be resolved
     * must not break the owner deletion.
     */
    protected function flush(Model $model): void
    {
        if (! $model instanceof Attachmentable) {
            return;
        }

        if (is_null($model->getKey())) {
            // Owner was never persisted, so it has no mount point of its own.
            return;
        }

        // Buckets first, so the default bucket may drop the emptied mount point.
        foreach ($this->enumBuckets($model) as $bucket) {
            try {
                // The interface declares no bucket argument; implementations accept one.
                $model->storage($bucket)->flush();
            } catch (\Throwable) {
                // Best effort: owner deletion may not fail because of files.
            }
        }

        try {
            $model->storage()->flush();
        } catch (\Throwable) {
            // Best effort: owner deletion may not fail because of files.
        }
    }

    /**
     * Buckets declared by the `storage` argument of the owner:
     *
     *     public function storage(Bucket $bucket = null)
     *
     * The signature is read by `BucketArgument`, the shared home of every
     * rule that turns a declared type into a bucket.
     *
     * @return array<int, \BackedEnum>
     */
    protected function enumBuckets(Model&Attachmentable $model): array
    {
        return (new BucketArgument($model))->buckets();
    }
}
