<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\EnclosedQuery;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;

#[CoversClass(EnclosedQuery::class)]
#[Medium]
final class EnclosedQueryTest extends TestCase
{
    public function testAcceptsNeedsAParenthesizedFirstOperand(): void
    {
        $enclosed = (new Semantics(Dialect::MySql, '5.7.44'))->analyze('CREATE TABLE t (SELECT 1 AS a) UNION SELECT 2')->statement;
        $plain = (new Semantics(Dialect::MySql, '5.7.44'))->analyze('CREATE TABLE t SELECT 1 AS a UNION SELECT 2')->statement;
        self::assertInstanceOf(CreateTable::class, $enclosed);
        self::assertInstanceOf(CreateTable::class, $plain);

        self::assertInstanceOf(TableQuery::class, $enclosed->query);
        self::assertInstanceOf(TableQuery::class, $plain->query);

        self::assertTrue((new EnclosedQuery())->accepts($enclosed->query->query));
        self::assertFalse((new EnclosedQuery())->accepts($plain->query->query));
    }

    public function testWriteWritesThePartitioningInsideTheFirstParenthesis(): void
    {
        $create = (new Semantics(Dialect::MySql, '5.6.51'))->analyze('CREATE TABLE t (PARTITION BY HASH (a) SELECT 1 AS a) UNION SELECT 2 ORDER BY 1');
        self::assertInstanceOf(CreateTable::class, $create->statement);

        self::assertTrue($create->statement->enclosed);
        self::assertSame('CREATE TABLE t (PARTITION BY HASH (a) SELECT 1 AS a) UNION SELECT 2 ORDER BY 1', $create->toString());
    }

    public function testWriteWritesTheOperandsOfALeadingUnion(): void
    {
        $create = (new Semantics(Dialect::MySql, '5.6.51'))->analyze('CREATE TABLE t (PARTITION BY HASH (a) SELECT 1 AS a) UNION SELECT 2 FROM DUAL FOR UPDATE UNION SELECT 3');
        self::assertInstanceOf(CreateTable::class, $create->statement);
        self::assertInstanceOf(TableQuery::class, $create->statement->query);

        self::assertTrue((new EnclosedQuery())->accepts($create->statement->query->query));
        self::assertSame('CREATE TABLE t (PARTITION BY HASH (a) SELECT 1 AS a) UNION SELECT 2 FROM DUAL FOR UPDATE UNION SELECT 3', $create->toString());
    }
}
