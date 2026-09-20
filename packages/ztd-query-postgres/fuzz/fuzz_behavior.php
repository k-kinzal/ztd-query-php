<?php

declare(strict_types=1);

/** @var PhpFuzzer\Config $config */
Fuzz\Shared\Database\Campaign::configure($config, 'pgsql', new ZtdQuery\Platform\Postgres\PgSqlSessionFactory());
