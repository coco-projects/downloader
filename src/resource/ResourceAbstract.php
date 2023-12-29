<?php

    declare(strict_types = 1);

    namespace Coco\downloader\resource;

    use Coco\downloader\Downloader;
    use Coco\downloader\processor\HandlerAbstract;

abstract class ResourceAbstract
{
    public bool $resourceAvailable = true;

    // kb
    protected int $bufferSize = 1024 * 16;

    protected Downloader $downloader;

    abstract public function readRange(int $start, int $end, HandlerAbstract $handler): static;

    abstract public function getSize(): int;

    abstract public function destroy(): void;

    abstract public function getFileName(): string;

    abstract public function getMtime(): int;

    abstract public function getCtime(): int;

    abstract public function getMimeType(): string;

    public function isResourceAvailable(): bool
    {
        return $this->resourceAvailable;
    }

    public function setDownloader(Downloader $downloader): static
    {
        $this->downloader = $downloader;

        return $this;
    }

    public function setBufferSize(int $bufferSize): static
    {
        $this->bufferSize = $bufferSize;

        return $this;
    }

    protected function delay(): static
    {
        if ($this->downloader->getLimitRateKB() > 1) {
            // kb/s
            $limitRate = $this->downloader->getLimitRateKB() * 1024;

            $sleepTime = 1000000 * ($this->bufferSize / $limitRate);

            usleep((int)$sleepTime);
        }

        return $this;
    }
}
