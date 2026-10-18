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
    public function testProjectionUsesResolvedAliasesAndReturnsTheRewriteNotice(): void
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
    public function testSimpleSeparatesTheProjectionFromRelationalRewrites(): void
    {
        $select = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
        self::assertTrue((new QueryNote())->simple($select));
        self::assertFalse((new QueryNote())->simple(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], $select->items, where: new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0'))));
    }
}
