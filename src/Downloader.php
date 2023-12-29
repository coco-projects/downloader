<?php

    declare(strict_types = 1);

    namespace Coco\downloader;

    use Coco\downloader\processor\HandlerAbstract;
    use Coco\downloader\resource\ResourceAbstract;

class Downloader
{
    const DOWNLOAD_TYPE_FULL_RANGE            = 0;
    const DOWNLOAD_TYPE_SINGLE_RANGE          = 1;
    const DOWNLOAD_TYPE_MULTI_RANGE           = 2;
    const DOWNLOAD_TYPE_RANGE_NOT_SATISFIABLE = 3;
    const DOWNLOAD_TYPE_NOT_FOUND             = 4;

    const DISPOSITION_ATTACHMENT = 'attachment';
    const DISPOSITION_INLINE     = 'inline';

    /**
     * 下载限速，单位为 kb/s
     *
     * @var $limitRateKB int
     */
    protected int $limitRateKB = -1;

    protected array $responseHeader = [];
    protected int   $responseCode   = 200;
    protected ?int  $downloadType   = null;

    protected ?string $downloadName = null;
    protected ?string $statusHeader = null;
    protected ?string $boundary     = null;
    protected ?string $disposition  = null;

    protected $on404Callback = null;
    protected $on416Callback = null;

    protected \SplFileObject|null $file      = null;
    protected ?HandlerAbstract    $processor = null;
    protected ?ResourceAbstract   $resource  = null;

    protected array $bytesToRead = [];

    protected array $statusMap = [
        200 => "OK",
        206 => "Partial Content",
        416 => "Requested Range Not Satisfiable",
        404 => "Not Found",
    ];

    public function __construct(ResourceAbstract $resource, HandlerAbstract $processor)
    {
        $this->resource  = $resource;
        $this->processor = $processor;

        $resource->setDownloader($this);
        $processor->setDownloader($this);

        $this->initRange();
        $this->dispositionAttachment();
    }


    public function dispositionInline(): static
    {
        $this->disposition = static::DISPOSITION_INLINE;

        return $this;
    }

    public function dispositionAttachment(): static
    {
        $this->disposition = static::DISPOSITION_ATTACHMENT;

        return $this;
    }

    public function setLimitRateKB(int $limitRateKB): static
    {
        $this->limitRateKB = $limitRateKB;

        return $this;
    }

    public function getLimitRateKB(): int
    {
        return $this->limitRateKB;
    }


    public function setBufferSize(int $bufferSize): static
    {
        $this->resource->setBufferSize($bufferSize);

        return $this;
    }

    public function getResponseHeader(): array
    {
        return $this->responseHeader;
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    public function sendHeader(): static
    {
        foreach ($this->responseHeader as $k => $v) {
            header($v);
        }

        return $this;
    }

    public function sendStatusHeader(): static
    {
        header($this->statusHeader);

        return $this;
    }

    public function setDownloadName(string $downloadName): static
    {
        $this->downloadName = $downloadName;

        return $this;
    }

    public function setOn404Callback($on404Callback): static
    {
        $this->on404Callback = $on404Callback;

        return $this;
    }

    public function setOn416Callback($on416Callback): static
    {
        $this->on416Callback = $on416Callback;

        return $this;
    }

    public function send(): void
    {
        $this->initHeader();

        $this->statusHeader = implode(' ', [
            'HTTP/1.1',
            $this->responseCode,
            $this->statusMap[$this->responseCode],
        ]);

        $this->processor->beforeProcess();

        switch ($this->downloadType) {
            case static::DOWNLOAD_TYPE_FULL_RANGE:
                $this->resource->readRange(0, $this->resource->getSize() - 1, $this->processor);
                break;

            case static::DOWNLOAD_TYPE_SINGLE_RANGE:
                $this->resource->readRange($this->bytesToRead[0]['start'], $this->bytesToRead[0]['end'], $this->processor);
                break;

            case static::DOWNLOAD_TYPE_MULTI_RANGE:
                foreach ($this->bytesToRead as $k => $range) {
                    $this->processor->process('--' . $this->boundary . PHP_EOL);
                    $this->processor->process('Content-Type: application/octet-stream' . PHP_EOL);
                    $this->processor->process('Content-Range: bytes ' . $range['start'] . '-' . $range['end'] . '/' . $this->resource->getSize() . PHP_EOL . PHP_EOL);
                    $this->resource->readRange($range['start'], $range['end'], $this->processor);
                    $this->processor->process(PHP_EOL);
                }
                $this->processor->process('--' . $this->boundary . '--' . PHP_EOL);
                break;

            case static::DOWNLOAD_TYPE_RANGE_NOT_SATISFIABLE:
                $this->processor->on416($this->on416Callback);
                break;

            case static::DOWNLOAD_TYPE_NOT_FOUND:
                $this->processor->on404($this->on404Callback);

                break;
        }

        $this->processor->afterProcess();
        $this->resource->destroy();
    }


    protected function initHeader(): static
    {
        if (!$this->resource->isResourceAvailable()) {
            $this->responseCode = 404;
            $this->downloadType = static::DOWNLOAD_TYPE_NOT_FOUND;

            return $this;
        }

        $this->downloadName = $this->downloadName ?? $this->resource->getFileName();

        ini_set('output_buffering', 'Off');
        ini_set('zlib.output_compression', 'Off');

        if ($this->disposition == static::DISPOSITION_ATTACHMENT) {
            $mimeType = 'application/octet-stream';
        } else {
            $mimeType = $this->resource->getMimeType();
        }

        $totalSize    = $this->resource->getSize();
        $lastModified = gmdate('D, d M Y H:i:s', $this->resource->getMtime()) . ' GMT';
        $etag         = sprintf('w/"%s:%s"', md5($lastModified), $totalSize);

        $this->responseHeader[] = "Accept-Ranges: bytes";
        $this->responseHeader[] = "X-Accel-Buffering: no";
        $this->responseHeader[] = sprintf('Last-Modified: %s', $lastModified);
        $this->responseHeader[] = sprintf('ETag: %s', $etag);

        //客户端没有设置 HTTP_RANGE
        if (count($this->bytesToRead) == 0) {
            $this->responseCode = 200;

            $this->responseHeader[] = "Content-Length: {$totalSize}";
            $this->responseHeader[] = "Content-Type: " . $mimeType;
            $this->responseHeader[] = 'Content-Disposition: ' . $this->attachmentTemplate($this->downloadName);
            $this->responseHeader[] = "Cache-Control: public";

            $this->downloadType = static::DOWNLOAD_TYPE_FULL_RANGE;
        }

        //客户端设置了一个范围的HTTP_RANGE
        elseif (count($this->bytesToRead) == 1) {
            if ($this->bytesToRead[0]['end'] >= $totalSize) {
                $this->responseCode = 416;

                $this->downloadType = static::DOWNLOAD_TYPE_RANGE_NOT_SATISFIABLE;
            } else {
                $size = $this->bytesToRead[0]['start'] - $this->bytesToRead[0]['end'] + 1;

                $this->responseCode = 206;

                $this->responseHeader[] = "Content-Length: " . $size;
                $this->responseHeader[] = "Content-Type: " . $mimeType;
                $this->responseHeader[] = 'Content-Disposition: ' . $this->attachmentTemplate($this->downloadName);
                $this->responseHeader[] = "Cache-Control: public";
                $this->responseHeader[] = sprintf('Content-Range: bytes %s-%s/%s', $this->bytesToRead[0]['start'], $this->bytesToRead[0]['end'], $totalSize);

                $this->downloadType = static::DOWNLOAD_TYPE_SINGLE_RANGE;
            }
        } else {
            $is416 = false;

            foreach ($this->bytesToRead as $k => $v) {
                if ($v['end'] >= $totalSize) {
                    $is416 = true;
                    break;
                }
            }

            if ($is416) {
                $this->responseCode = 416;

                $this->downloadType = static::DOWNLOAD_TYPE_RANGE_NOT_SATISFIABLE;
            } else {
                $this->boundary = $this->generateBoundary();
                $contentSize    = $this->calculateMultiRangeTotalLength($this->bytesToRead, $this->resource->getSize());

                $this->responseCode = 206;

                $this->responseHeader[] = "Content-Length: {$contentSize}";
                $this->responseHeader[] = "Content-Type: multipart/byteranges; boundary=" . $this->boundary;
                $this->responseHeader[] = "Cache-Control: public";

                $this->downloadType = static::DOWNLOAD_TYPE_MULTI_RANGE;
            }
        }

        return $this;
    }

    private function initRange(): static
    {
        //单个字节范围：bytes=0-499 表示请求资源的第 0 到 499 字节。
        //多个字节范围：bytes=0-499,1000-1499 表示请求资源的第 0 到 499 字节以及第 1000 到 1499 字节。
        //从指定字节开始到结束：bytes=500- 表示请求资源的第 500 字节到结尾。
        //仅请求资源的最后 n 个字节：bytes=-500 表示请求资源的最后 500 个字节。

        //$_SERVER['HTTP_RANGE'] = 'bytes=0-2,4-7,6-,-7';

        if (isset($_SERVER['HTTP_RANGE'])) {
            $http_range = $_SERVER['HTTP_RANGE'];

            preg_match_all('/(\d+)?-(\d+)?/', $http_range, $result, PREG_SET_ORDER);

            foreach ($result as $k => $v) {
                //没设置起始值，只设置结尾值，表示读文件的最后的几个字节
                //-600
                if ('' == $v[1]) {
                    $v[2] = $this->resource->getSize() - 1;

                    $this->bytesToRead[$k]['start'] = (int)($this->resource->getSize() - $v[2]);
                    $this->bytesToRead[$k]['end']   = (int)($this->resource->getSize() - 1);
                }

                //只设置起始值，没设置结尾值，起始值一直读到文件最后
                //500-
                elseif (!isset($v[2])) {
                    $this->bytesToRead[$k]['start'] = (int)$v[1];
                    $this->bytesToRead[$k]['end']   = (int)($this->resource->getSize() - 1);
                }

                //有开始值，有结束值
                //1000-1499
                else {
                    $this->bytesToRead[$k]['start'] = (int)$v[1];
                    $this->bytesToRead[$k]['end']   = (int)$v[2];
                }
            }
        }

        return $this;
    }

    private function attachmentTemplate($downloadName): string
    {
        $ua = ($_SERVER["HTTP_USER_AGENT"]) ?? '';

        if (preg_match("/MSIE|Trident/i", $ua)) {
            // IE 浏览器或者 IE 内核的浏览器（如 Edge）
            $contentDisposition = sprintf($this->disposition . '; filename="%s"', rawurlencode($downloadName));
        } elseif (preg_match("/Firefox/i", $ua)) {
            // Firefox 浏览器
            $contentDisposition = sprintf($this->disposition . '; filename*=UTF-8\'\'%s', rawurlencode($downloadName));
        } elseif (preg_match("/Chrome/i", $ua)) {
            // Chrome 浏览器
            $contentDisposition = sprintf($this->disposition . '; filename="%s"', addslashes($downloadName));
        } else {
            // 其他浏览器
            $contentDisposition = sprintf($this->disposition . '; filename="%s"', rawurlencode($downloadName));
        }

        return $contentDisposition;
    }

    private function calculateMultiRangeTotalLength($ranges, $fileSize): int
    {
        $length = 0;

        foreach ($ranges as $range) {
            $length += strlen('--' . $this->boundary . PHP_EOL);
            $length += strlen('Content-Type: application/octet-stream' . PHP_EOL);
            $length += strlen('Content-Range: bytes ' . $range['start'] . '-' . $range['end'] . '/' . $fileSize . PHP_EOL . PHP_EOL);
            $length += $range['end'] - $range['start'] + 1;
            $length += strlen(PHP_EOL);
        }

        $length += strlen('--' . $this->boundary . '--' . PHP_EOL);

        return $length;
    }

    private function generateBoundary(): string
    {
        return md5(uniqid(microtime()));
    }
}
