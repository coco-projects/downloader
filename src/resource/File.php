<?php

    declare(strict_types = 1);

    namespace Coco\downloader\resource;

    use Coco\downloader\processor\HandlerAbstract;

class File extends ResourceAbstract
{
    public ?\SplFileObject $file = null;

    public function __construct($filePath)
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            $this->resourceAvailable = false;
        } else {
            $this->file = new \SplFileObject($filePath);
        }
    }

    public function readRange($start, $end, HandlerAbstract $handler): static
    {
        $file = $this->file;
        $file->rewind();
        $file->fseek($start);

        while (!$file->eof() && $file->ftell() <= $end) {
            $this->delay();

            $chunk = $file->fread(min($this->bufferSize, $end - $file->ftell() + 1));
            $handler->process($chunk);
        }

        return $this;
    }

    public function getSize(): int
    {
        return $this->file->getSize();
    }

    public function destroy(): void
    {
        $this->file = null;
    }

    public function getFileName(): string
    {
        return $this->file->getFilename();
    }

    public function getMtime(): int
    {
        return $this->file->getMTime();
    }

    public function getCtime(): int
    {
        return $this->file->getCTime();
    }

    public function getMimeType(): string
    {
        $fi = new \finfo(FILEINFO_MIME_TYPE);
        return $fi->file($this->file->getPathname());
    }
}
