<?php

namespace Codewiser\Storage;

class File implements \Illuminate\Contracts\Support\Arrayable, \Illuminate\Contracts\Mail\Attachable, \Illuminate\Contracts\Support\Responsable
{
    public function __construct(readonly public \Illuminate\Contracts\Filesystem\Filesystem $disk, readonly public string $path)
    {
        //
    }

    public function filename(): string
    {
        return basename($this->path);
    }

    /**
     * Determine if a file exists.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->disk->fileExists($this->path);
    }

    /**
     * Determine if a file is missing.
     *
     * @return bool
     */
    public function missing()
    {
        return $this->disk->fileMissing($this->path);
    }

    /**
     * Get the full path to the file.
     *
     * @return string
     */
    public function path()
    {
        return $this->disk->path($this->path);
    }

    /**
     * Delete the file at a given path.
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->disk->delete($this->path);
    }

    /**
     * Get the contents of a file.
     *
     * @return string|null
     */
    public function get()
    {
        return $this->disk->get($this->path);
    }

    /**
     * Get a resource to read the file.
     *
     * @return resource|null The path resource or null on failure.
     */
    public function readStream()
    {
        return $this->disk->readStream($this->path);
    }

    /**
     * Write the contents of a file.
     *
     * @param  \Psr\Http\Message\StreamInterface|\Illuminate\Http\File|\Illuminate\Http\UploadedFile|string|resource  $contents
     * @param  mixed  $options
     *
     * @return bool
     */
    public function put($contents, $options = [])
    {
        return $this->disk->put($this->path, $contents, $options);
    }

    /**
     * Write a new file using a stream.
     *
     * @param  resource  $resource
     * @param  array  $options
     *
     * @return bool
     */
    public function writeStream($resource, array $options = [])
    {
        return $this->disk->writeStream($this->path, $resource, $options);
    }

    /**
     * Get the visibility for the given path.
     *
     * @return string
     */
    public function getVisibility()
    {
        return $this->disk->getVisibility($this->path);
    }

    /**
     * Set the visibility for the given path.
     *
     * @param  string  $visibility
     *
     * @return bool
     */
    public function setVisibility($visibility)
    {
        return $this->disk->setVisibility($this->path, $visibility);
    }

    /**
     * Prepend to a file.
     *
     * @param  string  $data
     *
     * @return bool
     */
    public function prepend($data)
    {
        return $this->disk->prepend($this->path, $data);
    }

    /**
     * Append to a file.
     *
     * @param  string  $data
     *
     * @return bool
     */
    public function append($data)
    {
        return $this->disk->append($this->path, $data);
    }

    public function mimeType(): bool|string
    {
        return $this->disk->mimeType($this->path);
    }

    public function mime(): ?string
    {
        if (($mime = $this->mimeType()) &&
            ($mime = explode('/', $mime)) &&
            isset($mime[0])) {
            return $mime[0];
        }

        return $mime;
    }

    /**
     * Get the file size of a given file.
     *
     * @return int
     */
    public function size()
    {
        return $this->disk->size($this->path);
    }

    /**
     * Get the checksum for a file.
     *
     * @return string|false
     *
     * @throws \League\Flysystem\UnableToProvideChecksum
     */
    public function checksum(array $options = [])
    {
        return $this->disk->checksum($this->path, $options);
    }

    /**
     * Get the file's last modification time.
     *
     * @return int
     */
    public function lastModified()
    {
        return $this->disk->lastModified($this->path);
    }

    public function dump(): static
    {
        dump($this->toArray());

        return $this;
    }

    public function toArray(): array
    {
        return [
            'path'          => $this->path,
            'url'           => $this->url(),
            'name'          => $this->filename(),
            'size'          => $this->size(),
            'hash'          => $this->checksum(),
            'mime_type'     => $this->mimeType(),
            'last_modified' => \Illuminate\Support\Carbon::createFromTimestamp($this->lastModified())->format('c'),
        ];
    }

    /**
     * Get the URL for the file at the given path.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function url()
    {
        return $this->disk->url($this->path);
    }

    /**
     * Create a streamed response for a given file.
     *
     * @param  string|null  $name
     * @param  array  $headers
     * @param  string|null  $disposition
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function response($name = null, array $headers = [], $disposition = 'inline')
    {
        return $this->disk->response($this->path, $name ?? $this->filename(), $headers, $disposition);
    }

    /**
     * Create a streamed download response for a given file.
     *
     * @param  string|null  $name
     * @param  array  $headers
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download($name = null, array $headers = [])
    {
        return $this->disk->download($this->path, $name ?? $this->filename(), $headers);
    }

    public function toMailAttachment(): \Illuminate\Mail\Attachment
    {
        return \Illuminate\Mail\Attachment::fromData(fn() => $this->get(), $this->filename());
    }

    public function toResponse($request): \Symfony\Component\HttpFoundation\Response
    {
        $response = $this->response()
            ->setEtag($this->checksum())
            ->setLastModified(\Illuminate\Support\Carbon::createFromTimestamp($this->lastModified()));

        // Check and set response status...
        $response->isNotModified($request);

        return $response;
    }
}
