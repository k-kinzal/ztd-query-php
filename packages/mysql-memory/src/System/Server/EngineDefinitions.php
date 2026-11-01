<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use RuntimeException;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Release-specific engine definitions used by SHOW and INFORMATION_SCHEMA.
 * Shared definitions and explicit release changes have one owner in the implementation.
 *
 * @visibility MySqlMemory
 */
final class EngineDefinitions
{
    /**
     * @var array<string, array{string, string, string, ?string, ?string, ?string}> Common definitions indexed by name
     */
    private const COMMON = [
        'ndbcluster' => ['ndbcluster', 'NO', 'Clustered, fault-tolerant tables', null, null, null],
        'MEMORY' => ['MEMORY', 'YES', 'Hash based, stored in memory, useful for temporary tables', 'NO', 'NO', 'NO'],
        'InnoDB' => ['InnoDB', 'DEFAULT', 'Supports transactions, row-level locking, and foreign keys', 'YES', 'YES', 'YES'],
        'PERFORMANCE_SCHEMA' => ['PERFORMANCE_SCHEMA', 'YES', 'Performance Schema', 'NO', 'NO', 'NO'],
        'MyISAM' => ['MyISAM', 'YES', 'MyISAM storage engine', 'NO', 'NO', 'NO'],
        'FEDERATED' => ['FEDERATED', 'NO', 'Federated MySQL storage engine', null, null, null],
        'ndbinfo' => ['ndbinfo', 'NO', 'MySQL Cluster system information storage engine', null, null, null],
        'MRG_MYISAM' => ['MRG_MYISAM', 'YES', 'Collection of identical MyISAM tables', 'NO', 'NO', 'NO'],
        'BLACKHOLE' => ['BLACKHOLE', 'YES', '/dev/null storage engine (anything you write to it disappears)', 'NO', 'NO', 'NO'],
        'CSV' => ['CSV', 'YES', 'CSV storage engine', 'NO', 'NO', 'NO'],
        'ARCHIVE' => ['ARCHIVE', 'YES', 'Archive storage engine', 'NO', 'NO', 'NO'],
    ];

    /**
     * @var array<string, list<string>> Available definitions in server order, for every supported release
     */
    private const RELEASES = [
        'mysql-5.6.51' => ['FEDERATED', 'MRG_MYISAM', 'MyISAM', 'BLACKHOLE', 'CSV', 'MEMORY', 'ARCHIVE', 'InnoDB', 'PERFORMANCE_SCHEMA'],
        'mysql-5.7.44' => ['InnoDB', 'MRG_MYISAM', 'MEMORY', 'BLACKHOLE', 'MyISAM', 'CSV', 'ARCHIVE', 'PERFORMANCE_SCHEMA', 'FEDERATED'],
        'mysql-8.0.44' => ['ndbcluster', 'MEMORY', 'InnoDB', 'PERFORMANCE_SCHEMA', 'MyISAM', 'FEDERATED', 'ndbinfo', 'MRG_MYISAM', 'BLACKHOLE', 'CSV', 'ARCHIVE'],
        'mysql-8.1.0' => ['ndbcluster', 'FEDERATED', 'MEMORY', 'InnoDB', 'PERFORMANCE_SCHEMA', 'MyISAM', 'ndbinfo', 'MRG_MYISAM', 'BLACKHOLE', 'CSV', 'ARCHIVE'],
        'mysql-8.2.0' => ['ndbcluster', 'FEDERATED', 'MEMORY', 'InnoDB', 'PERFORMANCE_SCHEMA', 'MyISAM', 'ndbinfo', 'MRG_MYISAM', 'BLACKHOLE', 'CSV', 'ARCHIVE'],
        'mysql-8.3.0' => ['ndbcluster', 'FEDERATED', 'MEMORY', 'InnoDB', 'PERFORMANCE_SCHEMA', 'MyISAM', 'ndbinfo', 'MRG_MYISAM', 'BLACKHOLE', 'CSV', 'ARCHIVE'],
        'mysql-8.4.7' => ['ndbcluster', 'MEMORY', 'InnoDB', 'PERFORMANCE_SCHEMA', 'MyISAM', 'FEDERATED', 'ndbinfo', 'MRG_MYISAM', 'BLACKHOLE', 'CSV', 'ARCHIVE'],
        'mysql-9.0.1' => ['ndbcluster', 'MEMORY', 'InnoDB', 'PERFORMANCE_SCHEMA', 'MyISAM', 'FEDERATED', 'ndbinfo', 'MRG_MYISAM', 'BLACKHOLE', 'CSV', 'ARCHIVE'],
        'mysql-9.1.0' => ['ndbcluster', 'MEMORY', 'InnoDB', 'PERFORMANCE_SCHEMA', 'MyISAM', 'FEDERATED', 'ndbinfo', 'MRG_MYISAM', 'BLACKHOLE', 'CSV', 'ARCHIVE'],
    ];

    /**
     * @throws RuntimeException When the release or one of its declarations is unavailable
     * @return list<array{string, string, string, ?string, ?string, ?string}>
     */
    public static function of(GrammarRelease $release): array
    {
        $definitions = self::COMMON;
        $names = self::RELEASES[$release->value] ?? throw new RuntimeException('A supported MySQL release is required.');
        $rows = [];
        foreach ($names as $name) {
            $row = $definitions[$name];
            $rows[] = $row;
        }
        return $rows;
    }
}
