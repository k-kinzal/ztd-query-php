<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\AssignmentOperator;

#[CoversClass(AssignmentOperator::class)]
#[Medium]
final class AssignmentOperatorTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['=', ':='] as $sign) {
                foreach ([
                    "SET PASSWORD $sign 'x'",
                    "SET PASSWORD FOR u $sign 'x'",
                    "SET @x $sign 1, @@session.autocommit $sign 1, sql_mode $sign ''",
                    "SET GLOBAL max_connections $sign 20",
                    "SET NAMES $sign ROW(1, 2), @x $sign 3",
                    "UPDATE t SET a $sign 1",
                    "INSERT INTO t SET a $sign 1 ON DUPLICATE KEY UPDATE a $sign 2",
                    "LOAD DATA INFILE 'f' INTO TABLE t SET a $sign 1",
                    "CREATE PROCEDURE p() SET @x $sign ROW(1, 2), @@autocommit $sign ROW(1, 2), sql_mode $sign ROW(1, 2)",
                    "CREATE PROCEDURE p() UPDATE t SET a $sign ROW(1, 2)",
                    "CREATE PROCEDURE p() INSERT INTO t VALUES (1) ON DUPLICATE KEY UPDATE a $sign ROW(1, 2)",
                ] as $sql) {
                    yield "$release->value: $sql" => [$release, $sql];
                }
                if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                    $sql = "SET PASSWORD FOR u $sign PASSWORD('x')";
                    yield "$release->value: $sql" => [$release, $sql];
                }
                if ($release === GrammarRelease::MySql5651) {
                    $sql = "SET @x $sign 1, PASSWORD $sign 'x'";
                    yield "$release->value: $sql" => [$release, $sql];
                }
                if (in_array($release, [GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910], true)) {
                    $sql = "INSTALL COMPONENT 'file://c' SET PERSIST c.v $sign 4";
                    yield "$release->value: $sql" => [$release, $sql];
                }
            }
            foreach (['', '=', ':='] as $sign) {
                foreach ([
                    "CREATE DATABASE d DEFAULT CHARACTER SET $sign utf8mb4 COLLATE $sign utf8mb4_bin",
                    "CREATE TABLE t (a INT, KEY k(a) KEY_BLOCK_SIZE $sign 4) ENGINE $sign InnoDB ROW_FORMAT $sign DYNAMIC MAX_ROWS $sign 10 COMMENT $sign 'x' DEFAULT CHARSET $sign utf8mb4 COLLATE $sign utf8mb4_bin TABLESPACE s INSERT_METHOD $sign LAST UNION $sign (u)",
                    "CREATE TABLE t (a INT) PARTITION BY HASH(a) (PARTITION p ENGINE $sign InnoDB COMMENT $sign 'x')",
                    "ALTER TABLE t ALGORITHM $sign DEFAULT, LOCK $sign DEFAULT",
                    "CREATE TABLESPACE s ADD DATAFILE 'f' INITIAL_SIZE $sign 1M NODEGROUP $sign 1 COMMENT $sign 'x' ENGINE $sign InnoDB",
                ] as $sql) {
                    yield "$release->value: $sql" => [$release, $sql];
                }
                if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                    foreach ([
                        "CREATE RESOURCE GROUP g TYPE $sign USER VCPU $sign 1 THREAD_PRIORITY $sign 2",
                        "ALTER RESOURCE GROUP g VCPU $sign 1 THREAD_PRIORITY $sign 2",
                        "CREATE DATABASE d ENCRYPTION $sign 'Y'",
                        "ALTER DATABASE d READ ONLY $sign 1",
                        "CLONE LOCAL DATA DIRECTORY $sign '/tmp/x'",
                        "CLONE INSTANCE FROM u@h:3306 IDENTIFIED BY 'x' DATA DIRECTORY $sign '/tmp/x' REQUIRE SSL",
                        "CREATE TABLE t (a INT ENGINE_ATTRIBUTE $sign '{}', KEY k(a) SECONDARY_ENGINE_ATTRIBUTE $sign '{}') SECONDARY_ENGINE $sign NULL AUTOEXTEND_SIZE $sign 4M TABLESPACE $sign s",
                        "CREATE TABLESPACE s ADD DATAFILE 'f' AUTOEXTEND_SIZE $sign 4M ENCRYPTION $sign 'Y' ENGINE_ATTRIBUTE $sign '{}'",
                    ] as $sql) {
                        yield "$release->value: $sql" => [$release, $sql];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testRenderPreservesAssignmentSignsAcrossStatementRoles(GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new Formatter($semantics->parser(), new FormatOptions(Style::Compact));
        $statement = (new Publication())->publish($semantics->context(), $semantics->analyze($sql));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }
}
