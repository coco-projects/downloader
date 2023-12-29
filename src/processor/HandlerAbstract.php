<?php

    declare(strict_types = 1);

    namespace Coco\downloader\processor;

    use Coco\downloader\Downloader;

abstract class HandlerAbstract
{
    protected Downloader $downloader;

    abstract public function beforeProcess(): static;

    abstract public function afterProcess(): static;

    abstract public function process(mixed $chunk): static;

    public function on416($callback): void
    {
        call_user_func_array($callback, [$this]);
    }

    public function on404($callback): void
    {
        call_user_func_array($callback, [$this]);
    }

    public function setDownloader(Downloader $downloader): static
    {
        $this->downloader = $downloader;

        return $this;
    }
}
