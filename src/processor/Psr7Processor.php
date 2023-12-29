<?php

    declare(strict_types = 1);

    namespace Coco\downloader\processor;

    use Psr\Http\Message\ResponseInterface;

class Psr7Processor extends HandlerAbstract
{
    public ?ResponseInterface $response = null;

    public function __construct(?ResponseInterface $response)
    {
        $this->setResponse($response);
    }

    public function beforeProcess(): static
    {
        ob_end_clean();
        ob_start();

        $this->response = $this->response->withStatus($this->downloader->getResponseCode());

        foreach ($this->downloader->getResponseHeader() as $k => $header) {
            $t = explode(':', $header);

            $this->response = $this->response->withHeader($t[0], $t[1]);
        }

        return $this;
    }

    public function process($chunk): static
    {
        $this->response->getBody()->write($chunk);
        ob_flush();
        flush();

        return $this;
    }

    public function afterProcess(): static
    {
        ob_end_flush();

        return $this;
    }


    public function setResponse(?ResponseInterface $response): static
    {
        $this->response = $response;

        return $this;
    }
}
