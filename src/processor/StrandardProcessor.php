<?php

    declare(strict_types = 1);

    namespace Coco\downloader\processor;

class StrandardProcessor extends HandlerAbstract
{

    public function beforeProcess(): static
    {
        ob_start();
        $this->downloader->sendHeader();
        $this->downloader->sendStatusHeader();

        return $this;
    }

    public function process($chunk): static
    {
        echo $chunk;

        ob_flush();
        flush();

        return $this;
    }

    public function afterProcess(): static
    {
        ob_end_flush();

        return $this;
    }
}
