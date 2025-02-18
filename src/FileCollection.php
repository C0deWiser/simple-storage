<?php

namespace Codewiser\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int,File>
 */
class FileCollection extends Collection
{
    public static function hydrate(Filesystem $disk, array $items = []): static
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
     * @return array<Attachment>
     */
    public function toMailAttachments(): array
    {
        return $this
            ->map(fn(File $file) => $file->toMailAttachment())
            ->toArray();
    }
}
