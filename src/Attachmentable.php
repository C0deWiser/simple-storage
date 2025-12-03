<?php

namespace Codewiser\Storage;

interface Attachmentable
{
    /**
     * @param  null|string|\BackedEnum  $bucket
     *
     * @return StorageContract
     */
    public function storage($bucket = null): StorageContract;
}