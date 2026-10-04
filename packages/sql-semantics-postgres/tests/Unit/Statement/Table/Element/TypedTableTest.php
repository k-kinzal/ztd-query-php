<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TypedTable::class)]
#[Medium]
final class TypedTableTest extends TestCase
{
    public function testElementsAnswersTheConstraints(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t OF ty (PRIMARY KEY (id))', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TypedTable::class, $n2);
        self::assertSame(1, count($n2->elements()));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t OF s.ty (id WITH OPTIONS NOT NULL)', []);
        self::assertSame('CREATE TABLE t OF s.ty (id NOT NULL)', $statement->toString());
    }
}
