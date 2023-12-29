<?php

    use Coco\downloader\Downloader;
    use Coco\downloader\processor\HandlerAbstract;
    use Coco\downloader\processor\StrandardProcessor;
    use Coco\downloader\resource\File;
    use Coco\downloader\resource\Strings;

    require '../vendor/autoload.php';

    $file = './test.zip';
    $file = './1.png';
//    $file = './1.jpg';

    $source = new File($file);
//    $source = new Strings('123456789');

    $processor = new StrandardProcessor();

    $d = new Downloader($source, $processor);

    $d->setDownloadName('test.png');

    $d->dispositionInline();
//    $d->dispositionAttachment();

    $d->setLimitRateKB(64);
//    $d->setBufferSize(1024);

    $d->setOn404Callback(function(HandlerAbstract $processor) {
        echo '404';
    });

    $d->setOn416Callback(function(HandlerAbstract $processor) {

        echo '416';
    });

    $d->send();
