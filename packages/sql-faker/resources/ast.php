<?php

declare(strict_types=1);

/**
 * Grammar AST metadata.
 *
 * @return array{default: string, default_pg: string, default_sqlite: string, versions: list<string>}
 */
return [
    'default' => 'mysql-8.4.7',
    'default_pg' => 'pg-17.2',
    'default_sqlite' => 'sqlite-3.47.2',
    'versions' => [
        'mysql-5.6.51',
        'mysql-5.7.44',
        'mysql-8.0.44',
        'mysql-8.1.0',
        'mysql-8.2.0',
        'mysql-8.3.0',
        'mysql-8.4.7',
        'mysql-9.0.1',
        'mysql-9.1.0',
        'pg-17.2',
        'sqlite-3.47.2',
    ],
];
