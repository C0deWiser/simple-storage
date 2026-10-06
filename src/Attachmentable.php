<?php

namespace Codewiser\Storage;

/**
 * Model with file storage.
 */
interface Attachmentable
{
    /**
     * Get the owner's storage.
     */
    public function storage(): StorageContract;
}
