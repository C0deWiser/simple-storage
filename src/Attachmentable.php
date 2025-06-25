<?php

namespace Codewiser\Storage;

interface Attachmentable
{
    public function storage(string|\BackedEnum $bucket = null): StorageContract;
}