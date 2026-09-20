<?php

declare(strict_types=1);

/** @var PhpFuzzer\Config $config */
Fuzz\Shared\Database\Campaign::configure($config, 'sqlite', new ZtdQuery\Platform\Sqlite\SqliteSessionFactory());
