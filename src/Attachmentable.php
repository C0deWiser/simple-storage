<?php

namespace Codewiser\Storage;

use BackedEnum;

/**
 * Контракт модели, у которой есть файлы.
 */
interface Attachmentable
{
    public function storage(string|BackedEnum $bucket = null): StorageContract;
}
