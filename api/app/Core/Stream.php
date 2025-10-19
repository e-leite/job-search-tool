<?php

namespace App\Core;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

class Stream implements StreamInterface
{
    private $resource;
    private ?int $size = null;

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    public static function create($content): self
    {
        if (is_resource($content)) {
            return new self($content);
        }

        $resource = fopen('php://temp', 'r+');
        fwrite($resource, (string) $content);
        rewind($resource);
        return new self($resource);
    }

    public function __toString(): string
    {
        if (!$this->resource) {
            return '';
        }

        try {
            $this->rewind();
            return stream_get_contents($this->resource);
        } catch (\Exception $e) {
            return '';
        }
    }

    public function close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }

        $this->resource = null;
        $this->size = null;
    }

    public function detach()
    {
        $res = $this->resource;
        $this->resource = null;
        $this->size = null;
        return $res;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stat = fstat($this->resource);
        return $stat['size'] ?? null;
    }

    public function tell(): int
    {
        $result = ftell($this->resource);
        if ($result === false) {
            throw new RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    public function eof(): bool
    {
        return feof($this->resource);
    }

    public function isSeekable(): bool
    {
        $meta = stream_get_meta_data($this->resource);
        return $meta['seekable'];
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable');
        }

        if (fseek($this->resource, $offset, $whence) !== 0) {
            throw new RuntimeException('Unable to seek stream');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        $mode = $this->getMetadata('mode');
        return strpbrk($mode, 'wca+') !== false;
    }

    public function write(string $string): int
    {
        $result = fwrite($this->resource, $string);
        if ($result === false) {
            throw new RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    public function isReadable(): bool
    {
        $mode = $this->getMetadata('mode');
        return strpbrk($mode, 'r+') !== false;
    }

    public function read(int $length): string
    {
        $result = fread($this->resource, $length);
        if ($result === false) {
            throw new RuntimeException('Unable to read from stream');
        }

        return $result;
    }

    public function getContents(): string
    {
        $result = stream_get_contents($this->resource);
        if ($result === false) {
            throw new RuntimeException('Unable to read stream contents');
        }
        
        return $result;
    }

    public function getMetadata(?string $key = null)
    {
        $meta = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}