<?php

    declare(strict_types = 1);

    namespace Coco\downloader\resource;

    use Coco\downloader\processor\HandlerAbstract;

class Strings extends ResourceAbstract
{
    public string $string   = '';
    public string $mimeType = 'application/octet-stream';

    public function __construct($string)
    {
        $this->string = $string;
    }

    public function readRange($start, $end, HandlerAbstract $handler): static
    {
        $fileLength = strlen($this->string);
        $position   = $start;

        while ($position <= $end && $position < $fileLength) {
            $this->delay();

            $chunkSize = min($this->bufferSize, $end - $position + 1);
            $chunk     = substr($this->string, $position, $chunkSize);

            $handler->process($chunk);

            $position += $chunkSize;
        }

        return $this;
    }

    public function getSize(): int
    {
        return mb_strlen($this->string);
    }

    public function destroy(): void
    {
    }

    public function getFileName(): string
    {
        return date('Y-m-d_H-i-s') . '.txt';
    }

    public function getMtime(): int
    {
        return time();
    }

    public function getCtime(): int
    {
        return time();
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }
}
