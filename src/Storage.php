<?php

namespace Codewiser\Storage;

class Storage implements StorageContract
{
    protected \Illuminate\Contracts\Filesystem\Filesystem $disk;

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
     * Resolve the owner storage from a morph alias, its key and a bucket name.
     *
     * @throws \InvalidArgumentException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function resolve(string $morph, int|string $id, ?string $bucket = null): StorageContract
    {
        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($morph);

        // Mount points are built from morph names, so an unmapped alias is a
        // configuration error: file paths must stay short and readable.
        if (! $class || ! class_exists($class)) {
            throw new \InvalidArgumentException(sprintf(
                'Morph alias "%s" is not registered',
                $morph
            ));
        }

        $model = $class::query()->findOrFail($id);

        if ($model instanceof Attachmentable) {
            // The contract declares no bucket argument, so the name is read
            // from the implementation's signature before it is passed along.
            return $model->storage(
                is_null($bucket) ? null : (new BucketArgument($model))->cast($bucket)
            );
        }

        throw new \InvalidArgumentException(sprintf(
            '%s does not implement %s',
            $class,
            Attachmentable::class
        ));
    }

    public static function make(
        \Illuminate\Database\Eloquent\Model&Attachmentable $owner,
        null|string|\Illuminate\Contracts\Filesystem\Filesystem $disk = null,
        null|string|\BackedEnum $bucket = null
    ): static {
        return new static($owner, $disk, $bucket);
    }

    public function __construct(
        protected \Illuminate\Database\Eloquent\Model&Attachmentable $owner,
        null|string|\Illuminate\Contracts\Filesystem\Filesystem $disk = null,
        protected null|string|\BackedEnum $bucket = null,
    ) {

        $disk = $disk ?? config('filesystems.default');

        $this->disk = is_string($disk)
            ? \Illuminate\Support\Facades\Storage::disk($disk)
            : $disk;

        $this->mount = app(MountPoint::class)->handle($this->owner, $this->bucket);
    }

    # Mutators

    public function bucket(string|\BackedEnum $bucket): static
    {
        return static::make($this->owner, $this->disk, $bucket)->mute($this->mute);
    }

    public function onDisk(string|\Illuminate\Contracts\Filesystem\Filesystem $disk): static
    {
        return static::make($this->owner, $disk, $this->bucket)->mute($this->mute);
    }

    /**
     * Make this bucket hold a single file.
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

    # Properties

    public function name(): ?string
    {
        if ($this->bucket instanceof \BackedEnum) {
            return $this->bucket->value;
        }

        return $this->bucket;
    }

    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return $this->disk;
    }

    public function owner(): \Illuminate\Database\Eloquent\Model&Attachmentable
    {
        return $this->owner;
    }

    public function path(): string
    {
        return $this->mount;
    }

    # Actions

    public function delete(string|array $keys): static
    {
        // Delete every listed file whose path matches one of the given keys.
        $keys = is_array($keys) ? $keys : [$keys];

        $this->files()
            ->filter(fn(File $file) => in_array($file->path, $keys, true))
            ->flush();

        return $this;
    }

    /**
     * Unlink files and drop the mount point then it keeps no files.
     */
    public function flush(): void
    {
        $this->files()->flush();

        if (! $this->disk->exists($this->mount)) {
            return;
        }

        // Keep the mount point while it holds at least one bucket.
        if ($this->disk->files($this->mount) || $this->disk->directories($this->mount)) {
            return;
        }

        $this->disk->deleteDirectory($this->mount);
    }

    public function files(): FileCollection
    {
        return FileCollection::hydrate($this->disk, $this->disk->files($this->mount))->latest();
    }

    protected function propagateNewFile($path): ?File
    {
        if ($path) {

            $file = new File($this->disk, $path);

            if (! $this->mute) {
                event(new FileWasStored($file->path(), $this->owner, $this->bucket));
            }

            return $file;
        }

        return null;
    }

    /**
     * Alias for store().
     *
     * @deprecated use store()
     */
    public function upload($content): null|File|FileCollection
    {
        return $this->store($content);
    }

    public function store($content): null|File|FileCollection
    {
        // A null, false, empty string or empty list means nothing to store.
        // Names like '0' are still taken literally.
        if (is_null($content) || $content === false || $content === '' || $content === []) {
            return null;
        }

        // An unsaved owner has no mount point of its own.
        $this->ensureOwnerIsPersisted();

        // Store a list of files one by one, flattening nested collections and
        // dropping the items that failed to store.
        if (is_array($content) || $content instanceof FileCollection) {
            $files = new FileCollection();

            foreach (is_array($content) ? $content : $content->all() as $item) {
                $stored = $this->store($item);

                if ($stored instanceof FileCollection) {
                    $files->push(...$stored->all());
                } elseif ($stored instanceof File) {
                    $files->push($stored);
                }
            }

            return $files;
        }

        // Copy a stored file through its own disk, so that a file kept on a
        // cloud disk can be read too.
        if ($content instanceof File) {
            return $this->copy($content);
        }

        $filename = null;

        if ($content instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $filename = basename($content->getClientOriginalName());
            $content = $content->getPathname();
        } elseif ($content instanceof \Symfony\Component\HttpFoundation\File\File) {
            $content = $content->getPathname();
        }

        if (is_string($content)) {
            if ($this->isRemoteUrl($content)) {
                return $this->storeUrl($content);
            }

            if (! file_exists($content)) {
                throw new \InvalidArgumentException(sprintf('Local file "%s" does not exist.', $content));
            }

            $filename = $filename ?? basename($content);

            $this->ensureFilenameIsPlain($filename);

            $path = $this->mount.'/'.$filename;

            // Note an already stored file, so the replacement may be reported
            // once the new file is in place.
            $replacing = $this->disk->exists($path);

            $file = $this->propagateNewFile($this->storeAs($content, $filename));

            if ($file && $replacing) {
                $this->warnWhenReplacing($path);
            }

            return $file;
        }

        throw new \InvalidArgumentException(sprintf(
            'Unable to store content of type "%s".',
            get_debug_type($content)
        ));
    }

    public function put(mixed $content, string $filename): null|File
    {
        // Only null (and a false flag) mean there is nothing to write; '0'
        // and an empty string are content worth storing.
        if (is_null($content) || $content === false) {
            return null;
        }

        $this->ensureOwnerIsPersisted();
        $this->ensureFilenameIsPlain($filename);

        $path = $this->mount.'/'.$filename;

        // Note an already stored file, so the replacement may be reported
        // once the new file is in place.
        $replacing = $this->disk->exists($path);

        $written = $this->disk->put($path, $content);

        $file = $this->propagateNewFile($path);

        if ($written && $replacing) {
            $this->warnWhenReplacing($path);
        }

        return $file;
    }

    /**
     * Store a local file under an exact name.
     */
    protected function storeAs(string $source, string $filename): string|false
    {
        $this->ensureFilenameIsPlain($filename);

        return $this->disk->putFileAs($this->mount, $source, $filename);
    }

    /**
     * Copy a stored file to this storage.
     */
    protected function copy(File $source): ?File
    {
        if ($source->missing()) {
            throw new \InvalidArgumentException(sprintf('File "%s" does not exist.', $source->path));
        }

        $filename = $source->filename();

        $this->ensureFilenameIsPlain($filename);

        $path = $this->mount.'/'.$filename;

        $stream = $source->readStream();

        if (! is_resource($stream)) {
            throw new \InvalidArgumentException(sprintf('Unable to read file "%s".', $source->path));
        }

        // Note an already stored file, so the replacement may be reported
        // once the new file is in place.
        $replacing = $this->disk->exists($path);

        $written = $this->disk->writeStream($path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $file = $written ? $this->propagateNewFile($path) : null;

        if ($file && $replacing) {
            $this->warnWhenReplacing($path);
        }

        return $file;
    }

    /**
     * Download a remote file to this storage.
     */
    protected function storeUrl(string $url): ?File
    {
        if (! filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new \InvalidArgumentException(
                'Uploading from a remote URL requires allow_url_fopen to be enabled.'
            );
        }

        $filename = basename((string) parse_url($url, PHP_URL_PATH));

        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = parse_url($url, PHP_URL_HOST) ?: 'file';
        }

        $this->ensureFilenameIsPlain($filename);

        $stream = @fopen($url, 'rb');

        if (! is_resource($stream)) {
            throw new \InvalidArgumentException(sprintf('Unable to open remote file "%s".', $url));
        }

        $path = $this->mount.'/'.$filename;

        // Note an already stored file, so the replacement may be reported
        // once the new file is in place.
        $replacing = $this->disk->exists($path);

        $written = $this->disk->writeStream($path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $file = $written ? $this->propagateNewFile($path) : null;

        if ($file && $replacing) {
            $this->warnWhenReplacing($path);
        }

        return $file;
    }

    protected function isRemoteUrl(string $content): bool
    {
        return preg_match('#^https?://#i', $content) === 1;
    }

    /**
     * An owner without a key has no mount point, so it may not store files.
     *
     * @throws \LogicException
     */
    protected function ensureOwnerIsPersisted(): void
    {
        if (is_null($this->owner->getKey())) {
            throw new \LogicException('Owner must be saved before its storage accepts files.');
        }
    }

    /**
     * A file sits inside the mount point, never below it, so its name must be
     * plain: not empty, not a dot name, free of directory separators.
     *
     * @throws \InvalidArgumentException
     */
    protected function ensureFilenameIsPlain(string $filename): void
    {
        if ($filename === '' || $filename === '.' || $filename === '..' || strpbrk($filename, "/\\\0") !== false) {
            throw new \InvalidArgumentException(sprintf(
                'Filename "%s" must be a plain, non-empty name without directory separators.',
                $filename
            ));
        }
    }

    /**
     * Report that a write replaced an already stored file.
     *
     * It is called after the write, so the new file stays in place even where
     * warnings are turned into exceptions.
     */
    protected function warnWhenReplacing(string $path): void
    {
        trigger_error(sprintf('File "%s" already exists and has been overwritten', $path), E_USER_WARNING);
    }

    public function toArray(): array
    {
        return $this->files()->toArray();
    }

    public function isEmpty(): bool
    {
        return $this->files()->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return $this->files()->isNotEmpty();
    }
}
