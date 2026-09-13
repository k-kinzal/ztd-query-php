<?php

declare(strict_types=1);

use Containers\MySql80Container;
use Containers\MySql84Container;
use Testcontainers\Testcontainers;

require dirname(__DIR__) . '/vendor/autoload.php';

Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
