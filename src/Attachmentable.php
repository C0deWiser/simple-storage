<?php

namespace Codewiser\Storage;

interface Attachmentable
{
    public function storage(string|\UnitEnum $bucket = null): StorageContract;
}