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
        // Flush previous
        $this->flush();

        // Make one!
        $content = is_array($content) ? current($content) : $content;

        return parent::upload($content);
    }

    /**
     * @return null|array
     */
    public function toArray()
    {
        return $this->file()?->toArray();
    }
}