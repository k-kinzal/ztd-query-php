<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;

#[CoversClass(TableQuery::class)]
#[Medium]
final class TableQueryTest extends TestCase
{
    public function testRenderWritesTheDuplicateHandlingAndTheQuery(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t REPLACE SELECT 1 AS a');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);

        self::assertSame(DuplicateHandling::Replace, $statement->query?->duplicate);
        self::assertSame('CREATE TABLE t REPLACE AS SELECT 1 AS a', $create->toString());
    }
}
