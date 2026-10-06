<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class)]
#[Medium]
final class ListedColumnsTest extends TestCase
{
    public function testElementsAnswersTheElementsInOrder(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, CHECK (a > 0))', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        self::assertSame(2, count($n2->elements()));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t () INHERITS (a, s.b)', []);
        self::assertSame('CREATE TABLE t () INHERITS (a, s.b)', $statement->toString());
    }

    public function testRefusesAColumnConstraint(): void
    {
        $this->expectExceptionMessage('A table element is a column definition, a LIKE clause or a table constraint.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns([new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull()]);
    }
}
