<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Table;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use mysqli;
use SqlSemantics\Platform\MySql\Statement as M;

/**
 * Reuses weight expressions across empty, populated and truncated table and view inputs.
 */
final class WeightHistory
{
    /**
     * Compares retained values over a single session history, keeping each native rejection visible.
     *
     * @param list<M\QueryRoot> $queries
     * @return array{list<string>, list<string>, list<array{int, string, string}>}
     */
    public function observe(\MySqlMemory\Session\Session $session, mysqli $native, array $queries): array
    {
        $expected = [];
        $actual = [];
        $errors = [];
        foreach (['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(d DATE, dt DATETIME(6), ts TIMESTAMP(6) NULL, tm TIME(6), y YEAR, b BIT(5))', 'CREATE ALGORITHM=MERGE VIEW vm AS SELECT * FROM t', 'CREATE ALGORITHM=TEMPTABLE VIEW vt AS SELECT * FROM t'] as $sql) {
            $setup = $session->analyze($sql)->statement;
            \SqlSemantics\Diagnostic\Check::invariant($setup instanceof M\Statement, 'The history setup is a MySQL statement.');
            $expected[] = serialize((new Native())->observe($native, $sql));
            $actual[] = serialize((new Memory())->observe($session, $setup));
        }
        $reference = new Reference();
        foreach ([null, "INSERT INTO t VALUES('2024-01-01','2024-01-01 01:02:03.123456','2024-01-01 01:02:03.123456','-01:02:03.123456',2024,b'10101')", 'TRUNCATE TABLE t'] as $sql) {
            if ($sql !== null) {
                $setup = $session->analyze($sql)->statement;
                \SqlSemantics\Diagnostic\Check::invariant($setup instanceof M\Statement, 'The row transition is a MySQL statement.');
                $expected[] = serialize((new Native())->observe($native, $sql));
                $actual[] = serialize((new Memory())->observe($session, $setup));
            }
            foreach ($queries as $query) {
                $observation = (new Native())->observe($native, $reference->sql($query, $session->semantics()->profile()));
                if (isset($observation['error'])) {
                    $errors[] = $observation['error'];
                }
                $expected[] = serialize($observation);
                $session->text = 'opaque request label';
                $actual[] = serialize((new Memory())->observe($session, $query));
            }
        }
        return [$expected, $actual, $errors];
    }
}
