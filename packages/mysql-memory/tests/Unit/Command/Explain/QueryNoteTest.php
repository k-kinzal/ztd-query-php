<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Explain;

use MySqlMemory\Command\Explain\QueryNote;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryNote::class)]
#[Small]
final class QueryNoteTest extends TestCase
{
    public function testItemRetainsTheDatabaseFunctionInTheRewrittenQuery(): void
    {
        $session = (new Instance(databases: ['d']))->connect();
        $session->query('EXPLAIN FOR DATABASE d SELECT DATABASE()');

        self::assertSame([['Note', 1003, '/* select#1 */ select database() AS `DATABASE()`']], $session->diagnostics->conditions);
        self::assertSame('', $session->variables->database);
    }

    public function testItemUsesResolvedAliasesAndReturnsTheRewriteNotice(): void
    {
        $session = (new Instance())->connect();
        $session->query('EXPLAIN FORMAT=JSON SELECT 1 AS answer');
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([['Note', '1003', '/* select#1 */ select 1 AS `answer`']], $warnings->rows);
    }
    public function testProjectionQualifiesTheResolvedTableAndColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE fz');
        $session->query('USE fz');
        $session->query('CREATE TABLE later_table(id INT)');
        $session->query('EXPLAIN FORMAT=JSON SELECT id FROM later_table');
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([['Note', '1003', '/* select#1 */ select `fz`.`later_table`.`id` AS `id` from `fz`.`later_table`']], $warnings->rows);
    }
    public function testColumnUsesResolvedAliasIdentityForExpandedStars(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); EXPLAIN SELECT * FROM t AS x WHERE NULL');
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([['Note', '1003', '/* select#1 */ select `d`.`x`.`a` AS `a` from `d`.`t` `x` where false']], $warnings->rows);
    }

    public function testProjectionIncludesTheLegacyFalsePredicateNote(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $result = $session->query('EXPLAIN SELECT * FROM t WHERE 0')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame('Impossible WHERE', $result->rows[0][11]);
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([['Note', '1003', '/* select#1 */ select `d`.`t`.`a` AS `a` from `d`.`t` where 0']], $warnings->rows);
    }

    public function testSimpleSeparatesTheProjectionFromRelationalRewrites(): void
    {
        $select = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
        self::assertTrue((new QueryNote())->simple($select));
        self::assertFalse((new QueryNote())->simple(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], $select->items, where: new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0'))));
    }
}
