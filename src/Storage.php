<?php

namespace Codewiser\Storage;

class Storage implements StorageContract
{
    protected \Illuminate\Contracts\Filesystem\Filesystem $filesystem;

    /**
     * Mount point (relative to the disk).
     *
     * @var string
     */
    protected string $mount;

    /**
     * Mute events.
     */
    protected bool $mute = false;

    /**
     * @throws \InvalidArgumentException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function resolve(string $morph, int|string $id, string $bucket = null): StorageContract
    {
        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($morph);

        if (!$class && !class_exists($class)) {
            throw new \InvalidArgumentException(__('Unrecognized class name for ":model"', [
                'model' => $class
            ]));
        }

        $model = $class::query()->findOrFail($id);

        if ($model instanceof Attachmentable) {
            return $model->storage($bucket);
        }

        throw new \InvalidArgumentException(__(':class is not instanceof :instance', [
            'class'    => $class,
            'instance' => Attachmentable::class
        ]));
    }

    public static function make(
        \Illuminate\Database\Eloquent\Model&Attachmentable $owner,
        string $disk,
        null|string|\BackedEnum $bucket = null
    ): static {
        return new static($owner, $disk, $bucket);
    }

    public function __construct(
        protected \Illuminate\Database\Eloquent\Model&Attachmentable $owner,
        protected string $disk,
        protected null|string|\BackedEnum $bucket = null,
    ) {
        $this->filesystem = \Illuminate\Support\Facades\Storage::disk($disk);

        $this->mount = $this->owner->getMorphClass().DIRECTORY_SEPARATOR.$this->owner->getKey();

        if ($bucket = $this->name()) {
            $this->mount = $this->mount.DIRECTORY_SEPARATOR.$bucket;
        }
    }

    public function name(): ?string
    {
        return $this->bucket instanceof \BackedEnum ? $this->bucket->value : $this->bucket;
    }

    public function disk(): string
    {
        return $this->disk;
    }

    public function filesystem(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return $this->filesystem;
    }

    public function owner(): \Illuminate\Database\Eloquent\Model&Attachmentable
    {
        return $this->owner;
    }

    /**
     * Make this bucket hold single file.
     */
    public function singular(): SingularContract
    {
        return Singular::make($this->owner, $this->disk, $this->bucket)->mute($this->mute);
    }

    /**
     * Mute storage events.
     */
    public function mute(bool $mute = true): static
    {
        $this->mute = $mute;

        return $this;
    }

    public function delete(string|array $keys): static
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            $this->files()->filterByPath($key)->flush();
        }

        return $this;
    }

    public function flush(): void
    {
        $this->files()->flush();
    }

    public function files(): FileCollection
    {
        return FileCollection::hydrate($this->filesystem, $this->filesystem->files($this->mount))->latest();
    }

    protected function propagateNewFile($path): ?File
    {
        if ($path) {

            $file = new File($this->filesystem, $path);

            if (!$this->mute && $this->owner->getKey()) {
                event(new FileWasStored($file, $this->owner));
            }

            return $file;
        }

        return null;
    }

    public function upload($content): null|File|FileCollection
    {
        if (is_array($content)) {
            return FileCollection::hydrate(
                $this->filesystem,
                array_map(fn($data) => $this->upload($data), $content)
            );
        }

        $filename = null;

        if (is_string($content)) {
            $info = pathinfo($content);
            $filename = $info['basename'];
        }

        if ($content instanceof \Illuminate\Http\UploadedFile) {
            $filename = $content->getClientOriginalName();
        }

        return $this->propagateNewFile(
            $filename
                ? $this->filesystem->putFileAs($this->mount, $content, $filename)
                : $this->filesystem->put($this->mount, $content)
        );
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->files()->toArray();
    }
}
