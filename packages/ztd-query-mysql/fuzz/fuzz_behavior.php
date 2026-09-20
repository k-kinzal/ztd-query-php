<?php

declare(strict_types=1);

/** @var PhpFuzzer\Config $config */
Fuzz\Shared\Database\Campaign::configure($config, 'mysql', new ZtdQuery\Platform\MySql\MySqlSessionFactory());
