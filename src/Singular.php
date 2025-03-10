<?php

namespace Codewiser\Storage;

class Singular extends Storage implements SingularContract
{
    public function file(): ?File
    {
        return $this->files()->first();
    }

    public function upload($content): null|File|FileCollection
    {
        // Make one!
        $content = is_array($content) ? current($content) : $content;

        // Flush previous
        if ($content) {
            $this->flush();
        }

        return parent::upload($content);
    }

    public function put(mixed $content, string $filename): null|File
    {
        // Flush previous
        if ($content) {
            $this->flush();
        }

        return parent::put($content, $filename);
    }

    /**
     * @return null|array
     */
    public function toArray()
    {
        return $this->file()?->toArray();
    }
}