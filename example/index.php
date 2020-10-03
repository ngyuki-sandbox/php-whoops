<?php
declare(strict_types = 1);

namespace App\Component\Whoops\Example;

use App\Component\Whoops\WhoopsEditorCallback;
use App\Component\Whoops\WhoopsMiddleware;
use App\Component\Whoops\WhoopsSmartyDataTable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Smarty;
use Whoops\Handler\PrettyPageHandler;

require __DIR__ . '/../vendor/autoload.php';

$app = (new AppFactory())->create();
$app->addRoutingMiddleware();

$app->add($whoopsMiddleware = new WhoopsMiddleware($app->getResponseFactory()));
$whoopsMiddleware->setHandlerDefinition(PrettyPageHandler::class, function () {
    $handler = new PrettyPageHandler();
    $handler->setApplicationPaths([__DIR__]);
    $handler->addDataTableCallback('Smarty', new WhoopsSmartyDataTable());
    $handler->setEditor(new WhoopsEditorCallback('vscode://file/%file:%line', [
        '/c/Users/' => 'C:/Users/',
    ]));
    return $handler;
});

$app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
    $renderer = new PhpRenderer(__DIR__);
    return $renderer->render($response, 'index.html.php');
});

$app->get('/exception', function (ServerRequestInterface $request, ResponseInterface $response) {
    throw new RuntimeException('oops!!!');
});

$app->get('/fatal', function (ServerRequestInterface $request, ResponseInterface $response) {
    require 'fatal';
});

$app->get('/smarty', function (ServerRequestInterface $request, ResponseInterface $response) {
    $smarty = new Smarty();
    $smarty->setCompileDir(__DIR__ . '/../cache/templates_c');
    if (!is_dir($smarty->getCompileDir())) {
        mkdir($smarty->getCompileDir(), 0777, true);
    }
    $content = $smarty->fetch(__DIR__ . '/smarty.tpl', [
        'aaa' => 111,
        'bbb' => 222,
        'ccc' => 333,
    ]);
    $response->getBody()->write($content);
    return $response;
});

$app->run();
