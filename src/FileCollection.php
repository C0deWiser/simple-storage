<?php

namespace Codewiser\Storage;

/**
 * @extends \Illuminate\Support\Collection<int,File>
 */
class FileCollection extends \Illuminate\Support\Collection
{
    public static function hydrate(\Illuminate\Contracts\Filesystem\Filesystem $disk, array $items = []): static
    {
        return (new static($items))
            ->map(fn($file) => is_string($file)
                ? new File($disk, $file)
                : $file);
    }

    /**
     * Unlink files.
     *
     * @return int Count of unlinked files.
     */
    public function flush(): int
    {
        return $this
            ->filter(fn(File $file) => $file->delete())
            ->count();
    }

    public function filterByPath(string $path): static
    {
        return $this->filter(
            fn(File $file) => $file->path == $path
        );
    }

    /**
     * Get file by its filename.
     */
    public function one(string $filename): ?File
    {
        return $this->first(
            fn(File $file) => $file->filename() == $filename
        );
    }

    public function latest(): static
    {
        return $this
            ->sort(function (File $a, File $b) {
                if ($a->lastModified() == $b->lastModified()) {
                    return 0;
                }
                return ($a->lastModified() < $b->lastModified()) ? -1 : 1;
            });
    }

    /**
     * @return array<int,\Illuminate\Mail\Attachment>
     */
    public function toMailAttachments(): array
    {
        return $this
            ->map(fn(File $file) => $file->toMailAttachment())
            ->toArray();
    }
}
