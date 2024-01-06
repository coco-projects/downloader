<?php

    require '../vendor/autoload.php';

    use Coco\downloader\Downloader;
    use Coco\downloader\processor\HandlerAbstract;
    use Coco\downloader\processor\Psr7Processor;
    use Coco\downloader\resource\File;
    use Psr\Http\Message\ResponseInterface as Response;
    use Psr\Http\Message\ServerRequestInterface as Request;
    use Slim\Factory\AppFactory;

    /**
     * Instantiate App
     *
     * In order for the factory to work you need to ensure you have installed
     * a supported PSR-7 implementation of your choice e.g.: Slim PSR-7 and a supported
     * ServerRequest creator (included with Slim PSR-7)
     */
    $app = AppFactory::create();

    /**
     * The routing middleware should be added earlier than the ErrorMiddleware
     * Otherwise exceptions thrown from it will not be handled by the middleware
     */
    $app->addRoutingMiddleware();

    /**
     * Add Error Middleware
     *
     * @param bool                 $displayErrorDetails -> Should be set to false in production
     * @param bool                 $logErrors           -> Parameter is passed to the default ErrorHandler
     * @param bool                 $logErrorDetails     -> Display error details in error log
     * @param LoggerInterface|null $logger              -> Optional PSR-3 Logger
     *
     * Note: This middleware should be added last. It will not handle any exceptions/errors
     * for middleware added after it.
     */
    $errorMiddleware = $app->addErrorMiddleware(true, true, true);

    $app->get('/download', function(Request $request, Response $response, $args) {

        $file = './1.png';

        $source = new File($file);
//    $source = new Strings('123456789');

        $nonBufferedBody = new \Slim\Psr7\NonBufferedBody();
        $response        = $response->withBody($nonBufferedBody);
        $processor       = new Psr7Processor($response);

        $d = new Downloader($source, $processor);

        $d->setDownloadName('test.png');

        $d->dispositionInline();
//        $d->dispositionAttachment();

        $d->setLimitRateKB(1024 * 1);
//    $d->setBufferSize(1024);

        $d->setOn404Callback(function(Psr7Processor $processor) use ($response) {
            $processor->getResponse()->getBody()->write('File not found');
        });

        $d->setOn416Callback(function(Psr7Processor $processor) {
            $processor->getResponse()->getBody()->write('范围错误');
        });

        $d->send();

        $response = $processor->getResponse();

        return $response;
    });

// Run app
    $app->run();