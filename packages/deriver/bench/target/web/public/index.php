<?php

declare(strict_types=1);

use Harbor\Application;
use Harbor\Database\Connection;
use Harbor\Http\{Problem, Request, Response};
use Harbor\Security\Session;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $database = Connection::open(getenv('HARBOR_DATABASE') ?: dirname(__DIR__) . '/var/harbor.sqlite');
    $session = Session::start(getenv('HARBOR_INSECURE_HTTP') !== '1');
    $application = new Application($database, $session, dirname(__DIR__) . '/templates');
    $application->handle(Request::capture())->send();
} catch (Problem $error) {
    Response::json(['error' => $error->getMessage()], $error->status)->send();
} catch (Throwable $error) {
    error_log((string) $error);
    Response::json(['error' => 'Service unavailable.'], 503)->send();
}
