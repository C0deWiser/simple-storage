<?php

namespace Codewiser\Storage;

use BackedEnum;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class Storage implements StorageContract
{
    readonly public Filesystem $disk;

    /**
     * Mount point (relative to the disk).
     *
     * @var string
     */
    protected string $mount;

    protected bool $singular = false;

    /**
     * Mute events.
     */
    protected bool $mute = false;

    public static function make(Model $owner, Filesystem|string $disk, null|string|BackedEnum $bucket = null): static
    {
        return new static($owner, $disk, $bucket);
    }

    /**
     * @param  Model  $owner  Storage owner.
     * @param  Filesystem|string  $disk  Laravel disk.
     */
    public function __construct(
        readonly public Model $owner,
        Filesystem|string $disk,
        readonly protected null|string|BackedEnum $bucket = null,
    ) {
        if (is_string($disk)) {
            $this->disk = \Illuminate\Support\Facades\Storage::disk($disk);
        } else {
            $this->disk = $disk;
        }

        $this->mount = $this->owner->getMorphClass().DIRECTORY_SEPARATOR.$this->owner->getKey();

        if ($bucket = $this->name()) {
            $this->mount = $this->mount.DIRECTORY_SEPARATOR.$bucket;
        }
    }

    public function name(): ?string
    {
        return $this->bucket instanceof BackedEnum ? $this->bucket->value : $this->bucket;
    }

    public function disk(): Filesystem
    {
        return $this->disk;
    }

    /**
     * Mark that bucket holds single file.
     */
    public function singular(bool $singular = true): static
    {
        $this->singular = $singular;

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function mapPathToModel(string $path): Model
    {
        $path = explode('/', $path);

        if (count($path) < 2) {
            throw new InvalidArgumentException("File path should be {model}/{id}");
        }

        $classname = Relation::getMorphedModel($path[0]);

        if (!$classname && !class_exists($classname)) {
            throw new InvalidArgumentException(__('Unrecognized class name for ":model"', [
                'model' => $path[0]
            ]));
        }

        return $classname::query()->findOrFail($path[1]);
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
        return FileCollection::hydrate($this->disk, $this->disk->files($this->mount));
    }

    public function single(): ?File
    {
        return $this->files()->first();
    }

    protected function propagateNewFile($path): ?File
    {
        if ($path) {

            $file = new File($this->disk, $path);

            if (!$this->mute && $this->owner->getKey()) {
                event(new FileWasStored($file, $this->owner));
            }

            return $file;
        }

        return null;
    }

    public function upload($content): ?File
    {
        if ($this->singular) {
            $this->flush();
        }

        $filename = null;

        if (is_string($content)) {
            $info = pathinfo($content);
            $filename = $info['basename'];
        }

        if ($content instanceof UploadedFile) {
            $filename = $content->getClientOriginalName();
        }

        return $this->propagateNewFile(
            $filename
                ? $this->disk->putFileAs($this->mount, $content, $filename)
                : $this->disk->put($this->mount, $content)
        );
    }
}
