<?php

namespace Codewiser\Storage;

class Singular extends Storage implements SingularContract
{
    public function file(): ?File
    {
        return $this->files()->first();
    }

    public function store($content): null|File|FileCollection
    {
        // Make one!
        $content = is_array($content) ? current($content) : $content;
        $content = $content instanceof FileCollection ? $content->first() : $content;

        // Flush previous
        if ($content) {
            $this->flush();
        }

        return parent::store($content);
    }

    public function put(mixed $content, string $filename): null|File
    {
        // Flush previous
        if ($content) {
            $this->flush();
        }

        return parent::put($content, $filename);
    }

    public function toArray(): array
    {
        return $this->file()?->toArray() ?? [];
    }
}