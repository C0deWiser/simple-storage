<?php

namespace Codewiser\Storage;

interface SingularContract extends StorageContract
{
    /**
     * Get single file.
     */
    public function file(): ?File;
}