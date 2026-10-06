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
        $this->ensureOwnerIsPersisted();

        // A singular storage keeps one file only, so take the first item of a
        // nested list, whatever shape it has.
        while (is_array($content) || $content instanceof FileCollection) {
            $content = is_array($content) ? reset($content) : $content->first();

            if (empty($content)) {
                return null;
            }
        }

        $old = $this->files();

        $file = parent::store($content);

        // Unlink previous files once a new one is stored successfully.
        if ($file instanceof File && $old->isNotEmpty()) {
            $old
                ->reject(fn(File $item) => $item->path === $file->path)
                ->flush();
        }

        return $file;
    }

    public function put(mixed $content, string $filename): null|File
    {
        $this->ensureOwnerIsPersisted();

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