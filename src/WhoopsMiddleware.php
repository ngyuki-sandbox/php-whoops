<?php
declare(strict_types = 1);

namespace App\Component\Whoops;

use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use Throwable;
use Whoops\Handler\HandlerInterface;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\XmlResponseHandler;
use Whoops\Run as Whoops;

class WhoopsMiddleware implements MiddlewareInterface
{
    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var callable[]
     */
    private $handlerDefinitions;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;

        $this->handlerDefinitions = [
            PlainTextHandler::class => function (ServerRequestInterface $request) {
                return new PlainTextHandler();
            },
            PrettyPageHandler::class => function (ServerRequestInterface $request) {
                return new PrettyPageHandler();
            },
            JsonResponseHandler::class => function (ServerRequestInterface $request) {
                return new JsonResponseHandler();
            },
            XmlResponseHandler::class => function (ServerRequestInterface $request) {
                return new XmlResponseHandler();
            },

            'plain' => PlainTextHandler::class,
            'html' => PrettyPageHandler::class,
            'json' => JsonResponseHandler::class,
            'xml' => XmlResponseHandler::class,

            '*/*' => 'plain',
            'text/*' => 'plain',
            'text/plain' => 'plain',
            'text/html' => 'html',
            'text/xml' => 'xml',
            'application/json' => 'json',
            'application/xml' => 'xml',
        ];
    }

    public function setHandlerDefinition(string $type, $definition)
    {
        $this->handlerDefinitions[$type] = $definition;
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $whoops = $this->createWhoopsInstance($request);
        try {
            $whoops->register();
            try {
                return $handler->handle($request);
            } finally {
                $whoops->unregister();
            }
        } catch (Throwable $ex) {
            return $this->handleThrowable($whoops, $ex);
        }
    }

    private function createWhoopsInstance(ServerRequestInterface $request)
    {
        $whoops = new Whoops();
        $type = $this->detectAcceptType($request);
        $handler = $this->resolveHandler($type, $request);
        $whoops->pushHandler($handler);
        return $whoops;
    }

    private function detectAcceptType(ServerRequestInterface $request)
    {
        $accepts = $this->parseAcceptHeaderLine($request);
        $detectedType = null;
        $detectedQuality = 0;
        foreach ($accepts as list($type, $quality)) {
            if ($detectedQuality >= $quality) {
                continue;
            }
            if (!isset($this->handlerDefinitions[$type])) {
                continue;
            }
            $detectedType = $type;
            $detectedQuality = $quality;
        }
        if ($detectedType === null) {
            $detectedType = '*/*';
        }
        return $detectedType;
    }

    private function parseAcceptHeaderLine(ServerRequestInterface $request)
    {
        $accepts = [];
        $header = $request->getHeaderLine('accept');
        if (strlen($header)) {
            $headers = explode(',', trim($header));
            foreach ($headers as $typeAndParams) {
                $params = explode(';', trim($typeAndParams));
                $type = trim(array_shift($params));
                $quality = 1.0;
                foreach ($params as $param) {
                    $keyValue = explode('=', trim($param));
                    if (count($keyValue) > 1) {
                        list($key, $val) = $keyValue;
                        if (trim($key) === 'q') {
                            $quality = (float)trim($val);
                        }
                    }
                }
                $accepts[] = [$type, $quality];
            }
        }
        return $accepts;
    }

    private function resolveHandler(string $type, ServerRequestInterface $request): HandlerInterface
    {
        $resolved = [];
        $handler = $type;
        for (;;) {
            if (is_string($handler)) {
                if (isset($resolved[$handler])) {
                    throw new LogicException("Unable resolve handoer \"$type\", circular reference");
                }
                $resolved[$handler] = $handler;
                if (!isset($this->handlerDefinitions[$handler])) {
                    throw new LogicException("Unable resolve handoer \"$type\", missing definition \"$handler\"");
                }
                $handler = $this->handlerDefinitions[$handler];
            } elseif (is_callable($handler)) {
                $handler = $handler($request);
            } elseif ($handler instanceof HandlerInterface) {
                break;
            } else {
                $t = is_object($handler) ? get_class($handler) : gettype($handler);
                throw new LogicException("Unable resolve handoer \"$type\", invalid handler type \"$t\"");
            }
        }
        assert($handler instanceof HandlerInterface);
        return $handler;
    }

    private function handleThrowable(Whoops $whoops, Throwable $ex)
    {
        if (ini_get('log_errors')) {
            error_log(trim((string)$ex) . PHP_EOL);
        }

        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);

        $method = Whoops::EXCEPTION_HANDLER;
        $output = $whoops->$method($ex);

        $contentType = $this->detectContentType($whoops);

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($output);
        return $response->withStatus(500)->withHeader('Content-Type', $contentType);
    }

    private function detectContentType(Whoops $whoops): string
    {
        foreach ($whoops->getHandlers() as $handler) {
            assert($handler instanceof HandlerInterface);
            try {
                $r = new ReflectionMethod($handler, 'contentType');
                if ($r->getNumberOfRequiredParameters() === 0) {
                    $contentType = $r->invoke($handler);
                    if (strlen($contentType)) {
                        return $contentType;
                    }
                }
            } catch (Throwable $ex) {}
        }
        return 'text/plain';
    }
}
