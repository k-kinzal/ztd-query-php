<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Session\Parse\Entry\ImplicitTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;

#[CoversClass(ImplicitTables::class)]
#[Small]
final class ImplicitTablesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerStatements(): iterable
    {
        yield 'table before the refused predicate' => ['SELECT 1 FROM t WHERE @@missing', '@@missing', true];
        yield 'table after the refused projection' => ['SELECT @@missing FROM t', '@@missing', false];
        yield 'qualified table' => ['SELECT 1 FROM d.t WHERE @@missing', '@@missing', false];
        yield 'common table expression' => ['WITH c AS (SELECT 1) SELECT * FROM c WHERE @@missing', '@@missing', false];
        yield 'show target with its own database' => ['SHOW KEYS FROM t FROM missing WHERE @@missing', '@@missing', false];
        yield 'show target without a database' => ['SHOW COLUMNS FROM t WHERE @@missing', '@@missing', true];
        yield 'empty table name' => ['SELECT BINARY 1 FROM ``', 'FROM ``', false];
        yield 'created table before its column type' => ['CREATE TABLE t (a INT(300))', 'INT(300)', true];
        yield 'repeated qualified tables before a later unqualified one' => ['SELECT 1 FROM d.t AS a, d.t AS a, t', 'd.t AS a, d.t AS a', false];
        yield 'unqualified table before the repeated qualified ones' => ['SELECT 1 FROM t, d.t AS a, d.t AS a', 'd.t AS a, d.t AS a', true];
    }

    #[DataProvider('providerStatements')]
    public function testBeforeFindsAnUnqualifiedTableWrittenBeforeTheOffset(string $sql, string $marker, bool $expected): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse($sql);
        $end = (int) strpos($sql, $marker) + strlen($marker);

        self::assertSame($expected, (new ImplicitTables())->before($tree, $end));
    }

    public function testBeforeLeavesALegacyShowFilterToResolveFirst(): void
    {
        $tree = (new MySqlParser('mysql-5.7.44'))->parse('SHOW COLUMNS FROM t WHERE @@missing');

        self::assertFalse((new ImplicitTables())->before($tree, 36, true));
        self::assertTrue((new ImplicitTables())->before($tree, 36));
    }

    public function testTablesSkipsTheTargetOfAShowWithItsOwnDatabase(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('SHOW KEYS FROM t FROM missing');

        self::assertSame([], (new ImplicitTables())->tables($tree));
        self::assertCount(1, (new ImplicitTables())->tables((new MySqlParser('mysql-8.4.7'))->parse('SHOW KEYS FROM t')));
    }
}
