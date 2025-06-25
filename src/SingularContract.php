<?php

namespace Codewiser\Storage;

interface SingularContract extends StorageContract
{
    /**
     * Get a single file.
     */
    public function file(): ?File;
}