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

        $old = $this->files();

        $file = parent::store($content);

        // Unlink previous files once a new one is stored successfully.
        if ($file && $old->isNotEmpty()) {
            $old
                ->reject(fn(File $item) => $item->path === $file->path)
                ->flush();
        }

        return $file;
    }

    public function put(mixed $content, string $filename): null|File
    {
        $old = $this->files();

        $file = parent::put($content, $filename);

        // Unlink previous files once a new one is stored successfully.
        if ($file && $old->isNotEmpty()) {
            $old
                ->reject(fn(File $item) => $item->path === $file->path)
                ->flush();
        }

        return $file;
    }

    public function toArray(): array
    {
        return $this->file()?->toArray() ?? [];
    }
}